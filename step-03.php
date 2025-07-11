<?php
/**
 * 步驟 03: BT.cn 主機建立網站
 * 透過 BT Panel API 建立新網站，使用 www.{domain} 格式，PHP 8.2
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

// 網站目錄格式：www.{domain}
$site_name = "www.{$domain}";
$document_root = "/www/wwwroot/{$site_name}";

$deployer->log("開始在 BT.cn 建立網站: {$site_name}");

// 取得 API 憑證（支援兩種認證方式）
$bt_config = [
    'BT_API_URL' => $config->get('api_credentials.btcn.panel_url'),
    'BT_SESSION_COOKIE' => $config->get('api_credentials.btcn.session_cookie'),
    'BT_HTTP_TOKEN' => $config->get('api_credentials.btcn.http_token'),
    'BT_KEY' => $config->get('api_credentials.btcn.api_key'), // 備用方案
];

if (empty($bt_config['BT_API_URL'])) {
    $deployer->log("跳過 BT.cn 網站建立 - 未設定 API URL");
    return ['status' => 'skipped', 'reason' => 'no_api_url'];
}

// 除錯：顯示 API 配置
$deployer->log("BT Panel API URL: " . $bt_config['BT_API_URL']);
$deployer->log("Cookie 配置來源: api_credentials.btcn.session_cookie");
$deployer->log("Cookie 值: " . (empty($bt_config['BT_SESSION_COOKIE']) ? '未設定' : $bt_config['BT_SESSION_COOKIE']));
$deployer->log("HTTP Token 配置來源: api_credentials.btcn.http_token");
$deployer->log("HTTP Token 值: " . (empty($bt_config['BT_HTTP_TOKEN']) ? '未設定' : $bt_config['BT_HTTP_TOKEN']));

// 初始化 BT Panel API
$btAPI = new BTPanelAPI($bt_config, $deployer);

try {
    // 建立網站
    $deployer->log("建立新網站: {$site_name}");
    $deployer->log("文檔根目錄: {$document_root}");
    $deployer->log("PHP 版本: 8.2");
    
    $create_result = $btAPI->createWebsite($site_name, $document_root, '82', "{$site_name}");
    
    if ($create_result['success']) {
        if (isset($create_result['existed'])) {
            $deployer->log("網站已存在: {$site_name}（該動作已完成）");
            $action = 'existing';
        } else {
            $deployer->log("網站建立成功");
            $action = 'created';
        }
        $site_info = $create_result['result'] ?? ['name' => $site_name, 'path' => $document_root];
    } else {
        throw new Exception("網站建立失敗: " . ($create_result['message'] ?? '未知錯誤'));
    }
    
    // 儲存網站資訊
    $website_info = [
        'domain' => $domain,
        'site_name' => $site_name,
        'site_info' => $site_info,
        'document_root' => $document_root,
        'php_version' => '8.2',
        'action' => $action,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/bt_website.json', json_encode($website_info, JSON_PRETTY_PRINT));
    
    $deployer->log("BT.cn 網站設定完成");
    $deployer->log("網站名稱: {$site_name}");
    $deployer->log("文檔根目錄: {$document_root}");
    
    return ['status' => 'success', 'action' => $action, 'website_info' => $website_info];
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    $deployer->log("BT.cn 網站建立失敗: " . $error_message);
    
    // 如果是認證失效錯誤，嘗試重新認證並重試一次
    if (strpos($error_message, '登录会话已经失效') !== false || 
        strpos($error_message, '登入會話已經失效') !== false ||
        strpos($error_message, 'session') !== false) {
        $deployer->log("偵測到認證失效，嘗試重新認證...");
        
        if ($authManager->ensureValidCredentials(true)) { // 強制更新
            $deployer->log("重新認證成功，嘗試重新建立網站...");
            
            try {
                // 重新載入配置以獲取最新的認證資訊
                $config->reload();
                
                // 重新初始化 API 連接
                $bt_config = [
                    'BT_API_URL' => $config->get('api_credentials.btcn.panel_url'),
                    'BT_SESSION_COOKIE' => $config->get('api_credentials.btcn.session_cookie'),
                    'BT_HTTP_TOKEN' => $config->get('api_credentials.btcn.http_token'),
                    'BT_KEY' => $config->get('api_credentials.btcn.api_key'),
                ];
                
                $deployer->log("重新載入後的認證資訊:");
                $deployer->log("Cookie 來源: api_credentials.btcn.session_cookie");
                $deployer->log("Cookie 值: " . $bt_config['BT_SESSION_COOKIE']);
                $deployer->log("Token 來源: api_credentials.btcn.http_token");
                $deployer->log("Token 值: " . $bt_config['BT_HTTP_TOKEN']);
                
                // 驗證認證資訊不為空
                if (empty($bt_config['BT_SESSION_COOKIE']) || empty($bt_config['BT_HTTP_TOKEN'])) {
                    $deployer->log("錯誤: 重新載入後認證資訊仍為空");
                    throw new Exception("認證資訊重新載入失敗");
                }
                
                $btAPI = new BTPanelAPI($bt_config, $deployer);
                $create_result = $btAPI->createWebsite($site_name, $document_root, '82', "{$site_name}");
                
                if ($create_result['success']) {
                    $deployer->log("重試成功！網站建立完成");
                    
                    $website_info = [
                        'domain' => $domain,
                        'site_name' => $site_name,
                        'site_info' => $create_result['result'] ?? ['name' => $site_name, 'path' => $document_root],
                        'document_root' => $document_root,
                        'php_version' => '8.2',
                        'action' => isset($create_result['existed']) ? 'existing' : 'created',
                        'created_at' => date('Y-m-d H:i:s'),
                        'retry_count' => 1
                    ];
                    
                    file_put_contents($work_dir . '/bt_website.json', json_encode($website_info, JSON_PRETTY_PRINT));
                    return ['status' => 'success', 'action' => $website_info['action'], 'website_info' => $website_info];
                }
            } catch (Exception $retry_e) {
                $deployer->log("重試失敗: " . $retry_e->getMessage());
            }
        } else {
            $deployer->log("重新認證失敗");
        }
    }
    
    // 提供具體的故障排除建議
    if (strpos($error_message, 'Connection reset by peer') !== false) {
        $deployer->log("");
        $deployer->log("🔧 故障排除建議：");
        $deployer->log("1. 檢查 BT Panel 是否正常運行: {$bt_config['BT_API_URL']}");
        $deployer->log("2. 更新 Cookie 和 HTTP Token：");
        $deployer->log("   - 開啟瀏覽器登入 BT Panel");
        $deployer->log("   - 開啟開發者工具 (F12) > Network 分頁");
        $deployer->log("   - 重新整理頁面，複製新的 Cookie 和 x-http-token");
        $deployer->log("   - 更新 deploy-config.json 中的認證資訊");
        $deployer->log("3. 確認防火牆未封鎖 API 請求");
        $deployer->log("4. 檢查網路連線是否正常");
        $deployer->log("");
    } elseif (strpos($error_message, 'API 返回錯誤代碼') !== false) {
        $deployer->log("");
        $deployer->log("🔧 API 錯誤排除：");
        $deployer->log("- 檢查 API 端點是否正確");
        $deployer->log("- 驗證認證資訊是否有效");
        $deployer->log("- 確認 BT Panel 版本相容性");
        $deployer->log("");
    }
    
    return ['status' => 'error', 'message' => $error_message, 'troubleshooting_provided' => true];
}