<?php
/**
 * 認證包裝器 - 在任何需要 BT 主機操作的步驟中包含此檔案
 * 自動處理認證更新邏輯
 */

// 防止重複包含
if (defined('AUTH_WRAPPER_LOADED')) {
    return;
}
define('AUTH_WRAPPER_LOADED', true);

// 引入認證管理器
require_once DEPLOY_BASE_PATH . '/includes/class-auth-manager.php';

/**
 * 認證包裝器類
 */
class AuthWrapper {
    private $authManager;
    private $deployer;
    private $stepName;
    
    public function __construct($deployer, $stepName = 'Unknown') {
        $this->authManager = new AuthManager();
        $this->deployer = $deployer;
        $this->stepName = $stepName;
    }
    
    /**
     * 執行認證檢查和更新
     * 
     * @param bool $force 是否強制更新認證
     * @return array 返回結果狀態
     */
    public function ensureAuth($force = false) {
        $this->deployer->log("🔐 [{$this->stepName}] 檢查 BT Panel 認證狀態");
        
        try {
            if ($this->authManager->ensureValidCredentials($force)) {
                $credentials = $this->authManager->getCredentials();
                
                $this->deployer->log("✅ [{$this->stepName}] 認證驗證成功");
                $this->deployer->log("   Cookie: " . substr($credentials['cookie'], 0, 50) . "...");
                $this->deployer->log("   Token: " . substr($credentials['token'], 0, 20) . "...");
                
                return [
                    'success' => true,
                    'credentials' => $credentials,
                    'message' => '認證驗證成功'
                ];
            } else {
                $this->deployer->log("❌ [{$this->stepName}] 認證驗證失敗", 'ERROR');
                
                return [
                    'success' => false,
                    'error' => 'auth_failed',
                    'message' => '認證驗證失敗，無法執行 BT 主機操作'
                ];
            }
        } catch (Exception $e) {
            $this->deployer->log("❌ [{$this->stepName}] 認證處理異常: " . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'error' => 'auth_exception',
                'message' => '認證處理異常: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 取得當前認證資訊
     */
    public function getCredentials() {
        return $this->authManager->getCredentials();
    }
    
    /**
     * 檢查是否需要認證
     */
    public function needsAuth() {
        return $this->authManager->needsUpdate();
    }
}

/**
 * 全域認證檢查函數
 * 在任何步驟的開始處呼叫此函數
 */
function ensureBTAuth($deployer, $stepName = null, $force = false) {
    if (!$stepName) {
        // 嘗試從呼叫棧獲取檔案名
        $backtrace = debug_backtrace();
        $stepName = basename($backtrace[0]['file'], '.php');
    }
    
    $wrapper = new AuthWrapper($deployer, $stepName);
    $result = $wrapper->ensureAuth($force);
    
    if (!$result['success']) {
        // 認證失敗，返回錯誤狀態
        return [
            'status' => 'error',
            'error' => $result['error'],
            'message' => $result['message'],
            'step' => $stepName
        ];
    }
    
    // 認證成功，將憑證存儲到全域變數
    global $bt_credentials;
    $bt_credentials = $result['credentials'];
    
    return $result;
}

/**
 * BT Panel API 請求函數
 * 使用最新的認證資訊執行 API 請求
 */
function btAPI($endpoint, $data = [], $method = 'POST') {
    global $config, $deployer, $bt_credentials;
    
    if (!$bt_credentials) {
        throw new Exception('BT 認證資訊未初始化，請先呼叫 ensureBTAuth()');
    }
    
    $panel_url = $config->get('api_credentials.btcn.panel_url');
    $url = rtrim($panel_url, '/') . '/' . ltrim($endpoint, '/');
    
    $headers = [
        'Cookie: ' . $bt_credentials['cookie'],
        'X-HTTP-Token: ' . $bt_credentials['token'],
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("BT API 請求失敗: $error");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("BT API 返回錯誤狀態: $httpCode, Response: $response");
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("BT API 返回無效 JSON: $response");
    }
    
    return $result;
}

/**
 * 檢查 BT API 響應狀態
 */
function checkBTResponse($response, $operation = 'API 操作') {
    if (!is_array($response)) {
        throw new Exception("$operation 失敗: 無效的響應格式");
    }
    
    // BT Panel 通常使用 status 欄位表示成功/失敗
    if (isset($response['status']) && !$response['status']) {
        $msg = $response['msg'] ?? $response['message'] ?? '未知錯誤';
        throw new Exception("$operation 失敗: $msg");
    }
    
    // 某些 API 使用不同的狀態欄位
    if (isset($response['error']) && $response['error']) {
        throw new Exception("$operation 失敗: " . $response['error']);
    }
    
    return true;
}

// 設置錯誤處理函數
function handleBTAuthError($error, $stepName) {
    global $deployer;
    
    $deployer->log("❌ [$stepName] BT 認證錯誤: $error", 'ERROR');
    
    return [
        'status' => 'error',
        'error' => 'bt_auth_error',
        'message' => $error,
        'step' => $stepName,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

$deployer->log("🔧 認證包裝器已載入");
?>