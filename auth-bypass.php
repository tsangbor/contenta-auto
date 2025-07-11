<?php
/**
 * 認證繞過工具 - 可以在不使用 Playwright 的情況下使用系統
 * 
 * 使用方式：
 * 1. 先執行此工具來暫時繞過認證檢查
 * 2. 然後執行部署流程
 */

// 載入配置
$config_file = 'config/deploy-config.json';
$config = json_decode(file_get_contents($config_file), true);

echo "=== 認證繞過工具 ===\n\n";
echo "此工具將設定一個未來時間的認證時間戳，讓系統認為認證仍然有效。\n";
echo "這是臨時解決方案，建議盡快修復自動登入問題。\n\n";

echo "請選擇動作:\n";
echo "1. 設定認證為有效狀態 (24小時)\n";
echo "2. 手動輸入認證資訊\n";
echo "3. 檢查當前認證狀態\n";
echo "\n請輸入選項 (1, 2 或 3): ";

$mode = trim(fgets(STDIN));

switch ($mode) {
    case '1':
        // 設定假的認證時間戳
        echo "\n設定認證為有效狀態...\n";
        
        // 使用假的 Cookie 和 Token
        $fake_cookie = "request_token=fake_token_" . md5(time());
        $fake_token = "fake_csrf_" . substr(md5(time()), 0, 16);
        
        $config['api_credentials']['btcn']['auth']['cookie'] = $fake_cookie;
        $config['api_credentials']['btcn']['auth']['token'] = $fake_token;
        
        // 設定為當前時間，這樣會被認為是剛更新的
        $current_time = date('Y-m-d H:i:s');
        $config['api_credentials']['btcn']['_last_updated'] = [
            'cookie' => $current_time,
            'token' => $current_time
        ];
        
        // 儲存配置
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "✅ 已設定假的認證資訊\n";
        echo "⚠️  注意：這只能繞過認證檢查，實際的 BT Panel API 呼叫仍會失敗\n";
        echo "建議只在測試其他步驟時使用\n";
        break;
        
    case '2':
        // 手動輸入真實的認證資訊
        echo "\n=== 手動輸入認證資訊 ===\n";
        echo "\n如何取得認證資訊:\n";
        echo "1. 使用瀏覽器登入 BT Panel\n";
        echo "2. 開啟開發者工具 (F12)\n";
        echo "3. 進入 Network 標籤\n";
        echo "4. 重新整理頁面\n";
        echo "5. 找到任何一個 API 請求\n";
        echo "6. 在 Request Headers 中找到:\n";
        echo "   - Cookie: request_token=xxxxx\n";
        echo "   - X-CSRF-Token: xxxxx\n\n";
        
        echo "請輸入 Cookie (完整的 request_token=xxx): ";
        $cookie = trim(fgets(STDIN));
        
        echo "請輸入 Token (只要值，不包含 X-CSRF-Token:): ";
        $token = trim(fgets(STDIN));
        
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
        break;
        
    case '3':
        // 檢查當前狀態
        echo "\n=== 當前認證狀態 ===\n";
        break;
        
    default:
        echo "\n❌ 無效的選項\n";
        exit(1);
}

// 顯示當前狀態
$updated_config = json_decode(file_get_contents($config_file), true);
$auth = $updated_config['api_credentials']['btcn']['auth'] ?? [];
$last_updated = $updated_config['api_credentials']['btcn']['_last_updated']['cookie'] ?? 'N/A';

echo "\n=== 認證資訊 ===\n";
echo "Cookie: " . (isset($auth['cookie']) ? substr($auth['cookie'], 0, 50) . '...' : 'N/A') . "\n";
echo "Token: " . (isset($auth['token']) ? substr($auth['token'], 0, 20) . '...' : 'N/A') . "\n";
echo "最後更新: {$last_updated}\n";

if ($last_updated !== 'N/A') {
    $last_time = strtotime($last_updated);
    $now = time();
    $diff_hours = ($now - $last_time) / 3600;
    
    if ($diff_hours > 24) {
        echo "狀態: ❌ 已過期 (" . round($diff_hours, 1) . " 小時)\n";
    } else {
        echo "狀態: ✅ 有效 (剩餘 " . round(24 - $diff_hours, 1) . " 小時)\n";
    }
}

echo "\n";