<?php
/**
 * 測試腳本：步驟 9.5 圖片需求掃描與分析功能
 * Phase 1 Day 3 - 實作圖片需求掃描與分析功能
 * 
 * 此腳本用於測試和驗證 step-09-5.php 的核心功能
 */

// 設定基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', __DIR__ . '/config');

// 載入必要的配置
require_once __DIR__ . '/config-manager.php';

// 初始化配置管理器
$config = ConfigManager::getInstance();

// 模擬部署器類別
class TestDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
}

$deployer = new TestDeployer();

// 載入 step-09-5.php 的核心函數
// 由於 step-09-5.php 是完整的步驟腳本，我們只載入需要的函數

/**
 * 載入原始用戶資料
 * 複製自 step-09-5.php 的函數
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
    $original_data_path = DEPLOY_BASE_PATH . '/data/' . $job_id . '.json';
    if (file_exists($original_data_path)) {
        return json_decode(file_get_contents($original_data_path), true);
    }
    
    // 方法3: 從配置檔案載入（簡化模式）
    $config_path = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (file_exists($config_path)) {
        $config = json_decode(file_get_contents($config_path), true);
        return $config['user_info'] ?? [];
    }
    
    return [];
}

/**
 * 掃描頁面圖片需求
 */
function scanPageImageRequirements($work_dir)
{
    $image_requirements = [];
    $layout_dir = $work_dir . '/layout';
    $page_files = glob($layout_dir . '/*-ai.json');
    
    foreach ($page_files as $file) {
        $page_data = json_decode(file_get_contents($file), true);
        if (!$page_data) {
            continue;
        }
        
        // 提取頁面名稱
        $page_name = basename($file, '-ai.json');
        
        // 遞歸搜尋所有 {{image:xxx}} 佔位符
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
    
    return $image_requirements;
}

/**
 * 遞歸提取圖片佔位符
 */
function extractImagePlaceholders($data)
{
    $placeholders = [];
    
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // 尋找 {{image:xxx}} 格式的佔位符
                if (preg_match_all('/\{\{image:([^}]+)\}\}/', $value, $matches)) {
                    foreach ($matches[0] as $match) {
                        if (!in_array($match, $placeholders)) {
                            $placeholders[] = $match;
                        }
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
        'widget_context' => []
    ];
    
    // 根據佔位符名稱推斷用途
    if (strpos($image_key, 'hero') !== false) {
        $context['section_name'] = 'hero';
        if (strpos($image_key, 'bg') !== false) {
            $context['purpose'] = 'background';
        } else {
            $context['purpose'] = 'portrait';
        }
    } elseif (strpos($image_key, 'about') !== false) {
        $context['section_name'] = 'about';
        $context['purpose'] = 'portrait';
    } elseif (strpos($image_key, 'logo') !== false) {
        $context['section_name'] = 'header';
        $context['purpose'] = 'logo';
    } elseif (strpos($image_key, 'footer') !== false) {
        $context['section_name'] = 'footer';
        $context['purpose'] = 'background';
    }
    
    // 尋找相鄰的文字內容
    $context['surrounding_text'] = findSurroundingText($placeholder, $page_data);
    
    return $context;
}

/**
 * 尋找圖片佔位符周圍的文字內容
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
    
    return "{$website_name} - {$section}{$purpose}";
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
    
    $style_map = [
        'background' => "符合{$brand_personality}品牌調性的背景氛圍",
        'portrait' => "展現{$brand_personality}特質的專業形象",
        'logo' => "體現{$brand_personality}理念的標誌設計",
        'icon' => "簡潔{$brand_personality}的圖示風格"
    ];
    
    return $style_map[$purpose] ?? "配合{$brand_personality}品牌的視覺風格";
}

/**
 * 決定技術規格
 */
function determineTechnicalSpecs($context)
{
    $purpose = $context['purpose'] ?? 'general';
    
    $specs_map = [
        'background' => ['size' => '1920x1080', 'format' => 'JPG', 'quality' => 'high'],
        'portrait' => ['size' => '800x600', 'format' => 'JPG', 'quality' => 'high'],
        'logo' => ['size' => '512x512', 'format' => 'PNG', 'quality' => 'high'],
        'icon' => ['size' => '256x256', 'format' => 'PNG', 'quality' => 'standard']
    ];
    
    return $specs_map[$purpose] ?? ['size' => '1024x1024', 'format' => 'JPG', 'quality' => 'standard'];
}

/**
 * 建立測試資料結構
 */
function createTestData($deployer) {
    $test_job_id = 'test-step-095-' . date('YmdHis');
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_job_id;
    
    $deployer->log("建立測試環境: {$test_job_id}");
    
    // 建立目錄結構
    $directories = [
        $work_dir,
        $work_dir . '/config',
        $work_dir . '/json',
        $work_dir . '/layout'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $deployer->log("建立目錄: {$dir}");
        }
    }
    
    // 建立 processed_data.json 測試檔案
    $processed_data = [
        'confirmed_data' => [
            'domain' => 'test-ai-analysis.tw',
            'website_name' => 'AI 圖片分析測試網站',
            'website_description' => '專業的 AI 圖片需求分析測試平台'
        ],
        'user_info' => [
            'industry' => '人工智慧技術服務',
            'company_background' => '專注於 AI 圖片分析與自動化的技術公司',
            'founder_info' => '資深 AI 工程師，具備豐富的機器學習經驗',
            'values' => '創新、效率、品質',
            'business_model' => 'B2B 技術服務提供商',
            'target_market' => '中小企業與新創公司',
            'visual_style' => '現代、簡潔、科技感'
        ]
    ];
    
    file_put_contents($work_dir . '/config/processed_data.json', 
        json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 建立 site-config.json 測試檔案
    $site_config = [
        'website_info' => [
            'website_name' => 'AI 圖片分析測試網站',
            'brand_personality' => '專業創新',
            'target_audience' => '技術決策者與產品經理',
            'service_categories' => ['AI 分析', '圖片處理', '自動化服務'],
            'unique_value' => '業界領先的智能圖片分析技術',
            'keywords' => ['AI', '圖片分析', '自動化', '機器學習']
        ]
    ];
    
    file_put_contents($work_dir . '/json/site-config.json',
        json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 建立測試頁面 JSON 檔案（含圖片佔位符）
    $test_pages = [
        'index-ai.json' => [
            'page_type' => 'index',
            'elements' => [
                [
                    'widgetType' => 'hero-section',
                    'settings' => [
                        'background_image' => '{{image:index_hero_bg}}',
                        'title' => 'AI 驅動的圖片分析平台',
                        'subtitle' => '智能化圖片處理解決方案'
                    ]
                ],
                [
                    'widgetType' => 'about-section',
                    'settings' => [
                        'image' => '{{image:index_about_photo}}',
                        'title' => '關於我們的技術',
                        'content' => '我們提供最先進的 AI 圖片分析服務'
                    ]
                ],
                [
                    'widgetType' => 'footer',
                    'settings' => [
                        'logo' => '{{image:logo}}',
                        'background_image' => '{{image:index_footer_cta_bg}}'
                    ]
                ]
            ]
        ],
        'about-ai.json' => [
            'page_type' => 'about',
            'elements' => [
                [
                    'widgetType' => 'hero-section',
                    'settings' => [
                        'background_image' => '{{image:about_hero_bg}}',
                        'title' => '關於我們的故事'
                    ]
                ],
                [
                    'widgetType' => 'team-section',
                    'settings' => [
                        'team_photo' => '{{image:about_team_photo}}',
                        'company_photo' => '{{image:about_company_photo}}'
                    ]
                ]
            ]
        ],
        'service-ai.json' => [
            'page_type' => 'service',
            'elements' => [
                [
                    'widgetType' => 'hero-section',
                    'settings' => [
                        'background_image' => '{{image:service_hero_bg}}',
                        'title' => '我們的專業服務'
                    ]
                ],
                [
                    'widgetType' => 'service-grid',
                    'settings' => [
                        'service_icon' => '{{image:service_icon}}',
                        'process_diagram' => '{{image:service_process}}'
                    ]
                ]
            ]
        ]
    ];
    
    foreach ($test_pages as $filename => $content) {
        file_put_contents($work_dir . '/layout/' . $filename,
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("建立測試頁面: {$filename}");
    }
    
    return $work_dir;
}

/**
 * 測試用戶資料載入功能
 */
function testLoadOriginalUserData($work_dir, $deployer) {
    $deployer->log("=== 測試用戶資料載入功能 ===");
    
    $user_data = loadOriginalUserData($work_dir);
    
    if (empty($user_data)) {
        $deployer->log("❌ 用戶資料載入失敗", 'ERROR');
        return false;
    }
    
    $deployer->log("✅ 用戶資料載入成功");
    $deployer->log("行業背景: " . ($user_data['industry'] ?? '未找到'));
    $deployer->log("公司背景: " . ($user_data['company_background'] ?? '未找到'));
    $deployer->log("視覺風格: " . ($user_data['visual_style'] ?? '未找到'));
    
    return true;
}

/**
 * 測試圖片需求掃描功能
 */
function testScanPageImageRequirements($work_dir, $deployer) {
    $deployer->log("=== 測試圖片需求掃描功能 ===");
    
    $image_requirements = scanPageImageRequirements($work_dir);
    
    if (empty($image_requirements)) {
        $deployer->log("❌ 圖片需求掃描失敗", 'ERROR');
        return false;
    }
    
    $deployer->log("✅ 圖片需求掃描成功");
    $deployer->log("發現 " . count($image_requirements) . " 個圖片需求");
    
    foreach ($image_requirements as $placeholder => $requirement) {
        $deployer->log("  - {$placeholder}");
        $deployer->log("    頁面: " . implode(', ', $requirement['pages']));
        $deployer->log("    語境數: " . count($requirement['contexts']));
    }
    
    return $image_requirements;
}

/**
 * 測試圖片佔位符提取功能
 */
function testExtractImagePlaceholders($work_dir, $deployer) {
    $deployer->log("=== 測試圖片佔位符提取功能 ===");
    
    $layout_dir = $work_dir . '/layout';
    $test_file = $layout_dir . '/index-ai.json';
    
    if (!file_exists($test_file)) {
        $deployer->log("❌ 測試檔案不存在: {$test_file}", 'ERROR');
        return false;
    }
    
    $page_data = json_decode(file_get_contents($test_file), true);
    $placeholders = extractImagePlaceholders($page_data);
    
    if (empty($placeholders)) {
        $deployer->log("❌ 圖片佔位符提取失敗", 'ERROR');
        return false;
    }
    
    $deployer->log("✅ 圖片佔位符提取成功");
    $deployer->log("從 index-ai.json 提取到 " . count($placeholders) . " 個佔位符:");
    
    foreach ($placeholders as $placeholder) {
        $deployer->log("  - {$placeholder}");
    }
    
    return true;
}

/**
 * 測試圖片語境分析功能
 */
function testAnalyzeImageContext($work_dir, $deployer) {
    $deployer->log("=== 測試圖片語境分析功能 ===");
    
    // 載入測試資料
    $layout_dir = $work_dir . '/layout';
    $test_file = $layout_dir . '/index-ai.json';
    $page_data = json_decode(file_get_contents($test_file), true);
    
    // 測試幾個不同的佔位符
    $test_placeholders = [
        '{{image:index_hero_bg}}',
        '{{image:logo}}',
        '{{image:index_about_photo}}'
    ];
    
    foreach ($test_placeholders as $placeholder) {
        $context = analyzeImageContext($placeholder, $page_data, 'index');
        
        $deployer->log("佔位符: {$placeholder}");
        $deployer->log("  頁面類型: " . $context['page_type']);
        $deployer->log("  區域名稱: " . $context['section_name']);
        $deployer->log("  圖片用途: " . $context['purpose']);
        $deployer->log("  周圍文字: " . substr($context['surrounding_text'], 0, 50) . "...");
    }
    
    return true;
}

/**
 * 測試結構化需求生成功能
 */
function testGenerateImageRequirementsJson($work_dir, $deployer) {
    $deployer->log("=== 測試結構化需求生成功能 ===");
    
    // 載入必要資料
    $site_config_file = $work_dir . '/json/site-config.json';
    $site_config = json_decode(file_get_contents($site_config_file), true);
    
    // 掃描圖片需求
    $image_requirements_raw = scanPageImageRequirements($work_dir);
    
    if (empty($image_requirements_raw)) {
        $deployer->log("❌ 無圖片需求資料，無法測試", 'ERROR');
        return false;
    }
    
    // 生成結構化需求
    $image_requirements = generateImageRequirementsJson($image_requirements_raw, $site_config);
    
    if (empty($image_requirements)) {
        $deployer->log("❌ 結構化需求生成失敗", 'ERROR');
        return false;
    }
    
    $deployer->log("✅ 結構化需求生成成功");
    $deployer->log("生成 " . count($image_requirements) . " 個結構化需求");
    
    // 儲存測試結果
    $output_file = $work_dir . '/json/test-image-requirements.json';
    file_put_contents($output_file, json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("測試結果已儲存: test-image-requirements.json");
    
    // 顯示部分結果
    $first_key = array_keys($image_requirements)[0];
    $first_requirement = $image_requirements[$first_key];
    
    $deployer->log("範例需求 ({$first_key}):");
    $deployer->log("  標題: " . $first_requirement['title']);
    $deployer->log("  頁面語境: " . $first_requirement['page_context']);
    $deployer->log("  風格指導: " . $first_requirement['style_guidance']);
    
    return $image_requirements;
}

/**
 * 測試尋找周圍文字功能
 */
function testFindSurroundingText($work_dir, $deployer) {
    $deployer->log("=== 測試尋找周圍文字功能 ===");
    
    $layout_dir = $work_dir . '/layout';
    $test_file = $layout_dir . '/index-ai.json';
    $page_data = json_decode(file_get_contents($test_file), true);
    
    $test_placeholder = '{{image:index_hero_bg}}';
    $surrounding_text = findSurroundingText($test_placeholder, $page_data);
    
    if (empty($surrounding_text)) {
        $deployer->log("⚠️ 未找到周圍文字，但這可能是正常的", 'WARNING');
    } else {
        $deployer->log("✅ 成功找到周圍文字");
        $deployer->log("周圍文字: " . $surrounding_text);
    }
    
    return true;
}

/**
 * 驗證佔位符格式
 */
function validatePlaceholderFormat($placeholders, $deployer) {
    $deployer->log("=== 驗證佔位符格式 ===");
    
    $valid_pattern = '/^\{\{image:[a-z_]+\}\}$/';
    $valid_count = 0;
    
    foreach ($placeholders as $placeholder) {
        if (preg_match($valid_pattern, $placeholder)) {
            $valid_count++;
            $deployer->log("✅ 格式正確: {$placeholder}");
        } else {
            $deployer->log("❌ 格式錯誤: {$placeholder}", 'ERROR');
        }
    }
    
    $total = count($placeholders);
    $deployer->log("格式驗證結果: {$valid_count}/{$total} 個佔位符格式正確");
    
    return $valid_count === $total;
}

/**
 * 執行完整測試流程
 */
function runCompleteTest() {
    $deployer = new TestDeployer();
    
    $deployer->log("🚀 開始執行步驟 9.5 完整功能測試");
    $deployer->log("測試項目: 圖片需求掃描與分析功能");
    
    $test_results = [];
    
    try {
        // 1. 建立測試資料
        $work_dir = createTestData($deployer);
        $test_results['create_test_data'] = true;
        
        // 2. 測試用戶資料載入
        $test_results['load_user_data'] = testLoadOriginalUserData($work_dir, $deployer);
        
        // 3. 測試圖片佔位符提取
        $test_results['extract_placeholders'] = testExtractImagePlaceholders($work_dir, $deployer);
        
        // 4. 測試圖片需求掃描
        $image_requirements = testScanPageImageRequirements($work_dir, $deployer);
        $test_results['scan_requirements'] = !empty($image_requirements);
        
        // 5. 測試圖片語境分析
        $test_results['analyze_context'] = testAnalyzeImageContext($work_dir, $deployer);
        
        // 6. 測試結構化需求生成
        $structured_requirements = testGenerateImageRequirementsJson($work_dir, $deployer);
        $test_results['generate_requirements'] = !empty($structured_requirements);
        
        // 7. 測試尋找周圍文字
        $test_results['find_surrounding_text'] = testFindSurroundingText($work_dir, $deployer);
        
        // 8. 驗證佔位符格式
        if (!empty($image_requirements)) {
            $all_placeholders = array_keys($image_requirements);
            $test_results['validate_format'] = validatePlaceholderFormat($all_placeholders, $deployer);
        }
        
        // 測試結果統計
        $deployer->log("=== 測試結果統計 ===");
        $passed = 0;
        $total = count($test_results);
        
        foreach ($test_results as $test_name => $result) {
            $status = $result ? "✅ 通過" : "❌ 失敗";
            $deployer->log("{$test_name}: {$status}");
            if ($result) $passed++;
        }
        
        $deployer->log("測試通過率: {$passed}/{$total} (" . round(($passed/$total)*100, 1) . "%)");
        
        if ($passed === $total) {
            $deployer->log("🎉 所有測試通過！步驟 9.5 核心功能運作正常");
        } else {
            $deployer->log("⚠️ 部分測試失敗，需要檢查相關功能", 'WARNING');
        }
        
        // 清理測試資料
        $deployer->log("清理測試資料: {$work_dir}");
        if (is_dir($work_dir)) {
            exec("rm -rf " . escapeshellarg($work_dir));
        }
        
        return $test_results;
        
    } catch (Exception $e) {
        $deployer->log("❌ 測試執行失敗: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $deployer = new TestDeployer();
    $deployer->log("Phase 1 Day 3: 實作圖片需求掃描與分析功能測試");
    $deployer->log("目標: 驗證 step-09-5.php 核心功能的正確性");
    
    $results = runCompleteTest();
    
    if ($results) {
        $deployer->log("測試完成！請檢查上方結果。");
    } else {
        $deployer->log("測試過程中發生錯誤，請檢查日誌。");
    }
}