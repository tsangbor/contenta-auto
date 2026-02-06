<?php
/**
 * 步驟 9.5: 動態圖片需求分析與生成
 * 基於已組合的頁面內容分析圖片需求，生成個性化的 image-prompts.json
 *
 * 圖片生成流程重構 - Phase 1 Day 2
 * 解決問題：AI 照抄模板、缺乏個性化、時機不當
 * 解決方案：基於實際頁面內容生成圖片需求，100% 個性化
 */

// 載入 OpenAI 輔助類別
require_once DEPLOY_BASE_PATH . '/includes/utilities/class-openai-helper.php';

// 只在作為步驟腳本執行時運行主要邏輯
if (isset($job_id) && isset($deployer)) {
    // 確保工作目錄存在
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
if (!is_dir($work_dir)) {
    $deployer->log("錯誤: 工作目錄不存在，請先執行步驟09");
    return ['status' => 'error', 'message' => '工作目錄不存在，請先執行步驟09'];
}

// 確保前置條件：步驟9必須已完成
$layout_dir = $work_dir . '/layout';
if (!is_dir($layout_dir)) {
    $deployer->log("錯誤: layout 目錄不存在，請先執行步驟09");
    return ['status' => 'error', 'message' => 'layout 目錄不存在，請先執行步驟09'];
}

// 載入必要的配置檔案
$processed_data_file = $work_dir . '/config/processed_data.json';
$site_config_file = $work_dir . '/json/site-config.json';

if (!file_exists($processed_data_file)) {
    $deployer->log("錯誤: processed_data.json 不存在");
    return ['status' => 'error', 'message' => 'processed_data.json 不存在'];
}

if (!file_exists($site_config_file)) {
    $deployer->log("錯誤: site-config.json 不存在");
    return ['status' => 'error', 'message' => 'site-config.json 不存在'];
}

$processed_data = json_decode(file_get_contents($processed_data_file), true);
$site_config = json_decode(file_get_contents($site_config_file), true);

$domain = $processed_data['confirmed_data']['domain'];
$deployer->log("開始動態圖片需求分析: {$domain}");

// 取得 AI API 設定
$openai_config = [
    'api_key' => $config->get('api_credentials.openai.api_key'),
    'model' => $config->get('api_credentials.openai.model') ?: 'gpt-4o-mini',
    'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
];

$gemini_config = [
    'api_key' => $config->get('api_credentials.gemini.api_key'),
    'model' => $config->get('api_credentials.gemini.model') ?: 'gemini-2.0-flash-preview',
    'base_url' => $config->get('api_credentials.gemini.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta/models/'
];

// 選擇 AI 服務
$use_openai = !empty($openai_config['api_key']);
if ($use_openai) {
    $ai_service = 'OpenAI';
    $ai_config = $openai_config;
    $deployer->log("使用 OpenAI 服務: " . $ai_config['model']);
} else {
    $ai_service = 'Gemini';
    $ai_config = $gemini_config;
    $deployer->log("使用 Gemini 服務: " . $ai_config['model']);
}

/**
 * 載入原始用戶資料
 * 確保完整背景資訊供 AI 個性化使用
 * @param string $work_dir 工作目錄
 * @return array 原始用戶資料
 */
function loadOriginalUserData($work_dir)
{
    // 方法1: 從 processed_data.json 中提取
    $processed_data_path = $work_dir . '/config/processed_data.json';
    if (file_exists($processed_data_path)) {
        $processed_data = json_decode(file_get_contents($processed_data_path), true);
        if (isset($processed_data['user_info'])) {
            return $processed_data['user_info'];
        }
    }
    
    // 方法2: 從 job_id 對應的原始檔案載入
    $job_id = basename($work_dir);
    
    // 支援新的目錄結構：data/{job_id}/{job_id}.json
    $job_dir = DEPLOY_BASE_PATH . '/data/' . $job_id;
    $original_data_path = $job_dir . '/' . $job_id . '.json';
    
    if (file_exists($original_data_path)) {
        return json_decode(file_get_contents($original_data_path), true);
    } else {
        // 向後相容：檢查舊的檔案位置
        $old_original_data_path = DEPLOY_BASE_PATH . '/data/' . $job_id . '.json';
        if (file_exists($old_original_data_path)) {
            return json_decode(file_get_contents($old_original_data_path), true);
        }
    }
    
    // 方法3: 從配置檔案載入（簡化模式）
    $config_path = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (file_exists($config_path)) {
        $config = json_decode(file_get_contents($config_path), true);
        return $config['user_info'] ?? [];
    }
    
    return [];
}
}

/**
 * 掃描頁面圖片需求
 * 分析所有 *-ai.json 檔案，提取圖片佔位符
 * @param string $work_dir 工作目錄
 * @return array 圖片需求清單
 */
function scanPageImageRequirements($work_dir)
{
    $image_requirements = [];
    $layout_dir = $work_dir . '/layout';
    $page_files = glob($layout_dir . '/*-ai.json');
    
    // 載入圖片佔位符函數庫
    $image_functions_file = DEPLOY_BASE_PATH . '/includes/image-placeholder-functions.php';
    if (file_exists($image_functions_file)) {
        require_once $image_functions_file;
    }
    
    foreach ($page_files as $file) {
        $page_data = json_decode(file_get_contents($file), true);
        if (!$page_data) {
            continue;
        }
        
        // 提取頁面名稱
        $page_name = basename($file, '-ai.json');
        
        // 使用新的圖片掃描函數（支援標準化格式）
        if (function_exists('scanImagePlaceholders')) {
            $image_placeholders = [];
            scanImagePlaceholders($page_data, $image_placeholders);
            
            foreach ($image_placeholders as $placeholder_info) {
                $placeholder = $placeholder_info['placeholder'];
                
                if (!isset($image_requirements[$placeholder])) {
                    $image_requirements[$placeholder] = [
                        'placeholder' => $placeholder,
                        'pages' => [],
                        'contexts' => [],
                        'type' => $placeholder_info['type'],
                        'purpose' => $placeholder_info['purpose'],
                        'section' => $placeholder_info['section']
                    ];
                }
                
                $image_requirements[$placeholder]['pages'][] = $page_name;
                $image_requirements[$placeholder]['contexts'][] = $placeholder_info['context'];
            }
        } else {
            // 回退到舊方法
            $placeholders = extractImagePlaceholders($page_data);
            
            foreach ($placeholders as $placeholder) {
                if (!isset($image_requirements[$placeholder])) {
                    $image_requirements[$placeholder] = [
                        'placeholder' => $placeholder,
                        'pages' => [],
                        'contexts' => []
                    ];
                }
                
                $image_requirements[$placeholder]['pages'][] = $page_name;
                
                // 分析這個圖片在頁面中的語境
                $context = analyzeImageContext($placeholder, $page_data, $page_name);
                $image_requirements[$placeholder]['contexts'][] = $context;
            }
        }
    }
    
    return $image_requirements;
}

/**
 * 遞歸提取圖片佔位符
 * @param array $data 頁面資料
 * @return array 佔位符清單
 */
function extractImagePlaceholders($data)
{
    $placeholders = [];
    
    // 載入圖片佔位符函數庫
    $image_functions_file = DEPLOY_BASE_PATH . '/includes/image-placeholder-functions.php';
    if (file_exists($image_functions_file)) {
        require_once $image_functions_file;
    }
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // 1. 尋找 {{image:xxx}} 格式的佔位符（舊格式）
                if (preg_match_all('/\{\{image:([^}]+)\}\}/', $value, $matches)) {
                    foreach ($matches[0] as $match) {
                        if (!in_array($match, $placeholders)) {
                            $placeholders[] = $match;
                        }
                    }
                }
                
                // 2. 使用新的圖片識別函數檢查
                if (function_exists('isImageField') && isImageField($key, $value)) {
                    // 檢查標準化圖片佔位符 {{*_BG}}, {{*_PHOTO}}, {{*_ICON}}
                    if (!in_array($value, $placeholders) && preg_match('/\{\{\w*_(BG|PHOTO|ICON)\}\}/', $value)) {
                        $placeholders[] = $value;
                    }
                }
            } elseif (is_array($value)) {
                $nested_placeholders = extractImagePlaceholders($value);
                $placeholders = array_merge($placeholders, $nested_placeholders);
            }
        }
    }
    
    return array_unique($placeholders);
}

/**
 * 分析圖片在頁面中的語境
 * @param string $placeholder 圖片佔位符
 * @param array $page_data 頁面資料
 * @param string $page_name 頁面名稱
 * @return array 語境資訊
 */
function analyzeImageContext($placeholder, $page_data, $page_name)
{
    // 解析佔位符以了解圖片用途
    $image_key = str_replace(['{{image:', '}}'], '', $placeholder);
    $parts = explode('_', $image_key);
    
    $context = [
        'page_type' => $page_name,
        'image_key' => $image_key,
        'section_name' => 'unknown',
        'purpose' => 'unknown',
        'surrounding_text' => '',
        'widget_context' => [],
        'priority_score' => 0
    ];
    
    // 根據佔位符名稱推斷用途與優先級
    if (strpos($image_key, 'logo') !== false) {
        $context['section_name'] = 'header';
        $context['purpose'] = 'logo';
        $context['priority_score'] = 100; // Logo 最高優先級
    } elseif (strpos($image_key, 'hero') !== false) {
        $context['section_name'] = 'hero';
        if (strpos($image_key, 'bg') !== false || strpos($image_key, 'background') !== false) {
            $context['purpose'] = 'background';
            $context['priority_score'] = 80; // Hero 背景高優先級
        } else {
            $context['purpose'] = 'portrait';
            $context['priority_score'] = 70; // Hero 人物照高優先級
        }
    } elseif (strpos($image_key, 'about') !== false) {
        $context['section_name'] = 'about';
        if (strpos($image_key, 'team') !== false) {
            $context['purpose'] = 'team_photo';
            $context['priority_score'] = 60;
        } elseif (strpos($image_key, 'company') !== false) {
            $context['purpose'] = 'company_photo';
            $context['priority_score'] = 55;
        } else {
            $context['purpose'] = 'portrait';
            $context['priority_score'] = 50;
        }
    } elseif (strpos($image_key, 'service') !== false) {
        $context['section_name'] = 'service';
        if (strpos($image_key, 'icon') !== false) {
            $context['purpose'] = 'icon';
            $context['priority_score'] = 40;
        } elseif (strpos($image_key, 'process') !== false) {
            $context['purpose'] = 'diagram';
            $context['priority_score'] = 35;
        } else {
            $context['purpose'] = 'service_image';
            $context['priority_score'] = 45;
        }
    } elseif (strpos($image_key, 'footer') !== false || strpos($image_key, 'cta') !== false) {
        $context['section_name'] = 'footer';
        $context['purpose'] = 'background';
        $context['priority_score'] = 20; // Footer 較低優先級
    } elseif (strpos($image_key, 'contact') !== false) {
        $context['section_name'] = 'contact';
        $context['purpose'] = 'location_photo';
        $context['priority_score'] = 30;
    }
    
    // 尋找相鄰的文字內容
    $context['surrounding_text'] = findSurroundingText($placeholder, $page_data);
    
    // 分析 widget 上下文
    $context['widget_context'] = analyzeWidgetContext($placeholder, $page_data);
    
    return $context;
}

/**
 * 分析 Widget 上下文
 * @param string $placeholder 圖片佔位符
 * @param array $page_data 頁面資料
 * @return array Widget 上下文資訊
 */
function analyzeWidgetContext($placeholder, $page_data)
{
    $widget_info = [];
    
    if (is_array($page_data)) {
        $found_widget = findWidgetContainingPlaceholder($placeholder, $page_data);
        if ($found_widget) {
            $widget_info = [
                'widget_type' => $found_widget['widgetType'] ?? 'unknown',
                'settings' => array_keys($found_widget['settings'] ?? []),
                'has_title' => isset($found_widget['settings']['title']),
                'has_content' => isset($found_widget['settings']['content']) || isset($found_widget['settings']['editor'])
            ];
        }
    }
    
    return $widget_info;
}

/**
 * 尋找包含佔位符的 Widget
 * @param string $placeholder 圖片佔位符
 * @param array $data 頁面資料
 * @return array|null Widget 資料
 */
function findWidgetContainingPlaceholder($placeholder, $data)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value) && strpos($value, $placeholder) !== false) {
                // 找到佔位符，返回包含它的頂層結構
                return $data;
            } elseif (is_array($value)) {
                $result = findWidgetContainingPlaceholder($placeholder, $value);
                if ($result) {
                    return $result;
                }
            }
        }
    }
    
    return null;
}

/**
 * 尋找圖片佔位符周圍的文字內容
 * @param string $placeholder 圖片佔位符
 * @param array $data 頁面資料
 * @return string 周圍文字
 */
function findSurroundingText($placeholder, $data, $path = '')
{
    $surrounding_text = '';
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $current_path = $path ? "{$path}.{$key}" : $key;
            
            if (is_string($value)) {
                if (strpos($value, $placeholder) !== false) {
                    // 找到佔位符，收集周圍的文字
                    $context_data = findNearbyTextContent($data);
                    $surrounding_text .= implode(' ', array_slice($context_data, 0, 5));
                }
            } elseif (is_array($value)) {
                $nested_text = findSurroundingText($placeholder, $value, $current_path);
                if ($nested_text) {
                    $surrounding_text .= $nested_text;
                }
            }
        }
    }
    
    return trim($surrounding_text);
}

/**
 * 尋找附近的文字內容
 * @param array $data 資料陣列
 * @return array 文字內容清單
 */
function findNearbyTextContent($data)
{
    $text_content = [];
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 5 && 
                !preg_match('/\{\{image:/', $value) && 
                !preg_match('/\.(jpg|png|gif)/', $value)) {
                $text_content[] = substr($value, 0, 100);
            } elseif (is_array($value)) {
                $nested_content = findNearbyTextContent($value);
                $text_content = array_merge($text_content, $nested_content);
            }
        }
    }
    
    return $text_content;
}

/**
 * 生成圖片需求 JSON
 * @param array $requirements 圖片需求清單
 * @param array $site_config 網站配置
 * @return array 結構化圖片需求
 */
function generateImageRequirementsJson($requirements, $site_config)
{
    $image_requirements = [];
    
    foreach ($requirements as $placeholder => $requirement_data) {
        $contexts = $requirement_data['contexts'];
        $main_context = $contexts[0] ?? [];
        
        $image_requirements[$placeholder] = [
            'title' => generateImageTitle($main_context, $site_config),
            'page_context' => implode(', ', $requirement_data['pages']),
            'content_summary' => summarizeImageContext($contexts),
            'style_guidance' => determineStyleGuidance($main_context, $site_config),
            'technical_specs' => determineTechnicalSpecs($main_context)
        ];
    }
    
    return $image_requirements;
}

/**
 * 生成圖片標題
 */
function generateImageTitle($context, $site_config)
{
    $website_name = $site_config['website_info']['website_name'] ?? '網站';
    $section = $context['section_name'] ?? '區域';
    $purpose = $context['purpose'] ?? '圖片';
    
    // 根據圖片用途生成更具體的標題
    $title_map = [
        'logo' => "{$website_name} - 品牌標誌",
        'background' => "{$website_name} - {$section}區域背景",
        'portrait' => "{$website_name} - {$section}專業形象",
        'team_photo' => "{$website_name} - 團隊合照",
        'company_photo' => "{$website_name} - 公司環境",
        'icon' => "{$website_name} - 服務圖示",
        'diagram' => "{$website_name} - 流程圖表",
        'location_photo' => "{$website_name} - 位置照片"
    ];
    
    return $title_map[$purpose] ?? "{$website_name} - {$section}{$purpose}";
}

/**
 * 整理圖片語境摘要
 */
function summarizeImageContext($contexts)
{
    $summary_parts = [];
    
    foreach ($contexts as $context) {
        if (!empty($context['surrounding_text'])) {
            $summary_parts[] = substr($context['surrounding_text'], 0, 50);
        }
    }
    
    return implode(' | ', array_unique($summary_parts));
}

/**
 * 決定風格指導
 */
function determineStyleGuidance($context, $site_config)
{
    $brand_personality = $site_config['website_info']['brand_personality'] ?? '專業';
    $purpose = $context['purpose'] ?? 'general';
    $section = $context['section_name'] ?? '';
    
    $style_map = [
        'logo' => "體現{$brand_personality}理念的現代標誌設計，需包含具體公司名稱文字",
        'background' => "符合{$brand_personality}品牌調性的{$section}區域背景氛圍",
        'portrait' => "展現{$brand_personality}特質的專業人物形象",
        'team_photo' => "體現團隊{$brand_personality}精神的群體照片",
        'company_photo' => "展現{$brand_personality}企業文化的辦公環境",
        'icon' => "簡潔{$brand_personality}的服務圖示設計",
        'diagram' => "清晰{$brand_personality}的流程圖表設計",
        'location_photo' => "展現{$brand_personality}環境特色的位置照片"
    ];
    
    $base_style = $style_map[$purpose] ?? "配合{$brand_personality}品牌的視覺風格";
    
    // 添加頁面特定的風格指導
    if ($context['page_type'] === 'index') {
        $base_style .= "，重點突出首頁吸引力";
    } elseif ($context['page_type'] === 'about') {
        $base_style .= "，強調信任感與專業度";
    } elseif ($context['page_type'] === 'service') {
        $base_style .= "，凸顯服務專業性";
    }
    
    return $base_style;
}

/**
 * 決定技術規格
 */
function determineTechnicalSpecs($context)
{
    $purpose = $context['purpose'] ?? 'general';
    $priority = $context['priority_score'] ?? 50;
    
    $specs_map = [
        'logo' => [
            'size' => '512x512', 
            'format' => 'PNG', 
            'quality' => 'high',
            'ai_preference' => 'openai', // Logo 建議使用 DALL-E 3
            'special_requirements' => 'transparent_background'
        ],
        'background' => [
            'size' => '1920x1080', 
            'format' => 'JPG', 
            'quality' => $priority >= 70 ? 'high' : 'standard',
            'ai_preference' => 'auto',
            'aspect_ratio' => '16:9'
        ],
        'portrait' => [
            'size' => '800x600', 
            'format' => 'JPG', 
            'quality' => 'high',
            'ai_preference' => 'auto',
            'aspect_ratio' => '4:3'
        ],
        'team_photo' => [
            'size' => '1200x800', 
            'format' => 'JPG', 
            'quality' => 'high',
            'ai_preference' => 'auto',
            'aspect_ratio' => '3:2'
        ],
        'company_photo' => [
            'size' => '1200x800', 
            'format' => 'JPG', 
            'quality' => 'high',
            'ai_preference' => 'auto',
            'aspect_ratio' => '3:2'
        ],
        'icon' => [
            'size' => '256x256', 
            'format' => 'PNG', 
            'quality' => 'standard',
            'ai_preference' => 'auto',
            'special_requirements' => 'simple_design'
        ],
        'diagram' => [
            'size' => '800x600', 
            'format' => 'PNG', 
            'quality' => 'high',
            'ai_preference' => 'auto',
            'special_requirements' => 'clear_text'
        ],
        'location_photo' => [
            'size' => '1024x768', 
            'format' => 'JPG', 
            'quality' => 'high',
            'ai_preference' => 'auto',
            'aspect_ratio' => '4:3'
        ]
    ];
    
    $default_specs = [
        'size' => '1024x1024', 
        'format' => 'JPG', 
        'quality' => 'standard',
        'ai_preference' => 'auto'
    ];
    
    return $specs_map[$purpose] ?? $default_specs;
}

/**
 * 按優先級排序圖片需求
 * @param array $image_requirements 圖片需求清單
 * @return array 排序後的圖片需求
 */
function sortImageRequirementsByPriority($image_requirements)
{
    // 為每個需求計算綜合優先級分數
    foreach ($image_requirements as $placeholder => &$requirement) {
        $contexts = $requirement['contexts'] ?? [];
        $max_priority = 0;
        
        foreach ($contexts as $context) {
            $priority = $context['priority_score'] ?? 0;
            if ($priority > $max_priority) {
                $max_priority = $priority;
            }
        }
        
        $requirement['final_priority'] = $max_priority;
        
        // 額外加分：如果出現在多個頁面
        if (count($requirement['pages']) > 1) {
            $requirement['final_priority'] += 10;
        }
    }
    
    // 按優先級排序
    uasort($image_requirements, function($a, $b) {
        return ($b['final_priority'] ?? 0) - ($a['final_priority'] ?? 0);
    });
    
    return $image_requirements;
}

/**
 * 生成圖片需求統計報告
 * @param array $image_requirements 圖片需求清單
 * @return array 統計報告
 */
function generateImageRequirementsReport($image_requirements)
{
    $report = [
        'total_images' => count($image_requirements),
        'priority_distribution' => [
            'high' => 0,    // 70+ 分
            'medium' => 0,  // 40-69 分
            'low' => 0      // <40 分
        ],
        'purpose_distribution' => [],
        'page_distribution' => [],
        'estimated_generation_time' => 0,
        'recommended_order' => []
    ];
    
    foreach ($image_requirements as $placeholder => $requirement) {
        $priority = $requirement['final_priority'] ?? 0;
        $contexts = $requirement['contexts'] ?? [];
        
        // 優先級分布
        if ($priority >= 70) {
            $report['priority_distribution']['high']++;
        } elseif ($priority >= 40) {
            $report['priority_distribution']['medium']++;
        } else {
            $report['priority_distribution']['low']++;
        }
        
        // 用途分布
        foreach ($contexts as $context) {
            $purpose = $context['purpose'] ?? 'unknown';
            $report['purpose_distribution'][$purpose] = ($report['purpose_distribution'][$purpose] ?? 0) + 1;
        }
        
        // 頁面分布
        foreach ($requirement['pages'] as $page) {
            $report['page_distribution'][$page] = ($report['page_distribution'][$page] ?? 0) + 1;
        }
        
        // 建議處理順序
        $report['recommended_order'][] = [
            'placeholder' => $placeholder,
            'priority' => $priority,
            'estimated_time' => $priority >= 70 ? 2 : 1 // 高優先級預估較長時間
        ];
    }
    
    // 預估總生成時間（分鐘）
    $report['estimated_generation_time'] = array_sum(array_column($report['recommended_order'], 'estimated_time'));
    
    return $report;
}

/**
 * AI 個性化圖片提示詞生成
 * 核心功能：基於用戶真實資料生成完全個性化的提示詞
 * @param array $image_requirements 圖片需求
 * @param array $site_config 網站配置
 * @param array $original_user_data 原始用戶資料檔案
 * @return array 個性化提示詞
 */
function generatePersonalizedImagePrompts($image_requirements, $site_config, $original_user_data, $ai_config, $ai_service, $deployer)
{
    // 載入圖片佔位符函數庫
    $image_functions_file = DEPLOY_BASE_PATH . '/includes/image-placeholder-functions.php';
    if (file_exists($image_functions_file)) {
        require_once $image_functions_file;
    }
    
    // 為每個圖片需求充實智能提示詞資訊
    foreach ($image_requirements as $placeholder => &$requirement) {
        if (function_exists('generateImagePromptInfo')) {
            // 使用新的智能提示詞生成函數
            $placeholder_info = [
                'type' => $requirement['type'] ?? 'image',
                'section' => $requirement['section'] ?? 'general',
                'purpose' => $requirement['purpose'] ?? 'general_image'
            ];
            
            $user_data = [
                'brand_keywords' => $site_config['website_info']['industry_keywords'] ?? [],
                'color_scheme' => $site_config['website_info']['color_scheme'] ?? 'modern professional'
            ];
            
            $prompt_info = generateImagePromptInfo($placeholder_info, $user_data);
            $requirement['smart_prompt_info'] = $prompt_info;
        }
    }
    
    $prompt = generateImagePromptTemplate($image_requirements, $site_config, $original_user_data);
    
    $deployer->log("生成圖片提示詞 AI 請求...");
    $deployer->log("提示詞長度: " . strlen($prompt) . " 字元");
    
    // 呼叫 AI API
    $response = callAIForImagePrompts($prompt, $ai_config, $ai_service, $deployer);
    
    if (!$response) {
        $deployer->log("❌ AI 圖片提示詞生成失敗");
        return null;
    }
    
    $deployer->log("✅ AI 圖片提示詞生成完成");
    
    // 解析回應
    return parseImagePromptsResponse($response, $deployer);
}

/**
 * 生成 AI 提示詞模板（完整用戶資料版）
 */
function generateImagePromptTemplate($image_requirements, $site_config, $original_user_data)
{
    // 從 job_id.json 提取豐富的確認資料
    $confirmed_data = $original_user_data['confirmed_data'] ?? [];
    
    // 提取核心品牌資訊
    $brand_data = [
        'website_name' => $confirmed_data['website_name'] ?? '',
        'website_description' => $confirmed_data['website_description'] ?? '',
        'brand_keywords' => is_array($confirmed_data['brand_keywords']) ? implode(', ', $confirmed_data['brand_keywords']) : '',
        'target_audience' => $confirmed_data['target_audience'] ?? '',
        'brand_personality' => $confirmed_data['brand_personality'] ?? '',
        'unique_value' => $confirmed_data['unique_value'] ?? '',
        'service_categories' => is_array($confirmed_data['service_categories']) ? implode(', ', $confirmed_data['service_categories']) : '',
        'domain' => $confirmed_data['domain'] ?? ''
    ];
    
    // 提取色彩方案
    $color_scheme = $confirmed_data['color_scheme'] ?? [];
    $colors_info = '';
    if (!empty($color_scheme)) {
        $colors_info = "主色調: {$color_scheme['primary']}, 次要色: {$color_scheme['secondary']}, 強調色: {$color_scheme['accent']}";
    }
    
    // 從 site-config.json 提取補充資訊（如果有的話）
    $site_info = $site_config['website_info'] ?? [];
    $industry_keywords = isset($site_info['industry_keywords']) ? implode(', ', $site_info['industry_keywords']) : ''; 
    
    // 分析圖片需求的語境資訊
    $context_analysis = analyzeImageContextForPrompt($image_requirements, $brand_data);
    
    $prompt = "
## 🎨 AI 藝術總監任務：高質量個性化圖片提示詞生成

### 🎯 終極目標
您是世界頂級的 AI 藝術總監與視覺概念設計師，專精於為企業品牌創造完美視覺識別。基於以下詳細的品牌資料，為網站 '{$brand_data['website_name']}' 生成專業級的 DALL-E 3 / Imagen 3 圖片提示詞。

### 📊 品牌深度檔案
**🏢 企業核心資訊**
- 網站全名：{$brand_data['website_name']}
- 企業描述：{$brand_data['website_description']}
- 網域名稱：{$brand_data['domain']}
- 核心關鍵字：{$brand_data['brand_keywords']}
- 服務項目：{$brand_data['service_categories']}

**🎭 品牌個性與定位**
- 品牌個性：{$brand_data['brand_personality']}
- 目標受眾：{$brand_data['target_audience']}
- 獨特價值：{$brand_data['unique_value']}
- 行業關鍵字：{$industry_keywords}

**🎨 視覺設計規範**
- 配色方案：{$colors_info}
- 設計風格：專業、現代、具親和力
- 視覺調性：需要反映品牌個性

### 📋 圖片需求分析
{$context_analysis}

### 🖼️ 具體圖片需求清單
" . json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "

### ✨ 創意指導原則
1. **品牌精神內化** - 每張圖片都必須深度反映品牌核心價值與服務特色
2. **目標受眾共鳴** - 視覺設計必須能與目標受眾產生情感連結
3. **行業權威展現** - 圖片要強化專業形象與行業地位
4. **視覺一致性** - 全站圖片風格統一，符合配色方案
5. **本土化設計** - 考慮中華文化與本土審美偏好
6. **細節品質保證** - 提示詞必須精確、具體、可執行
7. **文字使用規範** - 除 Logo 外，所有圖片都禁止包含任何文字或字符

### 🎨 分類設計指引

**1. Logo 設計特殊規範**
- 包含指定文字：`with text '{$brand_data['website_name']}'`
- 字體選擇：現代簡約 / 優雅永恆 / 科技未來
- 色彩遵循：主色調 + 強調色搭配
- 結合元素：與服務項目相關的視覺符號
- 格式要求：`transparent background, vector style`

### 📈 提示詞精炼策略

**品牌深度融合**
- 每個提示詞都必須包含 3-5 個品牌核心關鍵字
- 直接引用用戶提供的服務特色與價值主張
- 反映目標受眾的具體需求與痛點

**視覺一致性統一**
- 全站圖片風格保持高度一致性
- 嚴格遵循指定的配色方案
- 結合品牌個性的視覺表達

**專業品質保證**
- 使用專業攝影與設計術語
- 描述具體的光線、構圖、質感要求
- 確保英文描述的語法正確性與專業性

### 📝 最終交付規範

**JSON 格式要求**
返回標準的 image-prompts.json 格式，每個圖片包含：
- title: 中文圖片標題（供管理參考）
- prompt: Professional English prompt for AI image generation
- extra: 技術規格與專業說明
- ai: openai 或 gemini
- style: 圖片類型
- quality: high 或 standard
- size: 圖片尺寸 (如 1920x1080)

**品質檢查清單**
✓ 每個 prompt 包含 3+ 品牌關鍵字
✓ 直接引用用戶提供的服務特色
✓ 反映目標受眾的具體需求
✓ 使用指定的配色方案
✓ 英文語法正確且專業
✓ 描述具體的視覺元素
✓ 全站風格一致性

**創意品質保證**
請以世界頂級藝術總監的標準，為 '{$brand_data['website_name']}' 創造獨一無二、深度個性化的圖片提示詞。每個提示詞都應該完全反映用戶的真實背景、品牌特性與專業服務，絕不使用任何通用或模板化的內容。
";
    
    return $prompt;
}

/**
 * 分析圖片上下文為提示詞生成提供重點資訊
 * @param array $image_requirements 圖片需求清單
 * @param array $brand_data 品牌資料
 * @return string 經分析的上下文資訊
 */
function analyzeImageContextForPrompt($image_requirements, $brand_data)
{
    $analysis = [];
    $image_types = [];
    $page_coverage = [];
    $priority_items = [];
    
    foreach ($image_requirements as $placeholder => $requirement) {
        // 統計圖片類型
        $type = $requirement['type'] ?? 'unknown';
        $image_types[$type] = ($image_types[$type] ?? 0) + 1;
        
        // 統計頁面分佈
        $pages = $requirement['pages'] ?? [];
        foreach ($pages as $page) {
            $page_coverage[$page] = ($page_coverage[$page] ?? 0) + 1;
        }
        
        // 識別高優先級項目
        $priority = $requirement['final_priority'] ?? 0;
        if ($priority >= 70) {
            $priority_items[] = $placeholder . " (優先級: {$priority})";
        }
    }
    
    // 生成分析報告
    $analysis[] = "圖片類型分佈：";
    foreach ($image_types as $type => $count) {
        $type_name = [
            'background' => '背景圖片',
            'photo' => '人物照片',
            'icon' => '服務圖示',
            'logo' => '品牌標誌'
        ][$type] ?? $type;
        $analysis[] = "- {$type_name}: {$count} 張";
    }
    
    $analysis[] = "\n頁面分佈狀況：";
    foreach ($page_coverage as $page => $count) {
        $page_name = [
            'home' => '首頁',
            'about' => '關於頁面',
            'service' => '服務頁面',
            'blog' => '部落格',
            'contact' => '聯絡頁面'
        ][$page] ?? $page;
        $analysis[] = "- {$page_name}: {$count} 張圖片";
    }
    
    if (!empty($priority_items)) {
        $analysis[] = "\n高優先級項目（需重點關注）：";
        foreach ($priority_items as $item) {
            $analysis[] = "- {$item}";
        }
    }
    
    // 品牌視覺指引
    $analysis[] = "\n品牌視覺指引（重要參考）：";
    $services = is_array($brand_data['service_categories']) ? implode(', ', $brand_data['service_categories']) : $brand_data['service_categories'];
    $keywords = is_array($brand_data['brand_keywords']) ? implode(', ', $brand_data['brand_keywords']) : $brand_data['brand_keywords'];
    $analysis[] = "- 主要服務：{$services}";
    $analysis[] = "- 品牌關鍵字：{$keywords}";
    $analysis[] = "- 目標受眾：{$brand_data['target_audience']}";
    $analysis[] = "- 品牌個性：{$brand_data['brand_personality']}";
    
    return implode("\n", $analysis);
}

/**
 * 呼叫 AI 生成圖片提示詞
 */
function callAIForImagePrompts($prompt, $ai_config, $ai_service, $deployer)
{
    if ($ai_service === 'OpenAI') {
        return callOpenAIForImagePrompts($prompt, $ai_config, $deployer);
    } else {
        return callGeminiForImagePrompts($prompt, $ai_config, $deployer);
    }
}

/**
 * 呼叫 OpenAI API
 */
function callOpenAIForImagePrompts($prompt, $ai_config, $deployer)
{
    $url = rtrim($ai_config['base_url'], '/') . '/chat/completions';

    // 使用 OpenAIHelper 建構請求資料（自動處理新舊模型差異）
    $data = OpenAIHelper::buildRequestData(
        $ai_config['model'],
        [
            ['role' => 'user', 'content' => $prompt]
        ],
        [
            'max_tokens' => 3000,
            'temperature' => 0.7
        ]
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $ai_config['api_key']
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $deployer->log("❌ OpenAI API 錯誤: HTTP {$http_code}");
        return null;
    }
    
    $response_data = json_decode($response, true);
    if (!isset($response_data['choices'][0]['message']['content'])) {
        $deployer->log("❌ OpenAI 回應格式錯誤");
        return null;
    }
    
    return $response_data['choices'][0]['message']['content'];
}

/**
 * 呼叫 Gemini API  
 */
function callGeminiForImagePrompts($prompt, $ai_config, $deployer)
{
    $url = rtrim($ai_config['base_url'], '/') . '/' . $ai_config['model'] . ':generateContent?key=' . $ai_config['api_key'];
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 3000
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $deployer->log("❌ Gemini API 錯誤: HTTP {$http_code}");
        return null;
    }
    
    $response_data = json_decode($response, true);
    if (!isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $deployer->log("❌ Gemini 回應格式錯誤");
        return null;
    }
    
    return $response_data['candidates'][0]['content']['parts'][0]['text'];
}

/**
 * 解析 AI 圖片提示詞回應
 */
function parseImagePromptsResponse($response, $deployer)
{
    // 嘗試從回應中提取 JSON
    $json_start = strpos($response, '```json');
    $json_end = strrpos($response, '```');
    
    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($response, $json_start + 7, $json_end - $json_start - 7);
    } else {
        // 如果沒有 markdown 格式，嘗試直接解析整個回應
        $json_content = $response;
    }
    
    $parsed_data = json_decode(trim($json_content), true);
    
    if (!$parsed_data) {
        $json_error = json_last_error_msg();
        $deployer->log("❌ 圖片提示詞 JSON 解析錯誤: " . $json_error);
        $deployer->log("回應內容預覽: " . substr($response, 0, 500) . "...");
        return null;
    }
    
    $deployer->log("✅ 成功解析 " . count($parsed_data) . " 個圖片提示詞");
    return $parsed_data;
}

// 主要處理流程 - 只在有必要變數時執行
if (isset($job_id) && isset($deployer)) {
try {
    $deployer->log("=== 開始步驟 9.5: 動態圖片需求分析 ===");
    
    // 步驟 1: 載入原始用戶資料檔案
    $deployer->log("步驟 1: 載入原始用戶資料...");
    $original_user_data = loadOriginalUserData($work_dir);
    
    if (empty($original_user_data)) {
        $deployer->log("警告: 未找到原始用戶資料，將使用 site-config 資料");
        $original_user_data = $site_config['website_info'] ?? [];
    } else {
        $deployer->log("✅ 成功載入原始用戶資料");
    }
    
    // 步驟 2: 掃描頁面 JSON 檔案
    $deployer->log("步驟 2: 掃描頁面圖片需求...");
    $image_requirements_raw = scanPageImageRequirements($work_dir);
    
    if (empty($image_requirements_raw)) {
        $deployer->log("⚠️ 未發現任何圖片佔位符，可能步驟9未正確執行");
        return ['status' => 'warning', 'message' => '未發現圖片佔位符'];
    }
    
    $deployer->log("✅ 發現 " . count($image_requirements_raw) . " 個圖片需求");
    
    // 步驟 3: 分析圖片佔位符需求  
    $deployer->log("步驟 3: 分析圖片需求語境...");
    $image_requirements = generateImageRequirementsJson($image_requirements_raw, $site_config);
    
    // 儲存圖片需求分析結果（偵錯用）
    $requirements_file = $work_dir . '/json/image-requirements.json';
    file_put_contents($requirements_file, json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("儲存圖片需求分析: image-requirements.json");
    
    // 步驟 4: 生成圖片需求清單
    $deployer->log("步驟 4: 生成結構化圖片需求...");
    
    // 步驟 5: AI 個性化圖片提示詞（包含完整用戶背景）
    $deployer->log("步驟 5: AI 生成個性化圖片提示詞...");
    $personalized_prompts = generatePersonalizedImagePrompts(
        $image_requirements, 
        $site_config, 
        $original_user_data, 
        $ai_config, 
        $ai_service, 
        $deployer
    );
    
    if (!$personalized_prompts) {
        $deployer->log("❌ AI 圖片提示詞生成失敗，使用預設模板");
        
        // 降級處理：生成基本的圖片提示詞
        $personalized_prompts = generateFallbackImagePrompts($image_requirements, $site_config);
    }
    
    // 步驟 6: 輸出 image-prompts.json
    $output_file = $work_dir . '/json/image-prompts.json';
    file_put_contents($output_file, json_encode($personalized_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("✅ 輸出動態圖片提示詞: image-prompts.json");
    $deployer->log("✅ 步驟 9.5 完成 - 生成 " . count($personalized_prompts) . " 個個性化圖片提示詞");
    
    return [
        'status' => 'success',
        'message' => '動態圖片需求分析完成',
        'image_count' => count($personalized_prompts),
        'requirements_count' => count($image_requirements)
    ];
    
} catch (Exception $e) {
    $deployer->log("❌ 步驟 9.5 失敗: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => '動態圖片需求分析失敗: ' . $e->getMessage()
    ];
}
} // 結束主要執行邏輯


/**
 * 降級處理：生成基本圖片提示詞
 */
function generateFallbackImagePrompts($image_requirements, $site_config)
{
    $fallback_prompts = [];
    $website_name = $site_config['website_info']['website_name'] ?? 'Professional Website';
    
    foreach ($image_requirements as $placeholder => $requirement) {
        $image_key = str_replace(['{{image:', '}}'], '', $placeholder);
        
        $fallback_prompts[$image_key] = [
            'title' => $requirement['title'] ?? "圖片: {$image_key}",
            'prompt' => "Professional business image for {$website_name}, modern and clean design, high quality photography",
            'extra' => '降級處理生成',
            'ai' => 'openai',
            'style' => 'professional',
            'quality' => 'high',
            'size' => '1024x1024'
        ];
    }
    
    return $fallback_prompts;
}