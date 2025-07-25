<?php
/**
 * 步驟 10: 圖片佔位符識別、提示詞生成與 AI 圖片生成
 * 掃描 *-ai.json 檔案中的圖片佔位符，生成 AI 圖片並建立 image-mapping.json
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$site_config = json_decode(file_get_contents($work_dir . '/json/site-config.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("開始執行步驟 10: 圖片佔位符識別與 AI 圖片生成");

try {
    // 1. 掃描所有 *-ai.json 檔案提取圖片佔位符
    $deployer->log("開始掃描圖片佔位符");
    
    $image_placeholders = extractImagePlaceholders($work_dir, $job_id, $deployer);
    $deployer->log("發現 " . count($image_placeholders) . " 個圖片佔位符");
    
    if (empty($image_placeholders)) {
        $deployer->log("未發現任何圖片佔位符，跳過圖片生成");
        return ['status' => 'success', 'message' => '未發現圖片佔位符'];
    }
    
    // 2. 生成圖片提示詞
    $deployer->log("開始生成圖片提示詞");
    
    $ai_service = $config->get('ai_service', 'gemini');
    $image_prompts = generateImagePrompts($image_placeholders, $work_dir, $ai_service, $config, $deployer);
    
    if (empty($image_prompts)) {
        throw new Exception("圖片提示詞生成失敗");
    }
    
    // 儲存圖片提示詞
    $prompts_file = $work_dir . '/image-prompts.json';
    file_put_contents($prompts_file, json_encode($image_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("圖片提示詞已儲存: $prompts_file");
    
    // 3. 確保 images 目錄存在
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        mkdir($images_dir, 0755, true);
        $deployer->log("建立圖片目錄: $images_dir");
    }
    
    // 4. 呼叫 AI 生成圖片
    $deployer->log("開始 AI 圖片生成");
    
    $generated_images = generateImages($image_prompts, $images_dir, $ai_service, $config, $deployer);
    
    // 5. 建立 image-mapping.json（兼容 step-11 和 step-12）
    $image_mapping = buildImageMapping($generated_images, $image_placeholders, $deployer);
    
    $mapping_file = $work_dir . '/image-mapping.json';
    file_put_contents($mapping_file, json_encode($image_mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("圖片映射檔案已建立: $mapping_file");
    
    // 6. 儲存步驟結果
    $step_result = [
        'step' => '10',
        'title' => '圖片佔位符識別與 AI 圖片生成',
        'status' => 'success',
        'message' => "成功生成 " . count($generated_images) . " 個圖片",
        'placeholders_found' => count($image_placeholders),
        'images_generated' => count($generated_images),
        'images_failed' => count($image_prompts) - count($generated_images),
        'image_mapping_count' => count($image_mapping),
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/step-10-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("步驟 10: 圖片佔位符識別與 AI 圖片生成 - 完成");
    
    return ['status' => 'success', 'result' => $step_result];
    
} catch (Exception $e) {
    $deployer->log("步驟 10 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}

/**
 * 掃描所有 *-ai.json 檔案提取圖片佔位符
 */
function extractImagePlaceholders($work_dir, $job_id, $deployer)
{
    $placeholders = [];
    
    // 載入 layout_selection 以獲取容器映射資訊
    $site_config_file = $work_dir . '/json/site-config.json';
    $layout_selection = [];
    if (file_exists($site_config_file)) {
        $site_config = json_decode(file_get_contents($site_config_file), true);
        $layout_selection = $site_config['layout_selection'] ?? [];
    }
    
    // 建立頁面到容器的映射
    $page_container_map = [];
    foreach ($layout_selection as $page_config) {
        $page_name = $page_config['page'];
        $page_container_map[$page_name] = $page_config['container'] ?? [];
    }
    
    // 掃描頁面模板
    $layout_dir = $work_dir . '/layout';
    $ai_files = glob($layout_dir . '/*-ai.json');
    
    foreach ($ai_files as $file) {
        $template_name = basename($file, '-ai.json');
        $content = file_get_contents($file);
        
        if (preg_match_all('/\{\{([^}]+(?:_BG|_PHOTO|_IMG|_IMAGE|_LOGO|_ICON))\}\}/', $content, $matches)) {
            foreach ($matches[1] as $placeholder) {
                $key = $job_id . '_' . $template_name . '_' . strtolower(str_replace('_', '-', $placeholder));
                
                // 根據佔位符類型推斷容器類型
                $container_type = getContainerTypeFromPlaceholder($placeholder);
                $container_name = '';
                
                // 從 layout_selection 中找到對應的容器
                if (isset($page_container_map[$template_name][$container_type])) {
                    $container_name = $page_container_map[$template_name][$container_type];
                }
                
                $placeholders[$key] = [
                    'placeholder' => $placeholder,
                    'template' => $template_name,
                    'type' => 'page',
                    'file' => $file,
                    'container_type' => $container_type,
                    'container_name' => $container_name
                ];
                
                $deployer->log("  發現頁面圖片佔位符: {{$placeholder}} 在 $template_name (容器: $container_name)");
            }
        }
    }
    
    // 掃描全域模板
    $global_dir = $work_dir . '/layout/global';
    if (is_dir($global_dir)) {
        $global_files = glob($global_dir . '/*-ai.json');
        
        foreach ($global_files as $file) {
            $template_name = basename($file, '-ai.json');
            $content = file_get_contents($file);
            
            if (preg_match_all('/\{\{([^}]+(?:_BG|_PHOTO|_IMG|_IMAGE|_LOGO|_ICON))\}\}/', $content, $matches)) {
                foreach ($matches[1] as $placeholder) {
                    $key = $job_id . '_' . $template_name . '_' . strtolower(str_replace('_', '-', $placeholder));
                    
                    // 全域模板的容器名稱就是模板名稱本身
                    $container_type = getContainerTypeFromPlaceholder($placeholder);
                    $container_name = $template_name; // 例如 footer-001, header-001
                    
                    $placeholders[$key] = [
                        'placeholder' => $placeholder,
                        'template' => $template_name,
                        'type' => 'global',
                        'file' => $file,
                        'container_type' => $container_type,
                        'container_name' => $container_name
                    ];
                    
                    $deployer->log("  發現全域圖片佔位符: {{$placeholder}} 在 $template_name (容器: $container_name)");
                }
            }
        }
    }
    
    return $placeholders;
}

/**
 * 根據佔位符名稱推斷容器類型
 */
function getContainerTypeFromPlaceholder($placeholder)
{
    // 根據佔位符名稱推斷容器類型
    if (strpos($placeholder, 'HERO_') === 0) {
        return 'hero';
    } elseif (strpos($placeholder, 'ABOUT_') === 0) {
        return 'about';
    } elseif (strpos($placeholder, 'SERVICE_') === 0) {
        return 'service';
    } elseif (strpos($placeholder, 'CONTACT_') === 0) {
        return 'contact';
    } elseif (strpos($placeholder, 'CTA_') === 0) {
        return 'cta';
    } elseif (strpos($placeholder, 'FAQ_') === 0) {
        return 'faq';
    } elseif (strpos($placeholder, 'ARCHIVE_') === 0) {
        return 'archive';
    } elseif (strpos($placeholder, 'FOOTER_') === 0) {
        return 'footer';
    } elseif (strpos($placeholder, 'HEADER_') === 0) {
        return 'header';
    } else {
        // 如果無法識別，嘗試從更寬泛的規則推斷
        if (strpos($placeholder, '_BG') !== false) {
            return 'hero'; // 背景圖通常用於 hero 區塊
        } elseif (strpos($placeholder, '_PHOTO') !== false || strpos($placeholder, '_IMAGE') !== false) {
            return 'about'; // 照片和圖片通常用於 about 或 service
        } else {
            return 'unknown';
        }
    }
}

/**
 * 從容器資訊清單中取得容器資訊
 */
function getContainerInfo($container_name, $container_manifest)
{
    if (empty($container_manifest) || empty($container_name) || $container_name === 'unknown') {
        return [];
    }
    
    // 搜尋容器資訊
    foreach ($container_manifest as $category => $containers) {
        if (is_array($containers)) {
            foreach ($containers as $container_id => $container_data) {
                if ($container_id === $container_name) {
                    return $container_data;
                }
            }
        }
    }
    
    return [];
}

/**
 * 使用 AI 生成圖片提示詞
 */
function generateImagePrompts($placeholders, $work_dir, $ai_service, $config, $deployer)
{
    // 載入處理後的資料以獲取完整品牌資訊
    $processed_data_file = $work_dir . '/config/processed_data.json';
    $site_config_file = $work_dir . '/json/site-config.json';
    $container_manifest_file = $work_dir . '/json/container_manifest.json';
    
    // 載入容器資訊清單
    $container_manifest = [];
    if (file_exists($container_manifest_file)) {
        $container_manifest = json_decode(file_get_contents($container_manifest_file), true);
    }
    
    if (file_exists($processed_data_file)) {
        $processed_data = json_decode(file_get_contents($processed_data_file), true);
        $site_config = file_exists($site_config_file) ? json_decode(file_get_contents($site_config_file), true) : [];
        
        // 從 processed_data.json 讀取完整品牌資訊
        $brand_info = [
            'name' => $processed_data['website_name'] ?? $site_config['site_title'] ?? '網站',
            'description' => $processed_data['website_description'] ?? $site_config['site_description'] ?? '',
            'brand_personality' => $processed_data['confirmed_data']['brand_personality'] ?? '專業、親和',
            'target_audience' => $processed_data['confirmed_data']['target_audience'] ?? $site_config['target_audience'] ?? '一般用戶',
            'brand_keywords' => $processed_data['confirmed_data']['brand_keywords'] ?? [],
            'unique_value' => $processed_data['confirmed_data']['unique_value'] ?? '',
            'color_scheme' => $processed_data['confirmed_data']['color_scheme'] ?? []
        ];
    } else {
        // 如果找不到 processed_data.json，使用 site_config 作為備用
        $site_config = file_exists($site_config_file) ? json_decode(file_get_contents($site_config_file), true) : [];
        $brand_info = [
            'name' => $site_config['site_title'] ?? '網站',
            'description' => $site_config['site_description'] ?? '',
            'brand_personality' => $site_config['brand_tone'] ?? '專業、親和',
            'target_audience' => $site_config['target_audience'] ?? '一般用戶',
            'brand_keywords' => [],
            'unique_value' => '',
            'color_scheme' => []
        ];
    }
    
    // 將品牌關鍵字轉換為字串
    $keywords_str = is_array($brand_info['brand_keywords']) ? implode('、', $brand_info['brand_keywords']) : $brand_info['brand_keywords'];
    
    // 將色彩方案轉換為描述
    $color_description = '';
    if (!empty($brand_info['color_scheme'])) {
        $colors = [];
        if (isset($brand_info['color_scheme']['primary'])) {
            $colors[] = "primary color " . $brand_info['color_scheme']['primary'];
        }
        if (isset($brand_info['color_scheme']['secondary'])) {
            $colors[] = "secondary color " . $brand_info['color_scheme']['secondary'];
        }
        if (isset($brand_info['color_scheme']['accent'])) {
            $colors[] = "accent color " . $brand_info['color_scheme']['accent'];
        }
        $color_description = !empty($colors) ? implode(', ', $colors) : 'professional color palette';
    } else {
        $color_description = 'professional color palette';
    }

    // 取得圖片風格設定
    $image_style_config = $config->get('ai_features.image_style', []);
    $style_preference = $image_style_config['style'] ?? 'realistic';
    $enable_humaaans = $image_style_config['enable_humaaans'] ?? false;
    
    $deployer->log("圖片風格設定: {$style_preference}" . ($enable_humaaans ? " (Humaaans 已啟用)" : ""));

    $prompt = "你是一位專業的圖片提示詞生成師。請根據以下品牌資訊，為每個圖片佔位符生成詳細的英文圖片提示詞。

## 品牌資訊
- 品牌名稱：{$brand_info['name']}
- 品牌描述：{$brand_info['description']}
- 品牌個性：{$brand_info['brand_personality']}
- 目標受眾：{$brand_info['target_audience']}
- 品牌關鍵字：{$keywords_str}
- 獨特價值：{$brand_info['unique_value']}
- 色彩方案：{$color_description}

## 圖片佔位符清單與其所在的佈局情境
你必須根據每個佔位符所在的「容器描述」，來生成最匹配該佈局風格的圖片。
";

    foreach ($placeholders as $key => $info) {
        $container_name = $info['container_name'] ?? 'unknown';
        $container_info = getContainerInfo($container_name, $container_manifest);
        
        $prompt .= "- {$key}:\n";
        $prompt .= "  - 佔位符: {{" . $info['placeholder'] . "}}\n";
        $prompt .= "  - 所在模板: " . $info['template'] . "\n";
        $prompt .= "  - 所在容器: {$container_name}\n";
        
        if (!empty($container_info)) {
            $prompt .= "  - 容器描述: \"" . ($container_info['description'] ?? '標準容器佈局') . "\"\n";
            $prompt .= "  - 容器風格: " . json_encode($container_info['style'] ?? ['professional']) . "\n";
        } else {
            $prompt .= "  - 容器描述: \"標準容器佈局\"\n";
            $prompt .= "  - 容器風格: [\"professional\"]\n";
        }
        $prompt .= "\n";
    }

    // 根據風格偏好添加風格指導
    if ($style_preference === 'humaaans' && $enable_humaaans) {
        $style_guidance = "
## 🎨 Humaaans 風格要求 (優先級最高)
**必須使用 Humaaans 扁平插圖風格**，參考以下範例格式：

\"A modern flat illustration in the style of humaaans, featuring minimalist characters collaborating and interacting with abstract data nodes. The scene represents interconnected systems and knowledge sharing. The background is a clean, abstract geometric composition with overlapping shapes. The entire image uses a strict and professional color palette of deep blue (#2563EB), light blue (#38BDF8), with subtle dark gray (#0F172A) accents. Clean lines, vector art aesthetic, plenty of negative space for text overlay. Purely visual imagery, no text, no words, no letters.\"

### Humaaans 風格核心特徵：
- **扁平化插圖設計** (Flat illustration)
- **簡潔的幾何形狀和線條** (Simple geometric shapes, clean lines)
- **友善、親和的人物形象** (Friendly, approachable character design)
- **統一的色彩系統** (Consistent color palette)
- **Vector art aesthetic** (向量藝術美學)
- **Minimalist composition** (極簡構圖)

### 針對不同佔位符的 Humaaans 風格指導：
- **_BG (背景)**: \"Abstract flat illustration background in the style of humaaans, geometric shapes, clean composition suitable for text overlay\"
- **_PHOTO (人物)**: \"Flat illustration of [character] in the style of humaaans, friendly geometric features, simple character design\"  
- **_IMG/_IMAGE (圖片)**: \"Flat illustration in the style of humaaans featuring [subject], minimalist vector design\"
- **_LOGO (標誌)**: \"Minimalist logo in the style of humaaans flat illustration, simple geometric elements\"
- **_ICON (圖示)**: \"Simple flat icon in the style of humaaans, geometric design, vector art\"";
    } else {
        $style_guidance = "
## 🎨 寫實攝影風格要求
使用專業攝影風格，真實場景和人物，高品質視覺效果。";
    }

    $prompt .= $style_guidance . "

## 生成要求
1. **【風格優先】**" . ($style_preference === 'humaaans' && $enable_humaaans ? " 必須嚴格遵循上述 Humaaans 風格要求" : " 使用專業攝影風格") . "
2. 每個圖片提示詞要具體詳細，包含風格、顏色、構圖等
3. 提示詞必須是英文
4. 【重要】每個提示詞結尾必須加上 \"no text, no words, no letters, purely visual imagery\"
5. 必須融入上述品牌色彩方案中的顏色描述
6. 必須體現品牌個性和關鍵字的視覺意象
7. 【新增】必須根據容器描述和風格，生成最匹配該佈局環境的圖片
8. 【新增】為每個圖片加入負面提示詞以提升品質
9. 【新增】維持整體視覺風格一致性，除非容器類型有特殊需求
10. 針對不同類型的佔位符生成相應風格：
   - _BG (背景): 抽象或場景背景，要考慮容器的文字佈局和遮罩需求
   - _PHOTO (照片): 符合目標受眾特徵的人物或場景，體現品牌個性
   - _IMG/_IMAGE (圖片): 與服務內容和品牌關鍵字相關的視覺元素
   - _LOGO (標誌): 簡約但包含品牌特色的設計元素
   - _ICON (圖示): 與品牌關鍵字相關的簡單圖示

## 範例格式（請參考但不要照抄）" . 
    ($style_preference === 'humaaans' && $enable_humaaans ? "
### Humaaans 風格範例：
\"home_hero-bg\": {
    \"prompt\": \"A modern flat illustration in the style of humaaans, featuring minimalist characters collaborating and interacting with abstract data nodes. The scene represents interconnected systems and knowledge sharing. The background is a clean, abstract geometric composition with overlapping shapes. The entire image uses the brand color palette of {$color_description}. Clean lines, vector art aesthetic, plenty of negative space for text overlay, no text, no words, no letters, purely visual imagery\",
    \"negative_prompt\": \"realistic, photographic, 3d render, watermark, text, words, letters, busy composition, harsh shadows\",
    \"style\": \"flat illustration\",
    \"size\": \"1312x736\",
    \"quality\": \"standard\"
}

\"about_hero-photo\": {
    \"prompt\": \"Flat illustration of a friendly professional consultant in the style of humaaans, simple geometric facial features, wearing business casual attire, modern office background with abstract geometric elements, {$color_description}, minimalist character design, approachable and trustworthy appearance, no text, no words, no letters, purely visual imagery\",
    \"negative_prompt\": \"realistic, photographic, detailed facial features, 3d render, watermark, text, words, letters\",
    \"style\": \"flat illustration\",
    \"size\": \"1024x1024\",
    \"quality\": \"standard\"
}" : "
### 寫實攝影風格範例：
\"home_hero-bg\": {
    \"prompt\": \"Peaceful forest scene with soft morning light filtering through trees, {$color_description}, calming healing atmosphere, cinematic composition with center focus area for text overlay, natural therapeutic environment, no text, no words, no letters, purely visual imagery\",
    \"negative_prompt\": \"blurry, cartoon, 3d render, watermark, text, words, letters, busy composition, harsh lighting\",
    \"style\": \"cinematic\",
    \"size\": \"1312x736\",
    \"quality\": \"standard\"
}")

請以 JSON 格式回應，格式如下：
{
    \"key1\": {
        \"prompt\": \"詳細英文提示詞（必須包含品牌色彩、容器適配和結尾的無文字聲明）\",
        \"negative_prompt\": \"負面提示詞以提升圖片品質\",
        \"style\": \"攝影風格如 professional/abstract/minimalist/natural/cinematic\",
        \"size\": \"根據圖片類型選擇適當尺寸：背景圖(_BG)使用1312x736(16:9)，人像照片(_PHOTO)使用1024x1024(1:1)，圖示(_ICON)使用1024x1024(1:1)，標誌(_LOGO)使用1280x800(16:10)\",
        \"quality\": \"standard\"
    }
}

只回傳 JSON，不要額外說明。";

    $deployer->log("呼叫 AI 生成圖片提示詞...");
    $deployer->log("提示詞長度: " . strlen($prompt) . " 字元");
    
    if ($ai_service === 'gemini') {
        $response = callGeminiAPI($prompt, $config, $deployer);
    } else {
        $response = callOpenAIAPI($prompt, $config, $deployer);
    }
    
    if (!$response) {
        $deployer->log("AI 提示詞生成失敗，使用預設提示詞");
        return generateDefaultImagePrompts($placeholders);
    }
    
    // 解析 AI 回應
    $response_text = trim($response);
    if (strpos($response_text, '```json') !== false) {
        $response_text = preg_replace('/```json\s*|\s*```/', '', $response_text);
    }
    
    $prompts = json_decode($response_text, true);
    if (!$prompts) {
        $deployer->log("AI 回應解析失敗，使用預設提示詞");
        return generateDefaultImagePrompts($placeholders);
    }
    
    $deployer->log("AI 圖片提示詞生成成功，共 " . count($prompts) . " 個");
    return $prompts;
}

/**
 * 生成預設圖片提示詞
 */
function generateDefaultImagePrompts($placeholders)
{
    $prompts = [];
    
    foreach ($placeholders as $key => $info) {
        $placeholder = $info['placeholder'];
        
        if (strpos($placeholder, '_BG') !== false) {
            $prompt = "Abstract background image, professional design, soft gradient colors, modern minimalist style, no text, no words, no letters, purely visual imagery";
            $negative_prompt = "blurry, cartoon, 3d render, watermark, text, words, letters";
            $style = "abstract";
            $size = "1312x736"; // 16:9 適合背景圖
        } elseif (strpos($placeholder, '_PHOTO') !== false) {
            $prompt = "Professional portrait photography, natural lighting, warm atmosphere, business casual, no text, no words, no letters, purely visual imagery";
            $negative_prompt = "blurry, amateur, bad lighting, text, words, letters";
            $style = "professional";
            $size = "1024x1024"; // 1:1 適合人像照片
        } elseif (strpos($placeholder, '_LOGO') !== false) {
            $prompt = "Minimalist logo design, clean simple shapes, professional brand identity, transparent background, no text, no words, no letters, purely visual imagery";
            $negative_prompt = "complex, cluttered, text, words, letters, realistic";
            $style = "minimalist";
            $size = "1280x800"; // 16:10 適合標誌
        } elseif (strpos($placeholder, '_ICON') !== false) {
            $prompt = "Simple icon design, line art style, professional clean, minimalist, no text, no words, no letters, purely visual imagery";
            $negative_prompt = "complex, detailed, text, words, letters, realistic";
            $style = "minimalist";
            $size = "1024x1024"; // 1:1 適合圖示
        } else {
            $prompt = "Professional image, high quality, modern design, clean composition, no text, no words, no letters, purely visual imagery";
            $negative_prompt = "blurry, amateur, text, words, letters";
            $style = "professional";
            $size = "1024x1024"; // 預設 1:1
        }
        
        $prompts[$key] = [
            'prompt' => $prompt,
            'negative_prompt' => $negative_prompt,
            'style' => $style,
            'size' => $size,
            'quality' => 'standard' // 使用 standard 以啟用 TURBO 模式節省成本
        ];
    }
    
    return $prompts;
}

/**
 * 生成圖片
 */
function generateImages($image_prompts, $images_dir, $ai_service, $config, $deployer)
{
    $generated_images = [];
    $failed_count = 0;
    
    // 取得 AI API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'model' => 'dall-e-3',
        'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
    ];

    $gemini_config = [
        'api_key' => $config->get('api_credentials.gemini.api_key'),
        'model' => 'gemini-2.0-flash-preview-image-generation',
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models/'
    ];
    
    $ideogram_config = [
        'api_key' => $config->get('api_credentials.ideogram.api_key')
    ];
    
    foreach ($image_prompts as $key => $prompt_config) {
        $deployer->log("生成圖片: $key");
        
        try {
            $filename = generateSingleImage($key, $prompt_config, $images_dir, $ai_service, $openai_config, $gemini_config, $ideogram_config, $deployer);
            
            if ($filename) {
                $generated_images[$key] = $filename;
                $deployer->log("✅ 圖片生成成功: $filename");
            } else {
                $failed_count++;
                $deployer->log("❌ 圖片生成失敗: $key");
            }
            
            // API 請求間隔
            sleep(1);
            
        } catch (Exception $e) {
            $failed_count++;
            $deployer->log("❌ 圖片生成異常: $key - " . $e->getMessage());
        }
    }
    
    $deployer->log("圖片生成完成: " . count($generated_images) . " 成功, $failed_count 失敗");
    
    return $generated_images;
}

/**
 * 生成單張圖片
 */
function generateSingleImage($key, $prompt_config, $images_dir, $ai_service, $openai_config, $gemini_config, $ideogram_config, $deployer)
{
    $prompt = $prompt_config['prompt'];
    $size = $prompt_config['size'] ?? '1024x1024';
    $quality = $prompt_config['quality'] ?? 'standard';
    
    // 取得 ConfigManager 實例
    $config = ConfigManager::getInstance();
    
    // 取得 AI 圖片生成設定
    $primary_service = $config->get('ai_image_generation.primary_service', 'openai');
    $fallback_order = $config->get('ai_image_generation.fallback_order', ['openai', 'ideogram', 'gemini']);
    
    $deployer->log("使用圖片生成服務順序: " . implode(' → ', $fallback_order));
    
    $image_data = null;
    
    // 根據設定的順序嘗試不同的服務
    foreach ($fallback_order as $service) {
        if ($image_data) break; // 如果已成功生成，跳出迴圈
        
        switch ($service) {
            case 'openai':
                if (isset($openai_config['api_key']) && !empty($openai_config['api_key'])) {
                    $deployer->log("嘗試使用 OpenAI 生成圖片");
                    $image_data = generateImageWithOpenAI($prompt, $size, $quality, $openai_config, $deployer);
                    if (!$image_data && count($fallback_order) > 1) {
                        $deployer->log("🔄 OpenAI 失敗");
                    }
                }
                break;
                
            case 'ideogram':
                if (isset($ideogram_config['api_key']) && !empty($ideogram_config['api_key'])) {
                    $deployer->log("嘗試使用 Ideogram 生成圖片");
                    $image_data = generateImageWithIdeogram($prompt, $size, $quality, $ideogram_config, $deployer);
                    if (!$image_data && count($fallback_order) > 1) {
                        $deployer->log("🔄 Ideogram 失敗");
                    }
                }
                break;
                
            case 'gemini':
                if (isset($gemini_config['api_key']) && !empty($gemini_config['api_key'])) {
                    $deployer->log("嘗試使用 Gemini 生成圖片");
                    $image_data = generateImageWithGemini($prompt, $size, $quality, $gemini_config, $deployer);
                    if (!$image_data && count($fallback_order) > 1) {
                        $deployer->log("🔄 Gemini 失敗");
                    }
                }
                break;
        }
    }
    
    if (!$image_data) {
        $deployer->log("❌ 所有圖片生成服務都失敗");
        return null;
    }
    
    // 儲存圖片
    $filename = $key . '.png';
    $file_path = $images_dir . '/' . $filename;
    
    if (saveImageData($image_data, $file_path, $deployer)) {
        return $filename;
    }
    
    return null;
}

/**
 * 使用 Gemini 生成圖片
 */
function generateImageWithGemini($prompt, $size, $quality, $gemini_config, $deployer)
{
    $base_url = $gemini_config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
    $model = $gemini_config['model'] ?? 'gemini-2.0-flash-preview-image-generation';
    $api_key = $gemini_config['api_key'] ?? '';
    
    // 使用與 step-08 相同的 URL 構建方式
    $url = rtrim($base_url, '/') . '/' . $model . ':generateContent?key=' . $api_key;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => "Generate a high-quality image: " . $prompt . ". Professional, no text or words in the image."]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 4096,
            'temperature' => 0.2,
            'candidateCount' => 1,
            'responseModalities' => ['IMAGE']
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?key=' . $gemini_config['api_key']);
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
        
        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    return 'data:image/png;base64,' . $part['inlineData']['data'];
                }
            }
        }
    }
    
    $deployer->log("Gemini 圖片生成失敗: HTTP $http_code");
    return null;
}

/**
 * 使用 OpenAI DALL-E 生成圖片
 */
function generateImageWithOpenAI($prompt, $size, $quality, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/images/generations';
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $size,
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
    
    $deployer->log("OpenAI 圖片生成失敗: HTTP $http_code");
    return null;
}

/**
 * 使用 Ideogram API 生成圖片
 */
function generateImageWithIdeogram($prompt, $size, $quality, $ideogram_config, $deployer)
{
    $url = 'https://api.ideogram.ai/v1/ideogram-v3/generate';
    
    // 轉換尺寸格式為 Ideogram API 接受的格式
    $aspect_ratio = '1x1'; // 預設 1:1
    
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
    
    // 設定渲染速度 - 優化成本：預設使用 TURBO 模式（$0.025-0.04/圖），只有明確要求 high 才使用 DEFAULT（$0.08/圖）
    $rendering_speed = $quality === 'high' ? 'DEFAULT' : 'TURBO';
    
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
    $post_data .= $rendering_speed . "\r\n";
    
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
            $deployer->log("Ideogram 圖片生成成功");
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("Ideogram 圖片生成失敗: HTTP $http_code");
    if ($response) {
        $deployer->log("Ideogram 錯誤回應: " . substr($response, 0, 500));
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
            // Base64 編碼的圖片
            $base64_data = explode(',', $image_data)[1];
            $binary_data = base64_decode($base64_data);
        } else {
            // URL 圖片，需要下載
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
 * 建立圖片映射表（兼容 step-11 和 step-12）
 */
function buildImageMapping($generated_images, $placeholders, $deployer)
{
    $mapping = [];
    
    foreach ($generated_images as $key => $filename) {
        // 從新格式 key 中提取頁面名稱和佔位符
        // 新格式: job_id_template_placeholder
        $parts = explode('_', $key);
        if (count($parts) >= 3) {
            $template_name = $parts[1]; // 頁面名稱（如 home, about）
            $placeholder_parts = array_slice($parts, 2); // 佔位符部分
            $placeholder_name = strtoupper(str_replace('-', '_', implode('_', $placeholder_parts))); // 轉換為大寫佔位符
            
            // 為 step-12 建立分組結構（按頁面分組）
            if (!isset($mapping[$template_name])) {
                $mapping[$template_name] = [];
            }
            
            $mapping[$template_name][$placeholder_name] = "/wp-content/uploads/ai-generated/$filename";
            
            $deployer->log("建立分組圖片映射: {$template_name}[{$placeholder_name}] -> /wp-content/uploads/ai-generated/$filename");
        }
    }
    
    // 為向後相容，建立平面結構的映射（使用不同的key以避免衝突）
    foreach ($generated_images as $key => $filename) {
        $base_name = pathinfo($filename, PATHINFO_FILENAME);
        
        // 格式 1: 原始 key (step-11 主要使用) - 使用 '_file_' 前綴避免與頁面名稱衝突
        $mapping['_file_' . $key] = "/wp-content/uploads/ai-generated/$filename";
        
        // 格式 2: 只有檔名 (備用)
        $mapping['_file_' . $base_name] = "/wp-content/uploads/ai-generated/$filename";
        
        // 格式 3: 轉換底線為連字號 (備用)
        $hyphen_key = str_replace('_', '-', $key);
        if ($hyphen_key !== $key) {
            $mapping['_file_' . $hyphen_key] = "/wp-content/uploads/ai-generated/$filename";
        }
        
        $deployer->log("建立平面圖片映射: _file_$key -> /wp-content/uploads/ai-generated/$filename");
    }
    
    return $mapping;
}

/**
 * 呼叫 Gemini API
 */
function callGeminiAPI($prompt, $config, $deployer)
{
    $api_key = $config->get('api_credentials.gemini.api_key');
    $model = $config->get('api_credentials.gemini.model') ?? 'gemini-2.0-flash-exp';
    $base_url = $config->get('api_credentials.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    // 使用與 step-08 相同的 URL 構建方式
    $url = rtrim($base_url, '/') . '/' . $model . ':generateContent?key=' . $api_key;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => 4096,
            'temperature' => 0.1,
            'candidateCount' => 1
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
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
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    
    $deployer->log("Gemini API 呼叫失敗: HTTP $http_code");
    return null;
}

/**
 * 呼叫 OpenAI API
 */
function callOpenAIAPI($prompt, $config, $deployer)
{
    $api_key = $config->get('api_credentials.openai.api_key');
    $base_url = $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/';
    $url = rtrim($base_url, '/') . '/chat/completions';
    
    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => 4096,
        'temperature' => 0.1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);
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
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
    }
    
    $deployer->log("OpenAI API 呼叫失敗: HTTP $http_code");
    return null;
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