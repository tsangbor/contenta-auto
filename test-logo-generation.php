<?php
/**
 * Logo 生成專門測試
 * 針對 Logo 生成問題進行深度測試
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== Logo 生成專門測試 ===\n\n";

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

/**
 * 轉換尺寸為 Gemini 支援的長寬比格式
 */
function convertSizeToAspectRatio($size)
{
    if (strpos($size, 'x') === false) {
        return 'SQUARE';
    }
    
    list($width, $height) = explode('x', $size);
    $width = (int)$width;
    $height = (int)$height;
    
    if ($width <= 0 || $height <= 0) {
        return 'SQUARE';
    }
    
    $ratio = $width / $height;
    
    if ($ratio > 1.5) return 'LANDSCAPE';
    if ($ratio < 0.7) return 'PORTRAIT';
    return 'SQUARE';
}

/**
 * 使用 OpenAI DALL-E 生成圖片
 */
function generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/images/generations';
    
    $supported_size = convertToSupportedSize($size);
    $deployer->log("OpenAI 尺寸轉換: {$size} → {$supported_size}");
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $supported_size,
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
    
    $deployer->log("❌ OpenAI API 錯誤: HTTP {$http_code}");
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error']['message'])) {
            $deployer->log("錯誤詳情: " . $error_data['error']['message']);
        }
    }
    return null;
}

/**
 * 使用 Gemini Imagen 生成圖片
 */
function generateImageWithGemini($prompt, $quality, $size, $gemini_config, $deployer)
{
    if (!$gemini_config['api_key']) {
        $deployer->log("❌ Gemini API 金鑰未設定");
        return null;
    }
    
    $url = rtrim($gemini_config['base_url'], '/') . '/imagen-3.0-generate-001:generateImage';
    
    $aspect_ratio = convertSizeToAspectRatio($size);
    $deployer->log("Gemini 長寬比轉換: {$size} → {$aspect_ratio}");
    
    $data = [
        'prompt' => $prompt,
        'sampleCount' => 1,
        'aspectRatio' => $aspect_ratio,
        'safetyFilterLevel' => 'BLOCK_FEWEST',
        'personGeneration' => 'ALLOW_ADULT'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?key=' . $gemini_config['api_key']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['generatedImages'][0]['bytesBase64Encoded'])) {
            return 'data:image/png;base64,' . $result['generatedImages'][0]['bytesBase64Encoded'];
        }
    }
    
    $deployer->log("❌ Gemini API 錯誤: HTTP {$http_code}");
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error']['message'])) {
            $deployer->log("錯誤詳情: " . $error_data['error']['message']);
        }
    }
    return null;
}

/**
 * 下載圖片
 */
function downloadImage($image_url, $local_path, $deployer)
{
    try {
        if (strpos($image_url, 'data:image') === 0) {
            $base64_data = explode(',', $image_url)[1];
            $image_data = base64_decode($base64_data);
        } else {
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
        }
        
        if (file_put_contents($local_path, $image_data)) {
            $deployer->log("圖片儲存成功: " . basename($local_path) . " (" . formatFileSize(strlen($image_data)) . ")");
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        $deployer->log("圖片下載異常: " . $e->getMessage());
        return false;
    }
}

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

try {
    // 載入配置
    $config = ConfigManager::getInstance();
    $deployer = new MockDeployer();
    
    // 讀取原始 Logo 設定
    $job_id = '2506302336-TEST';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    $image_prompts_path = $work_dir . '/json/image-prompts.json';
    
    if (file_exists($image_prompts_path)) {
        $image_prompts = json_decode(file_get_contents($image_prompts_path), true);
        $original_logo_config = $image_prompts['logo'] ?? null;
    }
    
    echo "📋 原始 Logo 設定分析:\n";
    if ($original_logo_config) {
        echo "要求尺寸: " . $original_logo_config['size'] . "\n";
        echo "AI 服務: " . $original_logo_config['ai'] . "\n";
        echo "品質: " . $original_logo_config['quality'] . "\n";
        echo "Prompt: " . substr($original_logo_config['prompt'], 0, 50) . "...\n";
        echo "Extra: " . ($original_logo_config['extra'] ?? '無') . "\n\n";
    }
    
    // API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
    ];
    
    $gemini_config = [
        'api_key' => $config->get('api_credentials.gemini.api_key'),
        'base_url' => $config->get('api_credentials.gemini.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta/models/'
    ];
    
    echo "🔑 API 憑證檢查:\n";
    echo "OpenAI: " . ($openai_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n";
    echo "Gemini: " . ($gemini_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n\n";
    
    // 準備測試目錄
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    
    // 定義多個 Logo 測試案例
    $logo_tests = [
        'test1_original' => [
            'title' => '原始設定測試',
            'prompt' => $original_logo_config['prompt'] ?? '測試 Logo',
            'ai' => $original_logo_config['ai'] ?? 'openai',
            'size' => $original_logo_config['size'] ?? '800x200',
            'quality' => $original_logo_config['quality'] ?? 'high'
        ],
        'test2_english' => [
            'title' => '英文 Logo 測試',
            'prompt' => 'Modern minimalist logo with text "YaoGuo" in clean sans-serif font, mystical healing style, teal color #2D4C4A, stars and energy flow elements, professional design',
            'ai' => 'openai',
            'size' => '800x200',
            'quality' => 'high'
        ],
        'test3_gemini' => [
            'title' => 'Gemini 中文測試',
            'prompt' => '簡潔現代的文字 Logo「腰言豁眾」，神秘療癒風格，深綠色系 #2D4C4A，融入星星和能量流動圖案，專業設計',
            'ai' => 'gemini',
            'size' => '800x200',
            'quality' => 'standard'
        ],
        'test4_simple' => [
            'title' => '簡化版本測試',
            'prompt' => 'Clean typography logo "腰言豁眾" with minimal geometric elements, dark teal color, professional brand identity',
            'ai' => 'openai',
            'size' => '800x200',
            'quality' => 'standard'
        ]
    ];
    
    echo "🧪 開始 Logo 生成測試...\n\n";
    
    foreach ($logo_tests as $test_key => $test_config) {
        echo "--- {$test_config['title']} ---\n";
        echo "AI: {$test_config['ai']}\n";
        echo "尺寸: {$test_config['size']}\n";
        echo "Prompt: " . substr($test_config['prompt'], 0, 60) . "...\n";
        
        $ai_service = $test_config['ai'];
        $prompt = $test_config['prompt'];
        $size = $test_config['size'];
        $quality = $test_config['quality'];
        
        // 生成圖片
        if ($ai_service === 'openai') {
            $image_url = generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer);
        } elseif ($ai_service === 'gemini') {
            $image_url = generateImageWithGemini($prompt, $quality, $size, $gemini_config, $deployer);
        } else {
            echo "❌ 不支援的 AI 服務: {$ai_service}\n\n";
            continue;
        }
        
        if ($image_url) {
            // 下載並儲存
            $filename = $test_key . '.png';
            $local_path = $images_dir . '/' . $filename;
            
            if (downloadImage($image_url, $local_path, $deployer)) {
                // 檢查圖片資訊
                $image_info = @getimagesize($local_path);
                if ($image_info) {
                    echo "✅ 生成成功: {$filename}\n";
                    echo "   實際尺寸: {$image_info[0]}x{$image_info[1]} px\n";
                    echo "   檔案大小: " . formatFileSize(filesize($local_path)) . "\n";
                } else {
                    echo "❌ 生成檔案無效\n";
                }
            } else {
                echo "❌ 下載失敗\n";
            }
        } else {
            echo "❌ 生成失敗\n";
        }
        
        echo "\n";
    }
    
    echo "📊 測試完成總結:\n";
    
    // 掃描生成的測試檔案
    $test_files = glob($images_dir . '/test*.png');
    echo "生成檔案數量: " . count($test_files) . "\n\n";
    
    foreach ($test_files as $file) {
        $filename = basename($file);
        $image_info = @getimagesize($file);
        if ($image_info) {
            echo "📷 {$filename}: {$image_info[0]}x{$image_info[1]} px\n";
        }
    }
    
    echo "\n💡 Logo 生成建議:\n";
    echo "1. DALL-E 3 不支援透明背景，考慮後製處理\n";
    echo "2. 800x200 會被轉換為 1792x1024，需要裁切\n";
    echo "3. 中文字體效果可能不理想，建議使用英文 + 後製\n";
    echo "4. Gemini 支援原始比例，但可能品質略低\n";
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Logo 測試完成 ===\n";
?>