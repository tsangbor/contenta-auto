<?php
/**
 * BT Panel SSL API 端點測試
 * 測試不同的 SSL 相關 API 端點
 */

require_once __DIR__ . '/config-manager.php';
require_once __DIR__ . '/includes/class-bt-panel-api.php';

echo "BT Panel SSL API 端點測試\n";
echo "========================\n\n";

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
    
    echo "API URL: " . $bt_config['BT_API_URL'] . "\n\n";
    
    // 初始化 API
    $btAPI = new BTPanelAPI($bt_config);
    
    // 測試 1: 獲取網站列表
    echo "測試 1: 獲取網站列表\n";
    echo "===================\n";
    try {
        $sites = $btAPI->btApiRequest('/data?action=getData', [
            'table' => 'sites',
            'limit' => 10,
            'p' => 1
        ]);
        
        if (isset($sites['data'])) {
            echo "✅ 成功獲取網站列表 (" . count($sites['data']) . " 個網站)\n";
            foreach ($sites['data'] as $site) {
                echo "  - {$site['name']} (ID: {$site['id']})\n";
            }
        } else {
            echo "❌ 網站列表格式異常\n";
            echo "回應: " . json_encode($sites) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ 獲取網站列表失敗: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 測試 2: 測試 SSL 檢查端點
    echo "測試 2: SSL 狀態檢查\n";
    echo "==================\n";
    $testDomains = ['www.yaoguo.tw', 'yaoguo.tw'];
    
    foreach ($testDomains as $testDomain) {
        echo "檢查域名: {$testDomain}\n";
        try {
            $sslStatus = $btAPI->btApiRequest('/site?action=GetSSL', [
                'siteName' => $testDomain
            ]);
            echo "  ✅ SSL 檢查成功\n";
            echo "  回應: " . json_encode($sslStatus) . "\n";
        } catch (Exception $e) {
            echo "  ❌ SSL 檢查失敗: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
    // 測試 3: 測試不同的 SSL 申請端點
    echo "測試 3: SSL 申請端點測試\n";
    echo "=====================\n";
    
    $sslEndpoints = [
        '/acme?action=apply_cert_api',
        '/ssl?action=ApplyLetsEncrypt',
        '/site?action=HttpsToHttp',
        '/plugin?action=a&name=ssl&s=apply_cert_api'
    ];
    
    foreach ($sslEndpoints as $endpoint) {
        echo "測試端點: {$endpoint}\n";
        try {
            $ch = curl_init();
            $url = $bt_config['BT_API_URL'] . $endpoint;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['test' => 'check']));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            
            $host = parse_url($bt_config['BT_API_URL'], PHP_URL_HOST) . ':' . parse_url($bt_config['BT_API_URL'], PHP_URL_PORT);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Host: ' . $host,
                'Content-Type: application/x-www-form-urlencoded',
                'x-http-token: ' . $bt_config['BT_HTTP_TOKEN'],
                'Cookie: ' . $bt_config['BT_SESSION_COOKIE'],
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "  HTTP 代碼: {$httpCode}\n";
            if ($httpCode === 200) {
                echo "  ✅ 端點存在\n";
                $result = json_decode($response, true);
                if ($result) {
                    echo "  回應: " . json_encode($result) . "\n";
                } else {
                    echo "  回應片段: " . substr($response, 0, 100) . "\n";
                }
            } elseif ($httpCode === 404) {
                echo "  ❌ 端點不存在\n";
            } else {
                echo "  ⚠️ 其他錯誤\n";
            }
        } catch (Exception $e) {
            echo "  ❌ 測試失敗: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ 測試失敗: " . $e->getMessage() . "\n";
}

echo "測試完成。\n";