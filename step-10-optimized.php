<?php
/**
 * 步驟 10 優化版: 智能圖片生成與替換
 * 優先生成關鍵圖片，並智能復用相似圖片
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

// 載入圖片提示資料
$image_prompts_path = $work_dir . '/json/image-prompts.json';
if (!file_exists($image_prompts_path)) {
    $deployer->log("錯誤: image-prompts.json 不存在，請先執行步驟08");
    return ['status' => 'error', 'message' => 'image-prompts.json 不存在，請先執行步驟08'];
}
$image_prompts = json_decode(file_get_contents($image_prompts_path), true);

$domain = $processed_data['confirmed_data']['domain'];
$deployer->log("開始智能圖片生成與替換: {$domain}");

// 確保 images 目錄存在
$images_dir = $work_dir . '/images';
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}

// 取得 AI API 設定
$openai_config = [
    'api_key' => $config->get('api_credentials.openai.api_key'),
    'model' => 'dall-e-3',
    'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
];

$gemini_config = [
    'api_key' => $config->get('api_credentials.gemini.api_key'),
    'model' => 'imagen-3.0-generate-001',
    'base_url' => $config->get('api_credentials.gemini.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta/models/'
];

// 定義優先生成的關鍵圖片
$priority_images = [
    'logo' => 'high',
    'index_hero_bg' => 'high',
    'index_hero_photo' => 'high',
    'index_about_photo' => 'medium',
    'service_icon_human_design' => 'medium'
];

// 定義圖片復用策略
$image_reuse_strategy = [
    // Hero 背景圖片復用
    'hero_backgrounds' => [
        'master' => 'index_hero_bg',
        'reuse_for' => [
            'about_hero_bg', 
            'service_hero_bg', 
            'blog_hero_bg', 
            'contact_hero_bg',
            'home_hero-bg.jpg',
            'about_hero-bg.jpg',
            'service_hero-bg.jpg',
            'blog_hero-bg.jpg',
            'contact_hero-bg.jpg'
        ]
    ],
    // 個人照片復用
    'profile_photos' => [
        'master' => 'index_hero_photo',
        'reuse_for' => [
            'home_profile-photo.jpg',
            'about_profile-photo.jpg', 
            'service_profile-photo.jpg',
            'blog_profile-photo.jpg',
            'contact_profile-photo.jpg'
        ]
    ],
    // About 照片復用
    'about_photos' => [
        'master' => 'index_about_photo',
        'reuse_for' => [
            'home_about-photo.jpg',
            'about_about-photo.jpg',
            'service_about-photo.jpg',
            'blog_about-photo.jpg',
            'contact_about-photo.jpg'
        ]
    ],
    // Footer 背景復用
    'footer_backgrounds' => [
        'master' => 'index_footer_cta_bg',
        'reuse_for' => [
            'home_footer-bg.jpg',
            'about_footer-bg.jpg',
            'service_footer-bg.jpg',
            'blog_footer-bg.jpg',
            'contact_footer-bg.jpg'
        ]
    ]
];

/**
 * 智能圖片生成策略
 */
function generateImagesWithStrategy($image_prompts, $priority_images, $reuse_strategy, $images_dir, $openai_config, $deployer)
{
    $generated_images = [];
    $reused_images = [];
    
    // 第一階段：生成優先級高的圖片
    $deployer->log("階段 1: 生成關鍵圖片");
    
    foreach ($priority_images as $image_key => $priority) {
        if (isset($image_prompts[$image_key])) {
            $deployer->log("生成優先圖片: {$image_key} (優先級: {$priority})");
            $filename = generateSingleImage($image_key, $image_prompts[$image_key], $images_dir, $openai_config, $deployer);
            
            if ($filename) {
                $generated_images[$image_key] = $filename;
            }
        }
    }
    
    // 第二階段：根據復用策略生成主要圖片
    $deployer->log("階段 2: 生成可復用的主要圖片");
    
    foreach ($reuse_strategy as $category => $strategy) {
        $master_key = $strategy['master'];
        
        if (!isset($generated_images[$master_key]) && isset($image_prompts[$master_key])) {
            $deployer->log("生成主要圖片: {$master_key} (類別: {$category})");
            $filename = generateSingleImage($master_key, $image_prompts[$master_key], $images_dir, $openai_config, $deployer);
            
            if ($filename) {
                $generated_images[$master_key] = $filename;
                
                // 為所有復用的圖片建立符號連結或複製
                foreach ($strategy['reuse_for'] as $reuse_key) {
                    $reused_filename = $reuse_key . getImageExtension($filename);
                    $source_path = $images_dir . '/' . $filename;
                    $target_path = $images_dir . '/' . $reused_filename;
                    
                    if (copy($source_path, $target_path)) {
                        $reused_images[$reuse_key] = $reused_filename;
                        $deployer->log("複製圖片: {$master_key} → {$reuse_key}");
                    }
                }
            }
        }
    }
    
    // 第三階段：生成剩餘的獨特圖片（如有需要）
    $deployer->log("階段 3: 生成剩餘獨特圖片");
    
    $remaining_images = ['service_icon_online_course', 'service_icon_energy_workshop'];
    
    foreach ($remaining_images as $image_key) {
        if (isset($image_prompts[$image_key])) {
            $deployer->log("生成獨特圖片: {$image_key}");
            $filename = generateSingleImage($image_key, $image_prompts[$image_key], $images_dir, $openai_config, $deployer);
            
            if ($filename) {
                $generated_images[$image_key] = $filename;
            }
        }
    }
    
    return array_merge($generated_images, $reused_images);
}

/**
 * 生成單張圖片
 */
function generateSingleImage($image_key, $image_config, $images_dir, $openai_config, $deployer)
{
    $ai_service = $image_config['ai'] ?? 'openai';  // 讀取 AI 參數
    $style = $image_config['style'] ?? 'natural';
    $quality = $image_config['quality'] ?? 'standard';
    $size = $image_config['size'] ?? '1024x1024';
    
    $prompt = $image_config['prompt'];
    if (isset($image_config['extra'])) {
        $prompt .= ', ' . $image_config['extra'];
    }
    
    $deployer->log("生成: {$image_key} (使用 {$ai_service})");
    $deployer->log("提示詞: " . substr($prompt, 0, 100) . "...");
    
    try {
        // Logo 固定用 OpenAI，其他圖片優先用 Gemini
        if ($image_key === 'logo' || $ai_service === 'openai') {
            $image_url = generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer);
        } elseif ($ai_service === 'gemini') {
            // 使用全域 Gemini 配置
            global $config;
            $gemini_config = [
                'api_key' => $config->get('api_credentials.gemini.api_key'),
                'model' => 'gemini-2.0-flash-preview-image-generation',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models/'
            ];
            $image_url = generateImageWithGemini($prompt, $quality, $size, $gemini_config, $deployer);
            
            // 如果 Gemini 失敗，降級到 OpenAI
            if (!$image_url) {
                $deployer->log("🔄 Gemini 失敗，降級使用 OpenAI");
                $image_url = generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer);
            }
        } else {
            $deployer->log("⚠️ 未知的 AI 服務: {$ai_service}，使用預設 OpenAI");
            $image_url = generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer);
        }
        
        if ($image_url) {
            // 下載圖片並儲存
            $image_filename = $image_key . '.png';
            $local_path = $images_dir . '/' . $image_filename;
            
            // 檢查是否為 base64 資料 URL（Gemini 回傳格式）
            if (strpos($image_url, 'data:image/') === 0) {
                // 處理 base64 資料
                if (downloadImageFromBase64($image_url, $local_path, $deployer)) {
                    $deployer->log("✅ 圖片生成成功: {$image_filename}");
                    return $image_filename;
                }
            } else {
                // 處理 URL（OpenAI 回傳格式）
                if (downloadImageFromUrl($image_url, $local_path, $deployer)) {
                    $deployer->log("✅ 圖片生成成功: {$image_filename}");
                    return $image_filename;
                }
            }
        }
        
        $deployer->log("❌ 圖片生成失敗: {$image_key}");
        return null;
        
    } catch (Exception $e) {
        $deployer->log("❌ 圖片生成異常: {$image_key} - " . $e->getMessage());
        return null;
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
    
    $deployer->log("OpenAI 圖片 API 錯誤: HTTP {$http_code}");
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
    
    // 使用新的 Gemini 2.0 Flash Preview 圖片生成 API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent";
    
    $deployer->log("Gemini 圖片生成: {$size}");
    
    // 構建請求資料（使用新的 generateContent 格式）
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'responseModalities' => ['TEXT', 'IMAGE']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $gemini_config['api_key']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        
        // 檢查回應結構
        if (isset($result['candidates'][0]['content']['parts'])) {
            $parts = $result['candidates'][0]['content']['parts'];
            
            // 尋找圖片資料
            foreach ($parts as $part) {
                if (isset($part['inlineData']['data'])) {
                    $base64_data = $part['inlineData']['data'];
                    $deployer->log("✅ Gemini 圖片生成成功");
                    
                    // 返回 base64 資料 URL
                    return 'data:image/png;base64,' . $base64_data;
                }
            }
        }
        
        $deployer->log("❌ Gemini 回應中未找到圖片資料");
        $deployer->log("回應結構: " . substr(json_encode($result), 0, 200));
    } else {
        $deployer->log("❌ Gemini 圖片 API 錯誤: HTTP {$http_code}");
        if ($response) {
            $error_data = json_decode($response, true);
            if (isset($error_data['error']['message'])) {
                $deployer->log("錯誤詳情: " . $error_data['error']['message']);
            } else {
                $deployer->log("完整回應: " . substr($response, 0, 500));
            }
        }
    }
    
    return null;
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
 * 從 base64 資料 URL 儲存圖片
 */
function downloadImageFromBase64($data_url, $local_path, $deployer)
{
    try {
        // 解析 data URL: data:image/png;base64,iVBORw0KGgo...
        if (preg_match('/^data:image\/[^;]+;base64,(.+)$/', $data_url, $matches)) {
            $base64_data = $matches[1];
            $image_data = base64_decode($base64_data);
            
            if ($image_data === false) {
                $deployer->log("Base64 解碼失敗");
                return false;
            }
            
            // 儲存圖片
            if (file_put_contents($local_path, $image_data)) {
                $file_size = strlen($image_data);
                $deployer->log("Base64 圖片儲存成功: " . basename($local_path) . " (" . formatFileSize($file_size) . ")");
                return true;
            } else {
                $deployer->log("檔案寫入失敗: {$local_path}");
                return false;
            }
        } else {
            $deployer->log("無效的 base64 資料 URL 格式");
            return false;
        }
        
    } catch (Exception $e) {
        $deployer->log("Base64 圖片處理異常: " . $e->getMessage());
        return false;
    }
}

/**
 * 下載圖片並儲存到本地
 */
function downloadImageFromUrl($image_url, $local_path, $deployer)
{
    try {
        if (strpos($image_url, 'data:image') === 0) {
            // 處理 base64 編碼的圖片 (Gemini 格式)
            $base64_data = explode(',', $image_url)[1];
            $image_data = base64_decode($base64_data);
            $deployer->log("處理 base64 圖片資料");
        } else {
            // 從 URL 下載圖片 (OpenAI 格式)
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $image_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $image_data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200 || !$image_data) {
                $deployer->log("圖片下載失敗: HTTP {$http_code}");
                return false;
            }
        }
        
        // 儲存圖片
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

/**
 * 建立圖片路徑對應表
 */
function buildImageMappings($generated_images, $target_domain)
{
    $mappings = [];
    $base_new_path = "/wp-content/uploads/ai-generated";
    $base_new_url = "https://www.{$target_domain}{$base_new_path}";
    
    // 舊的圖片路徑模式
    $old_patterns = [
        'https://www.hsinnyu.tw/wp-content/uploads/2025/06/Gemini_Generated_Image_tdt8w0tdt8w0tdt8-scaled.jpeg',
        'https://www.hsinnyu.tw/wp-content/uploads/2025/06/6.png',
        'https://www.hsinnyu.tw/wp-content/uploads/2025/06/2.png'
    ];
    
    foreach ($generated_images as $image_key => $filename) {
        $new_url = "{$base_new_url}/{$filename}";
        $new_path = "{$base_new_path}/{$filename}";
        
        // 根據圖片類型建立對應關係
        switch (true) {
            case $image_key === 'logo':
                $mappings['/wp-content/uploads/2025/06/logo.png'] = $new_path;
                break;
                
            case strpos($image_key, 'hero_bg') !== false || strpos($image_key, 'hero-bg') !== false:
                // 所有 hero 背景圖片
                foreach ($old_patterns as $old_url) {
                    $mappings[$old_url] = $new_url;
                }
                break;
                
            case strpos($image_key, 'hero_photo') !== false || strpos($image_key, 'profile-photo') !== false:
                // 個人照片
                $mappings['https://www.hsinnyu.tw/wp-content/uploads/2025/06/6.png'] = $new_url;
                break;
                
            case strpos($image_key, 'about_photo') !== false || strpos($image_key, 'about-photo') !== false:
                // About 照片
                $mappings['https://www.hsinnyu.tw/wp-content/uploads/2025/06/2.png'] = $new_url;
                break;
        }
    }
    
    return $mappings;
}

/**
 * 替換檔案中的圖片路徑
 */
function replaceImagePaths($file_path, $image_mappings, $deployer)
{
    if (!file_exists($file_path)) {
        $deployer->log("檔案不存在: {$file_path}");
        return false;
    }
    
    $content = file_get_contents($file_path);
    $original_content = $content;
    $replacements_made = 0;
    
    foreach ($image_mappings as $old_path => $new_path) {
        $old_path_escaped = str_replace('/', '\/', $old_path);
        if (strpos($content, $old_path) !== false) {
            $content = str_replace($old_path, $new_path, $content);
            $replacements_made++;
            $deployer->log("替換路徑: " . basename($old_path) . " → " . basename($new_path));
        }
    }
    
    if ($replacements_made > 0) {
        file_put_contents($file_path, $content);
        $deployer->log("✅ 檔案更新完成: " . basename($file_path) . " ({$replacements_made} 項替換)");
        return true;
    }
    
    $deployer->log("檔案無需更新: " . basename($file_path));
    return false;
}

/**
 * 工具函數
 */
function getImageExtension($filename)
{
    if (strpos($filename, '.png') !== false) return '.png';
    if (strpos($filename, '.jpg') !== false || strpos($filename, '.jpeg') !== false) return '.jpg';
    return '.png';
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

if (!function_exists('formatFileSize')) {
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
}

// 主要處理流程
try {
    if (!$openai_config['api_key']) {
        throw new Exception("OpenAI API 金鑰未設定");
    }
    
    $deployer->log("開始智能圖片生成流程");
    $deployer->log("總圖片數量: " . count($image_prompts) . " 張");
    $deployer->log("優先生成: " . count($priority_images) . " 張關鍵圖片");
    
    // 使用智能策略生成圖片
    $all_generated_images = generateImagesWithStrategy(
        $image_prompts, 
        $priority_images, 
        $image_reuse_strategy, 
        $images_dir, 
        $openai_config, 
        $deployer
    );
    
    $deployer->log("圖片生成/複製完成: " . count($all_generated_images) . " 張");
    
    if (!empty($all_generated_images)) {
        // 建立圖片路徑對應表
        $image_mappings = buildImageMappings($all_generated_images, $domain);
        $deployer->log("建立路徑對應表: " . count($image_mappings) . " 項對應");
        
        // 替換 site-config.json 中的圖片路徑
        $site_config_path = $work_dir . '/json/site-config.json';
        replaceImagePaths($site_config_path, $image_mappings, $deployer);
        
        // 替換 layout 目錄中所有 *-ai.json 檔案的圖片路徑
        $layout_dir = $work_dir . '/layout';
        if (is_dir($layout_dir)) {
            $files = scandir($layout_dir);
            foreach ($files as $file) {
                if (preg_match('/-ai\.json$/', $file)) {
                    $file_path = $layout_dir . '/' . $file;
                    replaceImagePaths($file_path, $image_mappings, $deployer);
                }
            }
        }
        
        $deployer->log("✅ 圖片路徑替換完成");
    }
    
    // 生成圖片清單報告
    $image_report = [
        'total_prompts' => count($image_prompts),
        'generated_count' => count($all_generated_images),
        'generated_images' => $all_generated_images,
        'images_directory' => $images_dir,
        'strategy_used' => 'smart_generation_with_reuse',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/images/generation-report.json', json_encode($image_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("圖片生成報告儲存完成");
    $deployer->log("檔案儲存位置: {$images_dir}");
    
    return [
        'status' => 'success',
        'generated_count' => count($all_generated_images),
        'images_dir' => $images_dir,
        'total_prompts' => count($image_prompts)
    ];
    
} catch (Exception $e) {
    $deployer->log("圖片生成失敗: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}