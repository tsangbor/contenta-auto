<?php
/**
 * 測試 Zion Wu 專案的圖片提示詞 - 傳統風格 vs Humaaans 風格
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== Zion Wu 專案圖片提示詞測試 ===\n";
echo "比較傳統風格 vs Humaaans 插圖風格\n\n";

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
 * 將 Zion Wu 專案的提示詞轉換為 Humaaans 風格
 */
function convertZionPromptsToHumaaans($original_prompt, $image_key) {
    
    // 解析圖片類型
    if (strpos($image_key, 'hero-bg') !== false) {
        return "Flat illustration background in humaaans.com style, abstract geometric shapes representing technology and software development, clean vector art with color palette of deep blue (#2563EB), light blue (#38BDF8), and dark gray (#0F172A), minimalist design suitable for text overlay, professional and modern aesthetic";
        
    } elseif (strpos($image_key, 'hero-photo') !== false) {
        return "Flat illustration of Zion Wu in humaaans.com style, professional software consultant character with friendly smile, simple geometric facial features, wearing business casual attire, modern office background with subtle blue accents, clean vector art style, approachable and trustworthy appearance";
        
    } elseif (strpos($image_key, 'about-photo') !== false) {
        return "Flat illustration of Zion Wu in humaaans.com style, professional headshot character with confident and friendly expression, simple geometric features, business casual clothing, subtle blue background elements, clean vector art design, conveying expertise and approachability";
        
    } elseif (strpos($image_key, 'service-photo') !== false) {
        return "Flat illustration in humaaans.com style showing Zion Wu presenting a workshop, engaging with participants represented as simple geometric characters, whiteboard with agile diagrams in background, modern office setting, clean vector art with blue color accents, professional teamwork atmosphere";
        
    } elseif (strpos($image_key, 'home_about-photo') !== false) {
        return "Flat illustration in humaaans.com style of Zion Wu conducting an Agile development workshop, simple character design engaging with participant figures, whiteboard with geometric diagrams, modern office environment, vector art style with deep blue and light blue accents";
        
    } elseif (strpos($image_key, 'home_service-photo') !== false) {
        return "Flat illustration in humaaans.com style showing a diverse team of software developers, simple geometric character designs collaborating on laptops, whiteboard elements, modern office setting, clean vector art with blue color palette, teamwork and innovation theme";
        
    } elseif (strpos($image_key, 'blog_hero-bg') !== false) {
        return "Flat illustration background in humaaans.com style, dynamic network of interconnected nodes and lines representing knowledge flow, geometric shapes in deep blue (#2563EB), light blue (#38BDF8), and dark gray (#0F172A), clean vector design suitable for blog header";
        
    } elseif (strpos($image_key, 'contact_hero-bg') !== false) {
        return "Minimalist flat illustration background in humaaans.com style, subtle gradient from deep blue (#2563EB) to light blue (#38BDF8), simple geometric elements representing connection and communication, clean vector art suitable for contact forms";
        
    } elseif (strpos($image_key, 'service_hero-bg') !== false) {
        return "Flat illustration background in humaaans.com style, abstract representation of project management workflow with interconnected geometric shapes, blue color palette (#2563EB, #38BDF8) with dark gray accents, professional vector design for service pages";
        
    } elseif (strpos($image_key, 'footer-contact-bg') !== false) {
        return "Subtle flat illustration pattern in humaaans.com style, minimalist geometric elements in dark gray (#0F172A) with faint blue accents, low contrast vector design suitable for footer backgrounds, professional and modern appearance";
        
    } else {
        // 預設通用 humaaans 風格
        return "Flat illustration in humaaans.com style, simple geometric shapes and clean vector art design, professional color palette with blue accents, minimalist and modern aesthetic";
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
    
    // 建立測試目錄
    $test_id = date('ymdHi') . '-ZION-HUMAAANS';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_id;
    $images_dir = $work_dir . '/images';
    
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    
    // Zion Wu 專案的圖片提示詞
    $zion_prompts = [
        "2507171303-3269_about_hero-bg" => [
            "prompt" => "Abstract geometric background with overlapping shapes in shades of deep blue (#2563EB) and light blue (#38BDF8), subtle dark gray (#0F172A) accents, representing interconnected systems and knowledge sharing, professional and modern aesthetic, clean lines, suitable for text overlay, no text, no words, no letters, purely visual imagery",
            "style" => "abstract",
            "size" => "1312x736",
            "quality" => "standard"
        ],
        "2507171303-3269_about_about-photo" => [
            "prompt" => "Professional headshot of Zion Wu, smiling confidently, wearing business casual attire, in a modern office setting with blurred background, natural lighting, conveying expertise and approachability, primary color #2563EB subtly reflected in the background, no text, no words, no letters, purely visual imagery",
            "style" => "professional",
            "size" => "1024x1024",
            "quality" => "standard"
        ],
        "2507171303-3269_home_hero-bg" => [
            "prompt" => "Abstract representation of a software architecture diagram, with interconnected modules and data flows, using a color palette of deep blue (#2563EB), light blue (#38BDF8), and dark gray (#0F172A), professional and modern, suitable for hero section with text overlay, no text, no words, no letters, purely visual imagery",
            "style" => "abstract",
            "size" => "1312x736",
            "quality" => "standard"
        ],
        "2507171303-3269_home_hero-photo" => [
            "prompt" => "Professional portrait of Zion Wu, looking directly at the camera with a confident and friendly expression, in a modern office environment, wearing business attire, soft lighting, conveying expertise and trustworthiness, subtle deep blue (#2563EB) accent in the background, no text, no words, no letters, purely visual imagery",
            "style" => "professional",
            "size" => "1024x1024",
            "quality" => "standard"
        ],
        "2507171303-3269_home_service-photo" => [
            "prompt" => "A team of software developers collaborating on a project, using agile methodologies, working on laptops and whiteboards, modern office environment, diverse team members, conveying teamwork and innovation, deep blue (#2563EB) and light blue (#38BDF8) accents in the office decor, no text, no words, no letters, purely visual imagery",
            "style" => "professional",
            "size" => "1024x1024",
            "quality" => "standard"
        ]
    ];
    
    echo "🎨 Zion Wu 專案圖片測試案例:\n";
    $counter = 1;
    foreach ($zion_prompts as $key => $prompt_data) {
        $display_name = str_replace('2507171303-3269_', '', $key);
        echo "{$counter}. {$display_name}\n";
        $counter++;
    }
    
    echo "\n請選擇要測試的圖片編號 (1-" . count($zion_prompts) . ")，或輸入 'all' 測試全部: ";
    $choice = trim(fgets(STDIN));
    
    $selected_prompts = [];
    if ($choice === 'all') {
        $selected_prompts = $zion_prompts;
    } elseif (is_numeric($choice) && $choice >= 1 && $choice <= count($zion_prompts)) {
        $keys = array_keys($zion_prompts);
        $selected_key = $keys[$choice - 1];
        $selected_prompts[$selected_key] = $zion_prompts[$selected_key];
    } else {
        echo "❌ 無效的選擇\n";
        exit(1);
    }
    
    $results = [];
    
    foreach ($selected_prompts as $image_key => $prompt_data) {
        echo "\n" . str_repeat("=", 60) . "\n";
        $display_name = str_replace('2507171303-3269_', '', $image_key);
        echo "🧪 測試圖片: {$display_name}\n";
        echo str_repeat("=", 60) . "\n";
        
        // 原始風格
        echo "\n📸 生成傳統風格圖片...\n";
        echo "原始 Prompt: " . substr($prompt_data['prompt'], 0, 100) . "...\n";
        
        $original_url = generateImageWithDallE3(
            $prompt_data['prompt'],
            $prompt_data['size'],
            $prompt_data['quality'],
            $openai_config,
            $deployer
        );
        
        $original_success = false;
        if ($original_url) {
            $original_filename = $display_name . '_original.png';
            $original_path = $images_dir . '/' . $original_filename;
            $original_success = downloadImage($original_url, $original_path, $deployer);
        }
        
        // Humaaans 風格
        echo "\n🎨 生成 Humaaans 風格圖片...\n";
        $humaaans_prompt = convertZionPromptsToHumaaans($prompt_data['prompt'], $image_key);
        echo "Humaaans Prompt: " . substr($humaaans_prompt, 0, 100) . "...\n";
        
        $humaaans_url = generateImageWithDallE3(
            $humaaans_prompt,
            $prompt_data['size'],
            $prompt_data['quality'],
            $openai_config,
            $deployer
        );
        
        $humaaans_success = false;
        if ($humaaans_url) {
            $humaaans_filename = $display_name . '_humaaans.png';
            $humaaans_path = $images_dir . '/' . $humaaans_filename;
            $humaaans_success = downloadImage($humaaans_url, $humaaans_path, $deployer);
        }
        
        // 記錄結果
        $results[$display_name] = [
            'original' => $original_success,
            'humaaans' => $humaaans_success,
            'original_prompt' => $prompt_data['prompt'],
            'humaaans_prompt' => $humaaans_prompt
        ];
        
        // 結果顯示
        echo "\n📊 生成結果:\n";
        echo "傳統風格: " . ($original_success ? "✅ 成功" : "❌ 失敗") . "\n";
        echo "Humaaans風格: " . ($humaaans_success ? "✅ 成功" : "❌ 失敗") . "\n";
        
        // 等待檢視
        if (count($selected_prompts) > 1) {
            echo "\n按 Enter 繼續下一個測試...";
            fgets(STDIN);
        }
    }
    
    // 總結報告
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 測試總結報告\n";
    echo str_repeat("=", 60) . "\n";
    
    $total_count = count($results);
    $original_success_count = 0;
    $humaaans_success_count = 0;
    
    foreach ($results as $name => $result) {
        if ($result['original']) $original_success_count++;
        if ($result['humaaans']) $humaaans_success_count++;
        
        echo "\n🎨 {$name}:\n";
        echo "  傳統風格: " . ($result['original'] ? "✅" : "❌") . "\n";
        echo "  Humaaans風格: " . ($result['humaaans'] ? "✅" : "❌") . "\n";
    }
    
    echo "\n📈 成功率統計:\n";
    echo "傳統風格: {$original_success_count}/{$total_count} (" . round($original_success_count/$total_count*100, 1) . "%)\n";
    echo "Humaaans風格: {$humaaans_success_count}/{$total_count} (" . round($humaaans_success_count/$total_count*100, 1) . "%)\n";
    
    echo "\n📂 圖片儲存位置: {$images_dir}\n";
    
    echo "\n🎯 Humaaans 風格特色分析:\n";
    echo "✨ 扁平化插圖設計，風格統一\n";
    echo "👥 人物形象更友善親切\n";
    echo "🎨 色彩搭配保持品牌一致性\n";
    echo "📱 適合現代網頁設計趨勢\n";
    echo "🔄 更容易客製化和修改\n";
    
    echo "\n💡 應用建議:\n";
    echo "1. 如果品牌定位偏向親和力，建議使用 Humaaans 風格\n";
    echo "2. 如果強調專業權威感，可保持傳統攝影風格\n";
    echo "3. 可以混合使用：背景用 Humaaans，人物照片保持真實\n";
    echo "4. 考慮目標受眾年齡層對插圖的接受度\n";
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Zion Wu 專案 Humaaans 風格測試完成 ===\n";
?>