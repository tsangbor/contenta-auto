<?php
/**
 * 範例步驟 - 展示如何在任何步驟中整合認證更新
 * 
 * 只需要在步驟開始處加入以下幾行代碼：
 */

// === 步驟開始前的認證檢查 ===
require_once DEPLOY_BASE_PATH . '/includes/auth-wrapper.php';

// 執行認證檢查（如果失敗會自動返回錯誤狀態）
$authResult = ensureBTAuth($deployer, 'step-example');
if (isset($authResult['status']) && $authResult['status'] === 'error') {
    return $authResult; // 認證失敗，直接返回錯誤
}

$deployer->log("✅ 認證驗證完成，開始執行步驟邏輯");

// === 原有的步驟邏輯 ===
$deployer->log("開始執行步驟範例...");

try {
    // 範例 1: 使用 BT API 創建網站
    $siteData = [
        'domain' => 'example.com',
        'port' => '80',
        'ps' => '測試網站',
        'path' => '/www/wwwroot/example.com',
        'type_id' => 0,
        'type' => 'PHP',
        'version' => '8.1'
    ];
    
    $deployer->log("呼叫 BT API 創建網站...");
    $response = btAPI('site?action=AddSite', $siteData);
    checkBTResponse($response, '創建網站');
    
    $deployer->log("✅ 網站創建成功");
    
    // 範例 2: 使用 BT API 創建資料庫
    $dbData = [
        'name' => 'example_db',
        'codeing' => 'utf8mb4',
        'db_user' => 'example_user',
        'password' => 'secure_password_123'
    ];
    
    $deployer->log("呼叫 BT API 創建資料庫...");
    $response = btAPI('database?action=AddDatabase', $dbData);
    checkBTResponse($response, '創建資料庫');
    
    $deployer->log("✅ 資料庫創建成功");
    
    // 範例 3: 使用 BT API 設定 SSL
    $sslData = [
        'siteName' => 'example.com',
        'updateOf' => 'email@example.com'
    ];
    
    $deployer->log("呼叫 BT API 申請 SSL 憑證...");
    $response = btAPI('acme?action=ApplyDomainCert', $sslData);
    checkBTResponse($response, 'SSL 憑證申請');
    
    $deployer->log("✅ SSL 憑證申請成功");
    
    $deployer->log("✅ 步驟範例執行完成");
    
    return [
        'status' => 'success',
        'message' => '步驟執行成功',
        'auth_updated' => true,
        'operations' => [
            'site_created' => true,
            'database_created' => true,
            'ssl_applied' => true
        ]
    ];
    
} catch (Exception $e) {
    $deployer->log("❌ 步驟執行失敗: " . $e->getMessage(), 'ERROR');
    
    return [
        'status' => 'error',
        'error' => $e->getMessage(),
        'auth_updated' => true,
        'step' => 'step-example'
    ];
}

/**
 * 總結：
 * 
 * 要在任何現有步驟中加入認證更新，只需要：
 * 
 * 1. 在步驟開始處加入：
 *    require_once DEPLOY_BASE_PATH . '/includes/auth-wrapper.php';
 *    $authResult = ensureBTAuth($deployer, 'step-name');
 *    if (isset($authResult['status']) && $authResult['status'] === 'error') {
 *        return $authResult;
 *    }
 * 
 * 2. 使用 btAPI() 函數來呼叫 BT Panel API：
 *    $response = btAPI('endpoint', $data);
 *    checkBTResponse($response, '操作描述');
 * 
 * 3. 就這樣！系統會自動：
 *    - 檢查認證是否需要更新
 *    - 透過 Playwright 自動取得最新的 Cookie 和 Token
 *    - 將認證資訊用於所有 BT API 請求
 *    - 提供錯誤處理和日誌記錄
 */
?>