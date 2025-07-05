<?php
/**
 * 步驟 09: 頁面組裝與 AI 文案填充 (重構版)
 * 
 * 流程：
 * 1. 根據 site-config.json 中的 layout_selection，將 template/container 的 JSON 檔案合併成完整的頁面骨架
 * 2. 合併完成後，針對每個合成頁面進行{{}}的篩選，生成text-mapping.json
 * 3. 篩選出來的text-mapping.json 跟 site-config.json 傳給AI模型進行文案填充
 * 4. 根據AI填充後的text-mapping.json更換頁面內容，並儲存為 *-ai.json
 */

// 確保工作目錄存在
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
if (!is_dir($work_dir)) {
    $deployer->log("錯誤: 工作目錄不存在，請先執行步驟08");
    return ['status' => 'error', 'message' => '工作目錄不存在，請先執行步驟08'];
}

// 載入處理後的資料
$processed_data_file = $work_dir . '/config/processed_data.json';
if (!file_exists($processed_data_file)) {
    $deployer->log("錯誤: processed_data.json 不存在，請先執行步驟08");
    return ['status' => 'error', 'message' => 'processed_data.json 不存在，請先執行步驟08'];
}
$processed_data = json_decode(file_get_contents($processed_data_file), true);

// 載入 AI 生成的配置
$site_config_file = $work_dir . '/json/site-config.json';
if (!file_exists($site_config_file)) {
    $deployer->log("錯誤: site-config.json 不存在，請先執行步驟08");
    return ['status' => 'error', 'message' => 'site-config.json 不存在，請先執行步驟08'];
}
$site_config = json_decode(file_get_contents($site_config_file), true);

$domain = $processed_data['confirmed_data']['domain'];
$deployer->log("開始頁面組裝與 AI 文案填充: {$domain}");

// 確保 layout 目錄存在
$layout_dir = $work_dir . '/layout';
if (!is_dir($layout_dir)) {
    mkdir($layout_dir, 0755, true);
}

// 載入部署配置
$deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
if (!file_exists($deploy_config_file)) {
    $deployer->log("錯誤: deploy-config.json 不存在");
    return ['status' => 'error', 'message' => 'deploy-config.json 不存在'];
}

$deploy_config = json_decode(file_get_contents($deploy_config_file), true);
if (!$deploy_config) {
    $deployer->log("錯誤: 無法解析 deploy-config.json");
    return ['status' => 'error', 'message' => '無法解析 deploy-config.json'];
}

// 取得 AI API 設定
$ai_features = $deploy_config['ai_features'] ?? [];
$api_credentials = $deploy_config['api_credentials'] ?? [];
$openai_config = $api_credentials['openai'] ?? [];
$gemini_config = $api_credentials['gemini'] ?? [];

// 根據成本優化策略選擇 AI 服務
$cost_optimization = $ai_features['cost_optimization'] ?? [];
$prefer_gemini = $cost_optimization['prefer_gemini'] ?? false;
$selected_ai_service = $prefer_gemini ? 'gemini' : 'openai';
$selected_ai_config = $prefer_gemini ? $gemini_config : $openai_config;

/**
 * 步驟 1: 合併頁面容器
 */
function mergePageContainers($page_name, $containers, $template_base_path, $deployer) {
    $page_data = [
        'content' => [],
        'page_settings' => [],
        'version' => '0.4',
        'title' => $page_name,
        'type' => 'page'
    ];
    
    foreach ($containers as $container_name) {
        $container_file = $template_base_path . '/container/' . $container_name . '.json';
        
        if (file_exists($container_file)) {
            $deployer->log("合併容器: {$container_name}");
            $container_data = json_decode(file_get_contents($container_file), true);
            
            if ($container_data && isset($container_data['content'])) {
                // 將容器的 content 依序串接到頁面的 content 數組中
                if (is_array($container_data['content'])) {
                    $page_data['content'] = array_merge($page_data['content'], $container_data['content']);
                }
                
                // 合併其他屬性（如果需要）
                if (isset($container_data['page_settings']) && is_array($container_data['page_settings'])) {
                    $page_data['page_settings'] = array_merge($page_data['page_settings'], $container_data['page_settings']);
                }
            }
        } else {
            $deployer->log("警告: 容器檔案不存在: {$container_file}");
        }
    }
    
    return $page_data;
}

/**
 * 步驟 2: 提取頁面中的佔位符 ({{}}格式)，排除背景和圖片相關
 */
function extractPlaceholders($data, $page_name) {
    $placeholders = [];
    
    $findPlaceholdersRecursive = function($data, &$placeholders) use (&$findPlaceholdersRecursive) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    // 查找 {{}} 格式的佔位符
                    if (preg_match_all('/\{\{([^}]+)\}\}/', $value, $matches)) {
                        foreach ($matches[1] as $placeholder) {
                            // 排除背景和圖片相關的佔位符
                            if (!preg_match('/_(BG|PHOTO)$/', $placeholder)) {
                                $placeholders[$placeholder] = '{ai生成}';
                            }
                        }
                    }
                } elseif (is_array($value)) {
                    $findPlaceholdersRecursive($value, $placeholders);
                }
            }
        }
    };
    
    $findPlaceholdersRecursive($data, $placeholders);
    return $placeholders;
}

/**
 * 步驟 3: 呼叫 AI 進行文案填充
 */
function callAIForTextMapping($text_mapping, $site_config, $ai_service, $ai_config, $all_api_credentials, $deployer) {
    // 建構 AI 提示詞
    $prompt = buildAIPrompt($text_mapping, $site_config);
    
    $deployer->log("呼叫 AI 進行文案填充...");
    $deployer->log("提示詞長度: " . mb_strlen($prompt) . " 字元");
    $deployer->log("使用 AI 服務: " . $ai_service);
    
    // 除錯：顯示完整提示詞內容
    $deployer->log("=== AI 提示詞內容 (前1000字元) ===");
    $deployer->log(mb_substr($prompt, 0, 1000));
    $deployer->log("=== 提示詞結尾 (後500字元) ===");
    $deployer->log(mb_substr($prompt, -500));
    
    // 根據選擇的 AI 服務呼叫對應 API
    if ($ai_service === 'gemini') {
        $response = callGemini($prompt, $ai_config, $all_api_credentials, $deployer);
    } else {
        $response = callOpenAI($prompt, $ai_config, $deployer);
    }
    
    if (!$response) {
        throw new Exception("AI 文案填充失敗");
    }
    
    // 解析 AI 回應
    $filled_mapping = parseAIResponse($response, $deployer);
    
    return $filled_mapping;
}

/**
 * 建構 AI 提示詞
 */
function buildAIPrompt($text_mapping, $site_config) {
    // 從 site_config 的 website_info 結構中提取品牌資訊
    $website_info = $site_config['website_info'] ?? [];
    $menu_structure = $site_config['menu_structure'] ?? [];
    $categories = $site_config['categories'] ?? [];
    
    $brand_info = [
        'website_name' => $website_info['website_blogname'] ?? '',
        'website_description' => $website_info['website_blogdescription'] ?? '',
        'target_audience' => $website_info['seo_description'] ?? '',  // 使用 SEO 描述作為目標受眾參考
        'brand_tone' => '專業、親和'  // 預設品牌調性
    ];
    
    // 收集所有有效的連結路徑
    $valid_links = [];
    
    // 1. 從主選單結構中提取連結
    if (isset($menu_structure['primary'])) {
        foreach ($menu_structure['primary'] as $menu_item) {
            $valid_links[] = $menu_item['url'] ?? '';
        }
    }
    
    // 2. 從分類中生成連結
    foreach ($categories as $category) {
        if (isset($category['slug'])) {
            $valid_links[] = '/category/' . $category['slug'];
        }
    }
    
    // 3. 從頁面清單中生成連結（page_list）
    $page_list = $site_config['page_list'] ?? [];
    foreach ($page_list as $slug => $title) {
        $valid_links[] = '/' . $slug;
    }
    
    // 4. 從 contact_info 中提取聯絡連結
    $contact_info = $site_config['contact_info'] ?? [];
    if (isset($contact_info['email'])) {
        $valid_links[] = 'mailto:' . $contact_info['email'];
    }
    if (isset($contact_info['phone'])) {
        $valid_links[] = 'tel:' . $contact_info['phone'];
    }
    
    // 5. 從 social_media 中提取社群連結
    $social_media = $site_config['social_media'] ?? [];
    foreach ($social_media as $platform => $url) {
        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $valid_links[] = $url;
        }
    }
    
    // 6. 加入通用錨點連結
    $valid_links = array_merge($valid_links, [
        '#', '#top', '#contact', '#about', '#service', '#home'
    ]);
    
    // 7. 清理和去重
    $valid_links = array_filter($valid_links, function($link) {
        return !empty($link) && trim($link) !== '';
    });
    $valid_links = array_unique($valid_links);
    
    $prompt = "你是一位專業的網站文案撰寫師。請根據以下品牌資訊，為網站頁面填充具體的文案內容。\n\n";
    
    $prompt .= "## 品牌資訊\n";
    $prompt .= "網站名稱：{$brand_info['website_name']}\n";
    $prompt .= "網站描述：{$brand_info['website_description']}\n";
    $prompt .= "目標受眾：{$brand_info['target_audience']}\n";
    $prompt .= "品牌調性：{$brand_info['brand_tone']}\n\n";
    
    $prompt .= "## 可用連結清單\n";
    $prompt .= "所有 LINK 類型的內容只能使用以下連結，不可自創：\n";
    foreach ($valid_links as $link) {
        if (!empty($link)) {
            $prompt .= "- {$link}\n";
        }
    }
    $prompt .= "\n";
    
    $prompt .= "## 需要填充的文案對應表\n";
    $prompt .= "請將以下 JSON 中所有 \"{ai生成}\" 替換為具體的文案內容：\n\n";
    $prompt .= json_encode($text_mapping, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
    
    $prompt .= "## 填充要求\n";
    $prompt .= "1. 保持 JSON 格式完整\n";
    $prompt .= "2. 文案要符合品牌調性和目標受眾\n";
    $prompt .= "3. TITLE 類型控制在 10-30 字\n";
    $prompt .= "4. SUBTITLE 類型控制在 20-50 字\n";
    $prompt .= "5. CONTENT 或 DESCR 類型控制在 150-600 字（至少是 SUBTITLE 的 3 倍長度），內容要深入豐富\n";
    $prompt .= "6. BUTTON 類型控制在 2-8 字\n";
    $prompt .= "7. LINK 類型只能使用上述可用連結清單中的連結\n";
    $prompt .= "8. CONTENT 和 DESCR 類型需要包含具體細節、實例或深度說明，不可過於簡短\n";
    $prompt .= "9. 回應只包含填充完成的 JSON，不要額外說明\n";
    
    return $prompt;
}

/**
 * 呼叫 OpenAI API
 */
function callOpenAI($prompt, $openai_config, $deployer) {
    $api_key = $openai_config['api_key'] ?? '';
    $model = $openai_config['model'] ?? 'gpt-4o-mini';
    $base_url = $openai_config['base_url'] ?? 'https://api.openai.com/v1/';
    
    if (empty($api_key)) {
        throw new Exception("OpenAI API 金鑰未設定");
    }
    
    $deployer->log("使用模型: {$model}");
    
    $data = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 16000
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . 'chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("OpenAI API 呼叫失敗: HTTP {$http_code}");
    }
    
    $response_data = json_decode($response, true);
    
    if (!isset($response_data['choices'][0]['message']['content'])) {
        throw new Exception("OpenAI API 回應格式錯誤");
    }
    
    return $response_data['choices'][0]['message']['content'];
}

/**
 * 呼叫 Gemini API
 */
function callGemini($prompt, $gemini_config, $all_api_credentials, $deployer) {
    $api_key = $gemini_config['api_key'] ?? '';
    $model = $gemini_config['model'] ?? 'gemini-2.5-flash';
    $base_url = $gemini_config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta';
    
    if (empty($api_key)) {
        throw new Exception("Gemini API 金鑰未設定");
    }
    
    $deployer->log("使用模型: {$model}");
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 16000
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url . "/models/{$model}:generateContent?key={$api_key}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Gemini API 呼叫失敗: HTTP {$http_code}");
    }
    
    $response_data = json_decode($response, true);
    
    if (!$response_data) {
        throw new Exception("無法解析 Gemini API 回應 JSON");
    }
    
    // 詳細回應除錯
    $deployer->log("Gemini API 原始回應: " . substr($response, 0, 500) . "...");
    
    // 檢查回應是否被截斷
    if (isset($response_data['candidates'][0]['finishReason']) && 
        $response_data['candidates'][0]['finishReason'] === 'MAX_TOKENS') {
        $deployer->log("警告: Gemini 回應因達到最大 token 限制被截斷，降級使用 OpenAI");
        
        // 切換到 OpenAI 作為備援
        $openai_config = $all_api_credentials['openai'] ?? [];
        if (!empty($openai_config['api_key'])) {
            return callOpenAI($prompt, $openai_config, $deployer);
        } else {
            throw new Exception("Gemini API 回應被截斷且 OpenAI 備援未配置");
        }
    }
    
    // 多種可能的回應格式處理
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        return $response_data['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($response_data['candidates'][0]['output'])) {
        return $response_data['candidates'][0]['output'];
    } elseif (isset($response_data['text'])) {
        return $response_data['text'];
    } else {
        throw new Exception("Gemini API 回應格式錯誤: " . json_encode($response_data, JSON_UNESCAPED_UNICODE));
    }
}

/**
 * 解析 AI 回應
 */
function parseAIResponse($response, $deployer) {
    $deployer->log("AI 回應長度: " . mb_strlen($response) . " 字元");
    
    // 嘗試提取 JSON
    $json_content = trim($response);
    
    // 移除可能的 markdown 標記
    $json_content = preg_replace('/```json\s*/', '', $json_content);
    $json_content = preg_replace('/```\s*$/', '', $json_content);
    
    // 找到第一個 { 和最後一個 }
    $first_brace = strpos($json_content, '{');
    $last_brace = strrpos($json_content, '}');
    
    if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
        $json_content = substr($json_content, $first_brace, $last_brace - $first_brace + 1);
    }
    
    $parsed = json_decode($json_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("無法解析 AI 回應的 JSON: " . json_last_error_msg());
    }
    
    return $parsed;
}

/**
 * 步驟 4: 根據 text-mapping 替換頁面內容
 */
function applyTextMapping($page_data, $page_mapping) {
    $replaceInContent = function($data, $mapping) use (&$replaceInContent) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_string($value)) {
                    // 替換所有佔位符
                    foreach ($mapping as $placeholder => $replacement) {
                        $search = '{{' . $placeholder . '}}';
                        $data[$key] = str_replace($search, $replacement, $data[$key]);
                    }
                } elseif (is_array($value)) {
                    $data[$key] = $replaceInContent($value, $mapping);
                }
            }
        }
        return $data;
    };
    
    return $replaceInContent($page_data, $page_mapping);
}

// ===================== 主要執行流程 =====================

try {
    $template_base_path = DEPLOY_BASE_PATH . '/template';
    $layout_selection = $site_config['layout_selection'] ?? [];
    
    $text_mapping = [];
    $processed_pages = [];
    
    // 處理每個頁面 (layout_selection 是陣列結構)
    foreach ($layout_selection as $page_config) {
        $page_name = $page_config['page'] ?? '';
        $containers = $page_config['container'] ?? [];
        
        if (empty($page_name)) {
            continue;
        }
        
        $deployer->log("處理頁面: {$page_name}");
        
        // 步驟 1: 合併容器 (containers 是物件，需要轉換為陣列)
        $container_list = array_values($containers);
        $page_data = mergePageContainers($page_name, $container_list, $template_base_path, $deployer);
        
        // 儲存原始合併頁面
        $page_file = $layout_dir . '/' . $page_name . '.json';
        file_put_contents($page_file, json_encode($page_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $deployer->log("儲存合併頁面: {$page_name}.json");
        
        // 步驟 2: 提取佔位符
        $page_placeholders = extractPlaceholders($page_data, $page_name);
        
        if (!empty($page_placeholders)) {
            $text_mapping[$page_name] = $page_placeholders;
            $deployer->log("發現 " . count($page_placeholders) . " 個佔位符: " . implode(', ', array_keys($page_placeholders)));
        }
        
        $processed_pages[$page_name] = $page_data;
    }
    
    // 處理全域模板並提取佔位符
    $global_template_dir = $template_base_path . '/global';
    if (is_dir($global_template_dir)) {
        $global_templates = glob($global_template_dir . '/*.json');
        
        foreach ($global_templates as $template_file) {
            $template_name = basename($template_file, '.json');
            $deployer->log("處理全域模板: {$template_name}");
            
            // 讀取全域模板
            $template_data = json_decode(file_get_contents($template_file), true);
            if (!$template_data) {
                $deployer->log("警告: 無法解析全域模板: {$template_file}");
                continue;
            }
            
            // 儲存原始全域模板到 layout/global/
            $global_layout_dir = $layout_dir . '/global';
            if (!is_dir($global_layout_dir)) {
                mkdir($global_layout_dir, 0755, true);
            }
            
            $global_page_file = $global_layout_dir . '/' . $template_name . '.json';
            file_put_contents($global_page_file, json_encode($template_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $deployer->log("儲存全域模板: global/{$template_name}.json");
            
            // 提取全域模板的佔位符
            $global_placeholders = extractPlaceholders($template_data, $template_name);
            
            if (!empty($global_placeholders)) {
                $text_mapping['global_' . $template_name] = $global_placeholders;
                $deployer->log("發現 " . count($global_placeholders) . " 個全域佔位符: " . implode(', ', array_keys($global_placeholders)));
            }
            
            $processed_pages['global_' . $template_name] = $template_data;
        }
    }
    
    // 儲存 text-mapping.json
    $text_mapping_file = $layout_dir . '/text-mapping.json';
    file_put_contents($text_mapping_file, json_encode($text_mapping, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $deployer->log("儲存文案對應表: text-mapping.json");
    
    // 步驟 3: AI 文案填充
    if (!empty($text_mapping)) {
        $filled_mapping = callAIForTextMapping($text_mapping, $site_config, $selected_ai_service, $selected_ai_config, $api_credentials, $deployer);
        
        // 儲存 AI 填充後的 mapping
        $filled_mapping_file = $layout_dir . '/text-mapping-filled.json';
        file_put_contents($filled_mapping_file, json_encode($filled_mapping, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $deployer->log("儲存 AI 填充後的文案對應表: text-mapping-filled.json");
        
        // 步驟 4: 應用文案到各頁面和全域模板（包含沒有佔位符的）
        foreach ($processed_pages as $page_name => $page_data) {
            // 如果有佔位符要替換，就進行替換；否則直接使用原始資料
            if (isset($filled_mapping[$page_name])) {
                $updated_page_data = applyTextMapping($page_data, $filled_mapping[$page_name]);
                $replacement_count = count($filled_mapping[$page_name]);
            } else {
                $updated_page_data = $page_data;  // 沒有佔位符，直接使用原始資料
                $replacement_count = 0;
            }
            
            // 判斷是否為全域模板
            if (strpos($page_name, 'global_') === 0) {
                // 全域模板儲存到 global/ 子目錄
                $global_layout_dir = $layout_dir . '/global';
                if (!is_dir($global_layout_dir)) {
                    mkdir($global_layout_dir, 0755, true);
                }
                $actual_template_name = str_replace('global_', '', $page_name);
                $ai_page_file = $global_layout_dir . '/' . $actual_template_name . '-ai.json';
                $deployer->log("儲存 AI 調整後的全域模板: global/{$actual_template_name}-ai.json");
            } else {
                // 一般頁面
                $ai_page_file = $layout_dir . '/' . $page_name . '-ai.json';
                $deployer->log("儲存 AI 調整後的頁面: {$page_name}-ai.json");
            }
            
            file_put_contents($ai_page_file, json_encode($updated_page_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // 統計替換數量
            $item_type = strpos($page_name, 'global_') === 0 ? '全域模板' : '頁面';
            $display_name = strpos($page_name, 'global_') === 0 ? str_replace('global_', '', $page_name) : $page_name;
            if ($replacement_count > 0) {
                $deployer->log("✅ {$item_type} {$display_name} 處理完成，應用了 {$replacement_count} 個文案替換");
            } else {
                $deployer->log("✅ {$item_type} {$display_name} 處理完成，無需文案替換");
            }
        }
    }
    
    $deployer->log("頁面組裝與 AI 文案填充完成，共處理 " . count($processed_pages) . " 個頁面");
    $deployer->log("檔案儲存位置: {$layout_dir}");
    
    return [
        'status' => 'success',
        'pages_processed' => count($processed_pages),
        'text_mapping_count' => array_sum(array_map('count', $text_mapping)),
        'files_created' => [
            'pages' => array_keys($processed_pages),
            'text_mapping' => 'text-mapping.json',
            'ai_pages' => array_map(fn($p) => $p . '-ai.json', array_keys($processed_pages))
        ]
    ];
    
} catch (Exception $e) {
    $deployer->log("頁面組裝與 AI 文案填充失敗: " . $e->getMessage(), 'ERROR');
    return ['status' => 'error', 'message' => $e->getMessage()];
}