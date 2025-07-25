<?php
/**
 * 測試 Humaaans.com 風格圖片生成
 * 比較傳統風格 vs Humaaans 插圖風格的效果
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== Humaaans 風格圖片生成測試 ===\n\n";

class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
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

/**
 * 將傳統提示詞轉換為 Humaaans 風格
 */
function convertToHumaaansStyle($original_prompt, $image_type) {
    $humaaans_styles = [
        'logo' => function($prompt) {
            // 提取公司名稱
            if (preg_match("/text ['\"]([^'\"]+)['\"]/i", $prompt, $matches)) {
                $company_name = $matches[1];
                return "Minimalist flat illustration logo in humaaans.com style with text '{$company_name}', simple geometric shapes, clean typography, professional color scheme, vector art style, transparent background";
            }
            return "Minimalist flat illustration logo in humaaans.com style, simple geometric shapes, clean typography, professional color scheme, vector art style, transparent background";
        },
        
        'hero_photo' => function($prompt) {
            return "Flat illustration of a professional consultant in humaaans.com style, friendly smiling character with simple geometric features, wearing business casual attire, minimalist office background, warm color palette, clean vector art style, approachable and professional appearance";
        },
        
        'about_photo' => function($prompt) {
            return "Flat illustration of a professional person in humaaans.com style presenting or working, simple character design with geometric features, modern office setting, natural lighting representation, clean vector art style, professional and friendly atmosphere";
        },
        
        'hero_bg' => function($prompt) {
            return "Abstract flat illustration background in humaaans.com style, simple geometric shapes representing business growth and innovation, clean lines, professional color palette, minimalist design suitable for text overlay, vector art style";
        },
        
        'service_bg' => function($prompt) {
            return "Flat illustration background in humaaans.com style with abstract shapes representing services and solutions, geometric elements, professional color scheme, clean vector design suitable for content overlay";
        },
        
        'contact_bg' => function($prompt) {
            return "Minimalist flat illustration background in humaaans.com style representing communication and connection, simple geometric elements, clean design, professional color palette, suitable for contact forms";
        }
    ];
    
    // 自動偵測圖片類型
    if (stripos($image_type, 'logo') !== false) {
        return $humaaans_styles['logo']($original_prompt);
    } elseif (stripos($image_type, 'hero') !== false && stripos($image_type, 'photo') !== false) {
        return $humaaans_styles['hero_photo']($original_prompt);
    } elseif (stripos($image_type, 'about') !== false && stripos($image_type, 'photo') !== false) {
        return $humaaans_styles['about_photo']($original_prompt);
    } elseif (stripos($image_type, 'hero') !== false && stripos($image_type, 'bg') !== false) {
        return $humaaans_styles['hero_bg']($original_prompt);
    } elseif (stripos($image_type, 'service') !== false) {
        return $humaaans_styles['service_bg']($original_prompt);
    } elseif (stripos($image_type, 'contact') !== false) {
        return $humaaans_styles['contact_bg']($original_prompt);
    }
    
    // 預設：一般插圖風格
    return "Flat illustration in humaaans.com style, simple geometric shapes, clean vector art design, professional color palette, minimalist approach";
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
    
    // 建立測試目錄
    $test_id = date('ymdHi') . '-HUMAAANS-TEST';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_id;
    $images_dir = $work_dir . '/images';
    
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    
    // 預定義測試案例
    $test_cases = [
        'logo' => [
            'original' => "Professional technology logo with text 'TechCorp' in modern sans-serif font, incorporating abstract geometric elements, color #2563EB, minimalist corporate design, transparent background",
            'type' => 'logo',
            'size' => '1024x1024',
            'quality' => 'high'
        ],
        'hero_photo' => [
            'original' => "Professional headshot of a business consultant, smiling confidently, wearing business casual attire, in a modern office setting with blurred background, natural lighting, conveying expertise and approachability",
            'type' => 'hero_photo',
            'size' => '1024x1024',
            'quality' => 'high'
        ],
        'hero_bg' => [
            'original' => "Abstract geometric background with overlapping shapes in shades of blue and gray, representing innovation and technology, professional and modern aesthetic, suitable for text overlay",
            'type' => 'hero_bg',
            'size' => '1792x1024',
            'quality' => 'standard'
        ],
        'about_photo' => [
            'original' => "Professional person presenting a workshop, engaging with participants, whiteboard with diagrams in the background, modern office setting, natural lighting, conveying expertise and knowledge sharing",
            'type' => 'about_photo', 
            'size' => '1024x1024',
            'quality' => 'standard'
        ]
    ];
    
    echo "🎨 Humaaans 風格測試案例:\n";
    foreach ($test_cases as $key => $case) {
        echo ($key) . ". " . ucfirst(str_replace('_', ' ', $key)) . "\n";
    }
    
    echo "\n請選擇要測試的案例 (輸入 key，如 'logo')，或輸入 'all' 測試全部: ";
    $choice = trim(fgets(STDIN));
    
    $selected_cases = [];
    if ($choice === 'all') {
        $selected_cases = $test_cases;
    } elseif (isset($test_cases[$choice])) {
        $selected_cases[$choice] = $test_cases[$choice];
    } else {
        echo "❌ 無效的選擇\n";
        exit(1);
    }
    
    foreach ($selected_cases as $test_key => $test_case) {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "🧪 測試案例: " . ucfirst(str_replace('_', ' ', $test_key)) . "\n";
        echo str_repeat("=", 50) . "\n";
        
        // 原始風格
        echo "\n📸 生成傳統風格圖片...\n";
        echo "原始 Prompt: " . substr($test_case['original'], 0, 80) . "...\n";
        
        $original_url = generateImageWithDallE3(
            $test_case['original'],
            $test_case['size'],
            $test_case['quality'],
            $openai_config,
            $deployer
        );
        
        if ($original_url) {
            $original_filename = $test_key . '_original.png';
            $original_path = $images_dir . '/' . $original_filename;
            downloadImage($original_url, $original_path, $deployer);
        }
        
        // Humaaans 風格
        echo "\n🎨 生成 Humaaans 風格圖片...\n";
        $humaaans_prompt = convertToHumaaansStyle($test_case['original'], $test_case['type']);
        echo "Humaaans Prompt: " . substr($humaaans_prompt, 0, 80) . "...\n";
        
        $humaaans_url = generateImageWithDallE3(
            $humaaans_prompt,
            $test_case['size'],
            $test_case['quality'],
            $openai_config,
            $deployer
        );
        
        if ($humaaans_url) {
            $humaaans_filename = $test_key . '_humaaans.png';
            $humaaans_path = $images_dir . '/' . $humaaans_filename;
            downloadImage($humaaans_url, $humaaans_path, $deployer);
        }
        
        // 結果比較
        echo "\n📊 生成結果:\n";
        echo "傳統風格: " . ($original_url ? "✅ 成功" : "❌ 失敗") . "\n";
        echo "Humaaans風格: " . ($humaaans_url ? "✅ 成功" : "❌ 失敗") . "\n";
        
        if ($original_url && $humaaans_url) {
            echo "\n📝 Prompt 比較:\n";
            echo "原始: {$test_case['original']}\n\n";
            echo "Humaaans: {$humaaans_prompt}\n";
        }
        
        // 等待使用者檢視
        if (count($selected_cases) > 1) {
            echo "\n按 Enter 繼續下一個測試案例...";
            fgets(STDIN);
        }
    }
    
    echo "\n✅ 測試完成！\n";
    echo "📂 圖片儲存位置: {$images_dir}\n";
    echo "\n🎯 比較建議:\n";
    echo "1. 檢視傳統風格與 Humaaans 風格的視覺差異\n";
    echo "2. 評估 Humaaans 風格是否符合品牌調性\n"; 
    echo "3. 觀察插圖風格的一致性和專業度\n";
    echo "4. 考慮目標受眾對不同風格的接受度\n";
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Humaaans 風格測試完成 ===\n";
?>