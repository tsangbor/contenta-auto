<?php
/**
 * 測試 Gemini 模型生成 Humaaans 風格提示詞 + Ideogram 圖片生成
 * 功能：
 * 1. 使用 Gemini 生成包含 humaaans 風格的圖片提示詞
 * 2. 使用 Ideogram API 生成圖片
 * 3. 比較不同風格的效果
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== Gemini + Ideogram + Humaaans 風格測試 ===\n\n";

class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

/**
 * 使用 Gemini 生成 Humaaans 風格圖片提示詞
 */
function generateHumaaansPromptsWithGemini($image_requirements, $user_data, $gemini_config, $deployer)
{
    // 使用與 step-09 相同的方式構建 URL
    $api_key = $gemini_config['api_key'] ?? '';
    $model = $gemini_config['model'] ?? 'gemini-1.5-flash';
    $base_url = $gemini_config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    $url = rtrim($base_url, '/') . '/' . $model . ':generateContent?key=' . $api_key;
    
    // 建構 Gemini 提示詞
    $system_prompt = "你是專業的 AI 圖片生成提示詞專家，專門為 humaaans.com 插圖風格設計提示詞。

## 任務目標
基於用戶背景資料和圖片需求，生成高品質的 humaaans.com 風格 AI 圖片提示詞。

## Humaaans.com 風格特徵
- 扁平化插圖設計 (Flat illustration)
- 簡潔的幾何形狀和線條
- 友善、親和的人物形象
- 統一的色彩系統
- 商業應用友好
- 簡化的細節表現
- 現代感的視覺語言

## 用戶背景資料
" . json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "

## 圖片需求清單
" . json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "

## 提示詞生成規則

### 1. Logo 類型
- 格式: \"Flat illustration logo in humaaans.com style with text '[公司名稱]', [設計元素], [色彩], transparent background\"
- 必須包含具體公司名稱
- 指定字體風格和色彩
- 加入相關的圖形元素

### 2. 人物照片類型
- 格式: \"Flat illustration of [角色] in humaaans.com style, [外觀特徵], [服裝], [環境], [氛圍]\"
- 強調友善、專業的人物形象
- 簡化的幾何特徵
- 適當的商業環境

### 3. 背景圖片類型
- 格式: \"Abstract flat illustration background in humaaans.com style, [主題元素], [色彩搭配], suitable for text overlay\"
- 幾何抽象元素
- 與業務相關的視覺隱喻
- 適合文字疊加

## 輸出要求
1. 所有提示詞使用英文
2. 包含完整的 JSON 格式
3. 每個圖片包含: title, prompt, style, size, quality 等欄位
4. 深度融合用戶背景特色
5. 保持 humaaans.com 風格一致性

請生成完整的 image-prompts.json 格式輸出，確保為每個圖片需求生成對應的 JSON 對象。返回格式如下：
{
  \"image_key1\": {
    \"title\": \"圖片標題\",
    \"prompt\": \"英文提示詞\",
    \"style\": \"風格\",
    \"size\": \"尺寸\",
    \"quality\": \"品質\"
  },
  \"image_key2\": {
    ...
  }
}";

    $user_prompt = "請基於上述用戶資料和圖片需求，生成完整的 humaaans.com 風格圖片提示詞。確保每個提示詞都：
1. 深度個性化基於用戶背景
2. 符合 humaaans.com 扁平插圖風格
3. 使用英文描述
4. 包含具體的視覺元素描述
5. 適合商業應用場景";

    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $system_prompt . "\n\n" . $user_prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 2048
        ]
    ];
    
    $deployer->log("使用 Gemini 生成 Humaaans 風格提示詞...");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $generated_text = $result['candidates'][0]['content']['parts'][0]['text'];
            $deployer->log("✅ Gemini 提示詞生成完成");
            
            // 嘗試解析 JSON
            // 首先嘗試直接解析
            $parsed_prompts = json_decode($generated_text, true);
            if ($parsed_prompts && is_array($parsed_prompts)) {
                return $parsed_prompts;
            }
            
            // 如果不是純 JSON，嘗試提取 JSON 部分
            if (preg_match('/\{[\s\S]*\}/m', $generated_text, $matches)) {
                $json_text = $matches[0];
                $parsed_prompts = json_decode($json_text, true);
                if ($parsed_prompts && is_array($parsed_prompts)) {
                    return $parsed_prompts;
                }
            }
            
            // 如果還是無法解析，創建一個基本的提示詞
            $deployer->log("⚠️ 無法解析 JSON，使用預設格式");
            $default_prompts = [];
            foreach ($image_requirements as $key => $req) {
                $default_prompts[$key] = [
                    'title' => $req['title'],
                    'prompt' => "Flat illustration in humaaans.com style for {$req['title']}, minimalist design, clean vector art, professional color palette",
                    'style' => $req['style'],
                    'size' => $req['size'],
                    'quality' => 'standard'
                ];
            }
            return $default_prompts;
        }
    }
    
    $deployer->log("❌ Gemini API 調用失敗: HTTP {$http_code}");
    if ($response) {
        $deployer->log("錯誤詳情: " . substr($response, 0, 200));
    }
    
    return null;
}

/**
 * 使用 Ideogram API 生成圖片（參考 step-10 的實作）
 */
function generateImageWithIdeogram($prompt, $size, $style, $ideogram_config, $deployer)
{
    $url = 'https://api.ideogram.ai/v1/ideogram-v3/generate';
    
    // 轉換尺寸格式
    $aspect_ratio = convertToIdeogramAspectRatio($size);
    
    $deployer->log("調用 Ideogram API...");
    $deployer->log("尺寸: {$size} → {$aspect_ratio}");
    
    // 準備 multipart form data
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    
    $post_data = '';
    
    // 添加 prompt
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="prompt"' . "\r\n\r\n";
    $post_data .= $prompt . "\r\n";
    
    // 添加 aspect_ratio
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="aspect_ratio"' . "\r\n\r\n";
    $post_data .= $aspect_ratio . "\r\n";
    
    // 添加 rendering_speed
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="rendering_speed"' . "\r\n\r\n";
    $post_data .= "TURBO\r\n";
    
    // 添加 style_type
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="style_type"' . "\r\n\r\n";
    $post_data .= "GENERAL\r\n";
    
    // 添加 magic_prompt
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="magic_prompt"' . "\r\n\r\n";
    $post_data .= "ON\r\n";
    
    // 添加 num_images
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="num_images"' . "\r\n\r\n";
    $post_data .= "1\r\n";
    
    $post_data .= "--{$delimiter}--\r\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Api-Key: ' . $ideogram_config['api_key'],
        'Content-Type: multipart/form-data; boundary=' . $delimiter
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data']) && count($result['data']) > 0) {
            $deployer->log("✅ Ideogram 圖片生成成功");
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("❌ Ideogram API 調用失敗: HTTP {$http_code}");
    if ($response) {
        $deployer->log("API 回應: " . $response);
        $error_data = json_decode($response, true);
        if (isset($error_data['error'])) {
            $deployer->log("錯誤詳情: " . json_encode($error_data['error']));
        } elseif (isset($error_data['message'])) {
            $deployer->log("錯誤訊息: " . $error_data['message']);
        }
    }
    
    return null;
}

function convertToIdeogramAspectRatio($size)
{
    // 預設 1:1
    $aspect_ratio = '1x1';
    
    // 解析尺寸字串 (例如: "1312x736")
    if (preg_match('/(\d+)x(\d+)/', $size, $matches)) {
        $width = intval($matches[1]);
        $height = intval($matches[2]);
        $ratio = $width / $height;
        
        // 根據比例映射到 Ideogram 支援的長寬比
        if (abs($ratio - 16/9) < 0.1) {
            $aspect_ratio = '16x9'; // 16:9 (1312x736, 1920x1080)
        } elseif (abs($ratio - 9/16) < 0.1) {
            $aspect_ratio = '9x16'; // 9:16 (736x1312)
        } elseif (abs($ratio - 4/3) < 0.1) {
            $aspect_ratio = '4x3'; // 4:3 (1152x864)
        } elseif (abs($ratio - 3/4) < 0.1) {
            $aspect_ratio = '3x4'; // 3:4 (864x1152)
        } elseif (abs($ratio - 16/10) < 0.1) {
            $aspect_ratio = '16x10'; // 16:10 (1280x800)
        } elseif (abs($ratio - 10/16) < 0.1) {
            $aspect_ratio = '10x16'; // 10:16 (800x1280)
        } elseif (abs($ratio - 3/2) < 0.1) {
            $aspect_ratio = '3x2'; // 3:2 (1248x832)
        } elseif (abs($ratio - 2/3) < 0.1) {
            $aspect_ratio = '2x3'; // 2:3 (832x1248)
        } elseif (abs($ratio - 1) < 0.1) {
            $aspect_ratio = '1x1'; // 1:1 (1024x1024)
        } else {
            // 未知比例，使用最接近的標準比例
            if ($ratio > 1.6) {
                $aspect_ratio = '16x9'; // 寬圖片
            } elseif ($ratio > 1.2) {
                $aspect_ratio = '4x3'; // 中等寬度
            } elseif ($ratio > 0.8) {
                $aspect_ratio = '1x1'; // 接近正方形
            } else {
                $aspect_ratio = '9x16'; // 長圖片
            }
        }
    }
    
    return $aspect_ratio;
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
    
    // API 配置
    $gemini_config = [
        'api_key' => $config->get('api_credentials.gemini.api_key'),
        'base_url' => $config->get('api_credentials.gemini.base_url') ?: 'https://generativelanguage.googleapis.com'
    ];
    
    $ideogram_config = [
        'api_key' => $config->get('api_credentials.ideogram.api_key')
    ];
    
    echo "🔑 API 憑證檢查:\n";
    echo "Gemini: " . ($gemini_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n";
    echo "Ideogram: " . ($ideogram_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n\n";
    
    if (!$gemini_config['api_key']) {
        echo "❌ 需要設定 Gemini API 金鑰\n";
        echo "請在 config/deploy-config.json 中設定:\n";
        echo "{\n  \"api_credentials\": {\n    \"gemini\": {\n      \"api_key\": \"your-gemini-api-key\"\n    }\n  }\n}\n";
        exit(1);
    }
    
    if (!$ideogram_config['api_key']) {
        echo "❌ 需要設定 Ideogram API 金鑰\n";
        echo "請在 config/deploy-config.json 中設定:\n";
        echo "{\n  \"api_credentials\": {\n    \"ideogram\": {\n      \"api_key\": \"your-ideogram-api-key\"\n    }\n  }\n}\n";
        exit(1);
    }
    
    // 建立測試目錄
    $test_id = date('ymdHi') . '-GEMINI-IDEOGRAM';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_id;
    $images_dir = $work_dir . '/images';
    $json_dir = $work_dir . '/json';
    
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
    }
    if (!is_dir($json_dir)) {
        mkdir($json_dir, 0755, true);
    }
    
    // 模擬用戶資料 (Zion Wu 範例)
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
    
    // 圖片需求 (基於 Zion 專案)
    $image_requirements = [
        'logo' => [
            'title' => 'Company Logo',
            'context' => 'Brand identity for tech consulting company',
            'purpose' => 'website header and brand recognition',
            'size' => '1024x1024',
            'style' => 'logo'
        ],
        'hero_photo' => [
            'title' => 'Founder Portrait',
            'context' => 'Professional image of Zion Wu for homepage',
            'purpose' => 'build trust and personal connection',
            'size' => '1024x1024',
            'style' => 'professional'
        ],
        'hero_bg' => [
            'title' => 'Homepage Background',
            'context' => 'Software architecture theme',
            'purpose' => 'homepage hero section background',
            'size' => '1792x1024',
            'style' => 'abstract'
        ],
        'team_photo' => [
            'title' => 'Team Collaboration',
            'context' => 'Agile development team working together',
            'purpose' => 'showcase teamwork and methodology',
            'size' => '1024x1024',
            'style' => 'professional'
        ]
    ];
    
    echo "🧪 測試案例:\n";
    foreach ($image_requirements as $key => $req) {
        echo "- {$key}: {$req['title']}\n";
    }
    
    echo "\n請選擇要測試的圖片類型 (輸入 key，如 'logo')，或輸入 'all' 測試全部: ";
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
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎨 第一階段: Gemini 生成 Humaaans 風格提示詞\n";
    echo str_repeat("=", 60) . "\n";
    
    // 使用 Gemini 生成提示詞
    $humaaans_prompts = generateHumaaansPromptsWithGemini(
        $selected_requirements,
        $user_data,
        $gemini_config,
        $deployer
    );
    
    if (!$humaaans_prompts) {
        echo "❌ Gemini 提示詞生成失敗\n";
        exit(1);
    }
    
    // 儲存生成的提示詞
    $prompts_file = $json_dir . '/humaaans-prompts.json';
    file_put_contents($prompts_file, json_encode($humaaans_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "\n📝 Gemini 生成的 Humaaans 風格提示詞:\n";
    foreach ($humaaans_prompts as $key => $prompt_data) {
        if (is_array($prompt_data) && isset($prompt_data['prompt'])) {
            echo "\n{$key}:\n";
            echo "  標題: " . ($prompt_data['title'] ?? 'N/A') . "\n";
            echo "  提示詞: " . substr($prompt_data['prompt'], 0, 100) . "...\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🖼️  第二階段: Ideogram 圖片生成\n";
    echo str_repeat("=", 60) . "\n";
    
    $generation_results = [];
    
    foreach ($selected_requirements as $image_key => $requirement) {
        echo "\n🎨 生成圖片: {$requirement['title']}\n";
        
        // 取得對應的提示詞
        $prompt = '';
        if (isset($humaaans_prompts[$image_key]) && is_array($humaaans_prompts[$image_key])) {
            $prompt = $humaaans_prompts[$image_key]['prompt'] ?? '';
        }
        
        if (!$prompt) {
            // 使用預設的 humaaans 風格提示詞
            $prompt = "Flat illustration in humaaans.com style for " . $requirement['title'] . ", simple geometric shapes, clean vector art, professional design";
            echo "⚠️  使用預設提示詞\n";
        }
        
        echo "提示詞: " . substr($prompt, 0, 120) . "...\n";
        
        // 使用 Ideogram 生成圖片
        $image_url = generateImageWithIdeogram(
            $prompt,
            $requirement['size'],
            $requirement['style'],
            $ideogram_config,
            $deployer
        );
        
        $success = false;
        if ($image_url) {
            $filename = $image_key . '_humaaans_ideogram.png';
            $local_path = $images_dir . '/' . $filename;
            $success = downloadImage($image_url, $local_path, $deployer);
            
            if ($success) {
                $file_size = filesize($local_path);
                echo "✅ 圖片生成成功: {$filename} (" . round($file_size/1024/1024, 2) . " MB)\n";
            }
        }
        
        $generation_results[$image_key] = [
            'title' => $requirement['title'],
            'prompt' => $prompt,
            'success' => $success,
            'filename' => $success ? $filename : null
        ];
    }
    
    // 生成測試報告
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 測試結果報告\n";
    echo str_repeat("=", 60) . "\n";
    
    $total_tests = count($generation_results);
    $successful_tests = count(array_filter($generation_results, function($result) {
        return $result['success'];
    }));
    
    echo "\n🎯 整體統計:\n";
    echo "總測試數量: {$total_tests}\n";
    echo "成功生成: {$successful_tests}\n";
    echo "成功率: " . round($successful_tests/$total_tests*100, 1) . "%\n";
    
    echo "\n📋 詳細結果:\n";
    foreach ($generation_results as $key => $result) {
        $status = $result['success'] ? "✅" : "❌";
        echo "{$status} {$result['title']}\n";
        if ($result['success']) {
            echo "    檔案: {$result['filename']}\n";
        }
        echo "    提示詞: " . substr($result['prompt'], 0, 80) . "...\n\n";
    }
    
    echo "📂 輸出檔案位置:\n";
    echo "圖片: {$images_dir}\n";
    echo "提示詞: {$prompts_file}\n";
    
    echo "\n🎨 Humaaans 風格特色:\n";
    echo "✨ 扁平化插圖設計\n";
    echo "👥 友善親和的人物形象\n";
    echo "🎯 統一的視覺語言\n";
    echo "💼 商業應用友好\n";
    echo "🔄 易於客製化修改\n";
    
    echo "\n💡 實際應用建議:\n";
    echo "1. 如果效果良好，可整合到 step-09-5.php\n";
    echo "2. 在配置檔案中加入風格選項\n";
    echo "3. 考慮提供風格切換功能\n";
    echo "4. 建立風格指南文檔\n";
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Gemini + Ideogram + Humaaans 測試完成 ===\n";
?>