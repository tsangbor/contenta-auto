<?php
/**
 * 測試步驟10的 Humaaans 風格功能
 * 驗證 Gemini 生成提示詞和 Ideogram 圖片生成
 */

// 設定基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
require_once DEPLOY_BASE_PATH . '/config-manager.php';

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();

// 檢查配置
if (!isset($deploy_config['api_credentials']['gemini']['api_key']) || 
    !isset($deploy_config['api_credentials']['ideogram']['api_key'])) {
    die("錯誤: 請確保 Gemini 和 Ideogram API 金鑰已配置\n");
}

// 取得 AI 配置
$gemini_config = $deploy_config['api_credentials']['gemini'];
$ideogram_config = $deploy_config['api_credentials']['ideogram'];
$image_style_config = $deploy_config['ai_features']['image_style'] ?? [];

echo "=== 步驟10 Humaaans 風格測試 ===\n";
echo "圖片風格設定: " . json_encode($image_style_config, JSON_UNESCAPED_UNICODE) . "\n";
echo "風格: " . ($image_style_config['style'] ?? 'realistic') . "\n";
echo "啟用 Humaaans: " . ($image_style_config['enable_humaaans'] ? 'Yes' : 'No') . "\n\n";

// 模擬 image-prompts.json 資料
$test_image_prompts = [
    "HOMEPAGE_HERO_BG" => [
        "original_prompt" => "Professional team collaboration in modern office",
        "context" => "首頁主視覺背景",
        "page" => "homepage",
        "container" => "hero"
    ],
    "ABOUT_TEAM_PHOTO" => [
        "original_prompt" => "Diverse business team working together",
        "context" => "關於我們 - 團隊照片",
        "page" => "about",
        "container" => "team-section"
    ]
];

echo "=== 測試圖片提示詞資料 ===\n";
echo json_encode($test_image_prompts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

/**
 * 測試 Gemini 圖片提示詞生成
 */
function testGeminiPromptGeneration($original_prompt, $gemini_config, $image_style_config) {
    echo "--- 測試 Gemini 提示詞生成 ---\n";
    echo "原始提示詞: {$original_prompt}\n";
    
    // 取得風格設定
    $style_preference = $image_style_config['style'] ?? 'realistic';
    $enable_humaaans = $image_style_config['enable_humaaans'] ?? false;
    
    // 建構 Humaaans 風格指導
    $style_guidance = "";
    if ($style_preference === 'humaaans' && $enable_humaaans) {
        $style_guidance = "

轉換為 Humaaans 風格的具體要求：
1. 視覺風格：扁平化設計、幾何形狀、簡約角色
2. 色彩方案：使用深藍(#2563EB)、淺藍(#38BDF8)、深灰(#0F172A)
3. 構圖要求：清潔的線條、向量美術風格、充足的留白空間
4. 字元設計：minimalist characters, geometric shapes
5. 背景設計：abstract geometric composition with overlapping shapes
6. 整體風格：flat design illustration in the style of humaaans

參考範例格式：
'A modern flat illustration in the style of humaaans, featuring minimalist characters collaborating and interacting with abstract data nodes. The scene represents interconnected systems and knowledge sharing. The background is a clean, abstract geometric composition with overlapping shapes. The entire image uses a strict and professional color palette of deep blue (#2563EB), light blue (#38BDF8), with subtle dark gray (#0F172A) accents. Clean lines, vector art aesthetic, plenty of negative space for text overlay. Purely visual imagery, no text, no words, no letters.'

請將原始提示詞轉換為符合上述 Humaaans 風格的詳細提示詞。";
    }
    
    // 建構完整提示詞
    $full_prompt = "你是專業的AI圖片提示詞生成師。請根據以下原始需求，生成詳細的英文圖片提示詞。

原始需求：{$original_prompt}

{$style_guidance}

要求：
1. 提示詞必須是英文
2. 描述要具體且詳細
3. 包含構圖、色彩、風格等元素
4. 適合用於 AI 圖片生成服務
5. 回應只包含最終的英文提示詞，不要額外說明

請生成提示詞：";

    echo "完整 Gemini 提示詞長度: " . mb_strlen($full_prompt) . " 字元\n";
    
    // 呼叫 Gemini API
    $api_key = $gemini_config['api_key'];
    $model = $gemini_config['model'] ?? 'gemini-2.5-flash-lite';
    $base_url = $gemini_config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $full_prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1000
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, rtrim($base_url, '/') . '/' . $model . ':generateContent?key=' . $api_key);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo "Gemini API 錯誤: HTTP {$http_code}\n";
        echo "回應: " . $response . "\n";
        return null;
    }
    
    $response_data = json_decode($response, true);
    
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $generated_prompt = trim($response_data['candidates'][0]['content']['parts'][0]['text']);
        echo "生成的提示詞: {$generated_prompt}\n";
        return $generated_prompt;
    } else {
        echo "Gemini API 回應格式錯誤\n";
        echo "回應資料: " . json_encode($response_data, JSON_UNESCAPED_UNICODE) . "\n";
        return null;
    }
}

/**
 * 測試 Ideogram 圖片生成
 */
function testIdeogramImageGeneration($prompt, $ideogram_config) {
    echo "\n--- 測試 Ideogram 圖片生成 ---\n";
    echo "使用提示詞: " . substr($prompt, 0, 100) . "...\n";
    
    $api_key = $ideogram_config['api_key'];
    $url = 'https://api.ideogram.ai/v1/ideogram-v3/generate';
    
    // 準備 multipart form data
    $boundary = '----formdata' . uniqid();
    $data = '';
    
    // 添加表單字段
    $fields = [
        'image_request' => json_encode([
            'model' => 'V_3',
            'prompt' => $prompt,
            'aspect_ratio' => 'ASPECT_16_9',
            'style_type' => 'DESIGN',
            'rendering_speed' => 'FAST',
            'magic_prompt' => true
        ])
    ];
    
    foreach ($fields as $name => $value) {
        $data .= "--{$boundary}\r\n";
        $data .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
        $data .= $value . "\r\n";
    }
    $data .= "--{$boundary}--\r\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Api-Key: ' . $api_key,
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Ideogram API 回應碼: {$http_code}\n";
    
    if ($http_code === 200) {
        $response_data = json_decode($response, true);
        if (isset($response_data['data'][0]['url'])) {
            echo "✅ 圖片生成成功!\n";
            echo "圖片 URL: " . $response_data['data'][0]['url'] . "\n";
            return $response_data['data'][0]['url'];
        } else {
            echo "回應格式錯誤: " . $response . "\n";
        }
    } else {
        echo "Ideogram API 錯誤: " . $response . "\n";
    }
    
    return null;
}

// 執行測試
foreach ($test_image_prompts as $placeholder => $prompt_data) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "測試 {$placeholder}\n";
    echo str_repeat("=", 60) . "\n";
    
    // 步驟1: 測試 Gemini 提示詞生成
    $enhanced_prompt = testGeminiPromptGeneration(
        $prompt_data['original_prompt'], 
        $gemini_config, 
        $image_style_config
    );
    
    if ($enhanced_prompt) {
        // 步驟2: 測試 Ideogram 圖片生成
        $image_url = testIdeogramImageGeneration($enhanced_prompt, $ideogram_config);
        
        if ($image_url) {
            echo "✅ 完整流程測試成功!\n";
            echo "   原始提示詞: {$prompt_data['original_prompt']}\n";
            echo "   Humaaans 提示詞: " . substr($enhanced_prompt, 0, 150) . "...\n";
            echo "   生成圖片: {$image_url}\n";
        }
    }
    
    echo "\n--- 測試完成 ---\n";
    
    // 暫停一下避免 API 限制
    sleep(2);
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "所有測試完成\n";
echo str_repeat("=", 60) . "\n";
?>