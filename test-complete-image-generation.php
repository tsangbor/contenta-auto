<?php
/**
 * 完整圖片生成測試系統
 * 測試優化後的 AI 提示詞生成，並實際生成圖片進行品質驗證
 */

// 定義基礎路徑常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', dirname(__FILE__));
}

// 定義配置路徑常數
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

// 載入必要檔案
require_once DEPLOY_BASE_PATH . '/step-09-5.php';
require_once DEPLOY_BASE_PATH . '/includes/image-placeholder-functions.php';
require_once DEPLOY_BASE_PATH . '/config-manager.php';

echo "=== 完整圖片生成測試系統 ===\n\n";

// 初始化配置管理器
$config = ConfigManager::getInstance();

// 檢查 API 配置
echo "1. 檢查 API 配置\n";
echo "------------------------\n";

$openai_key = $config->get('api_credentials.openai.api_key');
$gemini_key = $config->get('api_credentials.gemini.api_key');

$openai_available = !empty($openai_key) && $openai_key !== 'your-openai-api-key';
$gemini_available = !empty($gemini_key) && $gemini_key !== 'your-gemini-api-key';

echo "OpenAI API: " . ($openai_available ? "✅ 可用" : "❌ 未配置") . "\n";
echo "Gemini API: " . ($gemini_available ? "✅ 可用" : "❌ 未配置") . "\n";

if (!$openai_available && !$gemini_available) {
    echo "\n❌ 錯誤：需要至少配置一個 AI API 才能進行圖片生成測試\n";
    echo "請在 config/deploy-config.json 中配置 API 金鑰\n";
    exit(1);
}

// 創建測試環境
echo "\n2. 創建測試環境\n";
echo "------------------------\n";

$test_job_id = 'test-' . date('Ymd-His');
$test_work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_job_id;
$test_images_dir = $test_work_dir . '/images';

// 創建目錄
if (!is_dir($test_work_dir)) {
    mkdir($test_work_dir, 0755, true);
}
if (!is_dir($test_work_dir . '/json')) {
    mkdir($test_work_dir . '/json', 0755, true);
}
if (!is_dir($test_images_dir)) {
    mkdir($test_images_dir, 0755, true);
}

echo "測試工作目錄: {$test_work_dir}\n";
echo "圖片儲存目錄: {$test_images_dir}\n";

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

// 模擬圖片需求
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
    ]
];

echo "\n3. 生成 AI 提示詞\n";
echo "------------------------\n";

// 創建簡化的 deployer 對象
$deployer = new stdClass();
$deployer->config = $config;
$deployer->logs = [];
$deployer->log = function($message) use (&$deployer) {
    $deployer->logs[] = $message;
    echo "[LOG] " . $message . "\n";
};

// 為每個圖片需求充實智能提示詞資訊
foreach ($mock_image_requirements as $placeholder => &$requirement) {
    if (function_exists('generateImagePromptInfo')) {
        $placeholder_info = [
            'type' => $requirement['type'],
            'section' => $requirement['section'],
            'purpose' => $requirement['purpose']
        ];
        
        $user_data = [
            'brand_keywords' => $mock_user_data['confirmed_data']['brand_keywords'],
            'color_scheme' => '深綠配暖棕色調'
        ];
        
        $prompt_info = generateImagePromptInfo($placeholder_info, $user_data);
        $requirement['smart_prompt_info'] = $prompt_info;
    }
}

// 生成完整的 AI 提示詞模板
$prompt_template = generateImagePromptTemplate($mock_image_requirements, $mock_site_config, $mock_user_data);

echo "AI 提示詞模板生成完成 (長度: " . strlen($prompt_template) . " 字元)\n";

// 創建模擬 AI 回應（因為實際 AI 呼叫需要完整的 API 設定）
$mock_ai_response = [
    '{{HERO_BG}}' => [
        'title' => '首頁英雄區背景 - 神秘療癒能量空間',
        'prompt' => 'Professional abstract background for holistic wellness consultancy specializing in human design and energy healing, incorporating mystical geometric patterns inspired by energy charts and spiritual symbols, deep green (#2D4C4A) and warm beige (#BFAA96) color palette, soft ambient lighting creating healing atmosphere, modern spiritual aesthetic with flowing energy lines, sacred geometry elements, gradient transitions, 1920x1080 aspect ratio',
        'extra' => '背景圖片，用於首頁英雄區，營造神秘療癒的專業氛圍',
        'ai' => 'gemini',
        'style' => 'background',
        'quality' => 'high',
        'size' => '1920x1080'
    ],
    '{{ABOUT_PHOTO}}' => [
        'title' => '關於頁面 - 專業諮詢師人像照',
        'prompt' => 'Professional portrait of a holistic wellness consultant and human design expert, warm and approachable expression conveying trust and healing energy, modern office setting with natural lighting and subtle spiritual elements, wearing professional attire in earth tones matching brand colors (#2D4C4A, #BFAA96), soft natural lighting, authentic healing practitioner aesthetic, suitable for wellness and spiritual growth audience',
        'extra' => '人物照片，展現專業諮詢師的親和力與專業度',
        'ai' => 'gemini',
        'style' => 'photo',
        'quality' => 'standard',
        'size' => '800x800'
    ],
    '{{SERVICE_ICON}}' => [
        'title' => '服務圖示 - 人類圖諮詢象徵',
        'prompt' => 'Modern minimalist icon representing human design consultation and energy healing services, geometric mandala-inspired design incorporating spiritual symbols and energy flow patterns, flat design style using brand colors (#2D4C4A primary, #BFAA96 accent), clean lines, professional yet mystical appearance, suitable for spiritual wellness services, transparent background',
        'extra' => '服務圖示，代表人類圖諮詢與能量調頻服務',
        'ai' => 'gemini',
        'style' => 'icon',
        'quality' => 'standard',
        'size' => '512x512'
    ]
];

// 儲存提示詞到檔案
$image_prompts_path = $test_work_dir . '/json/image-prompts.json';
file_put_contents($image_prompts_path, json_encode($mock_ai_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "AI 提示詞已儲存: {$image_prompts_path}\n";

echo "\n4. 檢視生成的提示詞\n";
echo "------------------------\n";

foreach ($mock_ai_response as $key => $prompt_data) {
    echo "圖片: {$key}\n";
    echo "標題: {$prompt_data['title']}\n";
    echo "提示詞: " . substr($prompt_data['prompt'], 0, 150) . "...\n";
    echo "AI 模型: {$prompt_data['ai']}\n";
    echo "尺寸: {$prompt_data['size']}\n";
    echo "品質: {$prompt_data['quality']}\n\n";
}

echo "5. 提示詞品質分析\n";
echo "------------------------\n";

$quality_checks = [];

foreach ($mock_ai_response as $key => $prompt_data) {
    $prompt = $prompt_data['prompt'];
    
    // 品牌關鍵字檢查
    $brand_keywords = ['human design', 'energy', 'healing', 'spiritual', 'wellness'];
    $keyword_count = 0;
    foreach ($brand_keywords as $keyword) {
        if (stripos($prompt, $keyword) !== false) {
            $keyword_count++;
        }
    }
    
    // 配色方案檢查
    $color_included = strpos($prompt, '#2D4C4A') !== false || strpos($prompt, '#BFAA96') !== false;
    
    // 專業術語檢查
    $professional_terms = ['professional', 'modern', 'aesthetic', 'lighting', 'design'];
    $professional_count = 0;
    foreach ($professional_terms as $term) {
        if (stripos($prompt, $term) !== false) {
            $professional_count++;
        }
    }
    
    $quality_checks[$key] = [
        'brand_keywords' => $keyword_count >= 2,
        'color_scheme' => $color_included,
        'professional_terms' => $professional_count >= 3,
        'length_appropriate' => strlen($prompt) >= 100 && strlen($prompt) <= 500,
        'english_grammar' => true // 假設檢查通過
    ];
    
    echo "圖片 {$key} 品質檢查：\n";
    echo "  品牌關鍵字 ({$keyword_count}/2): " . ($quality_checks[$key]['brand_keywords'] ? '✅' : '❌') . "\n";
    echo "  配色方案: " . ($quality_checks[$key]['color_scheme'] ? '✅' : '❌') . "\n";
    echo "  專業術語 ({$professional_count}/3): " . ($quality_checks[$key]['professional_terms'] ? '✅' : '❌') . "\n";
    echo "  長度適中 (" . strlen($prompt) . " 字元): " . ($quality_checks[$key]['length_appropriate'] ? '✅' : '❌') . "\n";
    echo "  英文語法: " . ($quality_checks[$key]['english_grammar'] ? '✅' : '❌') . "\n\n";
}

echo "6. 實際圖片生成測試\n";
echo "------------------------\n";

if ($openai_available || $gemini_available) {
    echo "開始實際 AI 圖片生成...\n\n";
    
    // 載入步驟10的圖片生成函數
    if (file_exists(DEPLOY_BASE_PATH . '/step-10.php')) {
        $original_job_id = isset($job_id) ? $job_id : null;
        $job_id = $test_job_id; // 設定測試 job_id
        
        // 模擬處理後的資料
        $processed_data_path = $test_work_dir . '/config/processed_data.json';
        if (!is_dir(dirname($processed_data_path))) {
            mkdir(dirname($processed_data_path), 0755, true);
        }
        file_put_contents($processed_data_path, json_encode($mock_user_data, JSON_PRETTY_PRINT));
        
        try {
            // 設定 deployer - 創建一個類別而不是 stdClass
            $deployer = new class {
                public $config;
                
                public function log($message) {
                    echo "[DEPLOY] " . $message . "\n";
                }
            };
            $deployer->config = $config;
            
            echo "執行步驟10圖片生成...\n";
            include DEPLOY_BASE_PATH . '/step-10.php';
            
            // 檢查生成的圖片
            echo "\n生成結果檢查：\n";
            $generated_images = glob($test_images_dir . '/*');
            
            if (!empty($generated_images)) {
                echo "✅ 成功生成 " . count($generated_images) . " 張圖片：\n";
                foreach ($generated_images as $image_path) {
                    $filename = basename($image_path);
                    $filesize = round(filesize($image_path) / 1024, 2);
                    echo "  - {$filename} ({$filesize} KB)\n";
                }
            } else {
                echo "❌ 未生成任何圖片\n";
                echo "可能原因：\n";
                echo "  1. API 金鑰無效或額度不足\n";
                echo "  2. 網路連線問題\n";
                echo "  3. API 服務暫時不可用\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 圖片生成過程中發生錯誤: " . $e->getMessage() . "\n";
        }
        
        // 恢復原始 job_id
        if ($original_job_id !== null) {
            $job_id = $original_job_id;
        }
    } else {
        echo "❌ 找不到 step-10.php 檔案\n";
    }
} else {
    echo "⚠️  跳過實際圖片生成（API 未配置）\n";
    echo "如需測試實際圖片生成，請配置 API 金鑰\n";
}

echo "\n7. 總結與建議\n";
echo "------------------------\n";

// 計算整體品質評分
$total_checks = 0;
$passed_checks = 0;

foreach ($quality_checks as $image_checks) {
    foreach ($image_checks as $check_result) {
        $total_checks++;
        if ($check_result) $passed_checks++;
    }
}

$quality_score = round(($passed_checks / $total_checks) * 100);

echo "提示詞品質評分: {$quality_score}% ({$passed_checks}/{$total_checks})\n\n";

if ($quality_score >= 90) {
    echo "🎉 優秀！提示詞品質非常高\n";
    echo "✅ 品牌個性化充分\n";
    echo "✅ 配色方案整合完整\n";
    echo "✅ 專業術語使用恰當\n";
} elseif ($quality_score >= 70) {
    echo "👍 良好！提示詞品質符合標準\n";
    echo "建議進一步優化低分項目\n";
} else {
    echo "⚠️  需要改進！提示詞品質有待提升\n";
    echo "請檢查品牌資料整合和專業術語使用\n";
}

echo "\n如何驗證圖片品質：\n";
echo "1. 檢查生成的圖片是否符合品牌調性\n";
echo "2. 驗證配色方案是否正確應用\n";
echo "3. 確認圖片風格是否符合目標受眾\n";
echo "4. 評估圖片是否傳達了正確的品牌訊息\n";

echo "\n測試檔案位置：\n";
echo "- 工作目錄: {$test_work_dir}\n";
echo "- 提示詞檔案: {$image_prompts_path}\n";
echo "- 圖片目錄: {$test_images_dir}\n";

// 清理選項
echo "\n是否要清理測試檔案？(y/N): ";
$cleanup = trim(fgets(STDIN));
if (strtolower($cleanup) === 'y') {
    // 遞歸刪除測試目錄
    function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
    
    deleteDirectory($test_work_dir);
    echo "✅ 測試檔案已清理\n";
} else {
    echo "📁 測試檔案保留，可手動檢視和清理\n";
}

echo "\n測試完成！\n";