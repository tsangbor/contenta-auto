<?php
/**
 * 檢查即夢 AI 帳戶狀態和免費試用情況
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once __DIR__ . '/config-manager.php';

$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

echo "=== 即夢 AI 帳戶狀態檢查 ===\n\n";

// 基本信息
echo "1. API 金鑰狀態:\n";
echo "   AccessKeyID: " . ($jimeng_credentials['AccessKeyID'] ?? '未配置') . "\n";
echo "   SecretAccessKey: " . (empty($jimeng_credentials['SecretAccessKey']) ? '未配置' : '已配置') . "\n\n";

// 檢查可能的問題
echo "2. 免費試用可能遇到的問題:\n";
echo "   ✓ 試用期是否已過期？\n";
echo "   ✓ 免費額度是否已用完？\n";
echo "   ✓ 是否需要升級到付費版本？\n";
echo "   ✓ API 金鑰是否從正確的控制台獲取？\n\n";

// 建議的檢查步驟
echo "3. 建議檢查步驟:\n";
echo "   1. 登入即夢 AI 控制台 (https://console.volcengine.com/)\n";
echo "   2. 檢查「視覺智能」->「圖像生成」服務狀態\n";
echo "   3. 查看 API 調用記錄和剩餘額度\n";
echo "   4. 確認 AccessKeyID 和 SecretAccessKey 是否最新\n";
echo "   5. 檢查服務是否需要實名認證或升級\n\n";

// 測試基礎連接
echo "4. 測試基礎連接...\n";

$test_url = 'https://visual.volcengineapi.com/';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 || $http_code == 405) {
    echo "   ✅ 火山引擎 API 端點可連接 (HTTP: $http_code)\n";
} else {
    echo "   ❌ 火山引擎 API 端點連接失敗 (HTTP: $http_code)\n";
}

echo "\n5. 常見解決方案:\n";
echo "   • 如果是試用期過期：需要升級到付費版本\n";
echo "   • 如果是額度用完：等待下個週期或充值\n";
echo "   • 如果是權限問題：檢查帳戶實名認證狀態\n";
echo "   • 如果是金鑰錯誤：重新生成 AccessKey\n\n";

echo "=== 檢查完成 ===\n";
?>