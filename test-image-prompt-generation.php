<?php
/**
 * 測試圖片提示詞生成系統
 * 驗證優化後的 AI 提示詞生成品質
 */

// 定義基礎路徑常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', dirname(__FILE__));
}

// 載入步驟9-5函數
require_once DEPLOY_BASE_PATH . '/step-09-5.php';

echo "=== 圖片提示詞生成系統測試 ===\n\n";

// 模擬用戶資料（基於實際案例）
$mock_user_data = [
    'confirmed_data' => [
        'website_name' => '腰言豁眾 - 自我能量探索與人類圖諮詢',
        'website_description' => '腰言豁眾專注於人類圖與能量調頻，協助個人認識自我、活出真我，提供諮詢、課程與自我成長資源，打造靈性與能量探索的專屬空間。',
        'domain' => 'yaoguo.tw',
        'brand_keywords' => ['人類圖', '能量調頻', '自我成長', '靈性探索', '個人品牌'],
        'target_audience' => '對自我認識、能量調整及靈性成長有興趣的上班族與自我探索者，尋找生活方向與內在平衡的人群。',
        'brand_personality' => '神秘、療癒、專業、溫暖、啟發性強',
        'unique_value' => '以結合占星、易經等多元調頻工具，提供獨特且深入的自我認識與能量調整方案，幫助個人活出真我。',
        'service_categories' => ['人類圖諮詢', '線上課程', '能量調頻工作坊'],
        'color_scheme' => [
            'primary' => '#2D4C4A',
            'secondary' => '#7A8370',
            'text' => '#1A1A1A',
            'accent' => '#BFAA96'
        ]
    ]
];

// 模擬 site-config.json
$mock_site_config = [
    'website_info' => [
        'industry_keywords' => ['身心靈', '諮詢服務', '個人成長', '能量工作']
    ]
];

// 模擬圖片需求（基於新的佔位符系統）
$mock_image_requirements = [
    '{{HERO_BG}}' => [
        'placeholder' => '{{HERO_BG}}',
        'pages' => ['home'],
        'contexts' => [
            [
                'page_type' => 'home',
                'section_name' => 'hero',
                'purpose' => 'background_image',
                'surrounding_text' => '腰言豁眾 自我能量探索與人類圖諮詢'
            ]
        ],
        'type' => 'background',
        'purpose' => 'background_image',
        'section' => 'hero',
        'final_priority' => 85
    ],
    '{{ABOUT_PHOTO}}' => [
        'placeholder' => '{{ABOUT_PHOTO}}',
        'pages' => ['about'],
        'contexts' => [
            [
                'page_type' => 'about',
                'section_name' => 'about',
                'purpose' => 'portrait_or_product',
                'surrounding_text' => '專業諮詢師 人類圖解讀'
            ]
        ],
        'type' => 'photo',
        'purpose' => 'portrait_or_product',
        'section' => 'about',
        'final_priority' => 75
    ],
    '{{SERVICE_ICON}}' => [
        'placeholder' => '{{SERVICE_ICON}}',
        'pages' => ['service'],
        'contexts' => [
            [
                'page_type' => 'service',
                'section_name' => 'service',
                'purpose' => 'service_icon',
                'surrounding_text' => '人類圖諮詢 能量調頻'
            ]
        ],
        'type' => 'icon',
        'purpose' => 'service_icon',
        'section' => 'service',
        'final_priority' => 60
    ],
    '{{LOGO}}' => [
        'placeholder' => '{{LOGO}}',
        'pages' => ['home', 'about'],
        'contexts' => [
            [
                'page_type' => 'header',
                'section_name' => 'header',
                'purpose' => 'brand_identity',
                'surrounding_text' => '腰言豁眾'
            ]
        ],
        'type' => 'logo',
        'purpose' => 'brand_identity',
        'section' => 'header',
        'final_priority' => 90
    ]
];

echo "1. 測試圖片上下文分析功能\n";
echo "------------------------\n";

$context_analysis = analyzeImageContextForPrompt($mock_image_requirements, $mock_user_data['confirmed_data']);
echo "分析結果：\n";
echo $context_analysis . "\n\n";

echo "2. 測試 AI 提示詞模板生成\n";
echo "------------------------\n";

$prompt_template = generateImagePromptTemplate($mock_image_requirements, $mock_site_config, $mock_user_data);
echo "生成的提示詞模板長度：" . strlen($prompt_template) . " 字元\n";
echo "模板預覽（前500字元）：\n";
echo substr($prompt_template, 0, 500) . "...\n\n";

echo "3. 檢查品牌資料整合\n";
echo "------------------------\n";

// 檢查關鍵品牌元素是否正確整合
$checks = [
    '網站名稱' => strpos($prompt_template, '腰言豁眾') !== false,
    '核心服務' => strpos($prompt_template, '人類圖諮詢') !== false,
    '品牌關鍵字' => strpos($prompt_template, '能量調頻') !== false,
    '目標受眾' => strpos($prompt_template, '自我探索者') !== false,
    '配色方案' => strpos($prompt_template, '#2D4C4A') !== false,
    '品牌個性' => strpos($prompt_template, '神秘、療癒') !== false
];

foreach ($checks as $item => $result) {
    $status = $result ? '✅' : '❌';
    echo "  {$status} {$item}: " . ($result ? '已整合' : '未找到') . "\n";
}

echo "\n4. 檢查提示詞結構\n";
echo "------------------------\n";

$structure_checks = [
    '任務目標' => strpos($prompt_template, 'AI 藝術總監任務') !== false,
    '品牌檔案' => strpos($prompt_template, '品牌深度檔案') !== false,
    '設計指引' => strpos($prompt_template, '分類設計指引') !== false,
    '精煉策略' => strpos($prompt_template, '提示詞精炼策略') !== false,
    '交付規範' => strpos($prompt_template, '最終交付規範') !== false,
    '品質保證' => strpos($prompt_template, '創意品質保證') !== false
];

foreach ($structure_checks as $section => $result) {
    $status = $result ? '✅' : '❌';
    echo "  {$status} {$section}: " . ($result ? '已包含' : '缺失') . "\n";
}

echo "\n5. 測試不同圖片類型的智能提示詞\n";
echo "------------------------\n";

// 載入圖片佔位符函數
$image_functions_file = DEPLOY_BASE_PATH . '/includes/image-placeholder-functions.php';
if (file_exists($image_functions_file)) {
    require_once $image_functions_file;
    
    foreach ($mock_image_requirements as $placeholder => $requirement) {
        $placeholder_info = [
            'type' => $requirement['type'],
            'section' => $requirement['section'],
            'purpose' => $requirement['purpose']
        ];
        
        $user_data = [
            'brand_keywords' => $mock_user_data['confirmed_data']['brand_keywords'],
            'color_scheme' => '深綠配暖棕色調'
        ];
        
        if (function_exists('generateImagePromptInfo')) {
            $prompt_info = generateImagePromptInfo($placeholder_info, $user_data);
            
            echo "  {$placeholder} ({$requirement['type']}):\n";
            echo "    提示詞: " . substr($prompt_info['prompt'], 0, 100) . "...\n";
            echo "    AI模型: {$prompt_info['ai']}\n";
            echo "    尺寸: {$prompt_info['size']}\n";
            echo "    品質: {$prompt_info['quality']}\n\n";
        }
    }
} else {
    echo "  ❌ 圖片佔位符函數庫未找到\n\n";
}

echo "6. 提示詞品質評估\n";
echo "------------------------\n";

$quality_metrics = [
    '品牌個性化程度' => (substr_count($prompt_template, '腰言豁眾') + 
                        substr_count($prompt_template, '人類圖') + 
                        substr_count($prompt_template, '能量調頻')) >= 3,
    '目標受眾針對性' => strpos($prompt_template, '上班族') !== false || 
                      strpos($prompt_template, '自我探索') !== false,
    '服務特色突出' => substr_count($prompt_template, '諮詢') + 
                    substr_count($prompt_template, '課程') + 
                    substr_count($prompt_template, '工作坊') >= 2,
    '配色方案整合' => strpos($prompt_template, '主色調') !== false,
    '專業術語使用' => strpos($prompt_template, '專業') !== false && 
                    strpos($prompt_template, '品質') !== false,
    '避免模板化' => strpos($prompt_template, '木子心') === false && 
                  strpos($prompt_template, '心理諮商') === false
];

$passed_count = 0;
foreach ($quality_metrics as $metric => $result) {
    $status = $result ? '✅' : '❌';
    echo "  {$status} {$metric}: " . ($result ? '通過' : '需改進') . "\n";
    if ($result) $passed_count++;
}

$quality_score = round(($passed_count / count($quality_metrics)) * 100);
echo "\n品質總分：{$quality_score}% ({$passed_count}/" . count($quality_metrics) . ")\n";

echo "\n7. 效能測試\n";
echo "------------------------\n";

$start_time = microtime(true);
for ($i = 0; $i < 10; $i++) {
    $test_prompt = generateImagePromptTemplate($mock_image_requirements, $mock_site_config, $mock_user_data);
}
$end_time = microtime(true);

$total_time = ($end_time - $start_time) * 1000;
$avg_time = $total_time / 10;

echo "執行 10 次提示詞生成測試\n";
echo "總時間: " . sprintf("%.2f", $total_time) . " ms\n";
echo "平均時間: " . sprintf("%.2f", $avg_time) . " ms\n";

echo "\n=== 測試總結 ===\n";

if ($quality_score >= 80) {
    echo "✅ 圖片提示詞生成系統品質優秀！\n";
    echo "✅ 成功整合用戶真實品牌資料\n";
    echo "✅ 提示詞結構完整且專業\n";
    echo "✅ 避免了模板化內容問題\n";
} else {
    echo "⚠️  系統需要進一步優化\n";
    echo "建議改進低分項目以提升整體品質\n";
}

echo "\n主要改進點：\n";
echo "1. 深度整合用戶 confirmed_data 中的所有品牌資訊\n";
echo "2. 基於新的圖片佔位符系統進行智能分析\n";
echo "3. 結構化的提示詞模板，確保專業性與一致性\n";
echo "4. 品牌個性化策略，避免通用模板內容\n";
echo "5. 完整的品質檢查與交付規範\n";

echo "\n測試完成！\n";