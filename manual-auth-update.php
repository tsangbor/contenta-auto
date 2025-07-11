<?php
/**
 * 手動更新 BT Panel 認證資訊
 * 可以手動輸入 Cookie 和 Token，或使用自動登入模式
 */

// 載入配置
$config_file = 'config/deploy-config.json';
$config = json_decode(file_get_contents($config_file), true);

echo "=== BT Panel 認證更新工具 ===\n\n";
echo "請選擇更新模式:\n";
echo "1. 手動輸入 Cookie 和 Token\n";
echo "2. 自動登入取得 (使用 Playwright)\n";
echo "\n請輸入選項 (1 或 2): ";

$mode = trim(fgets(STDIN));

if ($mode == '1') {
    // 手動輸入模式
    echo "\n=== 手動輸入模式 ===\n";
    echo "請從瀏覽器開發者工具中複製以下資訊:\n\n";
    
    echo "1. Cookie (request_token=...): ";
    $cookie = trim(fgets(STDIN));
    
    echo "2. Token (X-CSRF-Token): ";
    $token = trim(fgets(STDIN));
    
    // 驗證輸入
    if (empty($cookie) || empty($token)) {
        echo "\n❌ Cookie 或 Token 不能為空\n";
        exit(1);
    }
    
    // 更新配置
    $config['api_credentials']['btcn']['auth']['cookie'] = $cookie;
    $config['api_credentials']['btcn']['auth']['token'] = $token;
    $config['api_credentials']['btcn']['_last_updated'] = [
        'cookie' => date('Y-m-d H:i:s'),
        'token' => date('Y-m-d H:i:s')
    ];
    
    // 儲存配置
    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "\n✅ 認證資訊已更新成功!\n";
    echo "Cookie: " . substr($cookie, 0, 30) . "...\n";
    echo "Token: " . substr($token, 0, 20) . "...\n";
    
} elseif ($mode == '2') {
    // 自動登入模式
    echo "\n=== 自動登入模式 ===\n";
    
    // 檢查是否安裝了 Playwright
    $check_playwright = shell_exec('npx playwright --version 2>&1');
    if (strpos($check_playwright, 'command not found') !== false || strpos($check_playwright, 'not found') !== false) {
        echo "❌ Playwright 未安裝\n";
        echo "請先安裝 Playwright:\n";
        echo "npm install playwright\n";
        echo "npx playwright install chromium\n";
        exit(1);
    }
    
    echo "使用 Playwright 自動登入...\n";
    
    // 載入認證管理器
    require_once 'includes/class-auth-manager.php';
    
    // 使用自訂的認證管理器
    class ManualAuthManager extends AuthManager {
        protected function getLoginCredentials() {
            $config_data = json_decode(file_get_contents('config/deploy-config.json'), true);
            $btcn_config = $config_data['api_credentials']['btcn'] ?? [];
            
            return [
                'username' => $btcn_config['panel_login'] ?? getenv('BTPANEL_USERNAME'),
                'password' => $btcn_config['panel_password'] ?? getenv('BTPANEL_PASSWORD'),
                'login_url' => $btcn_config['panel_auth'] ?? 'https://jp3.contenta.tw:8888/btpanel'
            ];
        }
    }
    
    $authManager = new ManualAuthManager('config/deploy-config.json');
    
    // 執行更新
    if ($authManager->updateCredentials(true)) {
        echo "\n✅ 自動登入成功，認證資訊已更新!\n";
    } else {
        echo "\n❌ 自動登入失敗\n";
        echo "請檢查日誌: logs/auth-manager.log\n";
        exit(1);
    }
    
} else {
    echo "\n❌ 無效的選項\n";
    exit(1);
}

// 顯示認證資訊
echo "\n=== 當前認證資訊 ===\n";
$updated_config = json_decode(file_get_contents($config_file), true);
$last_updated = $updated_config['api_credentials']['btcn']['_last_updated']['cookie'] ?? 'N/A';
echo "最後更新時間: {$last_updated}\n";

// 計算過期時間
if ($last_updated !== 'N/A') {
    $last_time = strtotime($last_updated);
    $now = time();
    $diff_hours = ($now - $last_time) / 3600;
    
    if ($diff_hours > 24) {
        echo "⚠️  認證已過期 (" . round($diff_hours, 1) . " 小時)\n";
    } else {
        echo "✅ 認證有效 (剩餘 " . round(24 - $diff_hours, 1) . " 小時)\n";
    }
}

echo "\n";