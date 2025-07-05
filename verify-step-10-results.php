<?php
/**
 * 驗證 Step 10 圖片生成結果
 */

echo "=== Step 10 圖片生成結果驗證 ===\n\n";

$job_id = '2506302336-TEST';
$work_dir = __DIR__ . '/temp/' . $job_id;
$images_dir = $work_dir . '/images';

// 檢查圖片目錄
if (!is_dir($images_dir)) {
    echo "❌ 圖片目錄不存在: {$images_dir}\n";
    exit(1);
}

// 掃描圖片檔案
$files = scandir($images_dir);
$image_files = array_filter($files, function($file) {
    return $file !== '.' && $file !== '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
});

echo "📁 圖片目錄: {$images_dir}\n";
echo "📊 找到圖片: " . count($image_files) . " 張\n\n";

if (empty($image_files)) {
    echo "❌ 沒有找到任何圖片檔案\n";
    echo "\n建議執行以下測試:\n";
    echo "php test-image-generation-single.php  # 單張測試\n";
    echo "php test-step-10-auto.php            # 完整測試\n";
    exit(1);
}

// 分析每張圖片
$total_size = 0;
echo "📷 圖片詳細資訊:\n";

foreach ($image_files as $file) {
    $file_path = $images_dir . '/' . $file;
    $size = filesize($file_path);
    $total_size += $size;
    $time = date('Y-m-d H:i:s', filemtime($file_path));
    
    echo "  • {$file}\n";
    echo "    大小: " . formatFileSize($size) . "\n";
    echo "    時間: {$time}\n";
    
    // 檢查圖片是否有效
    $image_info = @getimagesize($file_path);
    if ($image_info) {
        echo "    尺寸: {$image_info[0]}x{$image_info[1]} px\n";
        echo "    類型: " . image_type_to_mime_type($image_info[2]) . "\n";
        echo "    狀態: ✅ 有效圖片\n";
    } else {
        echo "    狀態: ❌ 無效圖片檔案\n";
    }
    echo "\n";
}

echo "📈 總計資訊:\n";
echo "圖片數量: " . count($image_files) . " 張\n";
echo "總大小: " . formatFileSize($total_size) . "\n";

// 檢查生成報告
$report_file = $images_dir . '/generation-report.json';
if (file_exists($report_file)) {
    echo "\n📄 生成報告:\n";
    $report = json_decode(file_get_contents($report_file), true);
    if ($report) {
        echo "策略: " . ($report['strategy_used'] ?? '未知') . "\n";
        echo "時間戳: " . ($report['timestamp'] ?? '未知') . "\n";
        if (isset($report['total_prompts'])) {
            echo "原始提示: {$report['total_prompts']} 張\n";
        }
        if (isset($report['generated_count'])) {
            echo "實際生成: {$report['generated_count']} 張\n";
        }
    }
}

// 檢查路徑替換
echo "\n🔍 檢查路徑替換結果:\n";

$site_config_file = $work_dir . '/json/site-config.json';
if (file_exists($site_config_file)) {
    $site_config = file_get_contents($site_config_file);
    $old_paths = preg_match_all('/https:\/\/www\.hsinnyu\.tw\/wp-content\/uploads/', $site_config);
    $new_paths = preg_match_all('/ai-generated/', $site_config);
    
    echo "site-config.json:\n";
    echo "  舊路徑殘留: {$old_paths} 處\n";
    echo "  新路徑替換: {$new_paths} 處\n";
    
    if ($old_paths > 0) {
        echo "  ⚠️  仍有舊路徑未替換\n";
    } else {
        echo "  ✅ 路徑替換完成\n";
    }
}

// 檢查 AI 參數使用
echo "\n🤖 AI 服務使用驗證:\n";

$image_prompts_file = $work_dir . '/json/image-prompts.json';
if (file_exists($image_prompts_file)) {
    $image_prompts = json_decode(file_get_contents($image_prompts_file), true);
    
    $generated_keys = [];
    foreach ($image_files as $file) {
        $key = pathinfo($file, PATHINFO_FILENAME);
        $generated_keys[] = $key;
    }
    
    echo "已生成的圖片對應 AI 服務:\n";
    foreach ($generated_keys as $key) {
        if (isset($image_prompts[$key])) {
            $ai_service = $image_prompts[$key]['ai'] ?? 'openai';
            $ai_icon = $ai_service === 'gemini' ? '🟩' : '🟦';
            echo "  {$ai_icon} {$key}: {$ai_service}\n";
        }
    }
}

// 測試建議
echo "\n💡 接下來可以執行的測試:\n";

if (count($image_files) < 5) {
    echo "1. 完整優化測試: php test-step-10-auto.php\n";
}

echo "2. 單張新圖片測試: php test-image-generation-single.php\n";
echo "3. AI 參數功能驗證: php test-ai-parameter.php\n";
echo "4. 清理重新開始: rm {$images_dir}/*.png\n";

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

echo "\n=== 驗證完成 ===\n";
?>