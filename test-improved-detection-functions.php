<?php
/**
 * 改進的佔位符檢測函數（僅函數定義）
 */

/**
 * 判斷是否應該包含此欄位進行替換
 */
function shouldIncludeForReplacement($key, $value, $context = [])
{
    // 1. 明確的佔位符格式：包含 _TITLE、_SUBTITLE、_CONTENT
    if (preg_match('/_(TITLE|SUBTITLE|CONTENT)/i', $value)) {
        return true;
    }
    
    // 2. Elementor 元素的特定欄位
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    
    // heading 元素的 title 欄位
    if ($widget_type === 'heading' && $key === 'title') {
        return true;
    }
    
    // icon-box 元素的 title_text 和 description_text
    if ($widget_type === 'icon-box' && in_array($key, ['title_text', 'description_text'])) {
        return true;
    }
    
    // text-editor 元素的 editor 欄位
    if ($widget_type === 'text-editor' && $key === 'editor') {
        return true;
    }
    
    // 3. settings 中的 title 欄位（任何元素）
    if ($key === 'title' && strlen($value) > 2) {
        return true;
    }
    
    // 4. 包含明確佔位符模式的文字
    if (preg_match('/^[A-Z][A-Z_]*[A-Z]$/', $value) && strlen($value) >= 3) {
        return true;
    }
    
    // 5. 常見的內容欄位名稱
    if (preg_match('/(title|content|text|description|subtitle|heading)$/i', $key) && strlen($value) > 5) {
        return true;
    }
    
    return false;
}

/**
 * 簡化頁面內容，只保留需要替換的文字部分
 */
function simplifyPageContent($content, $max_depth = 4, $current_depth = 0)
{
    if ($current_depth > $max_depth) {
        return '[內容過深，已省略]';
    }
    
    $simplified = [];
    
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                // 檢查是否需要替換的內容
                if (shouldIncludeForReplacement($key, $value, $content)) {
                    $simplified[$key] = $value;
                }
            } elseif (is_array($value)) {
                $nested = simplifyPageContent($value, $max_depth, $current_depth + 1);
                if (!empty($nested)) {
                    $simplified[$key] = $nested;
                }
            }
        }
    }
    
    return $simplified;
}

/**
 * 為中文內容生成語義化佔位符
 */
function generateSemanticPlaceholder($key, $value, $context = [])
{
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    
    // 根據欄位名稱和 widget 類型生成語義化佔位符
    if ($key === 'title') {
        if ($widget_type === 'heading') {
            return 'HEADING_TITLE';
        } elseif ($widget_type === 'icon-box') {
            return 'ICONBOX_TITLE';
        } else {
            return 'ELEMENT_TITLE';
        }
    } elseif ($key === 'title_text') {
        return 'ICONBOX_TITLE';
    } elseif ($key === 'description_text') {
        return 'ICONBOX_DESCRIPTION';
    } elseif ($key === 'editor') {
        return 'TEXT_CONTENT';
    } elseif (preg_match('/subtitle/i', $key)) {
        return 'ELEMENT_SUBTITLE';
    } elseif (preg_match('/content/i', $key)) {
        return 'ELEMENT_CONTENT';
    }
    
    // 根據內容特徵生成
    if (mb_strlen($value, 'UTF-8') <= 10) {
        return 'SHORT_TEXT';
    } elseif (mb_strlen($value, 'UTF-8') <= 30) {
        return 'MEDIUM_TEXT';
    } else {
        return 'LONG_TEXT';
    }
}

/**
 * 找出頁面中所有可能需要替換的佔位符
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
                    // 1. 明確的佔位符格式
                    if (preg_match_all('/[A-Z_]+(TITLE|SUBTITLE|CONTENT)[A-Z_]*/', $value, $matches)) {
                        foreach ($matches[0] as $placeholder) {
                            if (!in_array($placeholder, $placeholders)) {
                                $placeholders[] = $placeholder;
                            }
                        }
                    }
                    // 2. 純大寫佔位符
                    elseif (preg_match('/^[A-Z][A-Z_]*[A-Z]$/', $value) && strlen($value) >= 3) {
                        if (!in_array($value, $placeholders)) {
                            $placeholders[] = $value;
                        }
                    }
                    // 3. 標記需要 AI 替換的中文內容
                    elseif (preg_match('/[\x{4e00}-\x{9fff}]/u', $value)) {
                        // 為中文內容生成語義化的佔位符
                        $semantic_placeholder = generateSemanticPlaceholder($key, $value, $content);
                        if ($semantic_placeholder && !in_array($semantic_placeholder, $placeholders)) {
                            $placeholders[] = $semantic_placeholder;
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