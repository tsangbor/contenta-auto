<?php
/**
 * 最小化即夢 AI 測試 - 基於官方範例
 */

// 設定基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once __DIR__ . '/config-manager.php';

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

// 基礎信息
$Host = "visual.volcengineapi.com";
$ContentType = "application/json";
$Service = "cv";
$Region = "cn-north-1";

// API 金鑰
$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey = $jimeng_credentials['SecretAccessKey'] ?? '';

if (empty($AccessKeyID) || empty($SecretAccessKey)) {
    die("錯誤: API 金鑰未配置\n");
}

echo "=== 即夢 AI 最小化測試 ===\n";
echo "AccessKeyID: " . substr($AccessKeyID, 0, 10) . "...\n\n";

// 火山引擎官方簽名函數 (完全按照範例)
function request($method, $query, $header, $ak, $sk, $action, $version, $body)
{
    global $Service, $Region, $Host, $ContentType;
    
    $credential = [
        'accessKeyId' => $ak,
        'secretKeyId' => $sk,
        'service' => $Service,
        'region' => $Region,
    ];
    
    $query = array_merge($query, [
        'Action' => $action,
        'Version' => $version
    ]);
    ksort($query);
    
    $requestParam = [
        'body' => $body,
        'host' => $Host,
        'path' => '/',
        'method' => $method,
        'contentType' => $ContentType,
        'date' => gmdate('Ymd\THis\Z'),
        'query' => $query
    ];
    
    $xDate = $requestParam['date'];
    $shortXDate = substr($xDate, 0, 8);
    $xContentSha256 = hash('sha256', $requestParam['body']);
    $signResult = [
        'Host' => $requestParam['host'],
        'X-Content-Sha256' => $xContentSha256,
        'X-Date' => $xDate,
        'Content-Type' => $requestParam['contentType']
    ];
    
    $signedHeaderStr = join(';', ['content-type', 'host', 'x-content-sha256', 'x-date']);
    $canonicalRequestStr = join("\n", [
        $requestParam['method'],
        $requestParam['path'],
        http_build_query($requestParam['query']),
        join("\n", [
            'content-type:'. $requestParam['contentType'], 
            'host:'. $requestParam['host'], 
            'x-content-sha256:'. $xContentSha256, 
            'x-date:'. $xDate
        ]),
        '',
        $signedHeaderStr,
        $xContentSha256
    ]);
    
    $hashedCanonicalRequest = hash("sha256", $canonicalRequestStr);
    $credentialScope = join('/', [$shortXDate, $credential['region'], $credential['service'], 'request']);
    $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);
    $kDate = hash_hmac("sha256", $shortXDate, $credential['secretKeyId'], true);
    $kRegion = hash_hmac("sha256", $credential['region'], $kDate, true);
    $kService = hash_hmac("sha256", $credential['service'], $kRegion, true);
    $kSigning = hash_hmac("sha256", 'request', $kService, true);
    $signature = hash_hmac("sha256", $stringToSign, $kSigning);
    $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", 
        $credential['accessKeyId']. '/'. $credentialScope, $signedHeaderStr, $signature);
    
    $header = array_merge($header, $signResult);
    
    // 使用 cURL
    $ch = curl_init();
    $full_url = 'https://' . $requestParam['host'] . $requestParam['path'] . '?' . http_build_query($requestParam['query']);
    
    $headers = [];
    foreach ($header as $key => $value) {
        $headers[] = "$key: $value";
    }
    
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestParam['body']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP 狀態: $http_code\n";
    echo "回應: " . substr($response, 0, 200) . "...\n";
    
    return $response;
}

// 測試最小請求
$action = "CVProcess";
$version = "2022-08-31";

// 嘗試不同的 req_key
$test_keys = [
    "high_aes",
    "general",
    "cv_general_v20",
    "high_aes_general_v30l",
    "high_aes_general_v30l_zt2i"
];

foreach ($test_keys as $req_key) {
    echo "\n=== 測試 req_key: $req_key ===\n";
    
    $requestBody = [
        "req_key" => $req_key,
        "prompt" => "simple test image",
        "return_url" => true
    ];
    
    $body = json_encode($requestBody);
    
    try {
        $response = request("POST", [], [], $AccessKeyID, $SecretAccessKey, $action, $version, $body);
        $result = json_decode($response, true);
        
        if (isset($result['data'])) {
            echo "✅ 成功! req_key: $req_key 有效\n";
            break;
        } else {
            echo "❌ 失敗\n";
        }
        
    } catch (Exception $e) {
        echo "❌ 異常: " . $e->getMessage() . "\n";
    }
    
    sleep(1); // 避免過快請求
}

echo "\n測試完成\n";
?>