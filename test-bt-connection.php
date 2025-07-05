<?php
/**
 * BT Panel 連線測試腳本
 * 用於檢測 BT Panel API 連線狀況
 */

require_once __DIR__ . '/config-manager.php';
require_once __DIR__ . '/includes/class-bt-panel-api.php';

echo "BT Panel 連線測試工具\n";
echo "==================\n\n";

try {
    // 載入配置
    $config = new ConfigManager();
    
    // 取得 BT API 配置
    $bt_config = [
        'BT_API_URL' => $config->get('api_credentials.btcn.panel_url'),
        'BT_SESSION_COOKIE' => $config->get('api_credentials.btcn.session_cookie'),
        'BT_HTTP_TOKEN' => $config->get('api_credentials.btcn.http_token'),
        'BT_KEY' => $config->get('api_credentials.btcn.api_key'),
    ];
    
    echo "配置檢查:\n";
    echo "API URL: " . ($bt_config['BT_API_URL'] ?: '❌ 未設定') . "\n";
    echo "Cookie: " . (empty($bt_config['BT_SESSION_COOKIE']) ? '❌ 未設定' : '✅ 已設定 (' . strlen($bt_config['BT_SESSION_COOKIE']) . ' 字元)') . "\n";
    echo "HTTP Token: " . (empty($bt_config['BT_HTTP_TOKEN']) ? '❌ 未設定' : '✅ 已設定 (' . strlen($bt_config['BT_HTTP_TOKEN']) . ' 字元)') . "\n";
    echo "API Key: " . (empty($bt_config['BT_KEY']) ? '❌ 未設定' : '✅ 已設定 (備用)') . "\n\n";
    
    if (empty($bt_config['BT_API_URL'])) {
        echo "❌ 錯誤：未設定 BT Panel API URL\n";
        exit(1);
    }
    
    // 簡單的連線測試
    echo "執行連線測試...\n";
    $url = $bt_config['BT_API_URL'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    echo "連線結果:\n";
    echo "HTTP 代碼: {$httpCode}\n";
    echo "連線時間: {$connectTime}s\n";
    echo "總時間: {$totalTime}s\n";
    
    if ($error) {
        echo "❌ CURL 錯誤: {$error}\n";
        
        if (strpos($error, 'Connection reset by peer') !== false) {
            echo "\n📝 連線被重置的可能原因:\n";
            echo "1. BT Panel 服務未啟動\n";
            echo "2. 防火牆封鎖連線\n";
            echo "3. 網路連線問題\n";
            echo "4. SSL/TLS 設定問題\n";
        }
    } else {
        if ($httpCode === 200) {
            echo "✅ 基本連線成功\n";
            
            if (strpos($response, 'bt') !== false || strpos($response, 'panel') !== false) {
                echo "✅ 看起來是 BT Panel 回應\n";
            } else {
                echo "⚠️ 回應內容可能不是 BT Panel\n";
            }
        } else {
            echo "⚠️ HTTP 狀態碼: {$httpCode}\n";
        }
    }
    
    echo "\n";
    
    // 如果有認證資訊，嘗試 API 測試
    if (!empty($bt_config['BT_SESSION_COOKIE']) && !empty($bt_config['BT_HTTP_TOKEN'])) {
        echo "執行 API 認證測試...\n";
        
        // 初始化 BT API
        $btAPI = new BTPanelAPI($bt_config);
        
        try {
            // 嘗試簡單的 API 測試 - 建立測試網站（不會實際建立）
            echo "嘗試 API 呼叫測試...\n";
            
            // 直接測試 API 請求而不呼叫特定方法
            $test_data = ['test' => 'connection'];
            $url = $bt_config['BT_API_URL'] . '/system?action=GetSystemTotal';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            
            $host = parse_url($bt_config['BT_API_URL'], PHP_URL_HOST) . ':' . parse_url($bt_config['BT_API_URL'], PHP_URL_PORT);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Host: ' . $host,
                'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
                'Accept: application/json, text/plain, */*',
                'Content-Type: application/x-www-form-urlencoded',
                'x-http-token: ' . $bt_config['BT_HTTP_TOKEN'],
                'Cookie: ' . $bt_config['BT_SESSION_COOKIE'],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception($error);
            }
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result && !json_last_error()) {
                    echo "✅ API 認證成功 - 回應為有效 JSON\n";
                    if (isset($result['status'])) {
                        echo "回應狀態: " . ($result['status'] ? '成功' : '失敗') . "\n";
                    }
                } else {
                    echo "⚠️ API 回應但非 JSON 格式\n";
                    echo "回應片段: " . substr($response, 0, 100) . "\n";
                }
            } else {
                echo "❌ API 回應 HTTP {$httpCode}\n";
            }
            
        } catch (Exception $e) {
            echo "❌ API 認證失敗: " . $e->getMessage() . "\n";
            
            if (strpos($e->getMessage(), 'Connection reset by peer') !== false) {
                echo "\n🔧 建議更新認證資訊:\n";
                echo "1. 瀏覽器登入 BT Panel: {$url}\n";
                echo "2. 開啟開發者工具 (F12)\n";
                echo "3. 重新整理頁面\n";
                echo "4. 在 Network 分頁找到 API 請求\n";
                echo "5. 複製新的 Cookie 和 x-http-token\n";
                echo "6. 更新 deploy-config.json\n";
            }
        }
    } else {
        echo "⚠️ 未設定完整認證資訊，跳過 API 測試\n";
    }
    
} catch (Exception $e) {
    echo "❌ 測試失敗: " . $e->getMessage() . "\n";
}

echo "\n測試完成。\n";