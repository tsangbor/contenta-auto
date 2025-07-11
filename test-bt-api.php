<?php
/**
 * BT Panel API 測試工具
 * 測試 POST https://jp3.contenta.tw:8888/panel/public/get_public_config
 * 使用 Cookie 和 Token 認證
 */

// 模擬部署環境
define('DEPLOY_BASE_PATH', __DIR__);

// 簡單的 deployer 模擬
class TestDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
}

$deployer = new TestDeployer();

echo "🧪 BT Panel API 測試工具\n";
echo "========================\n\n";

// 讀取配置
$configPath = 'config/deploy-config.json';
if (!file_exists($configPath)) {
    echo "❌ 設定檔不存在: $configPath\n";
    exit(1);
}

$config = json_decode(file_get_contents($configPath), true);
if (!$config) {
    echo "❌ 設定檔格式錯誤\n";
    exit(1);
}

$btcn_config = $config['api_credentials']['btcn'] ?? null;
if (!$btcn_config) {
    echo "❌ BT Panel 設定不存在\n";
    exit(1);
}

// 取得認證資訊
$panel_url = $btcn_config['panel_url'] ?? '';
$session_cookie = $btcn_config['session_cookie'] ?? '';
$http_token = $btcn_config['http_token'] ?? '';

echo "📋 配置資訊:\n";
echo "Panel URL: $panel_url\n";
echo "Cookie: " . (empty($session_cookie) ? '未設定' : '已設定 (' . substr($session_cookie, 0, 50) . '...)') . "\n";
echo "Token: " . (empty($http_token) ? '未設定' : '已設定 (' . substr($http_token, 0, 20) . '...)') . "\n\n";

if (empty($session_cookie) || empty($http_token)) {
    echo "⚠️  認證資訊不完整，嘗試更新認證...\n";
    
    // 嘗試更新認證
    require_once 'includes/class-auth-manager.php';
    
    class TestAuthManager extends AuthManager {
        protected function getLoginCredentials() {
            global $btcn_config;
            return [
                'username' => $btcn_config['panel_login'] ?? 'tsangbor',
                'password' => $btcn_config['panel_password'] ?? 'XSW2cde',
                'login_url' => $btcn_config['panel_auth'] ?? 'https://jp3.contenta.tw:8888/btpanel'
            ];
        }
    }
    
    $authManager = new TestAuthManager();
    
    if ($authManager->updateCredentials(true)) {
        echo "✅ 認證更新成功，重新讀取配置\n";
        
        // 重新讀取配置
        $config = json_decode(file_get_contents($configPath), true);
        $btcn_config = $config['api_credentials']['btcn'];
        $session_cookie = $btcn_config['session_cookie'] ?? '';
        $http_token = $btcn_config['http_token'] ?? '';
    } else {
        echo "❌ 認證更新失敗\n";
        exit(1);
    }
}

/**
 * 執行 BT Panel API 請求
 */
function testBTAPI($url, $cookie, $token, $data = [], $method = 'POST') {
    global $deployer;
    
    $deployer->log("🌐 測試 API: $url");
    $deployer->log("方法: $method");
    
    $headers = [
        'Cookie: ' . $cookie,
        'X-HTTP-Token: ' . $token,
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: zh-TW,zh;q=0.9,en;q=0.8',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-origin'
    ];
    
    $deployer->log("請求標頭:");
    foreach ($headers as $header) {
        if (strpos($header, 'Cookie:') === 0) {
            $deployer->log("  Cookie: " . substr($header, 8, 50) . "...");
        } elseif (strpos($header, 'X-HTTP-Token:') === 0) {
            $deployer->log("  X-HTTP-Token: " . substr($header, 14, 20) . "...");
        } else {
            $deployer->log("  $header");
        }
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_ENCODING, ''); // 支援 gzip
    
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        
        if (!empty($data)) {
            $deployer->log("POST 資料: " . http_build_query($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    
    $deployer->log("HTTP 狀態碼: $httpCode");
    $deployer->log("Content-Type: $contentType");
    
    if ($error) {
        $deployer->log("❌ CURL 錯誤: $error", 'ERROR');
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    return [
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'response' => $response
    ];
}

// === 開始測試 ===
echo "🚀 開始 API 測試\n";
echo "================\n\n";

// 測試 1: get_public_config
echo "測試 1: panel/public/get_public_config\n";
echo "--------------------------------------\n";

$test_url = $panel_url . '/panel/public/get_public_config';
$result = testBTAPI($test_url, $session_cookie, $http_token);

if ($result) {
    echo "\n📊 測試結果:\n";
    echo "HTTP 狀態碼: " . $result['http_code'] . "\n";
    echo "Content-Type: " . $result['content_type'] . "\n";
    echo "回應內容:\n";
    
    // 嘗試格式化 JSON 回應
    $json_data = json_decode($result['response'], true);
    if ($json_data) {
        echo "✅ JSON 格式正確\n";
        echo json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        
        // 分析回應內容
        echo "\n🔍 回應分析:\n";
        echo "資料類型: " . gettype($json_data) . "\n";
        if (is_array($json_data)) {
            echo "欄位數量: " . count($json_data) . "\n";
            echo "主要欄位: " . implode(', ', array_keys($json_data)) . "\n";
            
            // 檢查常見的狀態欄位
            if (isset($json_data['status'])) {
                echo "狀態: " . ($json_data['status'] ? '成功' : '失敗') . "\n";
            }
            if (isset($json_data['msg'])) {
                echo "訊息: " . $json_data['msg'] . "\n";
            }
            if (isset($json_data['data'])) {
                echo "資料: " . (is_array($json_data['data']) ? count($json_data['data']) . ' 項目' : gettype($json_data['data'])) . "\n";
            }
        }
    } else {
        echo "⚠️  非 JSON 格式或格式錯誤\n";
        echo "原始回應:\n";
        echo $result['response'] . "\n";
    }
} else {
    echo "❌ API 測試失敗\n";
}

echo "\n";

// 測試 2: 其他常見的 API 端點
echo "測試 2: 其他 API 端點\n";
echo "--------------------\n";

$additional_tests = [
    [
        'name' => '系統資訊',
        'endpoint' => '/system?action=GetNetWork',
        'method' => 'POST'
    ],
    [
        'name' => '網站列表',
        'endpoint' => '/data?action=getData',
        'method' => 'POST',
        'data' => ['table' => 'sites', 'limit' => 10, 'p' => 1]
    ],
    [
        'name' => '面板狀態',
        'endpoint' => '/ajax?action=get_load_average',
        'method' => 'POST'
    ]
];

foreach ($additional_tests as $test) {
    echo "\n測試: " . $test['name'] . "\n";
    $test_url = $panel_url . $test['endpoint'];
    $test_data = $test['data'] ?? [];
    
    $result = testBTAPI($test_url, $session_cookie, $http_token, $test_data, $test['method']);
    
    if ($result && $result['http_code'] == 200) {
        echo "✅ " . $test['name'] . " - HTTP " . $result['http_code'] . "\n";
        
        $json_data = json_decode($result['response'], true);
        if ($json_data) {
            echo "📄 回應預覽: " . substr(json_encode($json_data, JSON_UNESCAPED_UNICODE), 0, 200) . "...\n";
        }
    } else {
        echo "❌ " . $test['name'] . " - 失敗\n";
    }
}

echo "\n🎯 測試總結\n";
echo "==========\n";
echo "✅ BT Panel API 測試完成\n";
echo "📋 認證狀態: " . (empty($session_cookie) || empty($http_token) ? '需要更新' : '正常') . "\n";
echo "🌐 面板地址: $panel_url\n";
echo "⏰ 測試時間: " . date('Y-m-d H:i:s') . "\n";
echo "\n💡 建議:\n";
echo "1. 如果 API 返回認證錯誤，請執行步驟 00 更新認證\n";
echo "2. 檢查防火牆是否允許連接到 BT Panel\n";
echo "3. 確認 BT Panel 服務正常運行\n";
?>