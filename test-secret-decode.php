<?php
/**
 * 測試 SecretAccessKey 的不同解碼方式
 * 即使正式開通，仍需要正確的金鑰格式
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once __DIR__ . '/config-manager.php';

echo "=== SecretAccessKey 解碼測試 ===\n\n";

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey_raw = $jimeng_credentials['SecretAccessKey'] ?? '';

echo "原始 SecretAccessKey: {$SecretAccessKey_raw}\n";
echo "原始長度: " . strlen($SecretAccessKey_raw) . "\n\n";

// 嘗試不同的解碼方式
$decoding_methods = [
    '直接使用' => $SecretAccessKey_raw,
    'Base64 解碼' => base64_decode($SecretAccessKey_raw),
    'URL 解碼' => urldecode($SecretAccessKey_raw),
    'Base64 二次解碼' => base64_decode(base64_decode($SecretAccessKey_raw)),
];

// 安全處理 hex2bin 可能的錯誤
try {
    $decoding_methods['Hex 解碼'] = hex2bin($SecretAccessKey_raw);
} catch (Exception $e) {
    $decoding_methods['Hex 解碼'] = false;
}

foreach ($decoding_methods as $method => $decoded_key) {
    if ($decoded_key === false || $decoded_key === null) {
        echo "{$method}: ❌ 解碼失敗\n";
        continue;
    }
    
    echo "{$method}:\n";
    echo "  結果: " . (is_string($decoded_key) ? $decoded_key : '[不是字符串]') . "\n";
    echo "  長度: " . strlen($decoded_key) . "\n";
    echo "  是否可顯示: " . (ctype_print($decoded_key) ? '是' : '否') . "\n";
    
    // 檢查是否看起來像有效的密鑰
    $looks_valid = strlen($decoded_key) >= 20 && strlen($decoded_key) <= 64;
    echo "  看起來有效: " . ($looks_valid ? '✅' : '❌') . "\n\n";
}

// 檢查金鑰格式
echo "=== 金鑰格式分析 ===\n";
echo "AccessKeyID 格式檢查:\n";
echo "  以 'AK' 開頭: " . (strpos($AccessKeyID, 'AK') === 0 ? '✅' : '❌') . "\n";
echo "  長度合理 (40-50字元): " . (strlen($AccessKeyID) >= 40 && strlen($AccessKeyID) <= 50 ? '✅' : '❌') . "\n";
echo "  全為大寫字母數字: " . (ctype_alnum($AccessKeyID) && ctype_upper($AccessKeyID) ? '✅' : '❌') . "\n\n";

// 測試可能的正確格式
echo "=== 可能的問題分析 ===\n";

// 1. 檢查是否為新版本的金鑰格式
if (strlen($AccessKeyID) > 40) {
    echo "1. AccessKeyID 過長 (通常為40字元)\n";
    echo "   可能是新版本格式或包含額外字元\n\n";
}

// 2. 檢查 SecretAccessKey 是否為雙重編碼
$double_decoded = base64_decode(base64_decode($SecretAccessKey_raw));
if ($double_decoded && strlen($double_decoded) > 0) {
    echo "2. SecretAccessKey 可能被雙重 Base64 編碼\n";
    echo "   雙重解碼結果: {$double_decoded}\n\n";
}

// 3. 檢查是否需要特殊處理
echo "3. 建議檢查項目:\n";
echo "   - 確認金鑰從火山引擎控制台 '訪問控制' -> 'API密鑰管理' 複製\n";
echo "   - 檢查是否選擇了正確的服務（視覺智能）\n";
echo "   - 確認帳戶已通過實名認證\n";
echo "   - 檢查是否在正確的區域（中國大陸 vs 海外）\n";
echo "   - 嘗試重新生成 AccessKey 和 SecretAccessKey\n\n";

// 4. 生成一個測試簽名來驗證
echo "4. 簽名測試（使用原始金鑰）:\n";
$test_string = "test_signature_" . time();
$test_signature = hash_hmac('sha256', $test_string, $SecretAccessKey_raw);
echo "   測試字符串: {$test_string}\n";
echo "   生成簽名: {$test_signature}\n";
echo "   簽名長度: " . strlen($test_signature) . "\n\n";

echo "5. 建議下一步:\n";
echo "   如果以上都正確，問題可能在於:\n";
echo "   - 服務未正確開通或配置\n";
echo "   - 需要在控制台中為 AccessKey 分配特定權限\n";
echo "   - 帳戶餘額不足或未配置計費\n";
echo "   - IP 白名單或其他安全限制\n";

echo "\n調試完成。\n";
?>