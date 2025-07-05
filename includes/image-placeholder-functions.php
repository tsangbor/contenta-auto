<?php
/**
 * 圖片佔位符識別與處理函數庫
 * 支援標準化圖片佔位符格式：{{*_BG}}, {{*_PHOTO}}, {{*_ICON}}
 * 
 * @since v1.14.3
 */

/**
 * 判斷欄位是否為圖片欄位
 * 支援副檔名識別和標準化佔位符識別
 * 
 * @param string $key 欄位名稱
 * @param mixed $value 欄位值
 * @return bool
 */
function isImageField($key, $value)
{
    if (!is_string($value)) {
        return false;
    }
    
    // 1. 副檔名識別 - 傳統方式
    if (preg_match('/\.(jpg|jpeg|png|gif|svg|webp)$/i', $value)) {
        return true;
    }
    
    // 2. 標準化圖片佔位符識別
    $image_placeholder_patterns = [
        '/\{\{\w*_BG\}\}/',      // {{*_BG}} - 背景圖片
        '/\{\{\w*_PHOTO\}\}/',   // {{*_PHOTO}} - 照片
        '/\{\{\w*_ICON\}\}/',    // {{*_ICON}} - 圖示
    ];
    
    foreach ($image_placeholder_patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }
    
    // 3. 圖片路徑模式識別
    if (preg_match('/\/wp-content\/uploads\//', $value) || 
        preg_match('/\/images\//', $value) ||
        preg_match('/\/assets\//', $value)) {
        return true;
    }
    
    // 4. 欄位名稱暗示
    $image_field_names = [
        'image', 'img', 'photo', 'picture', 'avatar',
        'thumbnail', 'thumb', 'icon', 'logo', 'banner',
        'background', 'bg', 'cover', 'featured'
    ];
    
    foreach ($image_field_names as $field_name) {
        if (stripos($key, $field_name) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * 從頁面資料中掃描所有圖片佔位符
 * 
 * @param array $page_data 頁面資料
 * @param array &$image_placeholders 找到的圖片佔位符
 * @param string $path 當前路徑
 * @param array $context 上下文資訊
 * @return array
 */
function scanImagePlaceholders($page_data, &$image_placeholders = [], $path = '', $context = [])
{
    if (is_array($page_data)) {
        foreach ($page_data as $key => $value) {
            $current_path = $path ? "$path.$key" : $key;
            $current_context = array_merge($context, ['field_key' => $key]);
            
            // 保存 widget 類型資訊
            if ($key === 'widgetType') {
                $current_context['widgetType'] = $value;
            }
            
            if (is_string($value) && isImageField($key, $value)) {
                // 識別圖片類型
                $image_type = identifyImageType($value, $key, $current_context);
                
                $placeholder_info = [
                    'placeholder' => $value,
                    'path' => $current_path,
                    'context' => $current_context,
                    'field_key' => $key,
                    'type' => $image_type,
                    'section' => inferSectionFromPath($current_path),
                    'purpose' => inferPurposeFromType($image_type)
                ];
                
                // 避免重複
                if (!isImagePlaceholderExists($placeholder_info, $image_placeholders)) {
                    $image_placeholders[] = $placeholder_info;
                }
            } elseif (is_array($value)) {
                scanImagePlaceholders($value, $image_placeholders, $current_path, $current_context);
            }
        }
    }
    
    return $image_placeholders;
}

/**
 * 識別圖片類型
 * 
 * @param string $value 圖片值
 * @param string $key 欄位名稱
 * @param array $context 上下文
 * @return string
 */
function identifyImageType($value, $key, $context = [])
{
    // 1. 標準化佔位符類型
    if (preg_match('/\{\{\w*_BG\}\}/', $value)) {
        return 'background';
    }
    if (preg_match('/\{\{\w*_PHOTO\}\}/', $value)) {
        return 'photo';
    }
    if (preg_match('/\{\{\w*_ICON\}\}/', $value)) {
        return 'icon';
    }
    
    // 2. 根據欄位名稱推斷
    if (stripos($key, 'bg') !== false || stripos($key, 'background') !== false) {
        return 'background';
    }
    if (stripos($key, 'photo') !== false || stripos($key, 'portrait') !== false) {
        return 'photo';
    }
    if (stripos($key, 'icon') !== false || stripos($key, 'ico') !== false) {
        return 'icon';
    }
    if (stripos($key, 'logo') !== false) {
        return 'logo';
    }
    
    // 3. 根據路徑推斷
    if (stripos($value, 'background') !== false || stripos($value, 'bg') !== false) {
        return 'background';
    }
    if (stripos($value, 'photo') !== false || stripos($value, 'team') !== false) {
        return 'photo';
    }
    if (stripos($value, 'icon') !== false) {
        return 'icon';
    }
    
    // 4. 根據 widget 類型
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    if ($widget_type === 'icon' || $widget_type === 'icon-box') {
        return 'icon';
    }
    if ($widget_type === 'image') {
        return 'photo';
    }
    
    // 預設類型
    return 'image';
}

/**
 * 從路徑推斷區塊
 * 
 * @param string $path
 * @return string
 */
function inferSectionFromPath($path)
{
    $path_lower = strtolower($path);
    
    if (strpos($path_lower, 'hero') !== false) {
        return 'hero';
    }
    if (strpos($path_lower, 'about') !== false) {
        return 'about';
    }
    if (strpos($path_lower, 'service') !== false) {
        return 'service';
    }
    if (strpos($path_lower, 'contact') !== false) {
        return 'contact';
    }
    if (strpos($path_lower, 'footer') !== false) {
        return 'footer';
    }
    if (strpos($path_lower, 'cta') !== false) {
        return 'cta';
    }
    if (strpos($path_lower, 'header') !== false) {
        return 'header';
    }
    
    return 'general';
}

/**
 * 從類型推斷用途
 * 
 * @param string $type
 * @return string
 */
function inferPurposeFromType($type)
{
    $purpose_map = [
        'background' => 'background_image',
        'photo' => 'portrait_or_product',
        'icon' => 'service_icon',
        'logo' => 'brand_identity',
        'image' => 'general_image'
    ];
    
    return isset($purpose_map[$type]) ? $purpose_map[$type] : 'general_image';
}

/**
 * 檢查圖片佔位符是否已存在
 * 
 * @param array $new_placeholder
 * @param array $existing_placeholders
 * @return bool
 */
function isImagePlaceholderExists($new_placeholder, $existing_placeholders)
{
    foreach ($existing_placeholders as $existing) {
        if ($new_placeholder['placeholder'] === $existing['placeholder'] &&
            $new_placeholder['path'] === $existing['path']) {
            return true;
        }
    }
    return false;
}

/**
 * 生成標準化圖片佔位符
 * 
 * @param string $section 區塊名稱
 * @param string $type 圖片類型
 * @param string $key 欄位名稱
 * @return string
 */
function generateStandardizedImagePlaceholder($section, $type, $key = '')
{
    $section = strtoupper($section);
    
    switch ($type) {
        case 'background':
            return "{{" . $section . "_BG}}";
        case 'photo':
            return "{{" . $section . "_PHOTO}}";
        case 'icon':
            return "{{" . $section . "_ICON}}";
        case 'logo':
            return "{{LOGO}}"; // Logo 通常是全站統一
        default:
            // 根據欄位名稱進一步判斷
            if (stripos($key, 'bg') !== false) {
                return "{{" . $section . "_BG}}";
            }
            if (stripos($key, 'photo') !== false) {
                return "{{" . $section . "_PHOTO}}";
            }
            if (stripos($key, 'icon') !== false) {
                return "{{" . $section . "_ICON}}";
            }
            return "{{" . $section . "_IMG}}";
    }
}

/**
 * 將舊格式圖片路徑轉換為標準化佔位符
 * 
 * @param string $old_path 舊圖片路徑
 * @param string $section 區塊名稱
 * @param string $key 欄位名稱
 * @return string
 */
function convertToStandardizedImagePlaceholder($old_path, $section, $key)
{
    // 分析舊路徑推斷類型
    $type = 'image';
    
    if (stripos($old_path, 'background') !== false || stripos($old_path, 'bg') !== false) {
        $type = 'background';
    } elseif (stripos($old_path, 'photo') !== false || stripos($old_path, 'portrait') !== false) {
        $type = 'photo';
    } elseif (stripos($old_path, 'icon') !== false) {
        $type = 'icon';
    } elseif (stripos($old_path, 'logo') !== false) {
        $type = 'logo';
    }
    
    return generateStandardizedImagePlaceholder($section, $type, $key);
}

/**
 * 為圖片佔位符生成智能提示詞
 * 
 * @param array $placeholder_info 佔位符資訊
 * @param array $user_data 用戶資料
 * @return array
 */
function generateImagePromptInfo($placeholder_info, $user_data = [])
{
    $type = $placeholder_info['type'];
    $section = $placeholder_info['section'];
    $purpose = $placeholder_info['purpose'];
    
    // 基礎提示詞模板
    $base_prompts = [
        'background' => [
            'hero' => 'Professional hero section background, modern abstract design',
            'footer' => 'Subtle footer background pattern, elegant and minimal',
            'cta' => 'Engaging call-to-action background, soft gradient design'
        ],
        'photo' => [
            'about' => 'Professional portrait photo in modern office setting',
            'team' => 'Team collaboration photo, diverse professionals working',
            'service' => 'Service demonstration photo, professional environment'
        ],
        'icon' => [
            'service' => 'Modern flat design service icon, minimalist style',
            'feature' => 'Feature icon with gradient effect, professional design'
        ]
    ];
    
    // 選擇基礎提示詞
    $prompt = isset($base_prompts[$type][$section]) 
        ? $base_prompts[$type][$section] 
        : "Professional {$type} for {$section} section";
    
    // 加入品牌元素
    if (!empty($user_data['brand_keywords'])) {
        $brand_keywords = implode(', ', array_slice($user_data['brand_keywords'], 0, 3));
        $prompt .= ", incorporating themes of {$brand_keywords}";
    }
    
    // 加入顏色方案
    if (!empty($user_data['color_scheme'])) {
        $prompt .= ", using {$user_data['color_scheme']} color palette";
    }
    
    // 針對不同類型加入特殊要求
    if ($type === 'icon') {
        $prompt .= ", no text, no letters, no words, no characters, pure graphic design only";
    } elseif ($type === 'background') {
        $prompt .= ", no text overlay, no typography";
    } elseif ($type === 'photo') {
        $prompt .= ", natural photography without text overlay";
    }
    
    // 決定 AI 模型
    $ai_model = ($type === 'logo') ? 'openai' : 'gemini';
    
    // 決定圖片尺寸
    $size_map = [
        'background' => '1920x1080',
        'photo' => '800x800',
        'icon' => '512x512',
        'logo' => '800x200'
    ];
    $size = isset($size_map[$type]) ? $size_map[$type] : '1024x1024';
    
    return [
        'prompt' => $prompt,
        'ai' => $ai_model,
        'style' => $type,
        'size' => $size,
        'quality' => ($type === 'logo' || $section === 'hero') ? 'high' : 'standard'
    ];
}