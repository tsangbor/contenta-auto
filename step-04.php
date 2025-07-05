<?php
/**
 * 步驟 04: SSL 憑證設置
 * 透過 BT Panel Let's Encrypt API 申請 SSL 憑證
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$website_info = json_decode(file_get_contents($work_dir . '/bt_website.json'), true);

$domain = $processed_data['confirmed_data']['domain'];
$site_name = $website_info['site_name']; // www.{domain}
$user_email = $processed_data['confirmed_data']['user_email'];

$deployer->log("開始設置 SSL 憑證: {$site_name}");

// 取得 API 憑證（支援兩種認證方式）
$api_key = $config->get('api_credentials.btcn.api_key');
$panel_url = $config->get('api_credentials.btcn.panel_url');
$session_cookie = $config->get('api_credentials.btcn.session_cookie');
$http_token = $config->get('api_credentials.btcn.http_token');

if (empty($panel_url) || (empty($session_cookie) && empty($api_key))) {
    $deployer->log("跳過 SSL 設置 - 未設定 API 憑證");
    return ['status' => 'skipped', 'reason' => 'no_api_credentials'];
}

// 優先使用 Cookie 認證
if (!empty($session_cookie) && !empty($http_token)) {
    $deployer->log("使用 Cookie 認證模式");
} else {
    $deployer->log("使用 API Key 認證模式（備用）");
}

/**
 * 獲取網站 ID
 */
function getSiteId($site_name, $panel_url, $session_cookie, $http_token)
{
    $url = rtrim($panel_url, '/') . '/data?action=getData';
    
    $post_data = [
        'table' => 'sites',
        'limit' => 100,
        'p' => 1
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $host = parse_url($panel_url, PHP_URL_HOST) . ':' . parse_url($panel_url, PHP_URL_PORT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Host: ' . $host,
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'x-http-token: ' . $http_token,
        'Cookie: ' . $session_cookie,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'])) {
            foreach ($result['data'] as $site) {
                if ($site['name'] === $site_name) {
                    return $site['id'];
                }
            }
        }
    }
    
    return null;
}

/**
 * Let's Encrypt SSL 申請 - 第一步：申請憑證
 */
function applyLetsEncryptSSL($site_name, $domain, $email, $panel_url, $session_cookie, $http_token)
{
    $url = rtrim($panel_url, '/') . '/acme?action=apply_cert_api';
    
    // 準備請求參數（基於實際 API 格式）
    // 需要先獲取網站 ID
    $site_id = getSiteId($site_name, $panel_url, $session_cookie, $http_token);
    if (!$site_id) {
        return [
            'http_code' => 404,
            'response' => 'Site not found',
            'post_data' => []
        ];
    }
    
    $post_data = [
        'domains' => json_encode([$domain, $site_name]),  // ["yaoguo.tw", "www.yaoguo.tw"]
        'auth_type' => 'http',
        'auth_to' => json_encode([$domain, $site_name]),  // ["yaoguo.tw", "www.yaoguo.tw"] 
        'auto_wildcard' => 0,
        'id' => $site_id
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90); // SSL 申請可能需要較長時間
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $host = parse_url($panel_url, PHP_URL_HOST) . ':' . parse_url($panel_url, PHP_URL_PORT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Host: ' . $host,
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'x-http-token: ' . $http_token,
        'Cookie: ' . $session_cookie,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => $response,
        'post_data' => $post_data
    ];
}

/**
 * 設置 WordPress 偽靜態規則
 */
function setupWordPressRewrite($domain, $site_name, $panel_url, $session_cookie, $http_token, $custom_admin_url = 'wp-admin', $hide_admin_login = false)
{
    global $deployer;
    
    $deployer->log("⚙️ 設置 WordPress Nginx 偽靜態規則");

    // WordPress 偽靜態規則（使用簡化格式）
    $rewriteRule = "location /
{
     try_files \$uri \$uri/ /index.php?\$args;
}";

    // 只有當啟用 hide_admin_login 且自訂管理後台網址不是預設的 wp-admin 時，才新增重定向規則
    if ($hide_admin_login && $custom_admin_url !== 'wp-admin') {
        $rewriteRule .= "

rewrite /wp-admin$ \$scheme://\$host\$uri/ permanent;";
    }

    // 使用正確的檔案路徑和參數格式
    $rewrite_file_path = "/www/server/panel/vhost/rewrite/{$site_name}.conf";
    $deployer->log("偽靜態規則檔案路徑: {$rewrite_file_path}");
    
    $data = [
        'path' => $rewrite_file_path,
        'data' => $rewriteRule,
        'encoding' => 'utf-8'
    ];

    $url = rtrim($panel_url, '/') . '/files?action=SaveFileBody';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $host = parse_url($panel_url, PHP_URL_HOST) . ':' . parse_url($panel_url, PHP_URL_PORT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Host: ' . $host,
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'x-http-token: ' . $http_token,
        'Cookie: ' . $session_cookie,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['status']) && $result['status'] === true) {
            $deployer->log("✅ WordPress 偽靜態規則設置成功");
            return true;
        } else {
            $errorMsg = $result['msg'] ?? '未知錯誤';
            $deployer->log("❌ 設置偽靜態規則失敗：{$errorMsg}");
            return false;
        }
    } else {
        $deployer->log("❌ 偽靜態規則 API 請求失敗: HTTP {$http_code}");
        return false;
    }
}

/**
 * 檢查 SSL 狀態
 */
function checkSSLStatus($site_name, $panel_url, $session_cookie, $http_token)
{
    $url = rtrim($panel_url, '/') . '/site?action=GetSSL';
    
    $request_data = [
        'siteName' => $site_name
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($request_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $host = parse_url($panel_url, PHP_URL_HOST) . ':' . parse_url($panel_url, PHP_URL_PORT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Host: ' . $host,
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'x-http-token: ' . $http_token,
        'Cookie: ' . $session_cookie,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $http_code, 'response' => json_decode($response, true)];
}

/**
 * 安裝 SSL 憑證 - 第二步：安裝憑證到網站
 */
function installSSLCertificate($site_name, $private_key, $certificate, $panel_url, $session_cookie, $http_token)
{
    $url = rtrim($panel_url, '/') . '/site?action=SetSSL';
    
    // 準備請求參數（基於實際 API 格式）
    $post_data = [
        'type' => 1,  // 1 = 自定義憑證
        'siteName' => $site_name,
        'key' => $private_key,
        'csr' => $certificate
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $host = parse_url($panel_url, PHP_URL_HOST) . ':' . parse_url($panel_url, PHP_URL_PORT);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Host: ' . $host,
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
        'Accept: application/json, text/plain, */*',
        'Content-Type: application/x-www-form-urlencoded',
        'x-http-token: ' . $http_token,
        'Cookie: ' . $session_cookie,
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => $response,
        'post_data' => $post_data
    ];
}

try {
    // 檢查當前 SSL 狀態
    $deployer->log("檢查 SSL 狀態...");
    $ssl_status = checkSSLStatus($site_name, $panel_url, $session_cookie, $http_token);
    
    $ssl_exists = false;
    if ($ssl_status['http_code'] === 200 && isset($ssl_status['response']['status'])) {
        if ($ssl_status['response']['status'] === true || 
            (isset($ssl_status['response']['data']) && !empty($ssl_status['response']['data']))) {
            $ssl_exists = true;
            $deployer->log("SSL 憑證已存在");
        }
    }
    
    if (!$ssl_exists) {
        // 申請 Let's Encrypt SSL 憑證
        $deployer->log("申請 Let's Encrypt SSL 憑證...");
        $deployer->log("網站名稱: {$site_name}");
        $deployer->log("網域: {$domain}");
        $deployer->log("電子郵件: {$user_email}");
        
        $ssl_result = applyLetsEncryptSSL($site_name, $domain, $user_email, $panel_url, $session_cookie, $http_token);
        
        $deployer->log("SSL 申請 HTTP 狀態: {$ssl_result['http_code']}");
        $deployer->log("SSL 申請回應: " . $ssl_result['response']);
        
        if ($ssl_result['http_code'] === 200) {
            // 嘗試解析回應
            $response_data = json_decode($ssl_result['response'], true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                // JSON 回應
                if (isset($response_data['status']) && $response_data['status'] === true) {
                    $deployer->log("步驟 1: SSL 憑證申請成功");
                    
                    // 檢查是否有私鑰和憑證
                    if (isset($response_data['private_key']) && isset($response_data['cert'])) {
                        $deployer->log("步驟 2: 開始安裝 SSL 憑證到網站...");
                        
                        $install_result = installSSLCertificate(
                            $site_name,
                            $response_data['private_key'],
                            $response_data['cert'],
                            $panel_url,
                            $session_cookie,
                            $http_token
                        );
                        
                        $deployer->log("SSL 安裝 HTTP 狀態: {$install_result['http_code']}");
                        $deployer->log("SSL 安裝回應: " . $install_result['response']);
                        
                        if ($install_result['http_code'] === 200) {
                            $install_response = json_decode($install_result['response'], true);
                            if (isset($install_response['status']) && $install_response['status'] === true) {
                                $deployer->log("SSL 憑證安裝成功");
                                $ssl_action = 'installed';
                            } else {
                                $deployer->log("SSL 憑證安裝失敗: " . ($install_response['msg'] ?? '未知錯誤'));
                                $ssl_action = 'install_failed';
                            }
                        } else {
                            $deployer->log("SSL 憑證安裝請求失敗");
                            $ssl_action = 'install_failed';
                        }
                    } else {
                        $deployer->log("SSL 憑證申請成功但未獲得私鑰和憑證");
                        $ssl_action = 'incomplete';
                    }
                    
                } elseif (isset($response_data['msg'])) {
                    $deployer->log("SSL 憑證申請失敗: " . $response_data['msg']);
                    $deployer->log("繼續進行後續步驟...");
                    $ssl_action = 'failed';
                    
                } else {
                    $deployer->log("SSL 憑證申請回應格式未知");
                    $deployer->log("繼續進行後續步驟...");
                    $ssl_action = 'unknown';
                }
                
            } else {
                // 非 JSON 回應，檢查是否包含成功訊息
                if (strpos($ssl_result['response'], 'success') !== false || 
                    strpos($ssl_result['response'], '成功') !== false ||
                    strpos($ssl_result['response'], 'Success') !== false) {
                    $deployer->log("SSL 憑證申請成功（文字回應）");
                    $ssl_action = 'applied';
                    
                } else {
                    $deployer->log("SSL 憑證申請失敗或狀態未明");
                    $deployer->log("繼續進行後續步驟...");
                    $ssl_action = 'failed';
                }
            }
            
        } else {
            $deployer->log("SSL 申請 API 請求失敗: HTTP {$ssl_result['http_code']}");
            $deployer->log("繼續進行後續步驟...");
            $ssl_action = 'failed';
        }
        
        // 等待 SSL 憑證可能的處理時間
        if ($ssl_action === 'installed') {
            $deployer->log("等待 SSL 憑證生效 (10秒)...");
            sleep(10);
        } elseif ($ssl_action === 'applied' || $ssl_action === 'incomplete') {
            $deployer->log("等待 SSL 憑證處理 (30秒)...");
            sleep(30);
        }
        
    } else {
        $ssl_action = 'existing';
    }
    
    // 設置 WordPress 偽靜態規則
    $deployer->log("開始設置 WordPress 偽靜態規則...");
    $custom_admin_url = $config->get('wordpress_security.custom_admin_url', 'wp-admin');
    $hide_admin_login = $config->get('wordpress_security.hide_admin_login', false);
    $deployer->log("自訂管理後台網址: /{$custom_admin_url}");
    $deployer->log("隱藏管理後台功能: " . ($hide_admin_login ? '啟用' : '停用'));
    
    $rewrite_success = setupWordPressRewrite($domain, $site_name, $panel_url, $session_cookie, $http_token, $custom_admin_url, $hide_admin_login);
    $rewrite_action = $rewrite_success ? 'success' : 'failed';
    
    if ($rewrite_success) {
        $deployer->log("偽靜態規則配置檔案: /www/server/panel/vhost/rewrite/{$site_name}.conf");
    } else {
        $deployer->log("偽靜態規則設置失敗，但繼續進行");
    }
    
    // 儲存 SSL 和偽靜態資訊
    $ssl_info = [
        'domain' => $domain,
        'site_name' => $site_name,
        'user_email' => $user_email,
        'ssl_action' => $ssl_action,
        'certificate_type' => 'Let\'s Encrypt',
        'rewrite_action' => $rewrite_action,
        'custom_admin_url' => $custom_admin_url,
        'configured_at' => date('Y-m-d H:i:s')
    ];
    
    if (isset($ssl_result)) {
        $ssl_info['api_response'] = [
            'http_code' => $ssl_result['http_code'],
            'response' => $ssl_result['response'],
            'post_data' => $ssl_result['post_data']
        ];
    }
    
    file_put_contents($work_dir . '/ssl_config.json', json_encode($ssl_info, JSON_PRETTY_PRINT));
    
    $deployer->log("SSL 與偽靜態設置流程完成");
    $deployer->log("SSL 狀態: {$ssl_action}");
    $deployer->log("偽靜態狀態: {$rewrite_action}");
    
    if ($ssl_action === 'failed') {
        $deployer->log("注意: SSL 申請失敗，但已繼續進行後續步驟");
    }
    
    if ($rewrite_action === 'failed') {
        $deployer->log("注意: 偽靜態設置失敗，建議手動檢查 BT Panel 設定");
    }
    
    return ['status' => 'success', 'ssl_action' => $ssl_action, 'ssl_info' => $ssl_info];
    
} catch (Exception $e) {
    $deployer->log("SSL 設置過程發生錯誤: " . $e->getMessage());
    $deployer->log("繼續進行後續步驟...");
    
    // SSL 失敗不中斷整個部署流程
    $ssl_info = [
        'domain' => $domain,
        'site_name' => $site_name,
        'ssl_action' => 'error',
        'error_message' => $e->getMessage(),
        'configured_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/ssl_config.json', json_encode($ssl_info, JSON_PRETTY_PRINT));
    
    return ['status' => 'success', 'ssl_action' => 'error', 'ssl_info' => $ssl_info];
}
