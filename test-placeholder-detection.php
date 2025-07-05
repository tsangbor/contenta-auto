<?php
/**
 * 佔位符偵測測試工具
 * 檢查頁面中的佔位符是否能正確被偵測到
 */

if ($argc < 2) {
    echo "使用方式: php test-placeholder-detection.php [job_id]\n";
    echo "或者: php test-placeholder-detection.php auto\n";
    exit(1);
}

// 定義必要常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

// 只載入需要的函數，不載入整個 step-09
function simplifyPageContent($content, $max_depth = 4, $current_depth = 0)
{
    if ($current_depth > $max_depth) {
        return '[內容過深，已省略]';
    }
    
    $simplified = [];
    
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            if (is_string($value)) {
                // 檢查是否包含可能需要替換的內容
                if (strlen($value) > 3 && (
                    // 包含大寫佔位符
                    preg_match('/[A-Z_]{3,}/', $value) ||
                    // 或是常見的文字欄位
                    preg_match('/(title|content|text|label|placeholder|description|subtitle|heading|editor)/i', $key) ||
                    // 或包含中文文字
                    preg_match('/[\x{4e00}-\x{9fff}]/u', $value) ||
                    // 或是 settings 相關的文字
                    (strpos($key, 'settings') !== false && strlen($value) > 10)
                )) {
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

function findPlaceholders($content, &$placeholders = [], $path = '')
{
    if (is_array($content)) {
        foreach ($content as $key => $value) {
            $current_path = $path ? "$path.$key" : $key;
            
            if (is_string($value)) {
                // 找出所有大寫佔位符
                if (preg_match_all('/[A-Z_]{3,}/', $value, $matches)) {
                    foreach ($matches[0] as $placeholder) {
                        if (!in_array($placeholder, $placeholders)) {
                            $placeholders[] = $placeholder;
                        }
                    }
                }
            } elseif (is_array($value)) {
                findPlaceholders($value, $placeholders, $current_path);
            }
        }
    }
    
    return $placeholders;
}

$base_dir = __DIR__;
$temp_dir = $base_dir . '/temp';

if ($argv[1] === 'auto') {
    // 自動找最新的任務
    $job_dirs = scandir($temp_dir);
    $available_jobs = [];
    
    foreach ($job_dirs as $dir) {
        if ($dir !== '.' && $dir !== '..' && is_dir($temp_dir . '/' . $dir)) {
            if (is_dir($temp_dir . '/' . $dir . '/layout')) {
                $available_jobs[$dir] = filemtime($temp_dir . '/' . $dir);
            }
        }
    }
    
    if (empty($available_jobs)) {
        echo "❌ 沒有找到任何任務目錄\n";
        exit(1);
    }
    
    arsort($available_jobs);
    $job_id = array_key_first($available_jobs);
} else {
    $job_id = $argv[1];
}

$layout_dir = $temp_dir . '/' . $job_id . '/layout';

if (!is_dir($layout_dir)) {
    echo "❌ 任務目錄不存在: {$layout_dir}\n";
    exit(1);
}

echo "=== 佔位符偵測測試 ===\n";
echo "任務 ID: {$job_id}\n\n";

// 找出所有 .json 檔案（非 -ai.json）
$files = scandir($layout_dir);
foreach ($files as $file) {
    if (preg_match('/^([^-]+)\.json$/', $file, $matches)) {
        $page_name = $matches[1];
        $file_path = $layout_dir . '/' . $file;
        
        echo "📄 分析頁面: {$page_name}\n";
        echo "檔案: {$file}\n";
        
        $content = json_decode(file_get_contents($file_path), true);
        if (!$content) {
            echo "❌ 無法解析 JSON\n\n";
            continue;
        }
        
        // 找出佔位符
        $placeholders = [];
        findPlaceholders($content, $placeholders);
        
        echo "🔍 發現的佔位符 (" . count($placeholders) . " 個):\n";
        if (empty($placeholders)) {
            echo "  (無)\n";
        } else {
            foreach ($placeholders as $placeholder) {
                echo "  - {$placeholder}\n";
            }
        }
        
        // 簡化內容測試
        $simplified = simplifyPageContent($content);
        $simplified_json = json_encode($simplified, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $simplified_length = strlen($simplified_json);
        
        echo "📝 簡化內容長度: {$simplified_length} 字元\n";
        echo "📊 原始/簡化比例: " . round($simplified_length / strlen(json_encode($content)) * 100, 1) . "%\n";
        
        // 顯示簡化內容的前幾個欄位
        echo "🔍 簡化內容預覽:\n";
        $preview_count = 0;
        foreach ($simplified as $key => $value) {
            if ($preview_count >= 5) break;
            
            if (is_string($value)) {
                $short_value = strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                echo "  {$key}: {$short_value}\n";
                $preview_count++;
            } elseif (is_array($value)) {
                echo "  {$key}: [陣列，" . count($value) . " 項目]\n";
                $preview_count++;
            }
        }
        
        echo "\n";
    }
}

echo "=== 測試完成 ===\n";
echo "\n💡 說明:\n";
echo "- 佔位符: 頁面中需要替換的大寫文字\n";
echo "- 簡化內容: 傳送給 AI 的精簡版本\n";
echo "- 如果佔位符很少，可能是容器模板沒有使用標準佔位符\n";