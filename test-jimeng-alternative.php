<?php
/**
 * 基於火山引擎官方範例的即夢 AI 測試
 */

require_once __DIR__ . '/config-manager.php';
require(__DIR__ . '/vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// 基礎信息，基本不用變更
$Host = "visual.volcengineapi.com";
$ContentType = "application/json";
$Service = "cv";
$Region = "cn-north-1";

/**
 * 火山引擎官方簽名函數
 * @throws GuzzleException
 */
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
    
    $client = new Client([
        'base_uri' => 'https://'. $requestParam['host'],
        'timeout' => 120.0,
    ]);
    
    $response = $client->request($method, 'https://'. $requestParam['host']. $requestParam['path'], [
        'headers' => $header,
        'query' => $requestParam['query'],
        'body' => $requestParam['body']
    ]);
    
    $responseContent = $response->getBody()->getContents();
    $responseContent = str_replace('\u0026', '&', $responseContent);
    return $responseContent;
}

echo "=== 火山引擎官方範例測試 ===\n\n";

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

// API 金鑰
$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey = base64_decode($jimeng_credentials['SecretAccessKey'] ?? '');

if (empty($AccessKeyID) || empty($SecretAccessKey)) {
    die("錯誤: 請在 config/deploy-config.json 中配置 jimeng API 金鑰\n");
}

echo "API 金鑰已配置\n";

// 測試參數
$action = "CVProcess";
$version = "2022-08-31";

$requestBody = [
    "req_key" => "high_aes_general_v30l_zt2i",
    "prompt" => "A modern flat illustration in the style of humaaans, featuring minimalist characters collaborating in an office setting",
    "seed" => -1,
    "scale" => 2.5,
    "width" => 1328,
    "height" => 1328,
    "return_url" => true,
    "logo_info" => [
        "add_logo" => false,
        "position" => 0,
        "language" => 0,
        "opacity" => 0.3
    ]
];

$body = json_encode($requestBody);

echo "開始測試圖片生成...\n";
echo "提示詞: " . $requestBody['prompt'] . "\n\n";

try {
    $response = request("POST", [], [], $AccessKeyID, $SecretAccessKey, $action, $version, $body);
    
    echo "API 回應:\n";
    $result = json_decode($response, true);
    
    if (isset($result['data']['binary_data_base64']) && is_array($result['data']['binary_data_base64'])) {
        echo "✅ 生成成功！共生成 " . count($result['data']['binary_data_base64']) . " 張圖片\n";
        
        // 建立目錄
        if (!is_dir(__DIR__ . '/temp')) {
            mkdir(__DIR__ . '/temp', 0755, true);
        }
        
        // 儲存圖片
        $image_data = base64_decode($result['data']['binary_data_base64'][0]);
        $filename = "jimeng_official_test_" . time() . ".jpg";
        file_put_contents(__DIR__ . "/temp/{$filename}", $image_data);
        echo "圖片已儲存: temp/{$filename}\n";
        
    } else {
        echo "❌ 生成失敗或回應格式異常\n";
        print_r($result);
    }
    
} catch (GuzzleException $e) {
    echo "❌ 請求錯誤: " . $e->getMessage() . "\n";
    if ($e->hasResponse()) {
        echo "錯誤回應: " . $e->getResponse()->getBody()->getContents() . "\n";
    }
}

echo "\n測試完成\n";
?>