<?php
/**
 * 測試標準化佔位符掃描功能
 * 驗證正規化表達式是否正確識別佔位符：
 * {{*_TITLE}} {{*_SUBTITLE}} {{*_LIST_TITLE}} {{*_LIST_SUBTITLE}} {{*_DESCR}} {{*_CONTENT}} {{*CTA_BUTTON}} {{*CTA_LINK}} {{*_ADDR}}
 */

require_once __DIR__ . '/config-manager.php';

echo "🔍 測試標準化佔位符掃描功能\n";
echo "================================\n\n";

// 模擬包含各種佔位符的頁面 JSON 結構
$test_page_data = [
    'hero_section' => [
        'widgetType' => 'heading',
        'title' => '{{HOME_TITLE}}',
        'subtitle' => '{{HOME_SUBTITLE}}',
        'description' => '{{HOME_DESCR}}',
        'content' => '{{HOME_CONTENT}}',
        'cta_button' => '{{HOMECTA_BUTTON}}',
        'cta_link' => '{{HOMECTA_LINK}}'
    ],
    'about_section' => [
        'widgetType' => 'text-editor',
        'main_title' => '{{ABOUT_TITLE}}',
        'list_title' => '{{ABOUT_LIST_TITLE}}',
        'list_subtitle' => '{{ABOUT_LIST_SUBTITLE}}',
        'text_content' => '{{ABOUT_CONTENT}}',
        'address' => '{{CONTACT_ADDR}}'
    ],
    'service_section' => [
        'items' => [
            [
                'title' => '{{SERVICE_TITLE}}',
                'description' => '{{SERVICE_DESCR}}',
                'button_text' => '{{SERVICECTA_BUTTON}}'
            ]
        ]
    ],
    'legacy_content' => [
        'old_title' => 'HERO_TITLE',
        'old_content' => 'ABOUT_CONTENT_P1',
        'chinese_text' => '這是需要AI替換的中文內容'
    ]
];

// 定義必要的常數
define('DEPLOY_BASE_PATH', __DIR__);

// 直接定義需要的函數而不載入整個 step-09.php
/**
 * 檢查欄位是否需要進行替換
 */
function shouldIncludeForReplacement($key, $value, $context = [])
{
    // 1. 跳過系統欄位
    if (in_array($key, ['widgetType', 'id', '_id', 'elType', '_element_id'])) {
        return false;
    }
    
    // 2. 跳過URL和圖片路徑（通常不需要文字替換）
    if (preg_match('/\.(jpg|jpeg|png|gif|svg|webp|css|js)$/i', $value)) {
        return false;
    }
    
    // 3. 跳過空值和數字
    if (empty($value) || is_numeric($value)) {
        return false;
    }
    
    // 4. 明確的佔位符總是包含
    if (preg_match('/^[A-Z][A-Z_]*[A-Z]$/', $value) || preg_match('/\{\{\w*.*\}\}/', $value)) {
        return true;
    }
    
    // 5. 常見的內容欄位名稱
    if (preg_match('/(title|content|text|description|subtitle|heading)$/i', $key) && strlen($value) > 5) {
        return true;
    }
    
    return false;
}

/**
 * 檢查佔位符是否已存在
 */
function isPlaceholderExists($new_placeholder, $existing_placeholders)
{
    foreach ($existing_placeholders as $existing) {
        $new_key = is_array($new_placeholder) ? $new_placeholder['placeholder'] : $new_placeholder;
        $existing_key = is_array($existing) ? $existing['placeholder'] : $existing;
        
        if ($new_key === $existing_key) {
            return true;
        }
    }
    return false;
}

/**
 * 為中文內容生成標準化佔位符
 */
function generateStandardizedPlaceholder($key, $value, $context = [], $path = '')
{
    // 根據欄位名稱和上下文生成標準化佔位符
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    $section_type = '';
    
    // 從路徑推斷區塊類型
    if (strpos($path, 'hero') !== false) {
        $section_type = 'HERO';
    } elseif (strpos($path, 'about') !== false) {
        $section_type = 'ABOUT';
    } elseif (strpos($path, 'service') !== false) {
        $section_type = 'SERVICE';
    } elseif (strpos($path, 'contact') !== false) {
        $section_type = 'CONTACT';
    } elseif (strpos($path, 'cta') !== false) {
        $section_type = 'CTA';
    } else {
        $section_type = 'ELEMENT';
    }
    
    // 根據欄位名稱生成標準化佔位符
    if (preg_match('/title/i', $key)) {
        if (strpos($key, 'list') !== false) {
            $placeholder = "{{" . $section_type . "_LIST_TITLE}}";
        } else {
            $placeholder = "{{" . $section_type . "_TITLE}}";
        }
    } elseif (preg_match('/subtitle/i', $key)) {
        if (strpos($key, 'list') !== false) {
            $placeholder = "{{" . $section_type . "_LIST_SUBTITLE}}";
        } else {
            $placeholder = "{{" . $section_type . "_SUBTITLE}}";
        }
    } elseif (preg_match('/descr|description/i', $key)) {
        $placeholder = "{{" . $section_type . "_DESCR}}";
    } elseif (preg_match('/content|text|editor/i', $key)) {
        $placeholder = "{{" . $section_type . "_CONTENT}}";
    } elseif (preg_match('/button.*text|btn.*text/i', $key)) {
        $placeholder = "{{" . $section_type . "CTA_BUTTON}}";
    } elseif (preg_match('/button.*link|btn.*link|url|href/i', $key)) {
        $placeholder = "{{" . $section_type . "CTA_LINK}}";
    } elseif (preg_match('/addr|address/i', $key)) {
        $placeholder = "{{" . $section_type . "_ADDR}}";
    } else {
        // 根據內容長度決定
        if (mb_strlen($value, 'UTF-8') <= 20) {
            $placeholder = "{{" . $section_type . "_TITLE}}";
        } else {
            $placeholder = "{{" . $section_type . "_CONTENT}}";
        }
    }
    
    return [
        'placeholder' => $placeholder,
        'path' => $path,
        'context' => $context,
        'original_value' => $value,
        'field_key' => $key,
        'type' => 'generated_standardized'
    ];
}

/**
 * 找出頁面中所有需要替換的標準化佔位符
 * 支援正規化格式：{{*_TITLE}} {{*_SUBTITLE}} {{*_LIST_TITLE}} {{*_LIST_SUBTITLE}} {{*_DESCR}} {{*_CONTENT}} {{*CTA_BUTTON}} {{*CTA_LINK}} {{*_ADDR}}
 */
function findPlaceholders($content, &$placeholders = [], $path = '', $context = [])
{
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            $current_path = $path ? "$path.$key" : $key;
            $current_context = array_merge($context, [$key => $value]);
            
            if (is_string($value)) {
                // 檢查是否應該包含進行替換
                if (shouldIncludeForReplacement($key, $value, $content)) {
                    
                    // 1. 標準化佔位符格式 - 使用正規化表達式識別
                    $standardized_patterns = [
                        '/\{\{\w*_TITLE\}\}/',           // {{*_TITLE}}
                        '/\{\{\w*_SUBTITLE\}\}/',        // {{*_SUBTITLE}}  
                        '/\{\{\w*_LIST_TITLE\}\}/',      // {{*_LIST_TITLE}}
                        '/\{\{\w*_LIST_SUBTITLE\}\}/',   // {{*_LIST_SUBTITLE}}
                        '/\{\{\w*_DESCR\}\}/',           // {{*_DESCR}}
                        '/\{\{\w*_CONTENT\}\}/',         // {{*_CONTENT}}
                        '/\{\{\w*CTA_BUTTON\}\}/',       // {{*CTA_BUTTON}}
                        '/\{\{\w*CTA_LINK\}\}/',         // {{*CTA_LINK}}
                        '/\{\{\w*_ADDR\}\}/',            // {{*_ADDR}}
                    ];
                    
                    $found_standardized = false;
                    foreach ($standardized_patterns as $pattern) {
                        if (preg_match_all($pattern, $value, $matches)) {
                            foreach ($matches[0] as $placeholder) {
                                $placeholder_info = [
                                    'placeholder' => $placeholder,
                                    'path' => $current_path,
                                    'context' => $current_context,
                                    'original_value' => $value,
                                    'field_key' => $key,
                                    'type' => 'standardized'
                                ];
                                if (!isPlaceholderExists($placeholder_info, $placeholders)) {
                                    $placeholders[] = $placeholder_info;
                                }
                            }
                            $found_standardized = true;
                        }
                    }
                    
                    // 2. 向後相容：舊格式的大寫佔位符
                    if (!$found_standardized) {
                        if (preg_match_all('/[A-Z_]+(TITLE|SUBTITLE|CONTENT)[A-Z_]*/', $value, $matches)) {
                            foreach ($matches[0] as $placeholder) {
                                $placeholder_info = [
                                    'placeholder' => $placeholder,
                                    'path' => $current_path,
                                    'context' => $current_context,
                                    'original_value' => $value,
                                    'field_key' => $key,
                                    'type' => 'legacy_semantic'
                                ];
                                if (!isPlaceholderExists($placeholder_info, $placeholders)) {
                                    $placeholders[] = $placeholder_info;
                                }
                            }
                        }
                        // 3. 純大寫佔位符
                        elseif (preg_match('/^[A-Z][A-Z_]*[A-Z]$/', $value) && strlen($value) >= 3) {
                            $placeholder_info = [
                                'placeholder' => $value,
                                'path' => $current_path,
                                'context' => $current_context,
                                'original_value' => $value,
                                'field_key' => $key,
                                'type' => 'legacy'
                            ];
                            if (!isPlaceholderExists($placeholder_info, $placeholders)) {
                                $placeholders[] = $placeholder_info;
                            }
                        }
                        // 4. 標記需要 AI 替換的中文內容
                        elseif (preg_match('/[\x{4e00}-\x{9fff}]/u', $value)) {
                            // 為中文內容生成標準化佔位符
                            $semantic_placeholder = generateStandardizedPlaceholder($key, $value, $current_context, $current_path);
                            if ($semantic_placeholder && !isPlaceholderExists($semantic_placeholder, $placeholders)) {
                                $placeholders[] = $semantic_placeholder;
                            }
                        }
                    }
                }
            } elseif (is_array($value)) {
                findPlaceholders($value, $placeholders, $current_path, $current_context);
            }
        }
    }
    
    return $placeholders;
}

// 測試佔位符掃描
echo "📋 掃描測試頁面中的佔位符：\n";
echo "-----------------------------\n";

$placeholders = [];
findPlaceholders($test_page_data, $placeholders);

echo "找到 " . count($placeholders) . " 個佔位符：\n\n";

// 按類型分組顯示
$grouped_placeholders = [
    'standardized' => [],
    'legacy_semantic' => [],
    'legacy' => [],
    'generated_standardized' => []
];

foreach ($placeholders as $placeholder_info) {
    $type = is_array($placeholder_info) ? $placeholder_info['type'] : 'unknown';
    if (!isset($grouped_placeholders[$type])) {
        $grouped_placeholders[$type] = [];
    }
    $grouped_placeholders[$type][] = $placeholder_info;
}

// 顯示結果
foreach ($grouped_placeholders as $type => $items) {
    if (empty($items)) continue;
    
    echo "🏷️  " . strtoupper($type) . " 類型：\n";
    foreach ($items as $item) {
        if (is_array($item)) {
            echo "  ✓ {$item['placeholder']} (路徑: {$item['path']}, 欄位: {$item['field_key']})\n";
        } else {
            echo "  ✓ {$item}\n";
        }
    }
    echo "\n";
}

// 測試正規化表達式
echo "🧪 測試正規化表達式：\n";
echo "---------------------\n";

$test_patterns = [
    '{{HOME_TITLE}}' => 'HOME_TITLE',
    '{{ABOUT_SUBTITLE}}' => 'ABOUT_SUBTITLE', 
    '{{SERVICE_LIST_TITLE}}' => 'SERVICE_LIST_TITLE',
    '{{CONTACT_LIST_SUBTITLE}}' => 'CONTACT_LIST_SUBTITLE',
    '{{HERO_DESCR}}' => 'HERO_DESCR',
    '{{ABOUT_CONTENT}}' => 'ABOUT_CONTENT',
    '{{HOMECTA_BUTTON}}' => 'HOMECTA_BUTTON',
    '{{SERVICECTA_LINK}}' => 'SERVICECTA_LINK',
    '{{CONTACT_ADDR}}' => 'CONTACT_ADDR'
];

$standardized_patterns = [
    '/\{\{\w*_TITLE\}\}/',           // {{*_TITLE}}
    '/\{\{\w*_SUBTITLE\}\}/',        // {{*_SUBTITLE}}  
    '/\{\{\w*_LIST_TITLE\}\}/',      // {{*_LIST_TITLE}}
    '/\{\{\w*_LIST_SUBTITLE\}\}/',   // {{*_LIST_SUBTITLE}}
    '/\{\{\w*_DESCR\}\}/',           // {{*_DESCR}}
    '/\{\{\w*_CONTENT\}\}/',         // {{*_CONTENT}}
    '/\{\{\w*CTA_BUTTON\}\}/',       // {{*CTA_BUTTON}}
    '/\{\{\w*CTA_LINK\}\}/',         // {{*CTA_LINK}}
    '/\{\{\w*_ADDR\}\}/',            // {{*_ADDR}}
];

foreach ($test_patterns as $test_string => $expected_content) {
    $matched = false;
    $pattern_matched = '';
    
    foreach ($standardized_patterns as $pattern) {
        if (preg_match($pattern, $test_string)) {
            $matched = true;
            $pattern_matched = $pattern;
            break;
        }
    }
    
    $status = $matched ? '✅' : '❌';
    echo "  {$status} {$test_string} - " . ($matched ? "匹配模式: {$pattern_matched}" : "未匹配") . "\n";
}

echo "\n";

// 測試生成標準化佔位符
echo "🏭 測試生成標準化佔位符：\n";
echo "-------------------------\n";

$test_cases = [
    ['key' => 'title', 'value' => '歡迎來到我們的網站', 'path' => 'hero.section.title'],
    ['key' => 'subtitle', 'value' => '專業服務提供商', 'path' => 'about.intro.subtitle'],
    ['key' => 'list_title', 'value' => '服務項目', 'path' => 'service.list.title'],
    ['key' => 'description', 'value' => '這是詳細的服務描述', 'path' => 'service.item.description'],
    ['key' => 'button_text', 'value' => '立即聯繫', 'path' => 'cta.section.button'],
    ['key' => 'address', 'value' => '台北市信義區', 'path' => 'contact.info.address']
];

foreach ($test_cases as $test_case) {
    $generated = generateStandardizedPlaceholder(
        $test_case['key'], 
        $test_case['value'], 
        [], 
        $test_case['path']
    );
    
    if ($generated) {
        echo "  📝 {$test_case['key']} (路徑: {$test_case['path']}) → {$generated['placeholder']}\n";
    }
}

echo "\n";

// 效能測試
echo "⚡ 效能測試：\n";
echo "------------\n";

$start_time = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $placeholders = [];
    findPlaceholders($test_page_data, $placeholders);
}
$end_time = microtime(true);

$execution_time = ($end_time - $start_time) * 1000; // 轉換為毫秒
echo "100次掃描執行時間: " . number_format($execution_time, 2) . " ms\n";
echo "平均每次掃描: " . number_format($execution_time / 100, 3) . " ms\n";

echo "\n✅ 標準化佔位符掃描功能測試完成！\n";