<?php
/**
 * 測試單張圖片生成 (僅測試一張以驗證功能)
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== 單張圖片生成測試 ===\n\n";

// 模擬部署器日誌類別
class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

/**
 * 轉換任意尺寸為 DALL-E 3 支援的尺寸
 */
function convertToSupportedSize($size)
{
    // DALL-E 3 支援的尺寸: 1024x1024, 1024x1792, 1792x1024
    if (strpos($size, 'x') === false) {
        return '1024x1024'; // 預設正方形
    }
    
    list($width, $height) = explode('x', $size);
    $width = (int)$width;
    $height = (int)$height;
    
    // 處理無效尺寸
    if ($width <= 0 || $height <= 0) {
        return '1024x1024'; // 預設正方形
    }
    
    // 計算長寬比
    $ratio = $width / $height;
    
    // 根據長寬比選擇最適合的支援尺寸
    if ($ratio > 1.3) {
        // 橫向圖片
        return '1792x1024';
    } elseif ($ratio < 0.7) {
        // 直向圖片
        return '1024x1792';
    } else {
        // 正方形或接近正方形
        return '1024x1024';
    }
}

/**
 * 使用 OpenAI DALL-E 生成圖片
 */
function generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/images/generations';
    
    // 轉換為支援的尺寸格式
    $supported_size = convertToSupportedSize($size);
    $deployer->log("原始尺寸: {$size} → 支援尺寸: {$supported_size}");
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $supported_size,
        'quality' => $quality === 'high' ? 'hd' : 'standard',
        'response_format' => 'url'
    ];
    
    $deployer->log("發送請求到 OpenAI: " . substr($prompt, 0, 50) . "...");
    
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
            $deployer->log("✅ OpenAI 圖片生成成功");
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("❌ OpenAI 圖片 API 錯誤: HTTP {$http_code}");
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error']['message'])) {
            $deployer->log("錯誤詳情: " . $error_data['error']['message']);
        }
    }
    return null;
}

try {
    // 載入配置
    $config = ConfigManager::getInstance();
    $deployer = new MockDeployer();
    
    // 檢查 API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
    ];
    
    echo "🔑 API 憑證檢查:\n";
    echo "OpenAI: " . ($openai_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n\n";
    
    if (!$openai_config['api_key']) {
        echo "⚠️  OpenAI API 金鑰未設定，無法進行測試\n";
        exit(1);
    }
    
    // 測試單張圖片生成
    $test_prompt = "A modern minimalist logo for a spiritual energy consulting company, simple geometric design, clean lines, peaceful colors";
    $test_size = "800x200"; // 這會被轉換為 1792x1024
    $test_quality = "standard";
    
    echo "🎨 測試圖片生成:\n";
    echo "提示詞: {$test_prompt}\n";
    echo "原始尺寸: {$test_size}\n";
    echo "品質: {$test_quality}\n\n";
    
    $image_url = generateImageWithOpenAI($test_prompt, $test_quality, $test_size, $openai_config, $deployer);
    
    if ($image_url) {
        echo "\n✅ 圖片生成測試成功！\n";
        echo "圖片 URL: {$image_url}\n";
        echo "\n💡 這證明 OpenAI DALL-E 3 尺寸轉換功能正常運作\n";
    } else {
        echo "\n❌ 圖片生成測試失敗\n";
        echo "可能原因:\n";
        echo "- API 金鑰無效\n";
        echo "- 網路連線問題\n";
        echo "- OpenAI 服務暫時不可用\n";
    }
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 測試完成 ===\n";