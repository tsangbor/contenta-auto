<?php
/**
 * 步驟 16: AI Logo 生成 (v2.0 - Luke API版本)
 * 
 * 核心職責：使用AI模型生成背景圖示，結合PHP GD文字圖層，合併生成專業品牌Logo
 * 專為Luke API設計，使用SSH連接上傳Logo到遠端WordPress
 * 
 * 執行工作流：
 * 1. 載入job配置和色彩方案
 * 2. 使用AI模型生成750x200的背景透明小圖示
 * 3. 用PHP GD創建文字圖層(primary色彩)
 * 4. 將背景圖示與文字圖層合併
 * 5. 儲存最終logo到temp/{job_id}/images目錄
 * 6. 透過SSH上傳logo到遠端WordPress伺服器
 * 
 * @package Contenta
 * @version 2.0
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("開始執行步驟 16: AI Logo 生成 (v2.0 - Luke API版本)");

try {
    // 1. 載入job配置以取得色彩方案
    $deployer->log("載入job配置和色彩方案");
    
    // Luke API 版本優先檢查 luke_config.json
    $job_data_file = null;
    $luke_config_file = $work_dir . '/luke_config.json';
    
    if (file_exists($luke_config_file)) {
        $job_data_file = $luke_config_file;
        $deployer->log("使用 Luke API 配置檔案: $job_data_file");
    } else {
        // 支援新的目錄結構：data/{job_id}/{job_id}.json
        $job_dir = DEPLOY_BASE_PATH . '/data/' . $job_id;
        $job_data_file = $job_dir . '/' . $job_id . '.json';
        
        // 檢查新的檔案位置
        if (!file_exists($job_data_file)) {
            // 向後相容：檢查舊的檔案位置
            $old_job_data_file = DEPLOY_BASE_PATH . '/data/' . $job_id . '.json';
            if (file_exists($old_job_data_file)) {
                $job_data_file = $old_job_data_file;
                $deployer->log("使用舊位置的 job 檔案: $job_data_file");
            } else {
                throw new Exception("Job配置檔案不存在: $job_data_file (也檢查了 $old_job_data_file)");
            }
        } else {
            $deployer->log("使用新位置的 job 檔案: $job_data_file");
        }
    }
    
    $job_data = json_decode(file_get_contents($job_data_file), true);
    if (!$job_data) {
        throw new Exception("無法解析job配置檔案");
    }
    
    // 取得色彩方案和網站名稱
    $color_scheme = $job_data['confirmed_data']['color_scheme'] ?? [];
    $full_website_name = $job_data['confirmed_data']['website_name'] ?? $domain;
    
    // 提取主要品牌名稱（取 " - " 前的部分，如果沒有則使用全名）
    $website_name = strpos($full_website_name, ' - ') !== false 
        ? trim(explode(' - ', $full_website_name)[0]) 
        : $full_website_name;
    
    $primary_color = $color_scheme['primary'] ?? '#2D4C4A';
    $secondary_color = $color_scheme['secondary'] ?? '#7A8370';
    
    $deployer->log("網站名稱: $website_name");
    $deployer->log("主色彩: $primary_color");
    $deployer->log("次色彩: $secondary_color");
    
    // 2. 檢查PHP GD擴展
    if (!extension_loaded('gd')) {
        throw new Exception("PHP GD擴展未安裝，無法生成圖片");
    }
    
    // 3. 檢查字體檔案
    $font_file = DEPLOY_BASE_PATH . '/logo/font/PottaOne-Regular.ttf';
    if (!file_exists($font_file)) {
        throw new Exception("字體檔案不存在: $font_file");
    }
    
    // 4. 建立images目錄
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
        $deployer->log("建立images目錄: $images_dir");
    }
    
    // 5. 取得 Logo 生成模式設定
    $logo_mode = $config->get('logo_generation.mode', 'ai_full');
    $deployer->log("Logo 生成模式: $logo_mode");
    
    $final_logo_path = null;
    $ai_logo_path = null;
    $background_image_path = null;
    $text_layer_path = null;
    
    if ($logo_mode === 'ai_full') {
        // 完全由 AI 生成 Logo
        $deployer->log("使用 AI 完整生成 Logo");
        $ai_logo_path = generateFullAILogo($website_name, $color_scheme, $job_data, $images_dir, $deployer);
        
        if (!$ai_logo_path) {
            $deployer->log("AI Logo 生成失敗，回退到 PHP 合成模式");
            $logo_mode = 'php_composite';
        }
    }
    
    if ($logo_mode === 'php_composite') {
        // PHP GD 合成模式
        $deployer->log("使用 PHP GD 合成模式生成 Logo");
        
        // 5.1 使用AI生成背景圖示
        $deployer->log("使用AI生成背景圖示 (750x200)");
        $background_image_path = generateBackgroundWithAI($full_website_name, $secondary_color, $job_data, $images_dir, $deployer);
        
        if (!$background_image_path) {
            $deployer->log("AI背景生成失敗，使用純色背景");
            $background_image_path = createSolidBackground($images_dir, $deployer);
        }
        
        // 5.2 生成文字圖層
        $deployer->log("生成文字圖層");
        $accent_color = $color_scheme['accent'] ?? '#BFAA96';
        $text_layer_path = generateTextLayer($website_name, $accent_color, $font_file, $images_dir, $deployer);
        
        if (!$text_layer_path) {
            throw new Exception("文字圖層生成失敗");
        }
        
        // 5.3 合併背景與文字圖層
        $deployer->log("合併背景與文字圖層");
        $final_logo_path = mergeLogoLayers($background_image_path, $text_layer_path, $images_dir, $deployer);
        
        if (!$final_logo_path) {
            throw new Exception("圖層合併失敗");
        }
    }
    
    // 6. 儲存步驟結果
    $primary_logo = $ai_logo_path ?: $final_logo_path;
    $logo_info = [];
    
    if ($ai_logo_path) {
        $logo_info = [
            'type' => 'AI 完整生成',
            'path' => $ai_logo_path,
            'url' => str_replace($work_dir, "/temp/$job_id", $ai_logo_path),
            'filename' => basename($ai_logo_path),
            'mode' => 'ai_full'
        ];
    } elseif ($final_logo_path) {
        $logo_info = [
            'type' => 'PHP GD 合成',
            'path' => $final_logo_path,
            'url' => str_replace($work_dir, "/temp/$job_id", $final_logo_path),
            'filename' => basename($final_logo_path),
            'mode' => 'php_composite',
            'components' => [
                'background' => $background_image_path ? basename($background_image_path) : null,
                'text_layer' => $text_layer_path ? basename($text_layer_path) : null
            ]
        ];
    }
    
    $step_result = [
        'step' => '16',
        'title' => 'AI Logo 生成',
        'status' => $primary_logo ? 'success' : 'error',
        'message' => $primary_logo ? "成功生成 Logo ({$logo_info['type']})" : "Logo 生成失敗",
        'logo_generation_mode' => $logo_mode,
        'primary_logo_path' => $primary_logo,
        'primary_logo_url' => $primary_logo ? str_replace($work_dir, "/temp/$job_id", $primary_logo) : null,
        'logo' => $logo_info,
        'website_name' => $website_name,
        'colors_used' => [
            'primary' => $primary_color,
            'secondary' => $secondary_color,
            'accent' => $color_scheme['accent'] ?? '#BFAA96',
            'text_color' => $color_scheme['accent'] ?? '#BFAA96'
        ],
        'dimensions' => '540x210',
        'format' => 'PNG',
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/step-16-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("步驟 16: AI Logo 生成 - 完成");
    $deployer->log("Logo檔案: " . ($primary_logo ? basename($primary_logo) : '無'));
    
    // 9. 將logo設定為WordPress網站logo（優先使用合成版本）
    $deployer->log("設定WordPress網站logo...");
    $logo_for_wp = $final_logo_path ?: $ai_logo_path; // 優先使用合成版本，如果失敗則使用AI版本
    $logo_upload_result = uploadAndSetWordPressLogo($logo_for_wp, $job_id, $config, $deployer);
    
    if ($logo_upload_result['success']) {
        $deployer->log("WordPress logo設定成功");
        $step_result['wordpress_logo_id'] = $logo_upload_result['attachment_id'];
        $step_result['wordpress_logo_url'] = $logo_upload_result['logo_url'];
        $step_result['wordpress_path'] = $logo_upload_result['wordpress_path'];
        $step_result['target_domain'] = $logo_upload_result['domain'];
        $step_result['upload_mode'] = $logo_upload_result['mode'];
    } else {
        $deployer->log("WordPress logo設定失敗: " . $logo_upload_result['error']);
    }
    
    // 更新結果檔案
    file_put_contents($work_dir . '/step-16-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return ['status' => 'success', 'result' => $step_result];
    
} catch (Exception $e) {
    $deployer->log("步驟 16 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}

/**
 * 生成完全由AI生成的Logo
 */
function generateFullAILogo($website_name, $color_scheme, $job_data, $images_dir, $deployer)
{
    try {
        // 載入部署配置
        $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
        if (!file_exists($deploy_config_file)) {
            throw new Exception("部署配置檔案不存在");
        }
        
        $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
        
        // 取得ConfigManager實例
        require_once DEPLOY_BASE_PATH . '/config-manager.php';
        $config = ConfigManager::getInstance();
        
        // 取得AI圖片生成設定
        $fallback_order = $config->get('ai_image_generation.fallback_order', ['openai', 'ideogram', 'gemini']);
        $api_credentials = $deploy_config['api_credentials'] ?? [];
        
        $deployer->log("使用圖片生成服務順序: " . implode(' → ', $fallback_order));
        
        // 從 job_data 中提取實際的網域名稱（移除.tw等後綴）
        $confirmed_data = $job_data['confirmed_data'] ?? [];
        $domain = $confirmed_data['domain'] ?? $website_name;
        $domain_name = preg_replace('/\.(tw|com|org|net|info|biz)$/i', '', $domain);
        
        $deployer->log("提取的網域名稱: $domain → $domain_name");
        
        // 階段1：建立基礎描述提示詞
        $base_prompt = buildFullLogoPrompt($website_name, $color_scheme, $job_data);
        //$deployer->log("基礎提示詞:\n" . $base_prompt);
        
        // 階段2：使用Gemini優化提示詞
        $optimized_prompt = null;
        if (isset($api_credentials['gemini']['api_key']) && !empty($api_credentials['gemini']['api_key'])) {
            //$deployer->log("使用Gemini優化提示詞...");
            $optimized_prompt = optimizePromptWithGemini($base_prompt, $api_credentials['gemini'], $deployer);
            
            if ($optimized_prompt) {
                $deployer->log("Gemini優化後的提示詞:\n" . $optimized_prompt);
            } else {
                $deployer->log("Gemini優化失敗，使用原始提示詞");
                $optimized_prompt = $base_prompt;
            }
        } else {
            $deployer->log("Gemini API未配置，使用原始提示詞");
            $optimized_prompt = $base_prompt;
        }
        
        // 階段3：使用Ideogram生成圖片
        $image_data = null;
        if (isset($api_credentials['ideogram']['api_key']) && !empty($api_credentials['ideogram']['api_key'])) {
            $deployer->log("使用Ideogram生成Logo...");
            $image_data = callIdeogramImageGeneration($optimized_prompt, $api_credentials['ideogram'], $deployer);
        }
        
        // 備用方案：如果Ideogram失敗，嘗試其他服務
        if (!$image_data) {
            $deployer->log("Ideogram失敗，嘗試備用方案...");
            foreach ($fallback_order as $service) {
                if ($image_data) break;
                
                switch ($service) {
                    case 'openai':
                        if (isset($api_credentials['openai']['api_key']) && !empty($api_credentials['openai']['api_key'])) {
                            $deployer->log("嘗試使用 OpenAI");
                            $image_data = callOpenAIImageGeneration($optimized_prompt, $api_credentials['openai'], $deployer);
                        }
                        break;
                        
                    case 'gemini':
                        if ($service !== 'ideogram' && isset($api_credentials['gemini']['api_key']) && !empty($api_credentials['gemini']['api_key'])) {
                            $deployer->log("嘗試使用 Gemini");
                            $image_data = callGeminiImageGeneration($optimized_prompt, $api_credentials['gemini'], $deployer);
                        }
                        break;
                }
            }
        }
        
        if (!$image_data) {
            throw new Exception("所有AI圖片生成服務都失敗");
        }
        
        // 儲存AI Logo
        $ai_logo_filename = 'ai-logo-full.png';
        $ai_logo_path = $images_dir . '/' . $ai_logo_filename;
        
        if (!saveImageData($image_data, $ai_logo_path, $deployer)) {
            throw new Exception("無法儲存AI Logo");
        }
        
        // 使用 rembg 進行去背處理
        $deployer->log("使用 rembg 進行去背處理...");
        $ai_logo_rembg_filename = 'ai-logo-full-remover.png';
        $ai_logo_rembg_path = $images_dir . '/' . $ai_logo_rembg_filename;
        
        $rembg_command = "/bin/zsh -c 'source ~/.zshrc && rembg i -m isnet-general-use " . escapeshellarg($ai_logo_path) . " " . escapeshellarg($ai_logo_rembg_path) . "'";
        exec($rembg_command . " 2>&1", $output, $return_var);
        
        if ($return_var !== 0) {
            $deployer->log("rembg 去背失敗: " . implode("\n", $output));
            $deployer->log("將使用原始圖片繼續處理");
            $logo_to_resize = $ai_logo_path;
        } else {
            $deployer->log("去背處理成功: $ai_logo_rembg_filename");
            // 檢查去背後的檔案是否存在
            if (file_exists($ai_logo_rembg_path)) {
                $logo_to_resize = $ai_logo_rembg_path;
                // 顯示檔案大小
                $file_size = formatFileSize(filesize($ai_logo_rembg_path));
                $deployer->log("去背後檔案大小: $file_size");
            } else {
                $deployer->log("去背檔案不存在，使用原始圖片");
                $logo_to_resize = $ai_logo_path;
            }
        }
        
        // 調整圖片尺寸為540x210
        $resized_path = resizeImageTo540x210($logo_to_resize, $images_dir, $deployer);
        
        // 重新命名調整後的檔案
        if ($resized_path) {
            $ai_logo_resized_filename = 'ai-logo-full-resized.png';
            $ai_logo_resized_path = $images_dir . '/' . $ai_logo_resized_filename;
            if (rename($resized_path, $ai_logo_resized_path)) {
                $resized_path = $ai_logo_resized_path;
            }
            
            // 檢測實際內容邊界
            $bounds = getImageBounds($resized_path);
            if ($bounds) {
                $totalPixels = 540 * 210;
                $contentPixels = $bounds['width'] * $bounds['height'];
                $occupancyRatio = round(($contentPixels / $totalPixels) * 100, 2);
                
                $deployer->log("Logo 實際內容區域: {$bounds['width']}x{$bounds['height']}");
                $deployer->log("Logo 內容占用比例: {$occupancyRatio}%");
                
                // 如果占用比例小於 60%，進行智能調整
                if ($occupancyRatio < 60) {
                    $deployer->log("Logo 內容偏小，進行智能調整...");
                    $smart_resized_path = smartResizeLogo($resized_path, $bounds, $images_dir, $deployer);
                    
                    if ($smart_resized_path) {
                        // 刪除原始調整後的檔案
                        @unlink($resized_path);
                        $resized_path = $smart_resized_path;
                        
                        // 再次檢測調整後的占用比例
                        $new_bounds = getImageBounds($resized_path);
                        if ($new_bounds) {
                            $new_occupancy = round(($new_bounds['width'] * $new_bounds['height'] / $totalPixels) * 100, 2);
                            $deployer->log("調整後的占用比例: {$new_occupancy}%");
                        }
                    }
                }
            }
        }
        
        if ($resized_path) {
            $file_size = formatFileSize(filesize($resized_path));
            $deployer->log("AI完整Logo生成成功: " . basename($resized_path) . " ($file_size)");
            return $resized_path;
        }
        
        return $ai_logo_path; // 如果調整失敗，返回原圖
        
    } catch (Exception $e) {
        $deployer->log("AI Logo生成失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 建立完整Logo的AI提示詞
 */
function buildFullLogoPrompt($website_name, $color_scheme, $job_data)
{
    $primary_color = $color_scheme['primary'] ?? '#2D4C4A';
    $secondary_color = $color_scheme['secondary'] ?? '#7A8370';
    
    // 取得網站描述或行業類型
    $confirmed_data = $job_data['confirmed_data'] ?? [];
    $website_description = $confirmed_data['website_description'] ?? '';
    $brand_keywords = $confirmed_data['brand_keywords'] ?? [];
    $business_type = $confirmed_data['business_type'] ?? '';
    $brand_personality = $confirmed_data['brand_personality'] ?? '';
    
    $keywords_text = is_array($brand_keywords) ? implode(', ', $brand_keywords) : $brand_keywords;
    
    // 從 domain 欄位提取網域名稱（移除.tw等後綴）
    $domain = $confirmed_data['domain'] ?? $website_name;
    $domain_name = preg_replace('/\.(tw|com|org|net|info|biz)$/i', '', $domain);
    
    // 建立一個簡化且更具體的基礎提示詞，讓 Gemini 依據品牌特性生成合適的 logo
    $prompt = "Create a logo design prompt for the brand name '{$domain_name}' following this style template:

Reference template:
'A modern logo design featuring the word \"grow\" rendered in a flowing, rounded typeface with smooth organic curves that create a natural, approachable feel. The font has a subtle upward motion blur effect. The letters are a vibrant gradient of forest green to sky blue, suggesting upward movement and vitality. Below the text, a single stylized leaf subtly emerges from the base of the letter \"o\", further reinforcing the theme of growth. The background is a clean white, allowing the logo to pop and maintain a professional, minimalist aesthetic.'

Brand information to incorporate:
- Brand name: {$domain_name}
- Business type: {$business_type}
- Primary color: {$primary_color}
- Secondary color: {$secondary_color}
- Brand keywords: {$keywords_text}
- Brand personality: {$brand_personality}
- Description: " . mb_substr($website_description, 0, 300, 'UTF-8') . "

REQUIREMENTS - Follow the exact 5-part structure:

1. \"A modern logo design featuring the word '{$domain_name}' rendered in a flowing, rounded typeface with smooth organic curves that create a [feeling] feel.\"

2. \"The font has a subtle [effect] effect\" - choose effects like: gentle flow, knowledge ripple, insight wave, progress motion, trust glow

3. \"The letters are a vibrant gradient from {$primary_color} to {$secondary_color}, suggesting [meaning]\"

4. \"[Position], a single stylized [simple_symbol] subtly emerges from [letter_location], further reinforcing the theme of [concept]\"
   - Use ONLY simple symbols: dot, star, line, accent mark, point, node, spark, gentle curve
   - Think Font Awesome simplicity - basic geometric shapes only

5. \"The background is a clean white, allowing the logo to pop and maintain a professional, minimalist aesthetic. Canvas size: 540x210 pixels.\"

CRITICAL: Keep symbols extremely simple - dots, stars, lines, basic geometric shapes only. Avoid complex technical graphics.";

    return $prompt;
}

/**
 * 使用Gemini優化提示詞
 */
function optimizePromptWithGemini($base_prompt, $gemini_config, $deployer)
{
    try {
        $api_key = $gemini_config['api_key'] ?? '';
        if (empty($api_key)) {
            throw new Exception("Gemini API金鑰未設定");
        }
        
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $api_key;
        
        $optimization_instruction = "You are a professional logo designer. Your task is to generate a logo design prompt based on the brand information provided. 

First, here is the brand information and requirements:
{$base_prompt}

Now create a logo design prompt that follows this exact format:

\"A modern logo design featuring the word '[brand_name]' rendered in a flowing, rounded typeface with smooth organic curves that create a [feeling] feel. The font has a subtle [effect] effect. The letters are a vibrant gradient from [primary_color] to [secondary_color], suggesting [meaning]. [Position], a single stylized [symbol] subtly emerges from [letter_location], further reinforcing the theme of [concept]. The background is a clean white, allowing the logo to pop and maintain a professional, minimalist aesthetic. Canvas size: 540x210 pixels.\"

Replace the placeholders based on the brand information provided. Be creative but maintain the exact sentence structure. The result should be 2-3 sentences that paint a vivid picture of the logo.

Provide ONLY the final prompt, no explanations.";
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $optimization_instruction
                        ]
                    ]
                ]
            ]
        ];
        
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $deployer->log("JSON編碼錯誤: " . json_last_error_msg());
            return null;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $optimized = trim($result['candidates'][0]['content']['parts'][0]['text']);
                // 移除可能的引號或格式化字符
                $optimized = trim($optimized, '"\'`');
                return $optimized;
            }
        }
        
        $deployer->log("Gemini優化失敗: HTTP $http_code");
        if ($response) {
            $deployer->log("Gemini錯誤回應: " . substr($response, 0, 200));
        }
        return null;
        
    } catch (Exception $e) {
        $deployer->log("Gemini優化錯誤: " . $e->getMessage());
        return null;
    }
}

/**
 * 根據業務類型和描述選擇適合的圖形元素
 */
function getLogoGraphicElements($business_type, $description, $brand_tone)
{
    // 預設元素（通用商業風格）
    $default_elements = ['幾何圖形', '簡約線條', '現代符號'];
    
    // 根據業務類型選擇元素
    $type_elements = [
        '科技' => ['電路', '數據流', '連接線', '未來感圖形'],
        '醫療' => ['十字', '心形', '生命樹', '守護符號'],
        '教育' => ['書本', '知識樹', '燈泡', '成長曲線'],
        '餐飲' => ['葉子', '天然元素', '溫暖曲線', '美食符號'],
        '金融' => ['穩定圖形', '增長箭頭', '安全盾牌', '平衡元素'],
        '服務' => ['人形', '握手', '服務圖標', '連接元素'],
        '零售' => ['購物元素', '產品圖形', '商業符號', '流通線條'],
        '製造' => ['齒輪', '工業元素', '製程線條', '精密圖形'],
        '娛樂' => ['動態元素', '創意圖形', '娛樂符號', '活力線條'],
        '運動' => ['動感線條', '力量符號', '運動元素', '活力圖形']
    ];
    
    // 根據品牌調性調整
    $tone_elements = [
        '專業' => ['商業圖形', '穩重線條', '權威符號'],
        '創新' => ['創意圖形', '突破線條', '未來元素'],
        '親和' => ['溫暖曲線', '親近符號', '友善元素'],
        '豪華' => ['精緻圖形', '優雅線條', '高端元素'],
        '年輕' => ['活力線條', '動感圖形', '青春元素'],
        '傳統' => ['經典圖形', '傳統符號', '文化元素'],
        '環保' => ['自然元素', '生態符號', '綠色圖形'],
        '神祕' => ['星星', '橋', '溪流', '能量流動'],
        '療癒' => ['治癒符號', '和諧圖形', '寧靜元素']
    ];
    
    $selected_elements = $default_elements;
    
    // 檢查業務類型
    foreach ($type_elements as $type => $elements) {
        if (stripos($business_type, $type) !== false || stripos($description, $type) !== false) {
            $selected_elements = array_merge($selected_elements, $elements);
            break;
        }
    }
    
    // 檢查品牌調性
    foreach ($tone_elements as $tone => $elements) {
        if (stripos($brand_tone, $tone) !== false || stripos($description, $tone) !== false) {
            $selected_elements = array_merge($selected_elements, $elements);
            break;
        }
    }
    
    // 隨機選擇 3-4 個元素
    $selected_elements = array_unique($selected_elements);
    shuffle($selected_elements);
    $final_elements = array_slice($selected_elements, 0, rand(3, 4));
    
    return implode('、', $final_elements);
}

/**
 * 根據業務類型選擇風格描述
 */
function getLogoStyleDescription($business_type, $brand_tone)
{
    // 預設風格
    $default_style = '專業簡約';
    
    // 根據業務類型的風格
    $type_styles = [
        '科技' => '現代科技',
        '醫療' => '專業可靠',
        '教育' => '知識啟發',
        '餐飲' => '溫暖親和',
        '金融' => '穩重信賴',
        '服務' => '親切專業',
        '零售' => '時尚商業',
        '製造' => '工業精準',
        '娛樂' => '活潑創意',
        '運動' => '動感活力'
    ];
    
    // 根據品牌調性的風格
    $tone_styles = [
        '專業' => '商務專業',
        '創新' => '創新前衛',
        '親和' => '溫馨親和',
        '豪華' => '奢華精緻',
        '年輕' => '青春活力',
        '傳統' => '經典穩重',
        '環保' => '自然環保',
        '神祕' => '神祕療癒',
        '療癒' => '溫和治癒'
    ];
    
    // 檢查品牌調性優先
    foreach ($tone_styles as $tone => $style) {
        if (stripos($brand_tone, $tone) !== false) {
            return $style;
        }
    }
    
    // 檢查業務類型
    foreach ($type_styles as $type => $style) {
        if (stripos($business_type, $type) !== false) {
            return $style;
        }
    }
    
    return $default_style;
}

/**
 * 根據行業獲取特定要求
 */
function getIndustryRequirements($business_type)
{
    $industry_requirements = [
        '科技' => '體現技術創新和數位化特色。',
        '醫療' => '傳達專業醫療和健康關懷理念。',
        '教育' => '表現知識傳遞和學習成長概念。',
        '餐飲' => '突出美食品質和用餐體驗。',
        '金融' => '展現信賴穩定和財務安全感。',
        '服務' => '強調客戶服務和專業支援。',
        '零售' => '展現商品品質和購物體驗。',
        '製造' => '體現生產品質和工藝精神。',
        '娛樂' => '傳達歡樂體驗和創意活力。',
        '運動' => '展現活力健康和運動精神。'
    ];
    
    foreach ($industry_requirements as $industry => $requirement) {
        if (stripos($business_type, $industry) !== false) {
            return $requirement;
        }
    }
    
    return '';
}

/**
 * 使用AI生成背景圖示
 */
function generateBackgroundWithAI($website_name, $secondary_color, $job_data, $images_dir, $deployer)
{
    try {
        // 載入部署配置
        $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
        if (!file_exists($deploy_config_file)) {
            throw new Exception("部署配置檔案不存在");
        }
        
        $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
        
        // 取得ConfigManager實例
        require_once DEPLOY_BASE_PATH . '/config-manager.php';
        $config = ConfigManager::getInstance();
        
        // 取得AI圖片生成設定
        $fallback_order = $config->get('ai_image_generation.fallback_order', ['openai', 'ideogram', 'gemini']);
        $api_credentials = $deploy_config['api_credentials'] ?? [];
        
        $deployer->log("使用圖片生成服務順序: " . implode(' → ', $fallback_order));
        
        // 構建背景圖示提示詞
        $prompt = buildBackgroundPrompt($website_name, $secondary_color, $job_data);
        
        $deployer->log("AI提示詞: " . $prompt );
        
        $image_data = null;
        
        // 根據設定的順序嘗試不同的服務
        foreach ($fallback_order as $service) {
            if ($image_data) break; // 如果已成功生成，跳出迴圈
            
            switch ($service) {
                case 'openai':
                    if (isset($api_credentials['openai']['api_key']) && !empty($api_credentials['openai']['api_key'])) {
                        $deployer->log("嘗試使用 OpenAI 生成背景圖示");
                        $image_data = callOpenAIImageGeneration($prompt, $api_credentials['openai'], $deployer);
                        if (!$image_data && count($fallback_order) > 1) {
                            $deployer->log("🔄 OpenAI 失敗");
                        }
                    }
                    break;
                    
                case 'ideogram':
                    if (isset($api_credentials['ideogram']['api_key']) && !empty($api_credentials['ideogram']['api_key'])) {
                        $deployer->log("嘗試使用 Ideogram 生成背景圖示");
                        $image_data = callIdeogramImageGeneration($prompt, $api_credentials['ideogram'], $deployer);
                        if (!$image_data && count($fallback_order) > 1) {
                            $deployer->log("🔄 Ideogram 失敗");
                        }
                    }
                    break;
                    
                case 'gemini':
                    if (isset($api_credentials['gemini']['api_key']) && !empty($api_credentials['gemini']['api_key'])) {
                        $deployer->log("嘗試使用 Gemini 生成背景圖示");
                        $image_data = callGeminiImageGeneration($prompt, $api_credentials['gemini'], $deployer);
                        if (!$image_data && count($fallback_order) > 1) {
                            $deployer->log("🔄 Gemini 失敗");
                        }
                    }
                    break;
            }
        }
        
        if (!$image_data) {
            throw new Exception("所有AI圖片生成服務都失敗");
        }
        
        // 儲存背景圖片
        $background_filename = 'background-layer.png';
        $background_path = $images_dir . '/' . $background_filename;
        
        if (!saveImageData($image_data, $background_path, $deployer)) {
            throw new Exception("無法儲存背景圖片");
        }
        
        // 直接調整圖片尺寸為540x210
        $resized_path = resizeImageTo540x210($background_path, $images_dir, $deployer);
        
        if ($resized_path) {
            $file_size = formatFileSize(filesize($resized_path));
            $deployer->log("AI背景圖示生成成功: " . basename($resized_path) . " ($file_size)");
            return $resized_path;
        }
        
        return $background_path; // 如果調整失敗，返回原圖
        
    } catch (Exception $e) {
        $deployer->log("AI背景生成失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 移除背景色變透明
 */
function removeBackgroundColor($source_path, $images_dir, $deployer)
{
    try {
        // 取得原圖資訊
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            throw new Exception("無法取得圖片資訊");
        }
        
        $source_type = $image_info[2];
        
        // 根據圖片類型載入圖片
        switch ($source_type) {
            case IMAGETYPE_PNG:
                $source_image = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_JPEG:
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_GIF:
                $source_image = imagecreatefromgif($source_path);
                break;
            default:
                throw new Exception("不支援的圖片格式");
        }
        
        if (!$source_image) {
            throw new Exception("無法載入來源圖片");
        }
        
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        
        // 創建透明背景的新圖片
        $transparent_image = imagecreatetruecolor($width, $height);
        imagealphablending($transparent_image, false);
        imagesavealpha($transparent_image, true);
        $transparent_color = imagecolorallocatealpha($transparent_image, 0, 0, 0, 127);
        imagefill($transparent_image, 0, 0, $transparent_color);
        imagealphablending($transparent_image, true);
        
        // 取得四角的顏色作為背景色參考
        $corner_colors = [
            imagecolorat($source_image, 0, 0), // 左上
            imagecolorat($source_image, $width-1, 0), // 右上
            imagecolorat($source_image, 0, $height-1), // 左下
            imagecolorat($source_image, $width-1, $height-1) // 右下
        ];
        
        // 選擇最常見的角落顏色作為背景色
        $background_color = array_count_values($corner_colors);
        $bg_color = array_keys($background_color, max($background_color))[0];
        
        // 提取背景色的RGB
        $bg_r = ($bg_color >> 16) & 0xFF;
        $bg_g = ($bg_color >> 8) & 0xFF;
        $bg_b = $bg_color & 0xFF;
        
        $deployer->log("檢測到背景色: RGB($bg_r, $bg_g, $bg_b)");
        
        // 逐像素處理，將背景色轉為透明
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel_color = imagecolorat($source_image, $x, $y);
                $pixel_r = ($pixel_color >> 16) & 0xFF;
                $pixel_g = ($pixel_color >> 8) & 0xFF;
                $pixel_b = $pixel_color & 0xFF;
                
                // 計算色彩差異（允許一些容差）
                $color_diff = abs($pixel_r - $bg_r) + abs($pixel_g - $bg_g) + abs($pixel_b - $bg_b);
                
                if ($color_diff < 30) { // 容差值，可調整
                    // 背景色，設為透明
                    continue; // 已經是透明背景
                } else {
                    // 非背景色，保留原色
                    $new_color = imagecolorallocate($transparent_image, $pixel_r, $pixel_g, $pixel_b);
                    imagesetpixel($transparent_image, $x, $y, $new_color);
                }
            }
        }
        
        // 儲存透明背景圖片
        $transparent_filename = 'background-transparent.png';
        $transparent_path = $images_dir . '/' . $transparent_filename;
        
        if (imagepng($transparent_image, $transparent_path)) {
            $deployer->log("背景透明化完成: $transparent_filename");
        } else {
            throw new Exception("無法儲存透明背景圖片");
        }
        
        // 清理記憶體
        imagedestroy($source_image);
        imagedestroy($transparent_image);
        
        return $transparent_path;
        
    } catch (Exception $e) {
        if (isset($source_image) && is_resource($source_image)) {
            imagedestroy($source_image);
        }
        if (isset($transparent_image) && is_resource($transparent_image)) {
            imagedestroy($transparent_image);
        }
        $deployer->log("背景透明化失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 調整圖片尺寸為540x210
 */
function resizeImageTo540x210($source_path, $images_dir, $deployer)
{
    try {
        $target_width = 540;
        $target_height = 210;
        
        // 取得原圖資訊
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            throw new Exception("無法取得圖片資訊");
        }
        
        $source_width = $image_info[0];
        $source_height = $image_info[1];
        $source_type = $image_info[2];
        
        // 根據圖片類型載入圖片
        switch ($source_type) {
            case IMAGETYPE_PNG:
                $source_image = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_JPEG:
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_GIF:
                $source_image = imagecreatefromgif($source_path);
                break;
            default:
                throw new Exception("不支援的圖片格式");
        }
        
        if (!$source_image) {
            throw new Exception("無法載入來源圖片");
        }
        
        // 使用改進的內容感知算法（參考用戶提供的代碼思路）
        $bounds = getContentBounds($source_image, $source_width, $source_height, $deployer);
        
        if ($bounds) {
            $content_x = $bounds['x'];
            $content_y = $bounds['y'];
            $content_width = $bounds['width'];
            $content_height = $bounds['height'];
            
            $deployer->log("偵測到內容區域: x={$content_x}, y={$content_y}, w={$content_width}, h={$content_height}");
        } else {
            // 如果偵測失敗，使用整張圖片
            $content_x = 0;
            $content_y = 0;
            $content_width = $source_width;
            $content_height = $source_height;
            $deployer->log("內容偵測失敗，使用整張圖片: {$source_width}x{$source_height}");
        }
        
        // 創建目標畫布
        $target_image = imagecreatetruecolor($target_width, $target_height);
        
        // 設定透明背景
        imagealphablending($target_image, false);
        imagesavealpha($target_image, true);
        $transparent = imagecolorallocatealpha($target_image, 0, 0, 0, 127);
        imagefill($target_image, 0, 0, $transparent);
        imagealphablending($target_image, true);
        
        // 使用改進的智能縮放算法（參考用戶提供的代碼思路）
        $content_aspect_ratio = $content_width / $content_height;
        $target_aspect_ratio = $target_width / $target_height;
        
        // 根據長寬比智能決定縮放方式
        if ($content_aspect_ratio > $target_aspect_ratio) {
            // 內容比較寬，以目標寬度為基準
            $new_width = $target_width;
            $new_height = intval($target_width / $content_aspect_ratio);
            $scale_mode = "以寬度為基準";
        } else {
            // 內容比較高，以目標高度為基準
            $new_height = $target_height;
            $new_width = intval($target_height * $content_aspect_ratio);
            $scale_mode = "以高度為基準";
        }
        
        // 加入 padding，避免貼邊
        $padding = 5;
        if ($new_width > ($target_width - $padding)) {
            $scale_ratio = ($target_width - $padding) / $new_width;
            $new_width = $target_width - $padding;
            $new_height = intval($new_height * $scale_ratio);
        }
        if ($new_height > ($target_height - $padding)) {
            $scale_ratio = ($target_height - $padding) / $new_height;
            $new_height = $target_height - $padding;
            $new_width = intval($new_width * $scale_ratio);
        }
        
        // 計算置中位置
        $dest_x = intval(($target_width - $new_width) / 2);
        $dest_y = intval(($target_height - $new_height) / 2);
        
        $deployer->log("內容區域: {$content_width}x{$content_height}");
        $deployer->log("內容長寬比: " . round($content_aspect_ratio, 3));
        $deployer->log("目標長寬比: " . round($target_aspect_ratio, 3));
        $deployer->log("縮放模式: {$scale_mode}");
        $deployer->log("最終尺寸: {$new_width}x{$new_height}");
        $deployer->log("置中位置: ({$dest_x}, {$dest_y})");
        
        // 精確地將內容區塊縮放並複製到目標畫布
        imagecopyresampled(
            $target_image,    // 目標畫布
            $source_image,    // 來源圖片
            $dest_x,          // 目標 X 座標
            $dest_y,          // 目標 Y 座標
            $content_x,       // 來源內容區塊的 X 座標
            $content_y,       // 來源內容區塊的 Y 座標
            $new_width,       // 縮放後的新寬度
            $new_height,      // 縮放後的新高度
            $content_width,   // 來源內容的原始寬度
            $content_height   // 來源內容的原始高度
        );
        
        // 儲存調整後的圖片
        $resized_filename = 'background-resized.png';
        $resized_path = $images_dir . '/' . $resized_filename;
        
        if (imagepng($target_image, $resized_path)) {
            $deployer->log("圖片已調整為540x210 (CSS fill模式): $resized_filename");
        } else {
            throw new Exception("無法儲存調整後的圖片");
        }
        
        // 清理記憶體
        imagedestroy($source_image);
        imagedestroy($target_image);
        
        return $resized_path;
        
    } catch (Exception $e) {
        if (isset($source_image) && is_resource($source_image)) {
            imagedestroy($source_image);
        }
        if (isset($target_image) && is_resource($target_image)) {
            imagedestroy($target_image);
        }
        $deployer->log("圖片調整失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 創建純色背景(備用方案)
 */
function createSolidBackground($images_dir, $deployer)
{
    try {
        $width = 540;
        $height = 210;
        
        $image = imagecreatetruecolor($width, $height);
        
        // 設定透明背景
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        
        $background_filename = 'background-solid.png';
        $background_path = $images_dir . '/' . $background_filename;
        
        if (imagepng($image, $background_path)) {
            $deployer->log("創建純色透明背景: $background_filename");
        } else {
            throw new Exception("無法創建背景圖片");
        }
        
        imagedestroy($image);
        return $background_path;
        
    } catch (Exception $e) {
        if (isset($image) && is_resource($image)) {
            imagedestroy($image);
        }
        $deployer->log("背景創建失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 生成文字圖層 (使用PHP GD)
 */
function generateTextLayer($website_name, $primary_color, $font_file, $images_dir, $deployer)
{
    try {
        // 圖片尺寸
        $width = 540;
        $height = 210;
        
        // 創建畫布
        $image = imagecreatetruecolor($width, $height);
        if (!$image) {
            throw new Exception("無法創建文字圖層畫布");
        }
        
        // 設定透明背景
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparent);
        imagealphablending($image, true);
        
        // 解析primary顏色
        $color_rgb = hexToRgb($primary_color);
        $text_color = imagecolorallocate($image, $color_rgb['r'], $color_rgb['g'], $color_rgb['b']);
        
        // 計算適合的字體大小 - 優先考慮寬度，讓文字更寬扁
        $font_size = calculateOptimalFontSize($website_name, $font_file, $width - 60, $height - 50); // 調整邊距比例(540x210)
        
        $deployer->log("使用字體大小: {$font_size}px");
        
        // 計算文字位置(居中) - 針對寬扁字體優化
        $text_box = imagettfbbox($font_size, 0, $font_file, $website_name);
        $text_width = $text_box[4] - $text_box[0];
        $text_height = $text_box[1] - $text_box[7];
        
        // 水平置中
        $x = intval(($width - $text_width) / 2);
        // 垂直置中 - 針對寬扁字體稍微向上調整
        $y = intval(($height / 2) + ($text_height * 0.35));
        
        $deployer->log("文字尺寸: {$text_width}x{$text_height}, 位置: ({$x}, {$y}), 寬高比: " . round($text_width/$text_height, 2));
        
        // 渲染文字
        $result = imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_file, $website_name);
        if (!$result) {
            throw new Exception("文字渲染失敗");
        }
        
        // 儲存文字圖層
        $text_filename = 'text-layer.png';
        $text_path = $images_dir . '/' . $text_filename;
        
        if (!imagepng($image, $text_path)) {
            throw new Exception("無法儲存文字圖層");
        }
        
        // 清理記憶體
        imagedestroy($image);
        
        $file_size = formatFileSize(filesize($text_path));
        $deployer->log("文字圖層生成成功: $text_filename ($file_size)");
        
        return $text_path;
        
    } catch (Exception $e) {
        if (isset($image) && is_resource($image)) {
            imagedestroy($image);
        }
        $deployer->log("文字圖層生成失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 合併背景與文字圖層
 */
function mergeLogoLayers($background_path, $text_path, $images_dir, $deployer)
{
    try {
        // 載入背景圖層
        $background = imagecreatefrompng($background_path);
        if (!$background) {
            throw new Exception("無法載入背景圖層");
        }
        
        // 載入文字圖層
        $text_layer = imagecreatefrompng($text_path);
        if (!$text_layer) {
            throw new Exception("無法載入文字圖層");
        }
        
        // 取得圖片尺寸
        $bg_width = imagesx($background);
        $bg_height = imagesy($background);
        $text_width = imagesx($text_layer);
        $text_height = imagesy($text_layer);
        
        // 確保尺寸一致，如果不一致則調整文字圖層
        if ($bg_width !== $text_width || $bg_height !== $text_height) {
            $deployer->log("調整文字圖層尺寸以匹配背景");
            $resized_text = imagecreatetruecolor($bg_width, $bg_height);
            
            imagealphablending($resized_text, false);
            imagesavealpha($resized_text, true);
            $transparent = imagecolorallocatealpha($resized_text, 0, 0, 0, 127);
            imagefill($resized_text, 0, 0, $transparent);
            imagealphablending($resized_text, true);
            
            imagecopyresampled($resized_text, $text_layer, 0, 0, 0, 0, $bg_width, $bg_height, $text_width, $text_height);
            imagedestroy($text_layer);
            $text_layer = $resized_text;
        }
        
        // 合併圖層
        imagealphablending($background, true);
        imagesavealpha($background, true);
        
        // 將文字圖層合併到背景上
        imagecopy($background, $text_layer, 0, 0, 0, 0, $bg_width, $bg_height);
        
        // 儲存最終logo
        $final_filename = 'logo-final.png';
        $final_path = $images_dir . '/' . $final_filename;
        
        if (!imagepng($background, $final_path)) {
            throw new Exception("無法儲存最終logo");
        }
        
        // 清理記憶體
        imagedestroy($background);
        imagedestroy($text_layer);
        
        $file_size = formatFileSize(filesize($final_path));
        $deployer->log("Logo合併完成: $final_filename ($file_size)");
        
        return $final_path;
        
    } catch (Exception $e) {
        if (isset($background) && is_resource($background)) {
            imagedestroy($background);
        }
        if (isset($text_layer) && is_resource($text_layer)) {
            imagedestroy($text_layer);
        }
        if (isset($resized_text) && is_resource($resized_text)) {
            imagedestroy($resized_text);
        }
        $deployer->log("圖層合併失敗: " . $e->getMessage());
        return null;
    }
}

/**
 * 構建背景圖示提示詞
 */
function buildBackgroundPrompt($website_name, $secondary_color, $job_data)
{
    $confirmed_data = $job_data['confirmed_data'] ?? [];
    $website_description = $confirmed_data['website_description'] ?? '';
    $brand_keywords = $confirmed_data['brand_keywords'] ?? [];
    $brand_personality = $confirmed_data['brand_personality'] ?? '';
    
    $keywords_text = is_array($brand_keywords) ? implode('、', $brand_keywords) : $brand_keywords;
    
    // 超簡化：不管什麼業務都用最基本的形狀
    
    $prompt = "Ultra minimal logo background. NO TEXT.

Create maximum 2 tiny elements:
- One small solid star in corner
- One small solid circle in opposite corner
- MAXIMUM 2 elements only
- Each element very small (5% of image size)
- 85% transparent space (NOT white space)
- Elements placed only at far corners

Requirements:
- TRANSPARENT background (NOT white background)
- Empty space must be fully transparent, not white
- Single color: {$secondary_color}
- Extremely minimal
- Mostly transparent empty space
- Professional and clean

Style: Ultra-simple, barely visible, maximum transparency.";

    return $prompt;
}

/**
 * 呼叫Gemini圖片生成API
 */
function callGeminiImageGeneration($prompt, $gemini_config, $deployer)
{
    $api_key = $gemini_config['api_key'] ?? '';
    if (empty($api_key)) {
        throw new Exception("Gemini API金鑰未設定");
    }
    
    $base_url = $gemini_config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
    $model = 'imagen-3.0-generate-001';
    
    // 使用與 step-08 相同的 URL 構建方式
    $url = rtrim($base_url, '/') . '/' . $model . ':generateImage?key=' . $api_key;
    
    $data = [
        'prompt' => $prompt,
        'sampleCount' => 1,
        'aspectRatio' => 'LANDSCAPE',
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_VIOLENCE',
                'threshold' => 'BLOCK_ONLY_HIGH'
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?key=' . $api_key);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // 載入部署配置以檢查代理設定
    $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (file_exists($deploy_config_file)) {
        $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
        // 檢查是否需要使用代理
        if (isset($deploy_config['network']['use_proxy']) && 
            $deploy_config['network']['use_proxy'] === true && 
            !empty($deploy_config['network']['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $deploy_config['network']['proxy']);
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        
        if (isset($result['generatedImages'][0]['bytesBase64Encoded'])) {
            return 'data:image/png;base64,' . $result['generatedImages'][0]['bytesBase64Encoded'];
        }
    }
    
    $deployer->log("Gemini圖片生成失敗: HTTP $http_code");
    if ($http_code !== 200) {
        $deployer->log("錯誤回應: " . substr($response, 0, 500));
    }
    return null;
}

/**
 * 呼叫OpenAI圖片生成API
 */
function callOpenAIImageGeneration($prompt, $openai_config, $deployer)
{
    $api_key = $openai_config['api_key'] ?? '';
    $base_url = $openai_config['base_url'] ?? 'https://api.openai.com/v1/';
    
    if (empty($api_key)) {
        throw new Exception("OpenAI API金鑰未設定");
    }
    
    $url = rtrim($base_url, '/') . '/images/generations';
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1024x1024', // OpenAI的最接近比例，之後會調整
        'quality' => 'standard',
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
        'Authorization: Bearer ' . $api_key
    ]);
    
    // 載入部署配置以檢查代理設定
    $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (file_exists($deploy_config_file)) {
        $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
        // 檢查是否需要使用代理
        if (isset($deploy_config['network']['use_proxy']) && 
            $deploy_config['network']['use_proxy'] === true && 
            !empty($deploy_config['network']['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $deploy_config['network']['proxy']);
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'][0]['url'])) {
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("OpenAI圖片生成失敗: HTTP $http_code");
    return null;
}

/**
 * 呼叫Ideogram圖片生成API (Logo專用版本)
 */
function callIdeogramImageGeneration($prompt, $ideogram_config, $deployer)
{
    $api_key = $ideogram_config['api_key'] ?? '';
    
    if (empty($api_key)) {
        throw new Exception("Ideogram API金鑰未設定");
    }
    
    $url = 'https://api.ideogram.ai/v1/ideogram-v3/generate';
    
    // 準備 multipart form data
    $boundary = uniqid();
    $delimiter = '-------------' . $boundary;
    
    $post_data = '';
    
    // 添加 prompt
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="prompt"' . "\r\n\r\n";
    $post_data .= $prompt . "\r\n";
    
    // 添加 aspect_ratio (根據提示詞判斷使用哪種比例)
    $aspect_ratio = '2x1'; // 預設使用2x1適合Logo（修正）
    if (strpos($prompt, 'background') !== false || strpos($prompt, 'icon') !== false) {
        $aspect_ratio = '2x1'; // 背景圖示也使用2x1比例
    }
    
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="aspect_ratio"' . "\r\n\r\n";
    $post_data .= "{$aspect_ratio}\r\n";
    
    // 添加 rendering_speed
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="rendering_speed"' . "\r\n\r\n";
    $post_data .= "DEFAULT\r\n";
    
    // 添加 style_type
    $post_data .= "--{$delimiter}\r\n";
    $post_data .= 'Content-Disposition: form-data; name="style_type"' . "\r\n\r\n";
    $post_data .= "DESIGN\r\n"; // 使用設計風格適合logo
    
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
        'Api-Key: ' . $api_key,
        'Content-Type: multipart/form-data; boundary=' . $delimiter,
        'Content-Length: ' . strlen($post_data)
    ]);
    
    // 載入部署配置以檢查代理設定
    $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (file_exists($deploy_config_file)) {
        $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
        // 檢查是否需要使用代理
        if (isset($deploy_config['network']['use_proxy']) && 
            $deploy_config['network']['use_proxy'] === true && 
            !empty($deploy_config['network']['proxy'])) {
            curl_setopt($ch, CURLOPT_PROXY, $deploy_config['network']['proxy']);
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'][0]['url'])) {
            $deployer->log("Ideogram圖片生成成功");
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("Ideogram圖片生成失敗: HTTP $http_code");
    if ($response) {
        $deployer->log("Ideogram錯誤回應: " . substr($response, 0, 500));
    }
    return null;
}

/**
 * 儲存圖片資料
 */
function saveImageData($image_data, $file_path, $deployer)
{
    try {
        if (strpos($image_data, 'data:image') === 0) {
            // Base64編碼的圖片
            $base64_data = explode(',', $image_data)[1];
            $binary_data = base64_decode($base64_data);
        } else {
            // URL圖片，需要下載
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $image_data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            // 載入部署配置以檢查代理設定
            $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
            if (file_exists($deploy_config_file)) {
                $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
                // 檢查是否需要使用代理
                if (isset($deploy_config['network']['use_proxy']) && 
                    $deploy_config['network']['use_proxy'] === true && 
                    !empty($deploy_config['network']['proxy'])) {
                    curl_setopt($ch, CURLOPT_PROXY, $deploy_config['network']['proxy']);
                }
            }
            
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
 * 計算最佳字體大小
 */
function calculateOptimalFontSize($text, $font_file, $max_width, $max_height)
{
    // 二分搜索最佳字體大小 - 優化為寬扁設計
    $low = 10;
    $high = 120; // 提高上限讓文字有機會更大
    $best_size = $low;
    
    while ($low <= $high) {
        $mid = intval(($low + $high) / 2);
        $text_box = imagettfbbox($mid, 0, $font_file, $text);
        $text_width = $text_box[4] - $text_box[0];
        $text_height = $text_box[1] - $text_box[7];
        
        // 寬扁設計：優先填滿寬度，高度限制放寬
        $width_ratio = $text_width / $max_width;
        $height_ratio = $text_height / ($max_height * 1.3); // 放寬高度限制30%
        
        // 優先考慮寬度使用率，讓文字盡可能填滿橫向空間
        if ($width_ratio <= 0.95 && $height_ratio <= 1.0) {
            $best_size = $mid;
            $low = $mid + 1;
        } else {
            $high = $mid - 1;
        }
    }
    
    return $best_size;
}

/**
 * 將16進制顏色轉換為RGB
 */
function hexToRgb($hex)
{
    $hex = ltrim($hex, '#');
    
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    
    return [
        'r' => hexdec(substr($hex, 0, 2)),
        'g' => hexdec(substr($hex, 2, 2)),
        'b' => hexdec(substr($hex, 4, 2))
    ];
}

/**
 * 上傳並設定WordPress網站logo (Luke API版本 - 使用SSH)
 */
function uploadAndSetWordPressLogo($logo_path, $job_id, $config, $deployer)
{
    try {
        // 載入網站資訊檔案
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
        $website_info_file = $work_dir . '/luke_website.json';
        if (!file_exists($website_info_file)) {
            $website_info_file = $work_dir . '/wordpress_install.json';
        }
        if (!file_exists($website_info_file)) {
            $website_info_file = $work_dir . '/bt_website.json';
        }
        
        if (!file_exists($website_info_file)) {
            throw new Exception("找不到網站資訊檔案");
        }
        
        $website_info = json_decode(file_get_contents($website_info_file), true);
        
        // 從 processed_data.json 取得 domain，跟 step-17-luke.php 一樣
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
        $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
        $domain = $processed_data['confirmed_data']['domain'];
        
        // Luke 版本固定使用 /var/www/{domain}/html 路徑格式
        $document_root = '/var/www/' . $domain . '/html';
        
        $deployer->log("使用Luke模式上傳logo到WordPress媒體庫...");
        $deployer->log("目標網域: {$domain}");
        $deployer->log("WordPress路徑: {$document_root}");
        
        // 初始化 Luke 模式的 WP-CLI 執行器（跟 step-17-luke.php 一樣）
        require_once DEPLOY_BASE_PATH . '/includes/utilities/class-wp-cli-executor.php';
        
        $wp_cli = new WP_CLI_Executor($config, true); // true = Luke 模式
        $wp_cli->set_document_root($document_root);
        
        // 檢查WP-CLI可用性
        if (!$wp_cli->is_available()) {
            throw new Exception("WP-CLI不可用");
        }
        
        // 上傳logo到WordPress媒體庫（Luke模式直接使用本地檔案路徑）
        $media_title = $website_name . "Logo";
        $media_alt = $website_name . "Logo";
        
        $deployer->log("上傳logo到WordPress媒體庫: " . basename($logo_path));
        
        $upload_result = $wp_cli->upload_media($logo_path, $media_title, $media_alt);
        
        if ($upload_result['return_code'] !== 0 || !$upload_result['attachment_id']) {
            $error_msg = $upload_result['error'] ?? $upload_result['output'] ?? '上傳失敗';
            throw new Exception("Logo上傳失敗: " . $error_msg);
        }
        
        $attachment_id = $upload_result['attachment_id'];
        $deployer->log("Logo上傳成功，附件ID: {$attachment_id}");
        
        // 設定為網站logo (site_logo)
        $deployer->log("設定為網站logo...");
        $skip_options = ['skip-themes' => true, 'skip-plugins' => true];
        $logo_result = $wp_cli->execute("option update site_logo {$attachment_id}", $skip_options);
        
        if ($logo_result['return_code'] !== 0) {
            throw new Exception("設定網站logo失敗: " . $logo_result['output']);
        }
        
        // 同時設定customizer的logo (如果主題支援)
        $deployer->log("設定主題自訂logo...");
        $customizer_result = $wp_cli->execute("theme mod set custom_logo {$attachment_id}", $skip_options);
        
        if ($customizer_result['return_code'] === 0) {
            $deployer->log("主題自訂logo設定成功");
        }
        
        // 取得logo URL
        $url_result = $wp_cli->execute("post meta get {$attachment_id} _wp_attached_file", $skip_options);
        
        $logo_url = '';
        if ($url_result['return_code'] === 0 && !empty($url_result['output'])) {
            $logo_url = '/wp-content/uploads/' . trim($url_result['output']);
        }
        
        return [
            'success' => true,
            'attachment_id' => $attachment_id,
            'logo_url' => $logo_url,
            'wordpress_path' => $document_root,
            'domain' => $domain,
            'mode' => 'luke_api'
        ];
        
    } catch (Exception $e) {
        $deployer->log("Luke版本Logo上傳錯誤: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
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

/**
 * 檢測圖片中非透明像素的邊界
 * @param string $imagePath 圖片路徑
 * @return array|null 邊界資訊或 null
 */
/**
 * 使用改進的內容感知算法偵測邊界（參考用戶提供的代碼思路）
 */
function getContentBounds($image, $width, $height, $deployer) {
    // 改進的背景偵測：採樣多個角落點
    $corner_colors = [
        imagecolorat($image, 0, 0),                    // 左上
        imagecolorat($image, $width-1, 0),             // 右上
        imagecolorat($image, 0, $height-1),            // 左下
        imagecolorat($image, $width-1, $height-1)      // 右下
    ];
    
    // 找出最常見的角落顏色作為背景色
    $color_counts = array_count_values($corner_colors);
    $background_color = array_keys($color_counts, max($color_counts))[0];
    
    // 初始化邊界值
    $min_x = $width;
    $min_y = $height;
    $max_x = 0;
    $max_y = 0;
    $found = false;
    
    // 透過掃描像素來找出內容的邊界
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $pixelColor = imagecolorat($image, $x, $y);
            
            // 判斷是否為背景：不僅比較精確顏色，還要考慮相似度
            if (!isBackgroundColor($pixelColor, $background_color, $corner_colors)) {
                if ($x < $min_x) $min_x = $x;
                if ($x > $max_x) $max_x = $x;
                if ($y < $min_y) $min_y = $y;
                if ($y > $max_y) $max_y = $y;
                $found = true;
            }
        }
    }
    
    // 如果整張圖都是單一顏色，表示沒有內容
    if (!$found || $min_x === $width || $min_y === $height) {
        $deployer->log("警告: 在圖片中找不到非背景色的內容區塊");
        return null;
    }
    
    // 計算出內容區塊的實際寬高
    $content_width = $max_x - $min_x + 1; // +1 因為包含邊界像素
    $content_height = $max_y - $min_y + 1;
    
    $deployer->log("主要背景色: " . sprintf('#%06X', $background_color));
    $deployer->log("角落顏色: " . implode(', ', array_map(function($c) { return sprintf('#%06X', $c); }, $corner_colors)));
    $deployer->log("內容邊界: x={$min_x}-{$max_x}, y={$min_y}-{$max_y}");
    
    return [
        'x' => $min_x,
        'y' => $min_y,
        'width' => $content_width,
        'height' => $content_height,
        'right' => $max_x,
        'bottom' => $max_y
    ];
}

/**
 * 判斷像素是否為背景色（改進的背景偵測）
 */
function isBackgroundColor($pixelColor, $backgroundColor, $cornerColors) {
    // 首先檢查是否與主要背景色完全相同
    if ($pixelColor === $backgroundColor) {
        return true;
    }
    
    // 檢查是否與任何角落顏色相同
    if (in_array($pixelColor, $cornerColors)) {
        return true;
    }
    
    // 檢查顏色相似度（針對漸變和陰影）
    $r1 = ($pixelColor >> 16) & 0xFF;
    $g1 = ($pixelColor >> 8) & 0xFF;
    $b1 = $pixelColor & 0xFF;
    
    $r2 = ($backgroundColor >> 16) & 0xFF;
    $g2 = ($backgroundColor >> 8) & 0xFF;
    $b2 = $backgroundColor & 0xFF;
    
    // 計算顏色距離
    $distance = sqrt(pow($r1-$r2, 2) + pow($g1-$g2, 2) + pow($b1-$b2, 2));
    
    // 如果顏色距離小於閾值，視為背景色
    return $distance < 50; // 提高閾值，更積極地移除背景色
}

/**
 * 偵測圖片的實際內容邊界（舊版本，基於透明度）
 */
function getImageBounds($imagePath)
{
    if (!file_exists($imagePath)) {
        return null;
    }
    
    $image = imagecreatefrompng($imagePath);
    if (!$image) {
        return null;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    $minX = $width;
    $minY = $height;
    $maxX = 0;
    $maxY = 0;
    $found = false;
    
    // 掃描每個像素
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgba = imagecolorat($image, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            
            // 如果不是完全透明（包含半透明內容）
            if ($alpha < 120) { // 降低閾值，包含更多半透明內容
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
                $found = true;
            }
        }
    }
    
    imagedestroy($image);
    
    if (!$found) {
        return null;
    }
    
    return [
        'x' => $minX,
        'y' => $minY,
        'width' => $maxX - $minX + 1,
        'height' => $maxY - $minY + 1,
        'right' => $maxX,
        'bottom' => $maxY
    ];
}

/**
 * 智能調整 Logo 大小，確保適當的視覺份量
 * @param string $sourcePath 原始圖片路徑
 * @param array $bounds 邊界資訊
 * @param string $images_dir 圖片目錄
 * @param object $deployer 部署器實例
 * @return string|null 調整後的圖片路徑
 */
function smartResizeLogo($sourcePath, $bounds, $images_dir, $deployer)
{
    try {
        $source = imagecreatefrompng($sourcePath);
        if (!$source) {
            throw new Exception("無法讀取原始圖片");
        }
        
        // 目標尺寸
        $targetWidth = 540;
        $targetHeight = 210;
        
        // 計算內容區域的中心點
        $contentCenterX = $bounds['x'] + $bounds['width'] / 2;
        $contentCenterY = $bounds['y'] + $bounds['height'] / 2;
        
        // 計算縮放比例，讓內容占據 75% 的版面
        $targetContentWidth = $targetWidth * 0.75;
        $targetContentHeight = $targetHeight * 0.75;
        
        $scaleX = $targetContentWidth / $bounds['width'];
        $scaleY = $targetContentHeight / $bounds['height'];
        $scale = min($scaleX, $scaleY);
        
        // 限制最大縮放比例，避免過度放大失真
        $scale = min($scale, 2.0);
        
        // 計算新的內容尺寸
        $newContentWidth = (int)($bounds['width'] * $scale);
        $newContentHeight = (int)($bounds['height'] * $scale);
        
        // 建立新圖片
        $newImage = imagecreatetruecolor($targetWidth, $targetHeight);
        
        // 保持透明度
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
        imagefill($newImage, 0, 0, $transparent);
        
        // 計算目標位置（置中）
        $destX = ($targetWidth - $newContentWidth) / 2;
        $destY = ($targetHeight - $newContentHeight) / 2;
        
        // 復製並縮放內容
        imagecopyresampled(
            $newImage, $source,
            $destX, $destY,
            $bounds['x'], $bounds['y'],
            $newContentWidth, $newContentHeight,
            $bounds['width'], $bounds['height']
        );
        
        // 儲存結果
        $outputFilename = 'ai-logo-smart-resized.png';
        $outputPath = $images_dir . '/' . $outputFilename;
        
        if (imagepng($newImage, $outputPath)) {
            $file_size = formatFileSize(filesize($outputPath));
            $deployer->log("智能調整完成: {$outputFilename} ($file_size)");
            $deployer->log("內容縮放比例: " . round($scale * 100) . "%");
        } else {
            throw new Exception("無法儲存調整後的圖片");
        }
        
        imagedestroy($source);
        imagedestroy($newImage);
        
        return $outputPath;
        
    } catch (Exception $e) {
        if (isset($source) && is_resource($source)) {
            imagedestroy($source);
        }
        if (isset($newImage) && is_resource($newImage)) {
            imagedestroy($newImage);
        }
        $deployer->log("智能調整失敗: " . $e->getMessage());
        return null;
    }
}