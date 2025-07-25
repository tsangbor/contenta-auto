<?php
/**
 * 備用測試方案：使用 OpenAI GPT-4o 生成 Humaaans 風格提示詞
 * 並使用 DALL-E 3 生成圖片（因為 Ideogram 需要 API key）
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== OpenAI + Humaaans 風格測試（備用方案）===\n\n";

class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

/**
 * 使用 GPT-4o 生成 Humaaans 風格圖片提示詞
 */
function generateHumaaansPromptsWithGPT($image_requirements, $user_data, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/chat/completions';
    
    $system_prompt = "你是專業的 AI 圖片生成提示詞專家，專門為 humaaans.com 插圖風格設計提示詞。

## Humaaans.com 風格特徵
- 扁平化插圖設計 (Flat illustration)  
- 簡潔的幾何形狀和線條
- 友善、親和的人物形象
- 統一的色彩系統
- 商業應用友好
- 簡化的細節表現
- 現代感的視覺語言

## 提示詞範例格式

### Logo:
\"Flat illustration logo in humaaans.com style with text '[公司名稱]', minimalist geometric shapes, professional color palette, transparent background\"

### 人物照片:
\"Flat illustration of [角色] in humaaans.com style, friendly character with simple geometric features, business casual attire, modern office background, clean vector art\"

### 背景圖片:
\"Abstract flat illustration background in humaaans.com style, geometric shapes representing [主題], professional colors, suitable for text overlay\"

請基於用戶資料為每個圖片需求生成專業的英文提示詞。";

    $user_prompt = "用戶背景資料：\n" . json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . 
                   "\n\n圖片需求：\n" . json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                   "\n\n請為每個圖片生成 humaaans.com 風格的英文提示詞，返回 JSON 格式。";

    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $user_prompt]
        ],
        'temperature' => 0.7,
        'max_tokens' => 2000
    ];
    
    $deployer->log("使用 GPT-4o 生成 Humaaans 風格提示詞...");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_config['api_key']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            $content = $result['choices'][0]['message']['content'];
            $deployer->log("✅ GPT-4o 提示詞生成完成");
            
            // 嘗試解析 JSON
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $json_text = $matches[0];
                $parsed = json_decode($json_text, true);
                if ($parsed) {
                    return $parsed;
                }
            }
            
            return ['raw_response' => $content];
        }
    }
    
    $deployer->log("❌ GPT-4o API 調用失敗: HTTP {$http_code}");
    return null;
}

/**
 * 使用 DALL-E 3 生成圖片
 */
function generateImageWithDallE3($prompt, $size, $quality, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/images/generations';
    
    // 轉換為 DALL-E 3 支援的尺寸
    $dalle_size = convertToSupportedSize($size);
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $dalle_size,
        'quality' => $quality === 'high' ? 'hd' : 'standard',
        'response_format' => 'url'
    ];
    
    $deployer->log("調用 DALL-E 3 API...");
    $deployer->log("尺寸: {$size} → {$dalle_size}");
    
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
            $deployer->log("✅ 圖片生成成功");
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("❌ 圖片生成失敗: HTTP {$http_code}");
    return null;
}

function convertToSupportedSize($size)
{
    if (strpos($size, 'x') === false) {
        return '1024x1024';
    }
    
    list($width, $height) = explode('x', $size);
    $width = (int)$width;
    $height = (int)$height;
    
    if ($width <= 0 || $height <= 0) {
        return '1024x1024';
    }
    
    $ratio = $width / $height;
    
    if ($ratio > 1.3) {
        return '1792x1024';  // 橫向
    } elseif ($ratio < 0.7) {
        return '1024x1792';  // 直向
    } else {
        return '1024x1024';  // 正方形
    }
}

function downloadImage($image_url, $local_path, $deployer)
{
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$image_data) {
            return false;
        }
        
        if (file_put_contents($local_path, $image_data)) {
            $deployer->log("圖片儲存成功: " . basename($local_path));
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        $deployer->log("圖片下載異常: " . $e->getMessage());
        return false;
    }
}

try {
    $config = ConfigManager::getInstance();
    $deployer = new MockDeployer();
    
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1'
    ];
    
    echo "🔑 API 憑證檢查:\n";
    echo "OpenAI: " . ($openai_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n\n";
    
    if (!$openai_config['api_key']) {
        echo "❌ 需要設定 OpenAI API 金鑰\n";
        exit(1);
    }
    
    // 建立測試目錄
    $test_id = date('ymdHi') . '-OPENAI-HUMAAANS';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_id;
    $images_dir = $work_dir . '/images';
    $json_dir = $work_dir . '/json';
    
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    
    // 使用 Zion Wu 的實際資料
    $user_data = [
        'company_name' => 'Zion Tech Consulting',
        'founder_name' => 'Zion Wu',
        'industry' => 'Software Development & Agile Consulting',
        'services' => ['Agile Development', 'Project Management', 'Software Architecture'],
        'brand_personality' => 'Professional, Innovative, Approachable',
        'target_audience' => 'Software Teams, Tech Startups, Enterprise Companies',
        'core_values' => 'Efficiency, Collaboration, Continuous Improvement',
        'visual_preferences' => 'Modern, Clean, Tech-oriented',
        'brand_colors' => ['#2563EB', '#38BDF8', '#0F172A']
    ];
    
    // 基於您提供的圖片提示詞範例
    $image_requirements = [
        'about_hero-bg' => [
            'title' => '關於頁面背景',
            'context' => 'Abstract geometric background for about page',
            'purpose' => 'hero section background',
            'size' => '1312x736',
            'style' => 'abstract'
        ],
        'about_about-photo' => [
            'title' => 'Zion Wu 個人照片',
            'context' => 'Professional headshot of Zion Wu',
            'purpose' => 'about section portrait',
            'size' => '1024x1024',
            'style' => 'professional'
        ],
        'home_hero-photo' => [
            'title' => '首頁 Zion Wu 肖像',
            'context' => 'Professional portrait for homepage',
            'purpose' => 'hero section portrait',
            'size' => '1024x1024',
            'style' => 'professional'
        ],
        'home_service-photo' => [
            'title' => '團隊協作照片',
            'context' => 'Team collaboration in Agile development',
            'purpose' => 'service section illustration',
            'size' => '1024x1024',
            'style' => 'professional'
        ]
    ];
    
    echo "🧪 測試案例:\n";
    foreach ($image_requirements as $key => $req) {
        echo "- {$key}: {$req['title']}\n";
    }
    
    echo "\n請選擇要測試的圖片類型 (輸入 key)，或輸入 'all' 測試全部: ";
    $choice = trim(fgets(STDIN));
    
    $selected_requirements = [];
    if ($choice === 'all') {
        $selected_requirements = $image_requirements;
    } elseif (isset($image_requirements[$choice])) {
        $selected_requirements[$choice] = $image_requirements[$choice];
    } else {
        echo "❌ 無效的選擇\n";
        exit(1);
    }
    
    // 第一階段：生成提示詞
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎨 第一階段: 生成 Humaaans 風格提示詞\n";
    echo str_repeat("=", 60) . "\n";
    
    $humaaans_prompts = generateHumaaansPromptsWithGPT(
        $selected_requirements,
        $user_data,
        $openai_config,
        $deployer
    );
    
    if (!$humaaans_prompts) {
        echo "❌ 提示詞生成失敗\n";
        exit(1);
    }
    
    // 儲存提示詞
    $prompts_file = $json_dir . '/humaaans-prompts.json';
    file_put_contents($prompts_file, json_encode($humaaans_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "\n📝 生成的 Humaaans 風格提示詞:\n";
    print_r($humaaans_prompts);
    
    // 第二階段：生成圖片
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🖼️  第二階段: DALL-E 3 圖片生成\n";
    echo str_repeat("=", 60) . "\n";
    
    echo "\n是否繼續生成圖片？(y/N): ";
    $continue = trim(fgets(STDIN));
    
    if (strtolower($continue) !== 'y') {
        echo "跳過圖片生成階段\n";
        echo "提示詞已儲存至: {$prompts_file}\n";
        exit(0);
    }
    
    $results = [];
    
    foreach ($selected_requirements as $key => $req) {
        echo "\n🎨 生成圖片: {$req['title']}\n";
        
        // 獲取對應的提示詞
        $prompt = '';
        if (isset($humaaans_prompts[$key])) {
            $prompt = is_array($humaaans_prompts[$key]) ? 
                     $humaaans_prompts[$key]['prompt'] ?? '' : 
                     $humaaans_prompts[$key];
        } elseif (isset($humaaans_prompts['raw_response'])) {
            // 使用預設的 humaaans 風格
            $prompt = "Flat illustration in humaaans.com style for {$req['title']}, simple geometric shapes, clean vector art";
        }
        
        if (!$prompt) {
            echo "⚠️  找不到提示詞，跳過\n";
            continue;
        }
        
        echo "提示詞: " . substr($prompt, 0, 100) . "...\n";
        
        // 原始風格（用於對比）
        echo "\n1️⃣ 生成原始風格...\n";
        $original_prompt = str_replace(
            ['humaaans.com style', 'flat illustration', 'Flat illustration'], 
            ['professional photography', 'realistic photo', 'Professional photo'],
            $prompt
        );
        
        $original_url = generateImageWithDallE3(
            $original_prompt,
            $req['size'],
            'standard',
            $openai_config,
            $deployer
        );
        
        if ($original_url) {
            $filename = $key . '_original.png';
            downloadImage($original_url, $images_dir . '/' . $filename, $deployer);
        }
        
        // Humaaans 風格
        echo "\n2️⃣ 生成 Humaaans 風格...\n";
        $humaaans_url = generateImageWithDallE3(
            $prompt,
            $req['size'],
            'standard',
            $openai_config,
            $deployer
        );
        
        if ($humaaans_url) {
            $filename = $key . '_humaaans.png';
            downloadImage($humaaans_url, $images_dir . '/' . $filename, $deployer);
        }
        
        $results[$key] = [
            'title' => $req['title'],
            'original_success' => !empty($original_url),
            'humaaans_success' => !empty($humaaans_url)
        ];
    }
    
    // 結果報告
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 測試結果報告\n";
    echo str_repeat("=", 60) . "\n";
    
    foreach ($results as $key => $result) {
        echo "\n{$result['title']}:\n";
        echo "  原始風格: " . ($result['original_success'] ? "✅" : "❌") . "\n";
        echo "  Humaaans風格: " . ($result['humaaans_success'] ? "✅" : "❌") . "\n";
    }
    
    echo "\n📂 輸出檔案位置:\n";
    echo "圖片: {$images_dir}\n";
    echo "提示詞: {$prompts_file}\n";
    
    echo "\n🎨 Humaaans 風格優勢:\n";
    echo "✨ 風格統一，品牌一致性強\n";
    echo "👥 人物形象更親和友善\n";
    echo "🎯 適合科技公司形象\n";
    echo "💼 商業應用靈活度高\n";
    echo "🔄 易於後續修改調整\n";
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== OpenAI + Humaaans 測試完成 ===\n";
?>