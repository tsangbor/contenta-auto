<?php
/**
 * 詳細驗證即夢 AI 簽名生成過程
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once __DIR__ . '/config-manager.php';

echo "=== 詳細簽名生成驗證 ===\n\n";

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey = $jimeng_credentials['SecretAccessKey'] ?? '';

echo "金鑰信息:\n";
echo "AccessKeyID: {$AccessKeyID}\n";
echo "SecretAccessKey: {$SecretAccessKey}\n";
echo "AccessKeyID 長度: " . strlen($AccessKeyID) . "\n";
echo "SecretAccessKey 長度: " . strlen($SecretAccessKey) . "\n\n";

/**
 * 完全按照官方範例的簽名函數，但加入詳細調試
 */
function requestWithDebug($method, $query, $header, $ak, $sk, $action, $version, $body)
{
    echo "=== 開始簽名生成過程 ===\n";
    
    // 基礎參數
    $Service = "cv";
    $Region = "cn-north-1";
    $Host = "visual.volcengineapi.com";
    $ContentType = "application/json";
    
    echo "基礎參數:\n";
    echo "  Service: {$Service}\n";
    echo "  Region: {$Region}\n";
    echo "  Host: {$Host}\n";
    echo "  ContentType: {$ContentType}\n\n";
    
    $credential = [
        'accessKeyId' => $ak,
        'secretKeyId' => $sk,
        'service' => $Service,
        'region' => $Region,
    ];
    
    // 查詢參數處理
    $query = array_merge($query, [
        'Action' => $action,
        'Version' => $version
    ]);
    ksort($query);
    
    echo "查詢參數 (排序後):\n";
    foreach ($query as $k => $v) {
        echo "  {$k}: {$v}\n";
    }
    echo "\n";
    
    $requestParam = [
        'body' => $body,
        'host' => $Host,
        'path' => '/',
        'method' => $method,
        'contentType' => $ContentType,
        'date' => gmdate('Ymd\THis\Z'),
        'query' => $query
    ];
    
    echo "請求參數:\n";
    echo "  method: {$requestParam['method']}\n";
    echo "  path: {$requestParam['path']}\n";
    echo "  host: {$requestParam['host']}\n";
    echo "  contentType: {$requestParam['contentType']}\n";
    echo "  date: {$requestParam['date']}\n";
    echo "  body 長度: " . strlen($requestParam['body']) . "\n\n";
    
    // 簽名相關參數
    $xDate = $requestParam['date'];
    $shortXDate = substr($xDate, 0, 8);
    $xContentSha256 = hash('sha256', $requestParam['body']);
    
    echo "簽名基礎數據:\n";
    echo "  xDate: {$xDate}\n";
    echo "  shortXDate: {$shortXDate}\n";
    echo "  xContentSha256: {$xContentSha256}\n\n";
    
    $signResult = [
        'Host' => $requestParam['host'],
        'X-Content-Sha256' => $xContentSha256,
        'X-Date' => $xDate,
        'Content-Type' => $requestParam['contentType']
    ];
    
    // 構建 Canonical Request
    $signedHeaderStr = join(';', ['content-type', 'host', 'x-content-sha256', 'x-date']);
    $queryString = http_build_query($requestParam['query']);
    
    echo "Canonical Request 組件:\n";
    echo "  signedHeaderStr: {$signedHeaderStr}\n";
    echo "  queryString: {$queryString}\n\n";
    
    $canonicalHeaders = join("\n", [
        'content-type:'. $requestParam['contentType'], 
        'host:'. $requestParam['host'], 
        'x-content-sha256:'. $xContentSha256, 
        'x-date:'. $xDate
    ]);
    
    echo "Canonical Headers:\n{$canonicalHeaders}\n\n";
    
    $canonicalRequestStr = join("\n", [
        $requestParam['method'],
        $requestParam['path'],
        $queryString,
        $canonicalHeaders,
        '',
        $signedHeaderStr,
        $xContentSha256
    ]);
    
    echo "完整 Canonical Request:\n";
    echo "---\n{$canonicalRequestStr}\n---\n\n";
    
    $hashedCanonicalRequest = hash("sha256", $canonicalRequestStr);
    echo "Hashed Canonical Request: {$hashedCanonicalRequest}\n\n";
    
    $credentialScope = join('/', [$shortXDate, $credential['region'], $credential['service'], 'request']);
    echo "Credential Scope: {$credentialScope}\n\n";
    
    $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);
    echo "String to Sign:\n---\n{$stringToSign}\n---\n\n";
    
    // 簽名計算步驟
    echo "簽名計算步驟:\n";
    echo "  使用 secretKeyId: " . substr($credential['secretKeyId'], 0, 10) . "...\n";
    
    $kDate = hash_hmac("sha256", $shortXDate, $credential['secretKeyId'], true);
    echo "  kDate (hex): " . bin2hex($kDate) . "\n";
    
    $kRegion = hash_hmac("sha256", $credential['region'], $kDate, true);
    echo "  kRegion (hex): " . bin2hex($kRegion) . "\n";
    
    $kService = hash_hmac("sha256", $credential['service'], $kRegion, true);
    echo "  kService (hex): " . bin2hex($kService) . "\n";
    
    $kSigning = hash_hmac("sha256", 'request', $kService, true);
    echo "  kSigning (hex): " . bin2hex($kSigning) . "\n";
    
    $signature = hash_hmac("sha256", $stringToSign, $kSigning);
    echo "  最終簽名: {$signature}\n\n";
    
    $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", 
        $credential['accessKeyId']. '/'. $credentialScope, $signedHeaderStr, $signature);
    
    echo "Authorization Header:\n";
    echo $signResult['Authorization'] . "\n\n";
    
    $header = array_merge($header, $signResult);
    
    // 發送請求
    $ch = curl_init();
    $fullUrl = 'https://'. $requestParam['host']. $requestParam['path'] . '?' . $queryString;
    
    echo "請求 URL: {$fullUrl}\n";
    
    $headers = [];
    foreach ($header as $key => $value) {
        $headers[] = "$key: $value";
        echo "Header: $key: $value\n";
    }
    echo "\n";
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $requestParam['body'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "回應結果:\n";
    echo "HTTP Code: {$httpCode}\n";
    echo "Response: " . substr($response, 0, 500) . "\n";
    
    return [
        'http_code' => $httpCode,
        'response' => $response
    ];
}

// 測試
$requestBody = [
    "req_key" => "high_aes_general_v30l_zt2i",
    "prompt" => "simple test",
    "return_url" => true
];

$body = json_encode($requestBody);

echo "開始測試簽名...\n\n";

$result = requestWithDebug(
    'POST',
    [],
    [],
    $AccessKeyID,
    $SecretAccessKey,
    'CVProcess',
    '2022-08-31',
    $body
);

echo "\n=== 測試完成 ===\n";
?>