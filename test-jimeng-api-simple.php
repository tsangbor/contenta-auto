<?php
/**
 * 即夢 AI 正確 API 調用測試
 * 測試不同的端點和參數組合
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once __DIR__ . '/config-manager.php';

echo "=== 即夢 AI API 端點測試 ===\n\n";

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey = $jimeng_credentials['SecretAccessKey'] ?? '';

if (empty($AccessKeyID) || empty($SecretAccessKey)) {
    die("錯誤: API 金鑰未配置\n");
}

/**
 * 火山引擎官方簽名函數
 */
function makeRequest($endpoint, $action, $version, $requestBody, $ak, $sk) {
    $Host = parse_url($endpoint, PHP_URL_HOST);
    $Path = parse_url($endpoint, PHP_URL_PATH) ?: '/';
    $ContentType = "application/json";
    $Service = "cv";
    $Region = "cn-north-1";
    
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
    
    // 構建簽名
    $signedHeaderStr = 'content-type;host;x-content-sha256;x-date';
    $canonicalRequestStr = join("\n", [
        'POST',
        $Path,
        $queryString,
        join("\n", [
            'content-type:' . $ContentType,
            'host:' . $Host,
            'x-content-sha256:' . $xContentSha256,
            'x-date:' . $datetime
        ]),
        '',
        $signedHeaderStr,
        $xContentSha256
    ]);
    
    $hashedCanonicalRequest = hash("sha256", $canonicalRequestStr);
    $credentialScope = join('/', [$shortDate, $Region, $Service, 'request']);
    $stringToSign = join("\n", ['HMAC-SHA256', $datetime, $credentialScope, $hashedCanonicalRequest]);
    
    $kDate = hash_hmac("sha256", $shortDate, $sk, true);
    $kRegion = hash_hmac("sha256", $Region, $kDate, true);
    $kService = hash_hmac("sha256", $Service, $kRegion, true);
    $kSigning = hash_hmac("sha256", 'request', $kService, true);
    $signature = hash_hmac("sha256", $stringToSign, $kSigning);
    
    $authorization = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", 
        $ak . '/' . $credentialScope, $signedHeaderStr, $signature);
    
    // 發送請求
    $headers = [
        'Host: ' . $Host,
        'X-Date: ' . $datetime,
        'X-Content-Sha256: ' . $xContentSha256,
        'Content-Type: ' . $ContentType,
        'Authorization: ' . $authorization
    ];
    
    $ch = curl_init();
    $fullUrl = $endpoint . '?' . $queryString;
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $fullUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'response' => $response,
        'curl_error' => $curlError,
        'url' => $fullUrl
    ];
}

// 測試不同的端點和參數組合
$testConfigs = [
    [
        'name' => '標準 CV 端點',
        'endpoint' => 'https://visual.volcengineapi.com',
        'action' => 'CVProcess',
        'version' => '2022-08-31',
        'req_key' => 'high_aes_general_v30l_zt2i'
    ],
    [
        'name' => '舊版本 CV',
        'endpoint' => 'https://visual.volcengineapi.com',
        'action' => 'CVProcess',
        'version' => '2021-08-31',
        'req_key' => 'high_aes_general_v30l_zt2i'
    ],
    [
        'name' => '即夢專用端點',
        'endpoint' => 'https://visual.volcengineapi.com',
        'action' => 'CVProcess',
        'version' => '2022-08-31',
        'req_key' => 'jimeng_t2i_s20pro'
    ],
    [
        'name' => '基礎模型',
        'endpoint' => 'https://visual.volcengineapi.com',
        'action' => 'CVProcess',
        'version' => '2022-08-31',
        'req_key' => 'high_aes'
    ],
    [
        'name' => '簡化參數',
        'endpoint' => 'https://visual.volcengineapi.com',
        'action' => 'CVProcess',
        'version' => '2022-08-31',
        'req_key' => 'general'
    ]
];

foreach ($testConfigs as $testConfig) {
    echo "測試配置: {$testConfig['name']}\n";
    echo "端點: {$testConfig['endpoint']}\n";
    echo "Action: {$testConfig['action']}\n";
    echo "Version: {$testConfig['version']}\n";
    echo "req_key: {$testConfig['req_key']}\n";
    
    $requestBody = [
        "req_key" => $testConfig['req_key'],
        "prompt" => "simple test",
        "return_url" => true
    ];
    
    $result = makeRequest(
        $testConfig['endpoint'],
        $testConfig['action'],
        $testConfig['version'],
        $requestBody,
        $AccessKeyID,
        $SecretAccessKey
    );
    
    echo "HTTP 狀態: {$result['http_code']}\n";
    
    if ($result['curl_error']) {
        echo "cURL 錯誤: {$result['curl_error']}\n";
    }
    
    $response = json_decode($result['response'], true);
    
    if ($result['http_code'] === 200) {
        echo "✅ 成功！\n";
        if (isset($response['data'])) {
            echo "回應包含數據\n";
        }
    } else {
        echo "❌ 失敗\n";
        if (isset($response['ResponseMetadata']['Error'])) {
            $error = $response['ResponseMetadata']['Error'];
            echo "錯誤: {$error['Code']} - {$error['Message']}\n";
        } elseif (isset($response['message'])) {
            echo "錯誤: {$response['message']}\n";
        }
    }
    
    echo "回應預覽: " . substr($result['response'], 0, 150) . "...\n";
    echo str_repeat("-", 60) . "\n\n";
    
    sleep(1); // 避免請求過快
}

echo "=== 測試完成 ===\n";
echo "\n建議:\n";
echo "1. 如果所有測試都失敗，檢查帳戶權限和服務開通狀態\n";
echo "2. 如果某個配置成功，使用該配置更新主程式\n";
echo "3. 檢查火山引擎控制台中的 API 調用記錄\n";
echo "4. 確認 AccessKey 有 '視覺智能-圖像生成' 的權限\n";
?>