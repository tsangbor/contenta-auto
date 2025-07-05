<?php
/**
 * 部署系統測試腳本
 * 使用方式: php test-deploy.php
 */

require_once __DIR__ . '/config-manager.php';

// 定義基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
define('DEPLOY_LOGS_PATH', DEPLOY_BASE_PATH . '/logs');
define('DEPLOY_DATA_PATH', DEPLOY_BASE_PATH . '/data');
define('DEPLOY_JSON_PATH', DEPLOY_BASE_PATH . '/json');

// 建立必要目錄
$required_dirs = [DEPLOY_CONFIG_PATH, DEPLOY_LOGS_PATH];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

echo "=== Contenta 部署系統測試 ===\n\n";

// 測試 1: 配置管理器
echo "1. 測試配置管理器...\n";
try {
    $config = ConfigManager::getInstance();
    echo "   ✓ 配置管理器初始化成功\n";
    
    // 測試設定和取得
    $config->set('test.key', 'test_value');
    $value = $config->get('test.key');
    
    if ($value === 'test_value') {
        echo "   ✓ 配置讀寫功能正常\n";
    } else {
        echo "   ✗ 配置讀寫功能異常\n";
    }
} catch (Exception $e) {
    echo "   ✗ 配置管理器錯誤: " . $e->getMessage() . "\n";
}

// 測試 2: 檢查檔案結構
echo "\n2. 檢查檔案結構...\n";

$required_files = [
    'contenta-deploy.php' => '主部署腳本',
    'config-manager.php' => '配置管理器',
    'step-00.php' => '步驟 00',
    'step-01.php' => '步驟 01',
    'step-19.php' => '步驟 19'
];

foreach ($required_files as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "   ✓ {$description}: {$file}\n";
    } else {
        echo "   ✗ 缺少檔案: {$file}\n";
    }
}

// 測試 3: 檢查 JSON 檔案
echo "\n3. 檢查 JSON 配置檔案...\n";

$json_files = [
    'site-config.json' => '網站配置',
    'image-prompts.json' => '圖片提示詞',
    'article-prompts.json' => '文章提示詞'
];

foreach ($json_files as $file => $description) {
    $file_path = DEPLOY_JSON_PATH . '/' . $file;
    if (file_exists($file_path)) {
        echo "   ✓ {$description}: {$file}\n";
        
        // 驗證 JSON 格式
        $content = file_get_contents($file_path);
        $json = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "     → JSON 格式正確\n";
        } else {
            echo "     → JSON 格式錯誤: " . json_last_error_msg() . "\n";
        }
    } else {
        echo "   ✗ 缺少檔案: {$file}\n";
    }
}

// 測試 4: 建立測試 Job 資料
echo "\n4. 建立測試 Job 資料...\n";

$test_job_id = date('ymdHi') . '-' . rand(1000, 9999);
$test_job_data = [
    "job_id" => $test_job_id,
    "confirmed_data" => [
        "website_name" => "測試網站 - " . date('Y-m-d H:i:s'),
        "website_description" => "這是一個自動化部署系統的測試網站",
        "domain_suggestions" => "test" . rand(100, 999) . ".tw",
        "user_email" => "test@example.com",
        "brand_keywords" => ["測試", "自動化", "部署"],
        "target_audience" => "開發人員和測試人員",
        "brand_personality" => "技術性、可靠、高效",
        "unique_value" => "提供完整的網站自動化部署解決方案",
        "service_categories" => ["網站建置", "自動化部署", "技術支援"],
        "page_list" => ["home", "about", "service", "contact"],
        "domain" => "test" . rand(100, 999) . ".tw"
    ]
];

$test_file_path = DEPLOY_DATA_PATH . '/' . $test_job_id . '.json';

// 確保 data 目錄存在
if (!is_dir(DEPLOY_DATA_PATH)) {
    mkdir(DEPLOY_DATA_PATH, 0755, true);
}

file_put_contents($test_file_path, json_encode($test_job_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

if (file_exists($test_file_path)) {
    echo "   ✓ 測試 Job 資料建立成功\n";
    echo "   → Job ID: {$test_job_id}\n";
    echo "   → 檔案: {$test_file_path}\n";
} else {
    echo "   ✗ 測試 Job 資料建立失敗\n";
}

// 測試 5: 執行簡單部署測試
echo "\n5. 執行部署系統基本測試...\n";

try {
    // 載入主部署腳本類別
    require_once __DIR__ . '/contenta-deploy.php';
    
    echo "   ✓ 部署腳本載入成功\n";
    echo "   → 可以使用以下指令測試完整部署:\n";
    echo "     php contenta-deploy.php {$test_job_id}\n";
    
} catch (Exception $e) {
    echo "   ✗ 部署腳本載入失敗: " . $e->getMessage() . "\n";
}

echo "\n=== 測試完成 ===\n";
echo "系統已準備就緒，可以開始部署！\n\n";

echo "下一步:\n";
echo "1. 複製 config/deploy-config-example.json 為 config/deploy-config.json\n";
echo "2. 填入正確的 API 憑證和伺服器資訊\n";
echo "3. 執行: php contenta-deploy.php {$test_job_id}\n";
echo "\n注意: 在測試模式下，某些步驟會跳過或使用模擬數據\n";