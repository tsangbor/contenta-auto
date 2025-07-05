<?php
/**
 * 獨立執行 WordPress 偽靜態規則設置
 * 使用方式: php setup-rewrite-rules.php [domain]
 */

require_once __DIR__ . '/config-manager.php';
require_once __DIR__ . '/includes/class-bt-panel-api.php';

if ($argc < 2) {
    echo "使用方式: php setup-rewrite-rules.php [domain]\n";
    echo "例如: php setup-rewrite-rules.php yaoguo.tw\n";
    exit(1);
}

$domain = $argv[1];
$config = ConfigManager::getInstance();

echo "=== 設置 WordPress 偽靜態規則 ===\n";
echo "網域: {$domain}\n";
echo "執行時間: " . date('Y-m-d H:i:s') . "\n\n";

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

if (empty($bt_config['BT_API_URL'])) {
    echo "錯誤: 未設定 BT Panel API URL\n";
    exit(1);
}

// 初始化 BT Panel API
$btAPI = new BTPanelAPI($bt_config, $logger);

try {
    // 取得自訂管理後台設定
    $custom_admin_url = $config->get('wordpress_security.custom_admin_url', 'wp-admin');
    
    echo "自訂管理後台網址: /{$custom_admin_url}\n";
    echo "目標檔案: /www/server/panel/vhost/rewrite/{$domain}.conf\n\n";
    
    // 執行偽靜態規則設置
    $result = $btAPI->setupWordPressRewrite($domain, $custom_admin_url);
    
    if ($result['success']) {
        echo "\n✅ WordPress 偽靜態規則設置完成！\n\n";
        
        echo "📋 設置摘要:\n";
        echo "   - 網域: {$domain}\n";
        echo "   - 配置檔案: /www/server/panel/vhost/rewrite/{$domain}.conf\n";
        echo "   - 管理後台: /{$custom_admin_url}\n";
        echo "   - 狀態: 成功\n\n";
        
        echo "🔍 驗證步驟:\n";
        echo "   1. 訪問網站首頁: https://www.{$domain}\n";
        echo "   2. 測試文章固定連結\n";
        if ($custom_admin_url !== 'wp-admin') {
            echo "   3. 訪問管理後台: https://www.{$domain}/{$custom_admin_url}\n";
            echo "   4. 檢查 /wp-admin 重定向是否正常\n";
        } else {
            echo "   3. 訪問管理後台: https://www.{$domain}/wp-admin\n";
        }
        echo "\n如果網站無法正常訪問，請檢查 Nginx 配置是否已重載。\n";
        
    } else {
        echo "\n❌ 偽靜態規則設置失敗\n";
        $errorMsg = $result['message'] ?? '未知錯誤';
        echo "錯誤訊息: {$errorMsg}\n\n";
        
        echo "🔧 故障排除:\n";
        echo "   1. 檢查 BT Panel 是否可以正常訪問\n";
        echo "   2. 確認網站 {$domain} 在 BT Panel 中存在\n";
        echo "   3. 檢查 Cookie 或 API Key 是否有效\n";
        echo "   4. 確認目錄 /www/server/panel/vhost/rewrite/ 是否存在且可寫\n\n";
        
        if (isset($result['result'])) {
            echo "API 回應詳情:\n";
            echo json_encode($result['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n❌ 執行失敗: " . $e->getMessage() . "\n\n";
    
    echo "🔧 可能的解決方案:\n";
    echo "   1. 檢查網路連線\n";
    echo "   2. 更新 BT Panel 認證資訊\n";
    echo "   3. 確認 BT Panel 服務正常運行\n";
    echo "   4. 檢查防火牆設定\n";
    
    exit(1);
}

echo "=== 完成 ===\n";