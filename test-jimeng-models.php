<?php
/**
 * 測試不同的即夢 AI 模型和參數
 * 用於找出正確的 API 調用格式
 */

// 載入 Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// 設定基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once DEPLOY_BASE_PATH . '/config-manager.php';

// 基礎信息
$Host = "visual.volcengineapi.com";
$ContentType = "application/json";
$Service = "cv";
$Region = "cn-north-1";

echo "=== 即夢 AI 模型測試 ===\n";
echo "測試不同的 req_key 和參數組合\n\n";

// 載入配置
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];
$AccessKeyID = $jimeng_credentials['AccessKeyID'] ?? '';
$SecretAccessKey = $jimeng_credentials['SecretAccessKey'] ?? '';

/**
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
        join("\n", ['content-type:'. $requestParam['contentType'], 'host:'. $requestParam['host'], 'x-content-sha256:'. $xContentSha256, 'x-date:'. $xDate]),
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
    $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", $credential['accessKeyId']. '/'. $credentialScope, $signedHeaderStr, $signature);
    $header = array_merge($header, $signResult);
    $client = new Client([
        'base_uri' => 'https://'. $requestParam['host'],
        'timeout' => 120.0,
    ]);
    try {
        $response = $client->request($method, 'https://'. $requestParam['host']. $requestParam['path'], [
            'headers' => $header,
            'query' => $requestParam['query'],
            'body' => $requestParam['body']
        ]);
        $responseContent = $response->getBody()->getContents();
        $responseContent = str_replace('\u0026', '&', $responseContent);
        return $responseContent;
    } catch (GuzzleException $e) {
        if ($e->getResponse()) {
            return $e->getResponse()->getBody()->getContents();
        }
        throw $e;
    }
}

// 測試不同的模型和參數組合
$test_configs = [
    // 原始配置
    [
        'name' => '原始 req_key',
        'body' => [
            "req_key" => "high_aes_general_v30l_zt2i",
            "prompt" => "simple test",
            "return_url" => true
        ]
    ],
    // MCP 使用的模型名
    [
        'name' => 'MCP 模型名 (req_key)',
        'body' => [
            "req_key" => "jimeng_t2i_s20pro",
            "prompt" => "simple test",
            "return_url" => true
        ]
    ],
    // 使用 model 參數而非 req_key
    [
        'name' => 'MCP 模型名 (model)',
        'body' => [
            "model" => "jimeng_t2i_s20pro",
            "prompt" => "simple test",
            "return_url" => true
        ]
    ],
    // 嘗試其他可能的 req_key
    [
        'name' => '即夢通用模型',
        'body' => [
            "req_key" => "jimeng_general",
            "prompt" => "simple test",
            "return_url" => true
        ]
    ],
    // 測試最小參數
    [
        'name' => '最小參數測試',
        'body' => [
            "prompt" => "simple test"
        ]
    ],
    // 測試完整參數
    [
        'name' => '完整參數 (jimeng_t2i_s20pro)',
        'body' => [
            "req_key" => "jimeng_t2i_s20pro",
            "prompt" => "simple test",
            "seed" => -1,
            "scale" => 2.5,
            "width" => 512,
            "height" => 512,
            "return_url" => true
        ]
    ]
];

// 測試不同的 Action 和 Version 組合
$api_versions = [
    ['action' => 'CVProcess', 'version' => '2022-08-31'],
    ['action' => 'AIGCProcess', 'version' => '2022-08-31'],
    ['action' => 'ImageGeneration', 'version' => '2022-08-31']
];

echo "開始測試...\n\n";

foreach ($test_configs as $test) {
    echo str_repeat('=', 60) . "\n";
    echo "測試: {$test['name']}\n";
    echo str_repeat('=', 60) . "\n";
    
    $body = json_encode($test['body']);
    echo "請求體: " . substr($body, 0, 100) . "...\n";
    
    // 只測試第一個 API 版本組合
    $api = $api_versions[0];
    echo "Action: {$api['action']}, Version: {$api['version']}\n";
    
    try {
        $response = request("POST", [], [], $AccessKeyID, $SecretAccessKey, $api['action'], $api['version'], $body);
        $responseData = json_decode($response, true);
        
        if (isset($responseData['code']) && $responseData['code'] !== 200 && $responseData['code'] !== 0) {
            echo "❌ 錯誤: {$responseData['message']}\n";
            echo "錯誤碼: {$responseData['code']}\n";
            if (isset($responseData['request_id'])) {
                echo "Request ID: {$responseData['request_id']}\n";
            }
        } else {
            echo "✅ 成功!\n";
            echo "回應: " . substr($response, 0, 200) . "...\n";
            
            // 如果成功，保存配置
            echo "\n🎉 找到有效配置！\n";
            echo "請使用以下參數:\n";
            echo "- req_key 或 model: " . ($test['body']['req_key'] ?? $test['body']['model'] ?? 'N/A') . "\n";
            echo "- Action: {$api['action']}\n";
            echo "- Version: {$api['version']}\n";
            break; // 退出循環
        }
    } catch (Exception $e) {
        echo "❌ 異常: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    sleep(1); // 避免 API 限制
}

echo "\n測試完成。\n";

// 如果都失敗，提供建議
echo "\n建議:\n";
echo "1. 檢查火山引擎控制台的 API 文檔\n";
echo "2. 確認即夢 AI 的正確模型名稱\n";
echo "3. 聯繫技術支援獲取正確的 req_key 值\n";
echo "4. 嘗試安裝 jimeng-ai-mcp 並查看其源碼了解正確參數\n";
?>