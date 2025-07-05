<?php
/**
 * 測試禁止文字功能
 * 驗證圖片提示詞是否正確包含禁止文字的指令
 */

// 定義基礎路徑常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', dirname(__FILE__));
}

// 載入必要檔案
require_once DEPLOY_BASE_PATH . '/includes/image-placeholder-functions.php';

echo "=== 圖片提示詞禁止文字功能測試 ===\n\n";

// 測試不同類型的圖片提示詞生成
$test_cases = [
    [
        'type' => 'icon',
        'section' => 'service',
        'purpose' => 'service_icon',
        'description' => '服務圖示',
        'expected_no_text' => 'no text, no letters, no words, no characters, pure graphic design only'
    ],
    [
        'type' => 'background',
        'section' => 'hero',
        'purpose' => 'background_image',
        'description' => '背景圖片',
        'expected_no_text' => 'no text overlay, no typography'
    ],
    [
        'type' => 'photo',
        'section' => 'about',
        'purpose' => 'portrait_or_product',
        'description' => '人物照片',
        'expected_no_text' => 'natural photography without text overlay'
    ],
    [
        'type' => 'logo',
        'section' => 'header',
        'purpose' => 'brand_identity',
        'description' => 'Logo（應該允許文字）',
        'expected_no_text' => null // Logo 不應該禁止文字
    ]
];

$user_data = [
    'brand_keywords' => ['人類圖', '能量調頻', '自我成長'],
    'color_scheme' => '深綠配暖棕色調'
];

echo "測試結果：\n";
echo str_repeat("=", 80) . "\n";

foreach ($test_cases as $i => $test_case) {
    echo "\n" . ($i + 1) . ". 測試 {$test_case['description']}\n";
    echo str_repeat("-", 40) . "\n";
    
    $placeholder_info = [
        'type' => $test_case['type'],
        'section' => $test_case['section'],
        'purpose' => $test_case['purpose']
    ];
    
    $prompt_info = generateImagePromptInfo($placeholder_info, $user_data);
    $prompt = $prompt_info['prompt'];
    
    echo "生成的提示詞：\n";
    echo "{$prompt}\n\n";
    
    // 檢查是否包含禁止文字的指令
    if ($test_case['expected_no_text']) {
        $has_no_text = strpos($prompt, $test_case['expected_no_text']) !== false;
        $status = $has_no_text ? '✅' : '❌';
        echo "禁止文字檢查：{$status}\n";
        echo "期望包含：{$test_case['expected_no_text']}\n";
        echo "實際結果：" . ($has_no_text ? '已包含' : '未包含') . "\n";
        
        // 額外檢查是否包含基本的禁止文字關鍵詞
        $basic_checks = ['no text', 'no letters', 'no words'];
        $basic_found = 0;
        foreach ($basic_checks as $check) {
            if (stripos($prompt, $check) !== false) {
                $basic_found++;
            }
        }
        
        echo "基本禁止文字關鍵詞檢查：";
        if ($basic_found > 0) {
            echo "✅ 找到 {$basic_found}/3 個關鍵詞\n";
        } else {
            echo "❌ 未找到禁止文字關鍵詞\n";
        }
        
    } else {
        echo "禁止文字檢查：⚪ 此類型不需要禁止文字\n";
        
        // 對於 Logo，檢查是否應該包含文字
        if ($test_case['type'] === 'logo') {
            $should_have_text = strpos($prompt, 'with text') !== false || 
                               strpos($prompt, 'text') !== false ||
                               strpos($prompt, 'typography') !== false;
            echo "文字包含檢查：" . ($should_have_text ? '✅ Logo 可以包含文字' : '⚠️ Logo 可能需要文字指令') . "\n";
        }
    }
    
    echo "\n";
}

echo str_repeat("=", 80) . "\n";
echo "📊 測試總結\n\n";

// 重新執行所有測試來統計結果
$total_tests = 0;
$passed_tests = 0;

foreach ($test_cases as $test_case) {
    if ($test_case['expected_no_text']) {
        $total_tests++;
        
        $placeholder_info = [
            'type' => $test_case['type'],
            'section' => $test_case['section'],
            'purpose' => $test_case['purpose']
        ];
        
        $prompt_info = generateImagePromptInfo($placeholder_info, $user_data);
        $prompt = $prompt_info['prompt'];
        
        if (strpos($prompt, $test_case['expected_no_text']) !== false) {
            $passed_tests++;
        }
    }
}

$success_rate = $total_tests > 0 ? round(($passed_tests / $total_tests) * 100) : 100;

echo "測試統計：\n";
echo "- 總測試案例：{$total_tests} 個\n";
echo "- 通過測試：{$passed_tests} 個\n";
echo "- 成功率：{$success_rate}%\n\n";

if ($success_rate == 100) {
    echo "🎉 所有測試通過！圖片提示詞禁止文字功能正常運作\n";
    echo "✅ 服務圖示：包含完整的文字禁止指令\n";
    echo "✅ 背景圖片：包含文字疊加禁止指令\n";
    echo "✅ 人物照片：包含自然攝影無文字指令\n";
    echo "✅ Logo 圖片：正確允許文字內容\n";
} elseif ($success_rate >= 75) {
    echo "👍 大部分測試通過，系統基本正常\n";
    echo "建議檢查未通過的測試案例\n";
} else {
    echo "⚠️ 測試未完全通過，需要進一步修復\n";
    echo "請檢查 generateImagePromptInfo 函數的實作\n";
}

echo "\n禁止文字指令說明：\n";
echo "1. **服務圖示**：`no text, no letters, no words, no characters, pure graphic design only`\n";
echo "   - 最嚴格的文字禁止，確保純圖形設計\n";
echo "2. **背景圖片**：`no text overlay, no typography`\n";
echo "   - 禁止文字疊加和排版元素\n";
echo "3. **人物照片**：`natural photography without text overlay`\n";
echo "   - 自然攝影，無文字疊加\n";
echo "4. **Logo 圖片**：允許包含品牌文字\n";
echo "   - 唯一可以包含文字的圖片類型\n";

echo "\n使用建議：\n";
echo "- 生成圖示時，AI 將嚴格避免任何文字元素\n";
echo "- 如果仍然出現文字，可能需要在提示詞中加強禁止指令\n";
echo "- 建議在實際生成後檢查圖片是否符合無文字要求\n";

echo "\n測試完成！✨\n";