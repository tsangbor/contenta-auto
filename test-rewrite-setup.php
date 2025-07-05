<?php
/**
 * 測試 WordPress 偽靜態規則設置
 * 使用方式: php test-rewrite-setup.php [domain]
 */

require_once __DIR__ . '/config-manager.php';
require_once __DIR__ . '/includes/class-bt-panel-api.php';

if ($argc < 2) {
    echo "使用方式: php test-rewrite-setup.php [domain]\n";
    echo "例如: php test-rewrite-setup.php yaoguo.tw\n";
    exit(1);
}

$domain = $argv[1];
$config = ConfigManager::getInstance();

echo "=== WordPress 偽靜態規則設置測試 ===\n";
echo "網域: {$domain}\n";
echo "測試時間: " . date('Y-m-d H:i:s') . "\n\n";

// 建立 logger
class SimpleLogger {
    public function log($message) {
        echo "[" . date('H:i:s') . "] {$message}\n";
    }
}

$logger = new SimpleLogger();

// 取得 BT.CN API 設定
$bt_config = [
    'BT_API_URL' => $config->get('api_credentials.btcn.panel_url'),
    'BT_SESSION_COOKIE' => $config->get('api_credentials.btcn.session_cookie'),
    'BT_HTTP_TOKEN' => $config->get('api_credentials.btcn.http_token'),
    'BT_KEY' => $config->get('api_credentials.btcn.api_key'),
];

echo "1. 檢查 BT Panel API 設定...\n";
if (empty($bt_config['BT_API_URL'])) {
    echo "錯誤: 未設定 BT Panel API URL\n";
    exit(1);
}

echo "   API URL: " . $bt_config['BT_API_URL'] . "\n";
echo "   認證方式: " . (!empty($bt_config['BT_SESSION_COOKIE']) ? "Cookie 認證" : "API Key 認證") . "\n\n";

// 初始化 BT Panel API
$btAPI = new BTPanelAPI($bt_config, $logger);

try {
    echo "2. 檢查自訂管理後台設定...\n";
    $custom_admin_url = $config->get('wordpress_security.custom_admin_url', 'wp-admin');
    echo "   自訂管理後台網址: /{$custom_admin_url}\n\n";
    
    echo "3. 生成偽靜態規則內容...\n";
    
    // 手動生成規則以便檢查
    $rewriteRule = "location /
{
     try_files \$uri \$uri/ /index.php?\$args;
}";

    if ($custom_admin_url !== 'wp-admin') {
        $rewriteRule .= "

# 自訂管理後台重定向
location ^~ /{$custom_admin_url} {
    rewrite ^/{$custom_admin_url}/?(.*)$ /wp-admin/$1 last;
}

# 隱藏原始 wp-admin 路徑
location ^~ /wp-admin {
    return 301 \$scheme://\$host/{$custom_admin_url}/;
}

# 允許 wp-admin 內的資源載入
location ~ ^/wp-admin/(.*\.(?:js|css|png|jpg|jpeg|gif|ico|svg))$ {
    try_files \$uri =404;
}";
    } else {
        $rewriteRule .= "

# 預設 wp-admin 處理
location ^~ /wp-admin {
    try_files \$uri \$uri/ /index.php?\$args;
}";
    }
    
    echo "   偽靜態規則內容:\n";
    echo "   " . str_replace("\n", "\n   ", $rewriteRule) . "\n\n";
    
    echo "   目標檔案: /www/server/panel/vhost/rewrite/{$domain}.conf\n\n";
    
    echo "4. 執行偽靜態規則設置...\n";
    $result = $btAPI->setupWordPressRewrite($domain, $custom_admin_url);
    
    if ($result['success']) {
        echo "✅ 偽靜態規則設置成功！\n\n";
        
        echo "5. 驗證設置結果...\n";
        echo "   請檢查以下位置的偽靜態規則檔案:\n";
        echo "   /www/server/panel/vhost/rewrite/{$domain}.conf\n\n";
        
        echo "6. 測試建議...\n";
        echo "   1. 檢查網站是否可以正常訪問: https://www.{$domain}\n";
        echo "   2. 測試固定連結是否正常運作\n";
        if ($custom_admin_url !== 'wp-admin') {
            echo "   3. 測試自訂管理後台: https://www.{$domain}/{$custom_admin_url}\n";
        }
        echo "   4. 檢查 /wp-admin 是否正確重定向\n\n";
        
    } else {
        echo "❌ 偽靜態規則設置失敗\n";
        echo "   錯誤訊息: " . ($result['message'] ?? '未知錯誤') . "\n\n";
        
        echo "🔍 故障排除建議:\n";
        echo "   1. 檢查 BT Panel 認證設定是否正確\n";
        echo "   2. 確認網站 {$domain} 在 BT Panel 中存在\n";
        echo "   3. 檢查 BT Panel 檔案權限設定\n";
        echo "   4. 查看 BT Panel 錯誤日誌\n\n";
        
        if (isset($result['result'])) {
            echo "   API 回應詳情:\n";
            echo "   " . json_encode($result['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ 測試執行失敗\n";
    echo "錯誤: " . $e->getMessage() . "\n\n";
    
    echo "🔍 可能的原因:\n";
    echo "   1. BT Panel API 連線問題\n";
    echo "   2. 認證失敗 (Cookie 或 API Key 過期)\n";
    echo "   3. 網路連線問題\n";
    echo "   4. BT Panel 伺服器響應異常\n";
}

echo "=== 測試完成 ===\n";