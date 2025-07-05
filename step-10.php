<?php
/**
 * 步驟 10: 圖片佔位符識別、提示詞生成與 AI 圖片生成
 * 掃描 *-ai.json 檔案中的圖片佔位符，生成 AI 圖片並建立 image-mapping.json
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$site_config = json_decode(file_get_contents($work_dir . '/json/site-config.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("開始執行步驟 10: 圖片佔位符識別與 AI 圖片生成");

try {
    // 1. 掃描所有 *-ai.json 檔案提取圖片佔位符
    $deployer->log("開始掃描圖片佔位符");
    
    $image_placeholders = extractImagePlaceholders($work_dir, $deployer);
    $deployer->log("發現 " . count($image_placeholders) . " 個圖片佔位符");
    
    if (empty($image_placeholders)) {
        $deployer->log("未發現任何圖片佔位符，跳過圖片生成");
        return ['status' => 'success', 'message' => '未發現圖片佔位符'];
    }
    
    // 2. 生成圖片提示詞
    $deployer->log("開始生成圖片提示詞");
    
    $ai_service = $config->get('ai_service', 'gemini');
    $image_prompts = generateImagePrompts($image_placeholders, $site_config, $ai_service, $config, $deployer);
    
    if (empty($image_prompts)) {
        throw new Exception("圖片提示詞生成失敗");
    }
    
    // 儲存圖片提示詞
    $prompts_file = $work_dir . '/image-prompts.json';
    file_put_contents($prompts_file, json_encode($image_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("圖片提示詞已儲存: $prompts_file");
    
    // 3. 確保 images 目錄存在
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
        $deployer->log("建立圖片目錄: $images_dir");
    }
    
    // 4. 呼叫 AI 生成圖片
    $deployer->log("開始 AI 圖片生成");
    
    $generated_images = generateImages($image_prompts, $images_dir, $ai_service, $config, $deployer);
    
    // 5. 建立 image-mapping.json（兼容 step-11 和 step-12）
    $image_mapping = buildImageMapping($generated_images, $image_placeholders, $deployer);
    
    $mapping_file = $work_dir . '/image-mapping.json';
    file_put_contents($mapping_file, json_encode($image_mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("圖片映射檔案已建立: $mapping_file");
    
    // 6. 儲存步驟結果
    $step_result = [
        'step' => '10',
        'title' => '圖片佔位符識別與 AI 圖片生成',
        'status' => 'success',
        'message' => "成功生成 " . count($generated_images) . " 個圖片",
        'placeholders_found' => count($image_placeholders),
        'images_generated' => count($generated_images),
        'images_failed' => count($image_prompts) - count($generated_images),
        'image_mapping_count' => count($image_mapping),
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/step-10-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("步驟 10: 圖片佔位符識別與 AI 圖片生成 - 完成");
    
    return ['status' => 'success', 'result' => $step_result];
    
} catch (Exception $e) {
    $deployer->log("步驟 10 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}

/**
 * 掃描所有 *-ai.json 檔案提取圖片佔位符
 */
function extractImagePlaceholders($work_dir, $deployer)
{
    $placeholders = [];
    
    // 掃描頁面模板
    $layout_dir = $work_dir . '/layout';
    $ai_files = glob($layout_dir . '/*-ai.json');
    
    foreach ($ai_files as $file) {
        $template_name = basename($file, '-ai.json');
        $content = file_get_contents($file);
        
        if (preg_match_all('/\{\{([^}]+(?:_BG|_PHOTO|_IMG|_IMAGE|_LOGO|_ICON))\}\}/', $content, $matches)) {
            foreach ($matches[1] as $placeholder) {
                $key = $template_name . '_' . strtolower(str_replace('_', '-', $placeholder));
                
                $placeholders[$key] = [
                    'placeholder' => $placeholder,
                    'template' => $template_name,
                    'type' => 'page',
                    'file' => $file
                ];
                
                $deployer->log("  發現頁面圖片佔位符: {{$placeholder}} 在 $template_name");
            }
        }
    }
    
    // 掃描全域模板
    $global_dir = $work_dir . '/layout/global';
    if (is_dir($global_dir)) {
        $global_files = glob($global_dir . '/*-ai.json');
        
        foreach ($global_files as $file) {
            $template_name = basename($file, '-ai.json');
            $content = file_get_contents($file);
            
            if (preg_match_all('/\{\{([^}]+(?:_BG|_PHOTO|_IMG|_IMAGE|_LOGO|_ICON))\}\}/', $content, $matches)) {
                foreach ($matches[1] as $placeholder) {
                    $key = $template_name . '_' . strtolower(str_replace('_', '-', $placeholder));
                    
                    $placeholders[$key] = [
                        'placeholder' => $placeholder,
                        'template' => $template_name,
                        'type' => 'global',
                        'file' => $file
                    ];
                    
                    $deployer->log("  發現全域圖片佔位符: {{$placeholder}} 在 $template_name");
                }
            }
        }
    }
    
    return $placeholders;
}

/**
 * 使用 AI 生成圖片提示詞
 */
function generateImagePrompts($placeholders, $site_config, $ai_service, $config, $deployer)
{
    $brand_info = [
        'name' => $site_config['site_title'] ?? '網站',
        'description' => $site_config['site_description'] ?? '',
        'tone' => $site_config['brand_tone'] ?? '專業、親和',
        'target_audience' => $site_config['target_audience'] ?? '一般用戶'
    ];
    
    $prompt = "你是一位專業的圖片提示詞生成師。請根據以下品牌資訊，為每個圖片佔位符生成詳細的英文圖片提示詞。

## 品牌資訊
- 品牌名稱：{$brand_info['name']}
- 品牌描述：{$brand_info['description']}
- 品牌調性：{$brand_info['tone']}
- 目標受眾：{$brand_info['target_audience']}

## 圖片佔位符清單
";

    foreach ($placeholders as $key => $info) {
        $prompt .= "- {$key}: 佔位符 {{" . $info['placeholder'] . "}} 在 " . $info['template'] . " 模板中\n";
    }

    $prompt .= "
## 生成要求
1. 每個圖片提示詞要具體詳細，包含風格、顏色、構圖等
2. 提示詞必須是英文
3. 避免包含文字或品牌名稱
4. 針對不同類型的佔位符生成相應風格：
   - _BG (背景): 抽象或場景背景
   - _PHOTO (照片): 人物或產品照片
   - _IMG/_IMAGE (圖片): 一般圖片
   - _LOGO (標誌): 簡約標誌設計
   - _ICON (圖示): 簡單圖示

請以 JSON 格式回應，格式如下：
{
    \"key1\": {
        \"prompt\": \"詳細英文提示詞\",
        \"style\": \"攝影風格如 professional/abstract/minimalist\",
        \"size\": \"1024x1024\",
        \"quality\": \"high\"
    }
}

只回傳 JSON，不要額外說明。";

    $deployer->log("呼叫 AI 生成圖片提示詞...");
    $deployer->log("提示詞長度: " . strlen($prompt) . " 字元");
    
    if ($ai_service === 'gemini') {
        $response = callGeminiAPI($prompt, $config, $deployer);
    } else {
        $response = callOpenAIAPI($prompt, $config, $deployer);
    }
    
    if (!$response) {
        $deployer->log("AI 提示詞生成失敗，使用預設提示詞");
        return generateDefaultImagePrompts($placeholders);
    }
    
    // 解析 AI 回應
    $response_text = trim($response);
    if (strpos($response_text, '```json') !== false) {
        $response_text = preg_replace('/```json\s*|\s*```/', '', $response_text);
    }
    
    $prompts = json_decode($response_text, true);
    if (!$prompts) {
        $deployer->log("AI 回應解析失敗，使用預設提示詞");
        return generateDefaultImagePrompts($placeholders);
    }
    
    $deployer->log("AI 圖片提示詞生成成功，共 " . count($prompts) . " 個");
    return $prompts;
}

/**
 * 生成預設圖片提示詞
 */
function generateDefaultImagePrompts($placeholders)
{
    $prompts = [];
    
    foreach ($placeholders as $key => $info) {
        $placeholder = $info['placeholder'];
        
        if (strpos($placeholder, '_BG') !== false) {
            $prompt = "Abstract background image, professional design, soft gradient colors, modern minimalist style";
            $style = "abstract";
        } elseif (strpos($placeholder, '_PHOTO') !== false) {
            $prompt = "Professional portrait photography, natural lighting, warm atmosphere, business casual";
            $style = "professional";
        } elseif (strpos($placeholder, '_LOGO') !== false) {
            $prompt = "Minimalist logo design, clean simple shapes, professional brand identity, transparent background";
            $style = "minimalist";
        } elseif (strpos($placeholder, '_ICON') !== false) {
            $prompt = "Simple icon design, line art style, professional clean, minimalist";
            $style = "minimalist";
        } else {
            $prompt = "Professional image, high quality, modern design, clean composition";
            $style = "professional";
        }
        
        $prompts[$key] = [
            'prompt' => $prompt,
            'style' => $style,
            'size' => '1024x1024',
            'quality' => 'high'
        ];
    }
    
    return $prompts;
}

/**
 * 生成圖片
 */
function generateImages($image_prompts, $images_dir, $ai_service, $config, $deployer)
{
    $generated_images = [];
    $failed_count = 0;
    
    // 取得 AI API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'model' => 'dall-e-3',
        'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
    ];

    $gemini_config = [
        'api_key' => $config->get('api_credentials.gemini.api_key'),
        'model' => 'gemini-2.0-flash-preview-image-generation',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models/'
    ];
    
    foreach ($image_prompts as $key => $prompt_config) {
        $deployer->log("生成圖片: $key");
        
        try {
            $filename = generateSingleImage($key, $prompt_config, $images_dir, $ai_service, $openai_config, $gemini_config, $deployer);
            
            if ($filename) {
                $generated_images[$key] = $filename;
                $deployer->log("✅ 圖片生成成功: $filename");
            } else {
                $failed_count++;
                $deployer->log("❌ 圖片生成失敗: $key");
            }
            
            // API 請求間隔
            sleep(1);
            
        } catch (Exception $e) {
            $failed_count++;
            $deployer->log("❌ 圖片生成異常: $key - " . $e->getMessage());
        }
    }
    
    $deployer->log("圖片生成完成: " . count($generated_images) . " 成功, $failed_count 失敗");
    
    return $generated_images;
}

/**
 * 生成單張圖片
 */
function generateSingleImage($key, $prompt_config, $images_dir, $ai_service, $openai_config, $gemini_config, $deployer)
{
    $prompt = $prompt_config['prompt'];
    $size = $prompt_config['size'] ?? '1024x1024';
    $quality = $prompt_config['quality'] ?? 'standard';
    
    // 直接使用 OpenAI (Gemini 圖片生成不穩定)
    $image_data = generateImageWithOpenAI($prompt, $size, $quality, $openai_config, $deployer);
    
    // 只有在 OpenAI 失敗且 AI 服務設定為 gemini 時才嘗試 Gemini
    if (!$image_data && $ai_service === 'gemini') {
        $deployer->log("🔄 OpenAI 失敗，嘗試 Gemini");
        $image_data = generateImageWithGemini($prompt, $size, $quality, $gemini_config, $deployer);
    }
    
    if (!$image_data) {
        return null;
    }
    
    // 儲存圖片
    $filename = $key . '.png';
    $file_path = $images_dir . '/' . $filename;
    
    if (saveImageData($image_data, $file_path, $deployer)) {
        return $filename;
    }
    
    return null;
}

/**
 * 使用 Gemini 生成圖片
 */
function generateImageWithGemini($prompt, $size, $quality, $gemini_config, $deployer)
{
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent';
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => "Generate a high-quality image: " . $prompt . ". Professional, no text or words in the image."]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 4096,
            'temperature' => 0.2,
            'candidateCount' => 1,
            'responseModalities' => ['IMAGE']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?key=' . $gemini_config['api_key']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        
        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    return 'data:image/png;base64,' . $part['inlineData']['data'];
                }
            }
        }
    }
    
    $deployer->log("Gemini 圖片生成失敗: HTTP $http_code");
    return null;
}

/**
 * 使用 OpenAI DALL-E 生成圖片
 */
function generateImageWithOpenAI($prompt, $size, $quality, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/images/generations';
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $size,
        'quality' => $quality === 'high' ? 'hd' : 'standard',
        'response_format' => 'url'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_config['api_key']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'][0]['url'])) {
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("OpenAI 圖片生成失敗: HTTP $http_code");
    return null;
}

/**
 * 儲存圖片資料
 */
function saveImageData($image_data, $file_path, $deployer)
{
    try {
        if (strpos($image_data, 'data:image') === 0) {
            // Base64 編碼的圖片
            $base64_data = explode(',', $image_data)[1];
            $binary_data = base64_decode($base64_data);
        } else {
            // URL 圖片，需要下載
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $image_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $binary_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200 || !$binary_data) {
                $deployer->log("圖片下載失敗: HTTP $http_code");
                return false;
            }
        }
        
        if (file_put_contents($file_path, $binary_data)) {
            $size = formatFileSize(strlen($binary_data));
            $deployer->log("圖片儲存成功: " . basename($file_path) . " ($size)");
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        $deployer->log("圖片儲存失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 建立圖片映射表（兼容 step-11 和 step-12）
 */
function buildImageMapping($generated_images, $placeholders, $deployer)
{
    $mapping = [];
    
    foreach ($generated_images as $key => $filename) {
        // 為 step-11 和 step-12 建立多種格式的 key
        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        
        // 格式 1: 原始 key (step-12 主要使用)
        $mapping[$key] = "/wp-content/uploads/ai-generated/$filename";
        
        // 格式 2: 只有檔名 (step-12 備用)
        $mapping[$base_name] = "/wp-content/uploads/ai-generated/$filename";
        
        // 格式 3: 轉換底線為連字號 (step-12 可能需要)
        $hyphen_key = str_replace('_', '-', $key);
        if ($hyphen_key !== $key) {
            $mapping[$hyphen_key] = "/wp-content/uploads/ai-generated/$filename";
        }
        
        $deployer->log("建立圖片映射: $key -> /wp-content/uploads/ai-generated/$filename");
    }
    
    return $mapping;
}

/**
 * 呼叫 Gemini API
 */
function callGeminiAPI($prompt, $config, $deployer)
{
    $api_key = $config->get('api_credentials.gemini.api_key');
    $model = 'gemini-2.0-flash-exp';
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 4096,
            'temperature' => 0.1,
            'candidateCount' => 1
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    
    $deployer->log("Gemini API 呼叫失敗: HTTP $http_code");
    return null;
}

/**
 * 呼叫 OpenAI API
 */
function callOpenAIAPI($prompt, $config, $deployer)
{
    $api_key = $config->get('api_credentials.openai.api_key');
    $base_url = $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/';
    $url = rtrim($base_url, '/') . '/chat/completions';
    
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 4096,
        'temperature' => 0.1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
    }
    
    $deployer->log("OpenAI API 呼叫失敗: HTTP $http_code");
    return null;
}

/**
 * 格式化檔案大小
 */
function formatFileSize($size)
{
    $units = ['B', 'KB', 'MB'];
    $unit = 0;
    
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    
    return round($size, 1) . ' ' . $units[$unit];
}