<?php
/**
 * 測試單張圖片生成 - 使用 GPT-4o 優化的 OpenAI DALL-E 3
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== 單張圖片生成測試 (GPT-4o 優化版) ===\n\n";

class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

/**
 * 使用 GPT-4o 優化 Prompt
 */
function optimizePromptWithGPT4o($original_prompt, $image_type, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/chat/completions';
    
    $system_prompt = "你是專業的 AI 圖片生成 prompt 專家。優化以下 prompt 用於 DALL-E 3 生成專業的 {$image_type}。
要求：
1. 保持原意但更加專業和詳細
2. 使用英文描述
3. 加入適當的風格、光線、構圖等細節
4. 確保符合 DALL-E 3 的最佳實踐";
    
    $data = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                'role' => 'system',
                'content' => $system_prompt
            ],
            [
                'role' => 'user', 
                'content' => "請優化這個 {$image_type} 圖片生成 prompt：{$original_prompt}"
            ]
        ],
        'max_tokens' => 500,
        'temperature' => 0.7
    ];
    
    $deployer->log("使用 GPT-4o 優化 prompt...");
    
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
            $optimized_prompt = $result['choices'][0]['message']['content'];
            $deployer->log("✅ Prompt 優化完成");
            return $optimized_prompt;
        }
    }
    
    $deployer->log("⚠️  Prompt 優化失敗，使用原始 prompt");
    return $original_prompt;
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
    $deployer->log("品質: " . $data['quality']);
    
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
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error']['message'])) {
            $deployer->log("錯誤詳情: " . $error_data['error']['message']);
        }
    }
    
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
        'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
    ];
    
    echo "🔑 API 憑證檢查:\n";
    echo "OpenAI: " . ($openai_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n\n";
    
    if (!$openai_config['api_key']) {
        echo "❌ 需要設定 OpenAI API 金鑰\n";
        exit(1);
    }
    
    $job_id = '2506302336-TEST';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    $images_dir = $work_dir . '/images';
    
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    
    // 讀取圖片提示
    $image_prompts_path = $work_dir . '/json/image-prompts.json';
    if (!file_exists($image_prompts_path)) {
        throw new Exception("❌ 圖片提示檔案不存在: {$image_prompts_path}");
    }
    
    $image_prompts = json_decode(file_get_contents($image_prompts_path), true);
    
    // 選擇要生成的圖片
    echo "📸 可用的圖片類型:\n";
    $available_images = ['logo', 'index_hero_bg', 'index_hero_photo', 'index_about_photo'];
    foreach ($available_images as $i => $key) {
        if (isset($image_prompts[$key])) {
            echo ($i + 1) . ". {$key}\n";
        }
    }
    
    echo "\n請選擇要生成的圖片 (1-" . count($available_images) . "): ";
    $choice = trim(fgets(STDIN));
    
    if (!is_numeric($choice) || $choice < 1 || $choice > count($available_images)) {
        echo "❌ 無效的選擇\n";
        exit(1);
    }
    
    $selected_key = $available_images[$choice - 1];
    $image_config = $image_prompts[$selected_key];
    
    echo "\n🎨 選擇的圖片: {$selected_key}\n";
    echo "原始 Prompt: " . substr($image_config['prompt'], 0, 100) . "...\n";
    echo "尺寸: " . ($image_config['size'] ?? '1024x1024') . "\n";
    echo "品質: " . ($image_config['quality'] ?? 'standard') . "\n\n";
    
    // 優化 Prompt
    $original_prompt = $image_config['prompt'];
    if (isset($image_config['extra'])) {
        $original_prompt .= ', ' . $image_config['extra'];
    }
    
    $optimized_prompt = optimizePromptWithGPT4o($original_prompt, $image_config['style'] ?? 'image', $openai_config, $deployer);
    
    echo "\n📝 優化後的 Prompt:\n";
    echo $optimized_prompt . "\n\n";
    
    // 生成圖片
    echo "🎨 開始生成圖片...\n";
    
    $image_url = generateImageWithDallE3(
        $optimized_prompt,
        $image_config['size'] ?? '1024x1024',
        $image_config['quality'] ?? 'standard',
        $openai_config,
        $deployer
    );
    
    if ($image_url) {
        $filename = $selected_key . '.png';
        $local_path = $images_dir . '/' . $filename;
        
        if (downloadImage($image_url, $local_path, $deployer)) {
            $file_size = filesize($local_path);
            $image_info = @getimagesize($local_path);
            
            echo "\n✅ 圖片生成完成!\n";
            echo "檔案: {$filename}\n";
            echo "大小: " . round($file_size / 1024 / 1024, 2) . " MB\n";
            if ($image_info) {
                echo "尺寸: {$image_info[0]}x{$image_info[1]} px\n";
            }
            echo "路徑: {$local_path}\n";
        } else {
            echo "❌ 圖片下載失敗\n";
        }
    } else {
        echo "❌ 圖片生成失敗\n";
    }
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 測試完成 ===\n";
?>