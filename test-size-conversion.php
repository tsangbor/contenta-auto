<?php
/**
 * 測試 DALL-E 3 尺寸轉換功能
 */

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

echo "=== DALL-E 3 尺寸轉換測試 ===\n\n";

// 測試各種尺寸轉換
$test_sizes = [
    '800x200',    // 橫向 (4:1)
    '1920x1080',  // 橫向 (16:9) 
    '1024x1024',  // 正方形 (1:1)
    '600x800',    // 直向 (3:4)
    '400x800',    // 直向 (1:2)
    '300x600',    // 直向 (1:2)
    '1200x800',   // 橫向 (3:2)
    '500x500',    // 正方形
    '1080x1920',  // 直向 (9:16)
];

echo "📐 尺寸轉換測試:\n";
foreach ($test_sizes as $original_size) {
    $converted_size = convertToSupportedSize($original_size);
    list($w, $h) = explode('x', $original_size);
    $ratio = round($w / $h, 2);
    echo "  {$original_size} (比例 {$ratio}) → {$converted_size}\n";
}

echo "\n✅ 尺寸轉換函數測試完成\n";

// 測試無效輸入
echo "\n🔍 邊界情況測試:\n";
$edge_cases = ['invalid', '1024', '', '0x0', 'abcxdef'];
foreach ($edge_cases as $case) {
    $result = convertToSupportedSize($case);
    echo "  '{$case}' → {$result}\n";
}

echo "\n✅ 邊界情況測試完成\n";

// 驗證所有轉換結果都是支援的尺寸
$supported_sizes = ['1024x1024', '1024x1792', '1792x1024'];
echo "\n✅ 支援的 DALL-E 3 尺寸: " . implode(', ', $supported_sizes) . "\n";

$all_results = [];
foreach ($test_sizes as $size) {
    $all_results[] = convertToSupportedSize($size);
}

$unique_results = array_unique($all_results);
echo "轉換結果種類: " . implode(', ', $unique_results) . "\n";

$invalid_results = array_diff($unique_results, $supported_sizes);
if (empty($invalid_results)) {
    echo "✅ 所有轉換結果都是支援的尺寸\n";
} else {
    echo "❌ 發現不支援的尺寸: " . implode(', ', $invalid_results) . "\n";
}

echo "\n=== 測試完成 ===\n";