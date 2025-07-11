<?php
/**
 * 步驟 05: 資料庫建立
 * 透過 BT Panel API 建立 MySQL 資料庫
 * 資料庫名稱：網域名稱（移除 .tw）
 * 用戶名稱：同資料庫名稱
 * 密碼：82b15dc192ae
 */

// 載入 BT Panel API 類別
require_once DEPLOY_BASE_PATH . '/includes/class-bt-panel-api.php';
require_once DEPLOY_BASE_PATH . '/includes/class-auth-manager.php';

// 確保認證可用
$authManager = new AuthManager();
if (!$authManager->ensureValidCredentials()) {
    $deployer->log("認證失敗，無法執行 BT Panel 操作");
    return ['status' => 'error', 'message' => '認證失敗'];
}

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("開始建立資料庫: {$domain}");

// 取得 API 憑證（支援兩種認證方式）
$bt_config = [
    'BT_API_URL' => $config->get('api_credentials.btcn.panel_url'),
    'BT_SESSION_COOKIE' => $config->get('api_credentials.btcn.session_cookie'),
    'BT_HTTP_TOKEN' => $config->get('api_credentials.btcn.http_token'),
    'BT_KEY' => $config->get('api_credentials.btcn.api_key'), // 備用方案
];

if (empty($bt_config['BT_API_URL'])) {
    $deployer->log("跳過資料庫建立 - 未設定 API URL");
    return ['status' => 'skipped', 'reason' => 'no_api_url'];
}

// 初始化 BT Panel API
$btAPI = new BTPanelAPI($bt_config, $deployer);

try {
    // 生成資料庫名稱和用戶名稱（移除 .tw）
    $db_name = str_replace('.tw', '', $domain);
    $db_user = $db_name; // 用戶名稱同資料庫名稱
    $db_password = '82b15dc192ae'; // 統一密碼
    
    // 清理名稱中的特殊字符，只保留字母數字和底線
    $db_name = preg_replace('/[^a-zA-Z0-9_]/', '_', $db_name);
    $db_user = preg_replace('/[^a-zA-Z0-9_]/', '_', $db_user);
    
    $deployer->log("資料庫名稱: {$db_name}");
    $deployer->log("資料庫用戶: {$db_user}");
    $deployer->log("資料庫密碼: {$db_password}");
    
    // 使用新的 API 建立資料庫（包含用戶與權限）
    $db_result = $btAPI->createDatabase($db_name, $db_user, $db_password);
    
    if ($db_result['success']) {
        $deployer->log("資料庫建立成功");
        $db_action = 'created';
        $user_action = 'created';
        $perm_action = 'success';
    } else {
        throw new Exception("資料庫建立失敗: " . ($db_result['message'] ?? '未知錯誤'));
    }
    
    // 儲存資料庫資訊
    $database_info = [
        'host' => 'localhost',
        'database' => $db_name,
        'username' => $db_user,
        'password' => $db_password,
        'charset' => 'utf8mb4',
        'collate' => 'utf8mb4_unicode_ci',
        'table_prefix' => 'wp_',
        'db_action' => $db_action,
        'user_action' => $user_action,
        'permission_action' => $perm_action,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/database_config.json', json_encode($database_info, JSON_PRETTY_PRINT));
    
    $deployer->log("資料庫設定完成");
    $deployer->log("主機: localhost");
    $deployer->log("資料庫: {$db_name}");
    $deployer->log("用戶: {$db_user}");
    
    return ['status' => 'success', 'database_info' => $database_info];
    
} catch (Exception $e) {
    $deployer->log("資料庫建立失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}