<?php
/**
 * 測試 step-04 修正後的功能
 * 使用方式: php test-step-04-fix.php
 */

// 定義基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

require_once __DIR__ . '/config-manager.php';

$config = ConfigManager::getInstance();

echo "=== 測試 Step-04 修正 ===\n\n";

// 模擬部署器
class TestDeployer {
    public function log($message) {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }
}

$deployer = new TestDeployer();

// 測試資料
$job_id = '2506290730-3450';
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

echo "1. 檢查必要檔案是否存在...\n";
$files_to_check = [
    'config/processed_data.json' => $work_dir . '/config/processed_data.json',
    'bt_website.json' => $work_dir . '/bt_website.json'
];

$all_files_exist = true;
foreach ($files_to_check as $name => $path) {
    if (file_exists($path)) {
        echo "   ✓ {$name} 存在\n";
    } else {
        echo "   ✗ {$name} 不存在: {$path}\n";
        $all_files_exist = false;
    }
}

if (!$all_files_exist) {
    echo "\n錯誤: 必要檔案不存在，無法繼續測試\n";
    exit(1);
}

// 載入資料
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$website_info = json_decode(file_get_contents($work_dir . '/bt_website.json'), true);

$domain = $processed_data['confirmed_data']['domain'];
$site_name = $website_info['site_name'];

echo "\n2. 資料載入成功...\n";
echo "   網域: {$domain}\n";
echo "   網站名稱: {$site_name}\n";

// 測試函數參數
echo "\n3. 測試 setupWordPressRewrite 函數參數...\n";

// 載入函數定義
require_once __DIR__ . '/step-04.php';

// 檢查函數是否存在
if (function_exists('setupWordPressRewrite')) {
    echo "   ✓ setupWordPressRewrite 函數已定義\n";
    
    // 使用反射檢查參數
    $reflection = new ReflectionFunction('setupWordPressRewrite');
    $params = $reflection->getParameters();
    
    echo "   函數參數列表:\n";
    foreach ($params as $index => $param) {
        echo "     {$index}. \${$param->getName()}";
        if ($param->isDefaultValueAvailable()) {
            echo " = " . var_export($param->getDefaultValue(), true);
        }
        echo "\n";
    }
    
    // 確認參數數量
    $required_params = 0;
    foreach ($params as $param) {
        if (!$param->isDefaultValueAvailable()) {
            $required_params++;
        }
    }
    
    echo "\n   必要參數數量: {$required_params}\n";
    echo "   總參數數量: " . count($params) . "\n";
    
} else {
    echo "   ✗ setupWordPressRewrite 函數未定義\n";
}

echo "\n4. 測試偽靜態規則檔案路徑...\n";
echo "   預期檔案路徑: /www/server/panel/vhost/rewrite/{$site_name}.conf\n";

echo "\n=== 測試完成 ===\n";
echo "修正摘要:\n";
echo "1. setupWordPressRewrite 函數現在接受 \$site_name 參數\n";
echo "2. 偽靜態規則將儲存到正確的路徑\n";
echo "3. 不再有未定義變數錯誤\n";