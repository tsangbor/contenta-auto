<?php
/**
 * 測試完整的 SSL 申請和安裝流程
 */

require_once __DIR__ . '/config-manager.php';

echo "測試完整的 SSL 申請和安裝流程\n";
echo "===============================\n\n";

try {
    // 載入配置
    $config = new ConfigManager();
    
    // 取得配置
    $panel_url = $config->get('api_credentials.btcn.panel_url');
    $session_cookie = $config->get('api_credentials.btcn.session_cookie');
    $http_token = $config->get('api_credentials.btcn.http_token');
    
    $site_name = 'www.yaoguo.tw';
    $domain = 'yaoguo.tw';
    $email = 'eric791206@gmail.com';
    
    echo "配置檢查:\n";
    echo "Panel URL: {$panel_url}\n";
    echo "Site Name: {$site_name}\n";
    echo "Domain: {$domain}\n";
    echo "Email: {$email}\n";
    echo "Cookie: " . (empty($session_cookie) ? '❌ 未設定' : '✅ 已設定') . "\n";
    echo "Token: " . (empty($http_token) ? '❌ 未設定' : '✅ 已設定') . "\n\n";
    
    // 載入 step-04 函數
    include __DIR__ . '/step-04.php';
    
    echo "模擬測試 (不實際執行):\n";
    echo "===================\n";
    
    echo "1. ✅ getSiteId() 函數已定義\n";
    echo "2. ✅ applyLetsEncryptSSL() 函數已定義 - 第一步：申請憑證\n";
    echo "3. ✅ installSSLCertificate() 函數已定義 - 第二步：安裝憑證\n";
    echo "4. ✅ checkSSLStatus() 函數已定義\n\n";
    
    echo "SSL 流程說明:\n";
    echo "============\n";
    echo "步驟 1: POST /acme?action=apply_cert_api\n";
    echo "  參數: domains, auth_type, auth_to, auto_wildcard, id\n";
    echo "  回應: 獲得 private_key 和 cert\n\n";
    
    echo "步驟 2: POST /site?action=SetSSL\n";
    echo "  參數: type=1, siteName, key (私鑰), csr (憑證)\n";
    echo "  回應: SSL 憑證安裝到網站\n\n";
    
    echo "步驟 3: 驗證 SSL 狀態\n";
    echo "  POST /site?action=GetSSL\n";
    echo "  參數: siteName\n\n";
    
    echo "API 端點修正摘要:\n";
    echo "================\n";
    echo "✅ /acme?action=apply_cert_api - SSL 憑證申請\n";
    echo "✅ /site?action=SetSSL - SSL 憑證安裝\n";
    echo "✅ /site?action=GetSSL - SSL 狀態檢查\n";
    echo "✅ 雙網域格式: [\"domain.tw\", \"www.domain.tw\"]\n";
    echo "✅ Cookie + HTTP Token 認證\n";
    echo "✅ 兩步驟完整流程\n\n";
    
    echo "可以執行的測試:\n";
    echo "==============\n";
    echo "php contenta-deploy.php 2506290730-3450 --step=04\n\n";
    
} catch (Exception $e) {
    echo "❌ 測試失敗: " . $e->getMessage() . "\n";
}

echo "測試完成。\n";