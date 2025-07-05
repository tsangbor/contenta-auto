<?php
/**
 * 步驟 09: 生成頁面 JSON 並進行 AI 文字調整
 * 根據 layout_selection 合併容器 JSON，並使用 AI 替換文字內容
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

// 創建空的 image-prompts.json 檔案供動態生成使用
$image_prompts_path = $work_dir . '/json/image-prompts.json';
if (!file_exists($image_prompts_path)) {
    // 創建空的檔案，絕不複製模板避免內容污染
    file_put_contents($image_prompts_path, '{}');
    $deployer->log("✅ 創建空的 image-prompts.json 檔案（避免模板污染）");
}

$domain = $processed_data['confirmed_data']['domain'];
$deployer->log("開始生成頁面 JSON 檔案: {$domain}");

// 確保 layout 目錄存在
$layout_dir = $work_dir . '/layout';
if (!is_dir($layout_dir)) {
    mkdir($layout_dir, 0755, true);
}

// 取得 AI API 設定
$openai_config = [
    'api_key' => $config->get('api_credentials.openai.api_key'),
    'model' => 'gpt-4.1-nano', // 強制使用相容性最高的模型 gpt-3.5-turbo
    'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
];

$gemini_config = [
    'api_key' => $config->get('api_credentials.gemini.api_key'),
    'model' => $config->get('api_credentials.gemini.model') ?: 'gemini-2.5-flash',
    'base_url' => $config->get('api_credentials.gemini.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta'
];

// 選擇 AI 服務
$use_openai = !empty($openai_config['api_key']);
if ($use_openai) {
    $ai_service = 'OpenAI';
    $ai_config = $openai_config;
} else {
    $ai_service = 'Gemini';
    $ai_config = $gemini_config;
}

/**
 * 合併容器 JSON 檔案
 */
if (!function_exists('mergeContainerJsonFiles')) {
function mergeContainerJsonFiles($container_names, $template_dir, $deployer)
{
    $merged_content = [];
    $container_dir = $template_dir . '/container';
    
    foreach ($container_names as $container_name) {
        $file_path = $container_dir . '/' . $container_name . '.json';
        
        if (!file_exists($file_path)) {
            $deployer->log("警告: 容器檔案不存在: {$file_path}");
            continue;
        }
        
        $json_content = file_get_contents($file_path);
        $container_data = json_decode($json_content, true);
        
        if (isset($container_data['content']) && is_array($container_data['content'])) {
            // 合併 content 陣列
            $merged_content = array_merge($merged_content, $container_data['content']);
            $deployer->log("合併容器: {$container_name}");
        } else {
            $deployer->log("警告: 容器格式不正確: {$container_name}");
        }
    }
    
    return $merged_content;
}
}

/**
 * 簡化頁面內容，只保留需要替換的文字部分
 */
function simplifyPageContent($content, $max_depth = 4, $current_depth = 0)
{
    if ($current_depth > $max_depth) {
        return '[內容過深，已省略]';
    }
    
    $simplified = [];
    
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                // 檢查是否需要替換的內容
                if (shouldIncludeForReplacement($key, $value, $content)) {
                    $simplified[$key] = $value;
                }
            } elseif (is_array($value)) {
                $nested = simplifyPageContent($value, $max_depth, $current_depth + 1);
                if (!empty($nested)) {
                    $simplified[$key] = $nested;
                }
            }
        }
    }
    
    return $simplified;
}

/**
 * 判斷是否應該包含此欄位進行替換
 */
function shouldIncludeForReplacement($key, $value, $context = [])
{
    // 1. 明確的佔位符格式：包含 _TITLE、_SUBTITLE、_CONTENT
    if (preg_match('/_(TITLE|SUBTITLE|CONTENT)/i', $value)) {
        return true;
    }
    
    // 2. Elementor 元素的特定欄位
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    
    // heading 元素的 title 欄位
    if ($widget_type === 'heading' && $key === 'title') {
        return true;
    }
    
    // icon-box 元素的 title_text 和 description_text
    if ($widget_type === 'icon-box' && in_array($key, ['title_text', 'description_text'])) {
        return true;
    }
    
    // text-editor 元素的 editor 欄位
    if ($widget_type === 'text-editor' && $key === 'editor') {
        return true;
    }
    
    // 3. settings 中的 title 欄位（任何元素）
    if ($key === 'title' && strlen($value) > 2) {
        return true;
    }
    
    // 4. 包含明確佔位符模式的文字
    if (preg_match('/^[A-Z][A-Z_]*[A-Z]$/', $value) && strlen($value) >= 3) {
        return true;
    }
    
    // 5. 常見的內容欄位名稱
    if (preg_match('/(title|content|text|description|subtitle|heading)$/i', $key) && strlen($value) > 5) {
        return true;
    }
    
    return false;
}

/**
 * 找出頁面中所有需要替換的標準化佔位符
 * 支援正規化格式：{{*_TITLE}} {{*_SUBTITLE}} {{*_LIST_TITLE}} {{*_LIST_SUBTITLE}} {{*_DESCR}} {{*_CONTENT}} {{*CTA_BUTTON}} {{*CTA_LINK}} {{*_ADDR}}
 */
function findPlaceholders($content, &$placeholders = [], $path = '', $context = [])
{
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            $current_path = $path ? "$path.$key" : $key;
            $current_context = array_merge($context, [$key => $value]);
            
            if (is_string($value)) {
                // 檢查是否應該包含進行替換
                if (shouldIncludeForReplacement($key, $value, $content)) {
                    
                    // 1. 標準化佔位符格式 - 使用正規化表達式識別
                    $standardized_patterns = [
                        '/\{\{\w*_TITLE\}\}/',           // {{*_TITLE}}
                        '/\{\{\w*_SUBTITLE\}\}/',        // {{*_SUBTITLE}}  
                        '/\{\{\w*_LIST_TITLE\}\}/',      // {{*_LIST_TITLE}}
                        '/\{\{\w*_LIST_SUBTITLE\}\}/',   // {{*_LIST_SUBTITLE}}
                        '/\{\{\w*_DESCR\}\}/',           // {{*_DESCR}}
                        '/\{\{\w*_CONTENT\}\}/',         // {{*_CONTENT}}
                        '/\{\{\w*CTA_BUTTON\}\}/',       // {{*CTA_BUTTON}}
                        '/\{\{\w*CTA_LINK\}\}/',         // {{*CTA_LINK}}
                        '/\{\{\w*_ADDR\}\}/',            // {{*_ADDR}}
                    ];
                    
                    $found_standardized = false;
                    foreach ($standardized_patterns as $pattern) {
                        if (preg_match_all($pattern, $value, $matches)) {
                            foreach ($matches[0] as $placeholder) {
                                $placeholder_info = [
                                    'placeholder' => $placeholder,
                                    'path' => $current_path,
                                    'context' => $current_context,
                                    'original_value' => $value,
                                    'field_key' => $key,
                                    'type' => 'standardized'
                                ];
                                if (!isPlaceholderExists($placeholder_info, $placeholders)) {
                                    $placeholders[] = $placeholder_info;
                                }
                            }
                            $found_standardized = true;
                        }
                    }
                    
                    // 2. 向後相容：舊格式的大寫佔位符
                    if (!$found_standardized) {
                        if (preg_match_all('/[A-Z_]+(TITLE|SUBTITLE|CONTENT)[A-Z_]*/', $value, $matches)) {
                            foreach ($matches[0] as $placeholder) {
                                $placeholder_info = [
                                    'placeholder' => $placeholder,
                                    'path' => $current_path,
                                    'context' => $current_context,
                                    'original_value' => $value,
                                    'field_key' => $key,
                                    'type' => 'legacy_semantic'
                                ];
                                if (!isPlaceholderExists($placeholder_info, $placeholders)) {
                                    $placeholders[] = $placeholder_info;
                                }
                            }
                        }
                        // 3. 純大寫佔位符
                        elseif (preg_match('/^[A-Z][A-Z_]*[A-Z]$/', $value) && strlen($value) >= 3) {
                            $placeholder_info = [
                                'placeholder' => $value,
                                'path' => $current_path,
                                'context' => $current_context,
                                'original_value' => $value,
                                'field_key' => $key,
                                'type' => 'legacy'
                            ];
                            if (!isPlaceholderExists($placeholder_info, $placeholders)) {
                                $placeholders[] = $placeholder_info;
                            }
                        }
                        // 4. 標記需要 AI 替換的中文內容
                        elseif (preg_match('/[\x{4e00}-\x{9fff}]/u', $value)) {
                            // 為中文內容生成標準化佔位符
                            $semantic_placeholder = generateStandardizedPlaceholder($key, $value, $current_context, $current_path);
                            if ($semantic_placeholder && !isPlaceholderExists($semantic_placeholder, $placeholders)) {
                                $placeholders[] = $semantic_placeholder;
                            }
                        }
                    }
                }
            } elseif (is_array($value)) {
                findPlaceholders($value, $placeholders, $current_path, $current_context);
            }
        }
    }
    
    return $placeholders;
}

/**
 * 檢查佔位符是否已存在
 */
function isPlaceholderExists($new_placeholder, $existing_placeholders)
{
    foreach ($existing_placeholders as $existing) {
        $new_key = is_array($new_placeholder) ? $new_placeholder['placeholder'] : $new_placeholder;
        $existing_key = is_array($existing) ? $existing['placeholder'] : $existing;
        
        if ($new_key === $existing_key) {
            return true;
        }
    }
    return false;
}

/**
 * 為中文內容生成標準化佔位符
 */
function generateStandardizedPlaceholder($key, $value, $context = [], $path = '')
{
    // 根據欄位名稱和上下文生成標準化佔位符
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    $section_type = '';
    
    // 從路徑推斷區塊類型
    if (strpos($path, 'hero') !== false) {
        $section_type = 'HERO';
    } elseif (strpos($path, 'about') !== false) {
        $section_type = 'ABOUT';
    } elseif (strpos($path, 'service') !== false) {
        $section_type = 'SERVICE';
    } elseif (strpos($path, 'contact') !== false) {
        $section_type = 'CONTACT';
    } elseif (strpos($path, 'cta') !== false) {
        $section_type = 'CTA';
    } else {
        $section_type = 'ELEMENT';
    }
    
    // 根據欄位名稱生成標準化佔位符
    if (preg_match('/title/i', $key)) {
        if (strpos($key, 'list') !== false) {
            $placeholder = "{{" . $section_type . "_LIST_TITLE}}";
        } else {
            $placeholder = "{{" . $section_type . "_TITLE}}";
        }
    } elseif (preg_match('/subtitle/i', $key)) {
        if (strpos($key, 'list') !== false) {
            $placeholder = "{{" . $section_type . "_LIST_SUBTITLE}}";
        } else {
            $placeholder = "{{" . $section_type . "_SUBTITLE}}";
        }
    } elseif (preg_match('/descr|description/i', $key)) {
        $placeholder = "{{" . $section_type . "_DESCR}}";
    } elseif (preg_match('/content|text|editor/i', $key)) {
        $placeholder = "{{" . $section_type . "_CONTENT}}";
    } elseif (preg_match('/button.*text|btn.*text/i', $key)) {
        $placeholder = "{{" . $section_type . "CTA_BUTTON}}";
    } elseif (preg_match('/button.*link|btn.*link|url|href/i', $key)) {
        $placeholder = "{{" . $section_type . "CTA_LINK}}";
    } elseif (preg_match('/addr|address/i', $key)) {
        $placeholder = "{{" . $section_type . "_ADDR}}";
    } else {
        // 根據內容長度決定
        if (mb_strlen($value, 'UTF-8') <= 20) {
            $placeholder = "{{" . $section_type . "_TITLE}}";
        } else {
            $placeholder = "{{" . $section_type . "_CONTENT}}";
        }
    }
    
    return [
        'placeholder' => $placeholder,
        'path' => $path,
        'context' => $context,
        'original_value' => $value,
        'field_key' => $key,
        'type' => 'generated_standardized'
    ];
}

/**
 * 為中文內容生成語義化佔位符（向後相容）
 */
function generateSemanticPlaceholder($key, $value, $context = [])
{
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    
    // 根據欄位名稱和 widget 類型生成語義化佔位符
    if ($key === 'title') {
        if ($widget_type === 'heading') {
            return 'HEADING_TITLE';
        } elseif ($widget_type === 'icon-box') {
            return 'ICONBOX_TITLE';
        } else {
            return 'ELEMENT_TITLE';
        }
    } elseif ($key === 'title_text') {
        return 'ICONBOX_TITLE';
    } elseif ($key === 'description_text') {
        return 'ICONBOX_DESCRIPTION';
    } elseif ($key === 'editor') {
        return 'TEXT_CONTENT';
    } elseif (preg_match('/subtitle/i', $key)) {
        return 'ELEMENT_SUBTITLE';
    } elseif (preg_match('/content/i', $key)) {
        return 'ELEMENT_CONTENT';
    }
    
    // 根據內容特徵生成
    if (mb_strlen($value, 'UTF-8') <= 10) {
        return 'SHORT_TEXT';
    } elseif (mb_strlen($value, 'UTF-8') <= 30) {
        return 'MEDIUM_TEXT';
    } else {
        return 'LONG_TEXT';
    }
}

/**
 * 建立 AI 文字替換提示詞
 */
function getTextReplacementPrompt($page_name, $page_data, $user_data, $site_config)
{
    return '
## 任務：頁面文字內容替換

你需要根據用戶資料和網站配置，替換頁面 JSON 中的佔位符文字。

### 頁面資訊
- 頁面名稱：' . $page_name . '
- 頁面類型：' . ($page_data['page_type'] ?? 'general') . '

### 替換規則
1. **主要任務**: 替換所有大寫佔位符（如 HERO_TITLE, ABOUT_CONTENT 等）為實際內容
2. **資料來源**: 優先使用 content_options 中的對應值，其次使用用戶資料
3. **對應關係**: 
   - HERO_TITLE → index_hero_title 或 {page}_hero_title
   - ABOUT_CONTENT → about_content 或相關描述
   - SERVICE_TITLE → 服務標題
   - 其他佔位符根據語意對應
4. **風格要求**: 保持專業、簡潔、符合品牌調性

### 圖片識別與生成規則
同時識別頁面中需要生成的圖片：
- 找出所有包含圖片路徑的欄位（通常包含 /wp-content/uploads/）
- **必須基於用戶真實資料生成全新的圖片 prompt**
- **嚴格禁止**：
  * 複製任何現有模板的圖片描述
  * 使用模板品牌的特定元素（顏色代碼、品牌名稱等）
  * 重複使用通用的圖片描述

### ⚠️ 重要限制條件
1. **絕對禁止複製模板內容**：不得使用任何現有範例的具體描述
2. **必須客製化**：所有圖片 prompt 必須根據當前用戶的品牌特色生成
3. **避免通用描述**：每個 prompt 都應該是獨特且針對性的

### 輸出要求
請輸出一個 JSON，包含文字替換對照表和圖片清單：

```json
{
  "text_replacements": {
    "原始文字": "替換後文字",
    "HERO_TITLE": "實際的標題內容",
    "ABOUT_CONTENT": "實際的關於內容"
  },
  "image_prompts": {
    "圖片檔名": {
      "title": "圖片標題",
      "prompt": "詳細的圖片生成提示",
      "ai": "openai",
      "style": "風格",
      "size": "尺寸"
    }
  }
}
```

### 用戶資料
';
}

/**
 * 呼叫 AI 進行文字替換（帶自動降級）
 */
function callAIForTextReplacement($ai_config, $prompt, $ai_service, $deployer, $config = null)
{
    if ($ai_service === 'OpenAI') {
        $url = rtrim($ai_config['base_url'], '/') . '/chat/completions';
        
        // 檢查提示詞長度，如果太長則截斷
        $max_prompt_length = 20000; // 降低到 20K 字元以確保穩定性
        if (strlen($prompt) > $max_prompt_length) {
            $deployer->log("警告: 提示詞過長 (" . strlen($prompt) . " 字元)，將進行截斷");
            $prompt = substr($prompt, 0, $max_prompt_length) . "\n\n[內容已截斷，請根據以上資料進行處理]";
        }
        
        // 清理提示詞中可能破壞 JSON 的字元
        $prompt = str_replace(['"', "\r"], ['\"', ''], $prompt);
        
        $data = [
            'model' => $ai_config['model'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 2000, // 進一步降低避免超限
            'temperature' => 0.3
        ];
        
        $deployer->log("提示詞長度: " . strlen($prompt) . " 字元");
        $deployer->log("使用模型: " . $ai_config['model']);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $ai_config['api_key']
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL 錯誤: " . $curl_error);
        }
        
        if ($http_code !== 200) {
            $error_detail = '';
            if ($response) {
                $error_data = json_decode($response, true);
                if (isset($error_data['error']['message'])) {
                    $error_detail = ': ' . $error_data['error']['message'];
                }
            }
            // 如果是 403 錯誤且有 Gemini 可用，自動降級
            if ($http_code === 403 && $config) {
                $deployer->log("⚠️  OpenAI 模型權限不足，自動切換到 Gemini");
                $gemini_config = [
                    'api_key' => $config->get('api_credentials.gemini.api_key'),
                    'model' => $config->get('api_credentials.gemini.model') ?: 'gemini-2.5-flash'
                ];
                if (!empty($gemini_config['api_key'])) {
                    return callAIForTextReplacement($gemini_config, $prompt, 'Gemini', $deployer);
                }
            }
            
            throw new Exception("OpenAI API 請求失敗: HTTP {$http_code}{$error_detail}");
        }
        
        $result = json_decode($response, true);
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception("OpenAI API 回應格式錯誤: " . json_encode($result));
        }
        
        return $result['choices'][0]['message']['content'];
    }
    
    // Gemini 實作
    if ($ai_service === 'Gemini') {
        $deployer->log("🤖 使用 Gemini API 進行文字生成");
        
        // 檢查提示詞長度
        $max_prompt_length = 30000; // Gemini 支援更長的提示詞
        if (strlen($prompt) > $max_prompt_length) {
            $deployer->log("警告: 提示詞過長 (" . strlen($prompt) . " 字元)，將進行截斷");
            $prompt = substr($prompt, 0, $max_prompt_length) . "\n\n[內容已截斷，請根據以上資料進行處理]";
        }
        
        $url = rtrim($ai_config['base_url'], '/') . '/' . $ai_config['model'] . ':generateContent';
        
        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 4000,
                'temperature' => 0.3
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?key=' . $ai_config['api_key']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("Gemini API 請求失敗: HTTP {$http_code}");
        }
        
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
        
        throw new Exception("Gemini API 回應格式錯誤: " . json_encode($result));
    }
    
    throw new Exception("不支援的 AI 服務: {$ai_service}");
}

/**
 * 修復常見的 JSON 格式問題
 */
function fixCommonJsonIssues($json_content)
{
    // 移除多餘的空白和換行
    $json_content = trim($json_content);
    
    // 移除 BOM
    $json_content = str_replace("\xEF\xBB\xBF", '', $json_content);
    
    // 修復常見的引號問題
    $json_content = preg_replace('/([{,]\s*)([a-zA-Z_][a-zA-Z0-9_]*)\s*:/', '$1"$2":', $json_content);
    
    // 移除結尾多餘的逗號
    $json_content = preg_replace('/,(\s*[}\]])/', '$1', $json_content);
    
    // 修復單引號
    $json_content = str_replace("'", '"', $json_content);
    
    // 嘗試找到第一個 { 和最後一個 }
    $first_brace = strpos($json_content, '{');
    $last_brace = strrpos($json_content, '}');
    
    if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
        $json_content = substr($json_content, $first_brace, $last_brace - $first_brace + 1);
    }
    
    return $json_content;
}

/**
 * 應用文字替換到頁面內容
 */
function applyTextReplacements($content, $replacements, &$replacements_count = 0)
{
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                $original_value = $value;
                
                // 1. 直接佔位符替換
                foreach ($replacements as $search => $replace) {
                    if ($value === $search || strpos($value, $search) !== false) {
                        $value = str_replace($search, $replace, $value);
                        $replacements_count++;
                    }
                }
                
                // 2. 語義化替換 - 如果應該替換但沒有直接匹配
                if ($value === $original_value && shouldIncludeForReplacement($key, $value, $content)) {
                    $semantic_replacement = findSemanticReplacement($key, $value, $content, $replacements);
                    if ($semantic_replacement) {
                        $value = $semantic_replacement;
                        $replacements_count++;
                    }
                }
                
                $content[$key] = $value;
            } elseif (is_array($value)) {
                $content[$key] = applyTextReplacements($value, $replacements, $replacements_count);
            }
        }
    }
    return $content;
}

/**
 * 為語義化內容找到對應的替換文字
 */
function findSemanticReplacement($key, $value, $context, $replacements)
{
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    
    // 根據欄位和上下文找到最適合的替換
    $candidates = [];
    
    if ($key === 'title') {
        $candidates = ['HEADING_TITLE', 'ELEMENT_TITLE', 'HERO_TITLE', 'PAGE_TITLE', 'SECTION_TITLE'];
    } elseif ($key === 'title_text') {
        $candidates = ['ICONBOX_TITLE', 'SERVICE_TITLE', 'FEATURE_TITLE'];
    } elseif ($key === 'description_text') {
        $candidates = ['ICONBOX_DESCRIPTION', 'SERVICE_DESCRIPTION', 'FEATURE_DESCRIPTION'];
    } elseif ($key === 'editor') {
        $candidates = ['TEXT_CONTENT', 'ABOUT_CONTENT', 'PAGE_CONTENT'];
    }
    
    // 根據 widget 類型調整候選
    if ($widget_type === 'heading') {
        array_unshift($candidates, 'HEADING_TITLE', 'HERO_TITLE');
    } elseif ($widget_type === 'icon-box') {
        array_unshift($candidates, 'ICONBOX_TITLE', 'SERVICE_TITLE');
    }
    
    // 查找第一個可用的替換
    foreach ($candidates as $candidate) {
        if (isset($replacements[$candidate])) {
            return $replacements[$candidate];
        }
    }
    
    // 如果沒有具體匹配，尋找通用替換
    $generic_candidates = [];
    if (preg_match('/title/i', $key)) {
        $generic_candidates = ['TITLE', 'HEADING', 'NAME'];
    } elseif (preg_match('/content|text|description/i', $key)) {
        $generic_candidates = ['CONTENT', 'TEXT', 'DESCRIPTION'];
    }
    
    foreach ($generic_candidates as $candidate) {
        if (isset($replacements[$candidate])) {
            return $replacements[$candidate];
        }
    }
    
    return null;
}

/**
 * 解析 AI 回應並儲存檔案
 */
function parseAIResponseAndSave($ai_response, $page_name, $original_content, $layout_dir, $image_prompts_path, $deployer)
{
    // 儲存原始 AI 回應以便偵錯
    $response_file = $layout_dir . '/' . $page_name . '-ai-response.txt';
    file_put_contents($response_file, $ai_response);
    $deployer->log("儲存 AI 原始回應: {$page_name}-ai-response.txt");
    
    // 嘗試從回應中提取 JSON
    $json_start = strpos($ai_response, '```json');
    $json_end = strrpos($ai_response, '```');
    
    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($ai_response, $json_start + 7, $json_end - $json_start - 7);
    } else {
        // 如果沒有 markdown 格式，嘗試直接解析整個回應
        $json_content = $ai_response;
    }
    
    $deployer->log("嘗試解析 JSON 長度: " . strlen($json_content) . " 字元");
    
    $parsed_data = json_decode(trim($json_content), true);
    
    if (!$parsed_data) {
        $json_error = json_last_error_msg();
        $deployer->log("JSON 解析錯誤: " . $json_error);
        $deployer->log("JSON 內容預覽: " . substr($json_content, 0, 500) . "...");
        
        // 嘗試修復常見的 JSON 問題
        $fixed_json = fixCommonJsonIssues($json_content);
        $parsed_data = json_decode($fixed_json, true);
        
        if (!$parsed_data) {
            throw new Exception("無法解析 AI 回應的 JSON 格式，錯誤: {$json_error}");
        } else {
            $deployer->log("JSON 自動修復成功");
        }
    }
    
    // 應用文字替換
    $updated_content = $original_content;
    if (isset($parsed_data['text_replacements']) && !empty($parsed_data['text_replacements'])) {
        $deployer->log("發現 " . count($parsed_data['text_replacements']) . " 項文字替換規則:");
        foreach ($parsed_data['text_replacements'] as $search => $replace) {
            $deployer->log("  - '{$search}' → '{$replace}'");
        }
        
        $updated_content = applyTextReplacements($original_content, $parsed_data['text_replacements']);
        $deployer->log("已應用所有文字替換規則");
        
        // 驗證替換是否實際發生
        $original_json = json_encode($original_content);
        $updated_json = json_encode($updated_content);
        if ($original_json === $updated_json) {
            $deployer->log("警告: 內容沒有實際改變，可能佔位符不存在於頁面中");
        } else {
            $deployer->log("✅ 內容已成功更新");
        }
    } else {
        $deployer->log("警告: AI 回應中沒有 text_replacements 資料");
    }
    
    // 儲存 AI 調整後的頁面 JSON
    $ai_page_path = $layout_dir . '/' . $page_name . '-ai.json';
    file_put_contents($ai_page_path, json_encode($updated_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("儲存 AI 調整後的頁面: {$page_name}-ai.json");
    
    // 更新 image-prompts.json
    if (isset($parsed_data['image_prompts']) && !empty($parsed_data['image_prompts'])) {
        $existing_prompts = json_decode(file_get_contents($image_prompts_path), true) ?: [];
        
        // 為每個頁面的圖片添加前綴以區分
        foreach ($parsed_data['image_prompts'] as $key => $prompt) {
            $prefixed_key = $page_name . '_' . $key;
            $existing_prompts[$prefixed_key] = $prompt;
        }
        
        file_put_contents($image_prompts_path, json_encode($existing_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("更新 image-prompts.json，新增 " . count($parsed_data['image_prompts']) . " 個圖片提示");
    }
    
    return true;
}

// 主要處理流程
/**
 * 處理 global 模板檔案的佔位符替換
 */
if (!function_exists('processGlobalTemplates')) {
function processGlobalTemplates($work_dir, $site_config, $processed_data, $ai_config, $ai_service, $deployer, $config)
{
    $deployer->log("開始處理 global 模板檔案");
    
    // 確保 global 目錄存在
    $global_dir = $work_dir . '/layout/global';
    if (!is_dir($global_dir)) {
        mkdir($global_dir, 0755, true);
        $deployer->log("創建 global 目錄: {$global_dir}");
    }
    
    // 取得 global 模板目錄
    $template_global_dir = DEPLOY_BASE_PATH . '/template/global';
    if (!is_dir($template_global_dir)) {
        $deployer->log("警告: global 模板目錄不存在: {$template_global_dir}");
        return 0;
    }
    
    // 讀取所有 global 模板檔案
    $global_files = glob($template_global_dir . '/*.json');
    if (empty($global_files)) {
        $deployer->log("警告: 沒有找到 global 模板檔案");
        return 0;
    }
    
    $processed_count = 0;
    
    foreach ($global_files as $global_file) {
        $filename = basename($global_file, '.json');
        $deployer->log("處理 global 模板: {$filename}");
        
        try {
            // 讀取模板檔案
            $template_content = file_get_contents($global_file);
            $template_data = json_decode($template_content, true);
            
            if (!$template_data) {
                $deployer->log("警告: 無法解析 global 模板: {$filename}");
                continue;
            }
            
            // 儲存原始模板
            $original_path = $global_dir . '/' . $filename . '.json';
            file_put_contents($original_path, json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $deployer->log("儲存原始 global 模板: {$filename}.json");
            
            // 尋找佔位符
            $placeholders = [];
            findPlaceholders($template_data, $placeholders);
            
            if (empty($placeholders)) {
                $deployer->log("global 模板 {$filename} 沒有找到佔位符，複製原始檔案");
                $ai_path = $global_dir . '/' . $filename . '-ai.json';
                file_put_contents($ai_path, json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $processed_count++;
                continue;
            }
            
            $deployer->log("發現 " . count($placeholders) . " 個佔位符: " . implode(', ', array_column($placeholders, 'placeholder')));
            
            // 建立 AI 提示詞
            $prompt = getGlobalTemplateReplacementPrompt($filename, $template_data, $processed_data, $site_config, $placeholders);
            
            $deployer->log("呼叫 AI 進行 global 模板文字替換...");
            $deployer->log("提示詞長度: " . strlen($prompt) . " 字元");
            $deployer->log("使用 AI 服務: {$ai_service}");
            
            // 呼叫 AI API
            $ai_response = callAIForTextReplacement($ai_config, $prompt, $ai_service, $deployer, $config);
            
            $deployer->log("AI 回應長度: " . strlen($ai_response) . " 字元");
            
            if ($ai_response && strlen(trim($ai_response)) > 0) {
                // 解析 AI 回應並儲存
                parseGlobalAIResponseAndSave($ai_response, $filename, $template_data, $global_dir, $deployer);
                $processed_count++;
                $deployer->log("✅ global 模板 {$filename} 處理完成");
            } else {
                $deployer->log("警告: AI global 模板替換失敗 - 空回應: {$filename}");
                // 儲存原始檔案
                $ai_path = $global_dir . '/' . $filename . '-ai.json';
                file_put_contents($ai_path, json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
            
        } catch (Exception $e) {
            $deployer->log("錯誤: global 模板 {$filename} 處理失敗 - " . $e->getMessage());
            
            // 儲存原始檔案作為備份
            $ai_path = $global_dir . '/' . $filename . '-ai.json';
            file_put_contents($ai_path, json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    $deployer->log("global 模板處理完成，共處理 {$processed_count} 個檔案");
    $deployer->log("global 檔案儲存位置: {$global_dir}");
    
    return $processed_count;
}
}

/**
 * 建立 global 模板的 AI 替換提示詞
 */
if (!function_exists('getGlobalTemplateReplacementPrompt')) {
function getGlobalTemplateReplacementPrompt($template_name, $template_data, $user_data, $site_config, $placeholders)
{
    return '
## 任務：Global 模板文字內容替換

你需要根據用戶資料和網站配置，替換 global 模板中的佔位符文字。

### 模板資訊
- 模板名稱：' . $template_name . '
- 模板類型：global（全站共用元素）

### 替換規則
1. **主要任務**: 替換所有佔位符（如 {{FOOTER_CONTACT_TITLE}} 等）為實際內容
2. **資料來源**: 優先使用 content_options 中的對應值，其次使用用戶資料
3. **Global 模板對應關係**: 
   - {{FOOTER_CONTACT_TITLE}} → index_footer_cta_title
   - {{FOOTER_CONTACT_SUBTITLE}} → index_footer_cta_subtitle
   - {{HEADER_CTA_TITLE}} → index_header_cta_title
   - 其他佔位符根據語意對應到相關內容選項
4. **風格要求**: 保持專業、簡潔、符合品牌調性

### ⚠️ 重要限制條件
1. **絕對禁止複製模板內容**：不得使用任何現有範例的具體描述
2. **必須客製化**：所有內容必須根據當前用戶的品牌特色生成
3. **僅文字替換**：不修改 JSON 結構，只替換文字內容

### 發現的佔位符
' . implode(', ', array_column($placeholders, 'placeholder')) . '

### 圖片檔案檢測
模板 JSON 內容：
```json
' . json_encode($template_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '
```

### 輸出要求
請輸出一個 JSON，包含文字替換對照表和圖片生成提示：

```json
{
  "text_replacements": {
    "{{佔位符1}}": "替換後文字1",
    "{{佔位符2}}": "替換後文字2"
  },
  "image_prompts": {
    "background-image": {
      "title": "圖片標題",
      "prompt": "詳細的圖片生成提示，基於用戶品牌特色",
      "ai": "openai",
      "style": "圖片風格",
      "size": "1920x400"
    }
  }
}
```

**圖片生成規則**：
1. 僅在模板 JSON 中發現圖片檔案時才生成 image_prompts
2. 分析 JSON 中的 background_image、image 等字段
3. 根據用戶品牌特色生成個性化圖片提示
4. 圖片檔名使用語意化名稱（如 background-image、footer-bg 等）

### 用戶資料
網站配置：
```json
' . json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '
```

確認資料：
```json
' . json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '
```
';
}
}

/**
 * 解析 global 模板 AI 回應並儲存
 */
if (!function_exists('parseGlobalAIResponseAndSave')) {
function parseGlobalAIResponseAndSave($ai_response, $template_name, $original_content, $global_dir, $deployer)
{
    // 儲存 AI 原始回應
    $ai_response_file = $global_dir . '/' . $template_name . '-ai-response.txt';
    file_put_contents($ai_response_file, $ai_response);
    $deployer->log("儲存 AI 原始回應: {$template_name}-ai-response.txt");
    
    // 嘗試從回應中提取 JSON
    $json_start = strpos($ai_response, '```json');
    $json_end = strrpos($ai_response, '```');
    
    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($ai_response, $json_start + 7, $json_end - $json_start - 7);
    } else {
        // 如果沒有 markdown 格式，嘗試直接解析
        $json_content = $ai_response;
    }
    
    $deployer->log("嘗試解析 JSON 長度: " . strlen($json_content) . " 字元");
    
    $parsed_data = json_decode(trim($json_content), true);
    
    if (!$parsed_data || !isset($parsed_data['text_replacements'])) {
        $deployer->log("警告: 無法解析 AI 回應的 JSON 格式，儲存原始內容");
        $ai_path = $global_dir . '/' . $template_name . '-ai.json';
        file_put_contents($ai_path, json_encode($original_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return;
    }
    
    $replacements = $parsed_data['text_replacements'];
    $deployer->log("發現 " . count($replacements) . " 項文字替換規則:");
    
    // 套用文字替換
    $updated_content = $original_content;
    foreach ($replacements as $placeholder => $replacement) {
        $deployer->log("  - '{$placeholder}' → '{$replacement}'");
        $updated_content = replaceInNestedArray($updated_content, $placeholder, $replacement);
    }
    
    $deployer->log("已應用所有文字替換規則");
    $deployer->log("✅ 內容已成功更新");
    
    // 更新 image-prompts.json（處理 global 模板的圖片）
    if (isset($parsed_data['image_prompts']) && !empty($parsed_data['image_prompts'])) {
        $work_dir = dirname($global_dir);
        $image_prompts_path = $work_dir . '/json/image-prompts.json';
        $existing_prompts = json_decode(file_get_contents($image_prompts_path), true) ?: [];
        
        // 為 global 模板的圖片添加前綴以區分
        foreach ($parsed_data['image_prompts'] as $key => $prompt) {
            $prefixed_key = $template_name . '_' . $key;
            $existing_prompts[$prefixed_key] = $prompt;
        }
        
        file_put_contents($image_prompts_path, json_encode($existing_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("更新 image-prompts.json，新增 " . count($parsed_data['image_prompts']) . " 個 global 模板圖片提示");
    }

    // 儲存 AI 調整後的模板
    $ai_path = $global_dir . '/' . $template_name . '-ai.json';
    file_put_contents($ai_path, json_encode($updated_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("儲存 AI 調整後的 global 模板: {$template_name}-ai.json");
}
}

/**
 * 在多維陣列中遞歸替換文字
 */
if (!function_exists('replaceInNestedArray')) {
function replaceInNestedArray($data, $search, $replace)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = replaceInNestedArray($value, $search, $replace);
        }
    } elseif (is_string($data)) {
        $data = str_replace($search, $replace, $data);
    }
    
    return $data;
}
}

try {
    if (!isset($site_config['layout_selection']) || empty($site_config['layout_selection'])) {
        throw new Exception("site-config.json 中沒有 layout_selection 資料");
    }
    
    $template_dir = DEPLOY_BASE_PATH . '/template';
    $data_dir = DEPLOY_BASE_PATH . '/data';
    $image_prompts_path = $work_dir . '/json/image-prompts.json';
    
    // 讀取用戶資料
    $user_data_files = [];
    $files = scandir($data_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && $file !== 'json') {
            $file_path = $data_dir . '/' . $file;
            if (is_file($file_path)) {
                $user_data_files[$file] = file_get_contents($file_path);
            }
        }
    }
    
    $pages_generated = 0;
    
    // 處理每個頁面
    foreach ($site_config['layout_selection'] as $page_config) {
        $page_name = $page_config['page'];
        $containers = $page_config['container'];
        
        $deployer->log("處理頁面: {$page_name}");
        
        // 收集容器名稱（依順序）
        $container_names = array_values($containers);
        
        // 合併容器 JSON
        $merged_content = mergeContainerJsonFiles($container_names, $template_dir, $deployer);
        
        if (empty($merged_content)) {
            $deployer->log("警告: 頁面 {$page_name} 沒有有效的容器內容");
            continue;
        }
        
        // 儲存原始合併的 JSON
        $page_json_path = $layout_dir . '/' . $page_name . '.json';
        file_put_contents($page_json_path, json_encode($merged_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("儲存合併頁面: {$page_name}.json");
        
        // 準備 AI 文字替換
        $page_data = [
            'page_type' => $page_name,
            'containers' => $containers,
            'content' => $merged_content
        ];
        
        // 智能建立 AI 提示詞（避免過長）
        $prompt = getTextReplacementPrompt($page_name, $page_data, $user_data_files, $site_config);
        
        // 只包含關鍵的用戶資料（過濾掉過大的檔案）
        $prompt .= "\n\n**關鍵用戶資料：**\n";
        foreach ($user_data_files as $filename => $content) {
            // 跳過過大的檔案或非重要檔案
            if (strlen($content) > 10000) {
                $prompt .= "\n檔案: {$filename} (內容過長，已省略)\n";
                continue;
            }
            // 只包含關鍵的 JSON 檔案
            if (preg_match('/\.(json|txt)$/i', $filename)) {
                $prompt .= "\n檔案: {$filename}\n```\n" . substr($content, 0, 5000) . "\n```\n";
            }
        }
        
        // 只包含 content_options 而非完整的 site_config
        $content_options = $site_config['content_options'] ?? [];
        $prompt .= "\n\n**網站內容配置：**\n```json\n" . json_encode($content_options, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```";
        
        // 找出所有佔位符
        $placeholders = [];
        findPlaceholders($merged_content, $placeholders);
        
        if (!empty($placeholders)) {
            $prompt .= "\n\n**發現的佔位符：**\n";
            foreach ($placeholders as $placeholder) {
                $placeholder_text = is_array($placeholder) ? $placeholder['placeholder'] : $placeholder;
                $prompt .= "- {$placeholder_text}\n";
            }
        }
        
        // 簡化頁面 JSON - 只包含需要替換的文字部分
        $simplified_content = simplifyPageContent($merged_content);
        $prompt .= "\n\n**頁面內容（需要文字替換的部分）：**\n```json\n" . json_encode($simplified_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```";
        
        // 提取前5個佔位符的名稱用於日誌顯示
        $placeholder_names = array_map(function($p) { 
            return is_array($p) ? $p['placeholder'] : $p; 
        }, array_slice($placeholders, 0, 5));
        
        $deployer->log("發現 " . count($placeholders) . " 個佔位符: " . implode(', ', $placeholder_names) . (count($placeholders) > 5 ? '...' : ''));
        
        // 呼叫 AI 進行文字替換
        $deployer->log("呼叫 AI 進行文字替換...");
        $deployer->log("提示詞長度: " . strlen($prompt) . " 字元");
        $deployer->log("使用 AI 服務: {$ai_service}");
        
        try {
            $ai_response = callAIForTextReplacement($ai_config, $prompt, $ai_service, $deployer, $config);
            
            $deployer->log("AI 回應長度: " . strlen($ai_response) . " 字元");
            
            if ($ai_response && strlen(trim($ai_response)) > 0) {
                // 解析並儲存結果
                parseAIResponseAndSave($ai_response, $page_name, $merged_content, $layout_dir, $image_prompts_path, $deployer);
                $pages_generated++;
                $deployer->log("✅ 頁面 {$page_name} 處理完成");
            } else {
                $deployer->log("警告: AI 文字替換失敗 - 空回應: {$page_name}");
                $deployer->log("AI 回應內容: " . var_export($ai_response, true));
                
                // 至少儲存原始頁面
                $ai_page_path = $layout_dir . '/' . $page_name . '-ai.json';
                file_put_contents($ai_page_path, json_encode($merged_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $deployer->log("儲存原始頁面（未經 AI 調整）: {$page_name}-ai.json");
            }
        } catch (Exception $e) {
            $deployer->log("錯誤: 頁面 {$page_name} 處理失敗 - " . $e->getMessage());
            
            // 儲存錯誤資訊
            $error_file = $layout_dir . '/' . $page_name . '-error.txt';
            file_put_contents($error_file, "錯誤時間: " . date('Y-m-d H:i:s') . "\n" . 
                                         "錯誤訊息: " . $e->getMessage() . "\n\n" .
                                         "提示詞長度: " . strlen($prompt) . " 字元\n");
            
            // 至少儲存原始頁面
            $ai_page_path = $layout_dir . '/' . $page_name . '-ai.json';
            file_put_contents($ai_page_path, json_encode($merged_content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $deployer->log("儲存原始頁面（處理失敗）: {$page_name}-ai.json");
            
            // 繼續處理下一個頁面，不中斷整個流程
            continue;
        }
    }
    
    $deployer->log("頁面生成完成，共處理 {$pages_generated} 個頁面");
    $deployer->log("檔案儲存位置: {$layout_dir}");
    
    // 處理 global 模板檔案
    $global_processed = processGlobalTemplates($work_dir, $site_config, $processed_data, $ai_config, $ai_service, $deployer, $config);
    
    return [
        'status' => 'success',
        'pages_generated' => $pages_generated,
        'global_processed' => $global_processed,
        'layout_dir' => $layout_dir
    ];
    
} catch (Exception $e) {
    $deployer->log("頁面生成失敗: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}