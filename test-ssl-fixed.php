<?php
/**
 * 測試修正後的 SSL 功能
 */

require_once __DIR__ . '/config-manager.php';
require_once __DIR__ . '/includes/class-bt-panel-api.php';

echo "測試修正後的 SSL 功能\n";
echo "====================\n\n";

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
    
    // 初始化 API
    $btAPI = new BTPanelAPI($bt_config);
    
    // 測試網域
    $domain = 'yaoguo.tw';
    echo "測試網域: {$domain}\n\n";
    
    // 測試 1: 獲取網站列表
    echo "1. 獲取網站列表\n";
    echo "===============\n";
    try {
        $sites = $btAPI->btApiRequest('/data?action=getData', [
            'table' => 'sites',
            'limit' => 10,
            'p' => 1
        ]);
        
        if (isset($sites['data'])) {
            echo "✅ 成功獲取網站列表 (" . count($sites['data']) . " 個網站)\n";
            $foundSite = null;
            foreach ($sites['data'] as $site) {
                echo "  - {$site['name']} (ID: {$site['id']})\n";
                if ($site['name'] === "www.{$domain}" || $site['name'] === $domain) {
                    $foundSite = $site;
                }
            }
            
            if ($foundSite) {
                echo "✅ 找到目標網站: {$foundSite['name']} (ID: {$foundSite['id']})\n";
            } else {
                echo "❌ 未找到目標網站\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ 獲取網站列表失敗: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 測試 2: SSL 狀態檢查
    echo "2. SSL 狀態檢查\n";
    echo "==============\n";
    try {
        $sslStatus = $btAPI->btApiRequest('/site?action=GetSSL', [
            'siteName' => "www.{$domain}"
        ]);
        echo "✅ SSL 檢查成功\n";
        echo "回應: " . json_encode($sslStatus, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "❌ SSL 檢查失敗: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // 測試 3: 測試 SSL 申請端點（不實際申請）
    echo "3. 測試 SSL 申請端點\n";
    echo "==================\n";
    try {
        // 僅測試端點可用性，不實際申請
        $url = $bt_config['BT_API_URL'] . '/acme?action=apply_cert_api';
        echo "測試端點: {$url}\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['test' => 'endpoint']));
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
        
        echo "HTTP 代碼: {$httpCode}\n";
        if ($httpCode === 200) {
            echo "✅ SSL 申請端點可用\n";
            $result = json_decode($response, true);
            if ($result) {
                echo "回應: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
            }
        } elseif ($httpCode === 404) {
            echo "❌ SSL 申請端點不存在\n";
        } else {
            echo "⚠️ 其他狀態: {$httpCode}\n";
            echo "回應片段: " . substr($response, 0, 200) . "\n";
        }
    } catch (Exception $e) {
        echo "❌ 端點測試失敗: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    echo "📋 修正摘要:\n";
    echo "1. SSL 申請參數已修正為雙網域格式: [\"domain.tw\", \"www.domain.tw\"]\n";
    echo "2. 網站查找邏輯已支援多種格式匹配\n";
    echo "3. 偽靜態規則路徑已修正為 www.domain.tw.conf\n";
    echo "4. HTTPS 強制重定向已使用正確的網站名稱\n";
    
} catch (Exception $e) {
    echo "❌ 測試失敗: " . $e->getMessage() . "\n";
}

echo "\n測試完成。\n";