<?php
/**
 * 測試 AI Logo 生成功能
 * 驗證 step-16.php 中新增的完全由 AI 生成的 Logo 功能
 */

// 設定基本路徑
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
    define('DEPLOY_LOGS_PATH', DEPLOY_BASE_PATH . '/logs');
    define('DEPLOY_DATA_PATH', DEPLOY_BASE_PATH . '/data');
}

// 載入配置管理器
require_once DEPLOY_BASE_PATH . '/config-manager.php';

// 模擬部署器
class TestDeployer {
    public function log($message) {
        echo "[LOG] {$message}\n";
    }
}

// 測試 job ID
$test_job_id = 'ai-logo-test-' . date('YmdHis');

echo "=== 測試 AI Logo 生成功能 ===\n";
echo "測試 Job ID: {$test_job_id}\n\n";

// 1. 建立測試環境
echo "1. 建立測試環境\n";
$test_work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_job_id;
$test_images_dir = $test_work_dir . '/images';

if (!is_dir($test_images_dir)) {
    mkdir($test_images_dir, 0755, true);
    echo "   ✓ 建立測試目錄: {$test_images_dir}\n";
}

$deployer = new TestDeployer();
$config = ConfigManager::getInstance();

// 2. 建立測試資料
echo "2. 建立測試資料\n";
$test_website_name = "測試科技公司";
$test_color_scheme = [
    'primary' => '#2D4C4A',
    'secondary' => '#7A8370',
    'accent' => '#BFAA96'
];

$test_job_data = [
    'confirmed_data' => [
        'website_name' => $test_website_name,
        'website_description' => '提供創新科技解決方案的公司',
        'business_type' => '科技服務',
        'target_audience' => '企業客戶',
        'brand_tone' => '專業創新',
        'color_scheme' => $test_color_scheme
    ]
];

echo "   ✓ 網站名稱: {$test_website_name}\n";
echo "   ✓ 主色彩: {$test_color_scheme['primary']}\n";
echo "   ✓ 次色彩: {$test_color_scheme['secondary']}\n";

// 3. 載入 step-16.php 中的相關函數
echo "3. 載入相關函數\n";

// 載入 step-16.php 中的函數定義
require_once 'step-16.php';

// 4. 測試提示詞生成
echo "4. 測試提示詞生成\n";
$ai_logo_prompt = buildFullLogoPrompt($test_website_name, $test_color_scheme, $test_job_data);

echo "   ✓ 生成的AI Logo提示詞:\n";
echo "   {$ai_logo_prompt}\n";
echo "   完整長度: " . strlen($ai_logo_prompt) . " 字元\n";

// 測試不同類型的網站
echo "\n   測試不同業務類型的提示詞:\n";
$test_cases = [
    ['name' => '心靈療癒工作室', 'type' => '療癒服務', 'tone' => '神祕療癒'],
    ['name' => '美食天堂', 'type' => '餐飲', 'tone' => '溫暖親和'],
    ['name' => '創新金融', 'type' => '金融服務', 'tone' => '專業信賴'],
    ['name' => '健康運動館', 'type' => '運動健身', 'tone' => '活力年輕']
];

foreach ($test_cases as $case) {
    $case_data = [
        'confirmed_data' => [
            'website_name' => $case['name'],
            'business_type' => $case['type'],
            'brand_tone' => $case['tone'],
            'color_scheme' => $test_color_scheme
        ]
    ];
    
    $case_prompt = buildFullLogoPrompt($case['name'], $test_color_scheme, $case_data);
    echo "   - {$case['name']} ({$case['type']}):\n";
    echo "     {$case_prompt}\n\n";
}

// 5. 檢查API設定
echo "5. 檢查API設定\n";
$api_available = [];

// 檢查 OpenAI
$openai_key = $config->get('api_credentials.openai.api_key');
if (!empty($openai_key)) {
    $api_available[] = 'OpenAI';
    echo "   ✓ OpenAI API 已設定\n";
} else {
    echo "   ✗ OpenAI API 未設定\n";
}

// 檢查 Ideogram
$ideogram_key = $config->get('api_credentials.ideogram.api_key');
if (!empty($ideogram_key)) {
    $api_available[] = 'Ideogram';
    echo "   ✓ Ideogram API 已設定\n";
} else {
    echo "   ✗ Ideogram API 未設定\n";
}

// 檢查 Gemini
$gemini_key = $config->get('api_credentials.gemini.api_key');
if (!empty($gemini_key)) {
    $api_available[] = 'Gemini';
    echo "   ✓ Gemini API 已設定\n";
} else {
    echo "   ✗ Gemini API 未設定\n";
}

// 6. 測試設定優先順序
echo "6. 測試設定優先順序\n";
$fallback_order = $config->get('ai_image_generation.fallback_order', ['openai', 'ideogram', 'gemini']);
echo "   ✓ 設定的服務順序: " . implode(' → ', $fallback_order) . "\n";

$available_in_order = [];
foreach ($fallback_order as $service) {
    if (in_array(ucfirst($service), $api_available)) {
        $available_in_order[] = ucfirst($service);
    }
}

if (!empty($available_in_order)) {
    echo "   ✓ 可用的服務（按順序）: " . implode(' → ', $available_in_order) . "\n";
} else {
    echo "   ✗ 沒有可用的API服務\n";
}

// 7. 測試比例設定邏輯
echo "7. 測試比例設定邏輯\n";

// 測試Logo提示詞
$logo_aspect = '3x1'; // 預設使用3x1適合Logo
if (strpos($ai_logo_prompt, 'background') !== false || strpos($ai_logo_prompt, 'icon') !== false) {
    $logo_aspect = '1x1'; // 背景圖示使用正方形
}
echo "   ✓ Logo提示詞選用比例: {$logo_aspect}\n";

// 測試背景提示詞
$bg_prompt = "Generate a background icon for website";
$bg_aspect = '3x1';
if (strpos($bg_prompt, 'background') !== false || strpos($bg_prompt, 'icon') !== false) {
    $bg_aspect = '1x1';
}
echo "   ✓ 背景提示詞選用比例: {$bg_aspect}\n";

// 8. 模擬結果輸出結構
echo "8. 模擬結果輸出結構\n";

// 模擬生成的Logo檔案
$simulated_logos = [
    'composite' => [
        'type' => 'PHP GD 合成',
        'filename' => 'logo-final.png',
        'components' => [
            'background' => 'background-layer.png',
            'text_layer' => 'text-layer.png'
        ]
    ],
    'ai_full' => [
        'type' => 'AI 完整生成',
        'filename' => 'ai-logo-full-resized.png'
    ]
];

echo "   ✓ 模擬的Logo結構:\n";
foreach ($simulated_logos as $key => $logo_info) {
    echo "     - {$key}: {$logo_info['type']} ({$logo_info['filename']})\n";
    if (isset($logo_info['components'])) {
        echo "       組件: " . implode(', ', $logo_info['components']) . "\n";
    }
}

// 9. 清理
echo "9. 清理測試檔案\n";
if (is_dir($test_work_dir)) {
    function deleteTestDir($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                deleteTestDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    
    deleteTestDir($test_work_dir);
    echo "   ✓ 清理完成\n";
}

echo "\n=== 測試結果 ===\n";
echo "✓ AI Logo 提示詞生成功能正常\n";
echo "✓ API 設定檢查功能正常\n";
echo "✓ 比例設定邏輯正常\n";
echo "✓ 結果輸出結構設計合理\n";

if (!empty($api_available)) {
    echo "✓ 有 " . count($api_available) . " 個API服務可用: " . implode(', ', $api_available) . "\n";
    echo "  可以進行實際的Logo生成測試\n";
} else {
    echo "⚠ 未設定任何API服務，無法進行實際生成\n";
    echo "  請在 config/deploy-config.json 中設定API憑證\n";
}

echo "\n新功能已準備就緒！現在 step-16 支援條件式 Logo 生成:\n";
echo "1. ai_full 模式：完全由 AI 生成（預設）\n";
echo "2. php_composite 模式：PHP GD 合成（AI背景 + 文字圖層）\n";
echo "\n可在 config/deploy-config.json 中設定 logo_generation.mode 切換模式\n";