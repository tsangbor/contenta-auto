<?php
/**
 * 即夢 AI 認證調試腳本 - 詳細分析金鑰問題
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once __DIR__ . '/config-manager.php';

echo "=== 即夢 AI 認證調試分析 ===\n\n";

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey_raw = $jimeng_credentials['SecretAccessKey'] ?? '';
$SecretAccessKey_decoded = base64_decode($SecretAccessKey_raw);

echo "1. 金鑰基本資訊:\n";
echo "   AccessKeyID: {$AccessKeyID}\n";
echo "   AccessKeyID 長度: " . strlen($AccessKeyID) . " 字元\n";
echo "   SecretAccessKey (原始): {$SecretAccessKey_raw}\n";
echo "   SecretAccessKey (原始) 長度: " . strlen($SecretAccessKey_raw) . " 字元\n";
echo "   SecretAccessKey (解碼): {$SecretAccessKey_decoded}\n";
echo "   SecretAccessKey (解碼) 長度: " . strlen($SecretAccessKey_decoded) . " 字元\n\n";

// 檢查 Base64 有效性
echo "2. Base64 解碼驗證:\n";
$is_valid_base64 = base64_encode(base64_decode($SecretAccessKey_raw)) === $SecretAccessKey_raw;
echo "   Base64 格式: " . ($is_valid_base64 ? "✅ 有效" : "❌ 無效") . "\n";

if (!$is_valid_base64) {
    echo "   >> 可能問題: SecretAccessKey 不是有效的 Base64 格式\n";
}
echo "\n";

// 檢查金鑰格式
echo "3. 金鑰格式檢查:\n";
echo "   AccessKeyID 是否以 'AK' 開頭: " . (strpos($AccessKeyID, 'AK') === 0 ? "✅ 是" : "❌ 否") . "\n";
echo "   AccessKeyID 是否為 40 字元: " . (strlen($AccessKeyID) === 40 ? "✅ 是" : "❌ 否 (實際: " . strlen($AccessKeyID) . ")") . "\n";
echo "   SecretAccessKey 解碼後長度是否合理: " . (strlen($SecretAccessKey_decoded) >= 32 ? "✅ 是" : "❌ 否") . "\n\n";

// 測試不同的金鑰處理方式
function testSignature($ak, $sk, $label) {
    global $AccessKeyID;
    
    echo "4. 測試簽名生成 - {$label}:\n";
    
    // 基礎參數
    $Host = "visual.volcengineapi.com";
    $ContentType = "application/json";
    $Service = "cv";
    $Region = "cn-north-1";
    
    $action = "CVProcess";
    $version = "2022-08-31";
    
    $requestBody = [
        "req_key" => "high_aes_general_v30l_zt2i",
        "prompt" => "test",
        "return_url" => true
    ];
    
    $body = json_encode($requestBody);
    $datetime = gmdate('Ymd\THis\Z');
    $shortDate = substr($datetime, 0, 8);
    
    $query = [
        'Action' => $action,
        'Version' => $version
    ];
    ksort($query);
    $queryString = http_build_query($query);
    
    $xContentSha256 = hash('sha256', $body);
    
    echo "   DateTime: {$datetime}\n";
    echo "   ShortDate: {$shortDate}\n";
    echo "   ContentSHA256: {$xContentSha256}\n";
    echo "   Query: {$queryString}\n";
    
    // 構建 Canonical Request
    $canonicalRequestStr = join("\n", [
        'POST',
        '/',
        $queryString,
        join("\n", [
            'content-type:' . $ContentType,
            'host:' . $Host,
            'x-content-sha256:' . $xContentSha256,
            'x-date:' . $datetime
        ]),
        '',
        'content-type;host;x-content-sha256;x-date',
        $xContentSha256
    ]);
    
    $hashedCanonicalRequest = hash("sha256", $canonicalRequestStr);
    $credentialScope = join('/', [$shortDate, $Region, $Service, 'request']);
    $stringToSign = join("\n", ['HMAC-SHA256', $datetime, $credentialScope, $hashedCanonicalRequest]);
    
    echo "   HashedCanonicalRequest: {$hashedCanonicalRequest}\n";
    echo "   CredentialScope: {$credentialScope}\n";
    echo "   StringToSign 長度: " . strlen($stringToSign) . "\n";
    
    // 簽名計算
    $kDate = hash_hmac("sha256", $shortDate, $sk, true);
    $kRegion = hash_hmac("sha256", $Region, $kDate, true);
    $kService = hash_hmac("sha256", $Service, $kRegion, true);
    $kSigning = hash_hmac("sha256", 'request', $kService, true);
    $signature = hash_hmac("sha256", $stringToSign, $kSigning);
    
    echo "   最終簽名: {$signature}\n";
    echo "   簽名長度: " . strlen($signature) . "\n";
    
    $authorization = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", 
        $ak . '/' . $credentialScope, 'content-type;host;x-content-sha256;x-date', $signature);
    
    echo "   Authorization 長度: " . strlen($authorization) . "\n\n";
    
    // 發送測試請求
    $headers = [
        'Host: ' . $Host,
        'X-Date: ' . $datetime,
        'X-Content-Sha256: ' . $xContentSha256,
        'Content-Type: ' . $ContentType,
        'Authorization: ' . $authorization
    ];
    
    $ch = curl_init();
    $fullUrl = "https://{$Host}/?" . $queryString;
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    echo "5. 請求結果 - {$label}:\n";
    echo "   HTTP 狀態: {$httpCode}\n";
    echo "   cURL 錯誤: " . ($curlError ?: "無") . "\n";
    
    $result = json_decode($response, true);
    if (isset($result['ResponseMetadata']['Error'])) {
        $error = $result['ResponseMetadata']['Error'];
        echo "   錯誤代碼: {$error['Code']}\n";
        echo "   錯誤訊息: {$error['Message']}\n";
        echo "   錯誤號碼: {$error['CodeN']}\n";
    } elseif (isset($result['code'])) {
        echo "   錯誤代碼: {$result['code']}\n";
        echo "   錯誤訊息: {$result['message']}\n";
    }
    
    echo "   完整回應: " . substr($response, 0, 300) . "...\n";
    echo str_repeat("-", 60) . "\n\n";
    
    return $httpCode;
}

// 測試不同的金鑰處理方式
echo "開始測試不同的金鑰處理方式...\n\n";

$testResults = [];

// 測試 1: 原始 base64 編碼的金鑰
$testResults['raw'] = testSignature($AccessKeyID, $SecretAccessKey_raw, "原始 Base64 金鑰");

// 測試 2: 解碼後的金鑰
$testResults['decoded'] = testSignature($AccessKeyID, $SecretAccessKey_decoded, "解碼後的金鑰");

// 測試 3: 解碼後加 == 的金鑰
$testResults['decoded_padded'] = testSignature($AccessKeyID, $SecretAccessKey_decoded . '==', "解碼後加 == 的金鑰");

// 結果總結
echo "=== 測試結果總結 ===\n";
foreach ($testResults as $method => $httpCode) {
    $status = $httpCode === 200 ? "✅ 成功" : ($httpCode === 401 ? "❌ 認證失敗" : "❌ 其他錯誤");
    echo "{$method}: HTTP {$httpCode} - {$status}\n";
}

echo "\n=== 建議排查步驟 ===\n";
echo "1. 確認金鑰是從火山引擎控制台正確複製\n";
echo "2. 檢查帳戶是否已實名認證\n";
echo "3. 確認「視覺智能-圖像生成」服務已開通\n";
echo "4. 檢查 API 調用配額是否充足\n";
echo "5. 確認金鑰有對應服務的權限\n";
echo "6. 嘗試重新生成 AccessKey 和 SecretAccessKey\n";

echo "\n調試完成。\n";
?>