<?php
/**
 * BT.CN Panel API 統一管理類別
 * 支援 Cookie 認證和 API Key 認證兩種方式
 */

class BTPanelAPI
{
    private $config;
    private $logger;

    public function __construct($config, $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    private function log($message)
    {
        if ($this->logger && method_exists($this->logger, 'log')) {
            $this->logger->log($message);
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
        }
    }

    /**
     * BT.CN 面板 API 請求 - 使用瀏覽器 Cookie 認證
     */
    private function btApiRequest($endpoint, $data = [])
    {
        $apiUrl = $this->config['BT_API_URL'] ?? '';
        $sessionCookie = $this->config['BT_SESSION_COOKIE'] ?? '';
        $httpToken = $this->config['BT_HTTP_TOKEN'] ?? '';
        
        if (empty($apiUrl)) {
            throw new Exception("BT.CN API URL 未配置");
        }

        // 檢查是否有 Cookie 認證配置
        if (empty($sessionCookie) || empty($httpToken)) {
            $this->log("⚠️ 未配置瀏覽器 Cookie 認證，嘗試使用 API Key 認證");
            return $this->btApiRequestWithKey($endpoint, $data);
        }

        $url = $apiUrl . $endpoint;
        
        $this->log("API 請求 URL: {$url}");
        $this->log("使用瀏覽器 Cookie 認證");
        $this->log("實際使用的 Cookie: " . substr($sessionCookie, 0, 80) . "...");
        $this->log("實際使用的 Token: " . $httpToken);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        
        // 如果有 POST 數據，發送
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        
        // 設置與瀏覽器相同的請求頭
        $host = parse_url($apiUrl, PHP_URL_HOST) . ':' . parse_url($apiUrl, PHP_URL_PORT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Host: ' . $host,
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:140.0) Gecko/20100101 Firefox/140.0',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-TW,zh;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Encoding: gzip, deflate',
            'Content-Type: application/x-www-form-urlencoded',
            'x-http-token: ' . $httpToken,
            'Origin: ' . rtrim($apiUrl, '/'),
            'Connection: keep-alive',
            'Referer: ' . rtrim($apiUrl, '/') . '/site/php',
            'Cookie: ' . $sessionCookie,
            'Pragma: no-cache',
            'Cache-Control: no-cache'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        $this->log("API 響應代碼: {$httpCode}");
        $this->log("連線時間: {$connectTime}s, 總時間: {$totalTime}s");

        if ($error) {
            $this->log("CURL 錯誤詳情: {$error}");
            
            // 檢查是否為連線重置錯誤
            if (strpos($error, 'Connection reset by peer') !== false) {
                $this->log("⚠️ 連線被重置，可能原因：");
                $this->log("1. Cookie/Token 已過期");
                $this->log("2. BT Panel 防護機制觸發");
                $this->log("3. 伺服器拒絕連線");
                $this->log("建議：更新 Cookie 和 Token 後重試");
            }
            
            throw new Exception("BT.CN API 請求失敗：" . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("BT.CN API 返回錯誤代碼：" . $httpCode . " 響應內容：" . substr($response, 0, 200));
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果不是JSON格式，可能是HTML錯誤頁面
            if (strpos($response, '<html') !== false) {
                throw new Exception("BT.CN API 返回HTML頁面而非JSON，Cookie可能已過期");
            }
            throw new Exception("BT.CN API 響應解析失敗：" . json_last_error_msg() . " 響應內容：" . substr($response, 0, 200));
        }

        return $result;
    }

    /**
     * BT.CN 面板 API 請求 - 使用 API Key 認證（備用方案）
     */
    private function btApiRequestWithKey($endpoint, $data = [])
    {
        $apiUrl = $this->config['BT_API_URL'] ?? '';
        $btKey = $this->config['BT_KEY'] ?? '';
        
        if (empty($apiUrl) || empty($btKey)) {
            throw new Exception("BT.CN API 配置不完整");
        }

        // BT.CN API 使用特定的認證方式
        $requestTime = time();
        $token = md5($requestTime . '' . md5($btKey));
        
        $requestData = array_merge($data, [
            'request_token' => $token,
            'request_time' => $requestTime
        ]);

        $url = $apiUrl . $endpoint;
        
        $this->log("API 請求 URL: {$url}");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // 不跟隨重定向
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0 (compatible; BT-Panel)',
            'Accept: application/json, text/plain, */*'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $this->log("API 響應代碼: {$httpCode}");

        if ($error) {
            throw new Exception("BT.CN API 請求失敗：" . $error);
        }

        // 如果返回302重定向，說明需要登入或API Key無效
        if ($httpCode === 302) {
            throw new Exception("BT.CN API 認證失敗，請檢查 BT_KEY 是否正確或面板是否需要登入");
        }

        if ($httpCode !== 200) {
            throw new Exception("BT.CN API 返回錯誤代碼：" . $httpCode . " 響應內容：" . substr($response, 0, 200));
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 如果不是JSON格式，可能是HTML錯誤頁面
            if (strpos($response, '<html') !== false) {
                throw new Exception("BT.CN API 返回HTML頁面而非JSON，可能是認證或權限問題");
            }
            throw new Exception("BT.CN API 響應解析失敗：" . json_last_error_msg() . " 響應內容：" . substr($response, 0, 200));
        }

        return $result;
    }

    /**
     * 建立網站
     */
    public function createWebsite($domain, $path, $phpVersion = '81', $siteName = null)
    {
        $this->log("🌐 透過 BT.CN API 建立網站");

        $webPath = $path;
        $siteName = $siteName ?? $domain;

        // 處理 domain 參數 - 如果傳入 www.domain.com，需要分離出純網域
        $mainDomain = $domain; // www.domain.com
        $pureDomain = preg_replace('/^www\./', '', $domain); // domain.com
        
        $data = [
            'webname' => json_encode([
                'domain' => $mainDomain,  // www.domain.com
                'domainlist' => [$pureDomain], // ["domain.com"]
                'count' => 1
            ]),
            'path' => $webPath,
            'type_id' => '0',
            'type' => 'PHP',
            'version' => $phpVersion,
            'port' => '80',
            'ps' => $siteName,
            'ftp' => 'false',
            'sql' => 'false',
            'need_index' => '0',
            'need_404' => '0',
            'codeing' => 'utf8mb4',
            'add_dns_record' => 'false'
        ];

        try {
            // 除錯：顯示實際發送的參數
            $this->log("🔍 實際發送的網站建立參數：");
            $this->log("   主域名: {$mainDomain}");
            $this->log("   純域名: {$pureDomain}");
            $this->log("   路徑: {$webPath}");
            $this->log("   PHP版本: {$phpVersion}");
            $this->log("   描述: {$siteName}");
            $this->log("   webname JSON: " . $data['webname']);
            
            $result = $this->btApiRequest('/site?action=AddSite', $data);
            
            // 檢查多種可能的成功響應格式
            if ((isset($result['siteStatus']) && $result['siteStatus'] === true) ||
                (isset($result['status']) && $result['status'] === true) || 
                (isset($result['msg']) && (strpos($result['msg'], '成功') !== false || strpos($result['msg'], 'success') !== false))) {
                
                $siteId = $result['siteId'] ?? null;
                $this->log("✅ 網站建立成功：{$domain}" . ($siteId ? " (ID: {$siteId})" : ""));
                return ['success' => true, 'site_id' => $siteId, 'result' => $result];
            } else {
                $errorMsg = $result['msg'] ?? $result['error'] ?? '未知錯誤';
                
                // 如果網站已存在，視為成功
                if (strpos($errorMsg, '已存在') !== false || strpos($errorMsg, 'exists') !== false || 
                    strpos($errorMsg, 'already') !== false) {
                    $this->log("⚠️ 網站已存在，跳過建立步驟：{$domain}");
                    return ['success' => true, 'existed' => true, 'result' => $result];
                }
                
                throw new Exception("建立網站失敗：{$errorMsg}");
            }
        } catch (Exception $e) {
            // 如果網站已存在，繼續執行
            if (strpos($e->getMessage(), '已存在') !== false || 
                strpos($e->getMessage(), 'exists') !== false ||
                strpos($e->getMessage(), 'already') !== false) {
                $this->log("⚠️ 網站已存在，跳過建立步驟");
                return ['success' => true, 'existed' => true];
            }
            throw $e;
        }
    }

    /**
     * 建立資料庫
     */
    public function createDatabase($dbName, $dbUser, $dbPass)
    {
        $this->log("🗄️ 透過 BT.CN API 建立資料庫");

        // 使用完全符合官方與實際瀏覽器請求的參數格式
        $data = [
            'name' => $dbName,
            'db_user' => $dbUser,
            'password' => $dbPass,
            'dataAccess' => '127.0.0.1',
            'address' => '127.0.0.1',
            'codeing' => 'utf8mb4',
            'dtype' => 'MySQL',
            'ps' => $dbName,
            'sid' => 0,
            'listen_ip' => '0.0.0.0/0',
            'host' => ''
        ];

        try {
            // 調試：顯示實際發送的參數
            $this->log("🔍 實際發送的資料庫參數：");
            $this->log("   name: {$data['name']}");
            $this->log("   db_user: {$data['db_user']}");
            $this->log("   password: {$data['password']}");
            
            $result = $this->btApiRequest('/database?action=AddDatabase', $data);
            
            // 檢查 API 回傳的 status 欄位
            if (isset($result['status']) && $result['status'] === false) {
                $errorMsg = $result['msg'] ?? $result['error'] ?? '未知錯誤';
                throw new Exception("建立資料庫失敗：{$errorMsg}");
            }
            
            // 檢查多種可能的成功響應格式
            if ((isset($result['status']) && $result['status'] === true) || 
                (isset($result['msg']) && (strpos($result['msg'], '成功') !== false || strpos($result['msg'], 'success') !== false))) {
                $this->log("✅ 新增資料庫成功");
                $this->log("   資料庫名稱：{$dbName}");
                $this->log("   資料庫用戶：{$dbUser}");
                $this->log("   編碼格式：utf8mb4");
                return ['success' => true, 'result' => $result];
            } else {
                $errorMsg = $result['msg'] ?? $result['error'] ?? '未知錯誤';
                throw new Exception("建立資料庫失敗：{$errorMsg}");
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * 設置 SSL 憑證
     */
    public function setupSSL($domain)
    {
        $this->log("🔒 透過 BT.CN API 設置 SSL 憑證");

        // 先檢查是否已有 SSL 憑證
        if ($this->checkSSLStatus($domain)) {
            $this->log("✅ SSL 憑證已存在，跳過申請步驟");
            return ['success' => true, 'existed' => true];
        }

        // 先獲取網站ID - 嘗試匹配多種可能的網站名稱格式
        $this->log("正在查找網站 ID...");
        $siteListResult = $this->btApiRequest('/data?action=getData', [
            'table' => 'sites',
            'limit' => 100,
            'p' => 1
        ]);

        $siteId = null;
        $possibleNames = [
            "www.{$domain}",  // www.yaoguo.tw
            $domain           // yaoguo.tw
        ];
        
        if (isset($siteListResult['data'])) {
            $this->log("發現 " . count($siteListResult['data']) . " 個網站");
            foreach ($siteListResult['data'] as $site) {
                $this->log("檢查網站: " . $site['name']);
                if (in_array($site['name'], $possibleNames)) {
                    $siteId = $site['id'];
                    $this->log("找到匹配網站: {$site['name']} (ID: {$siteId})");
                    break;
                }
            }
        }

        if (!$siteId) {
            throw new Exception("找不到網站 {$domain} 的 ID");
        }

        // 使用正確的 SSL 申請參數格式（基於實際 API 調用）
        $siteName = "www.{$domain}";
        $data = [
            'domains' => json_encode([$domain, $siteName]),  // ["yaoguo.tw", "www.yaoguo.tw"]
            'auth_type' => 'http',
            'auth_to' => json_encode([$domain, $siteName]),  // ["yaoguo.tw", "www.yaoguo.tw"]
            'auto_wildcard' => 0,
            'id' => $siteId
        ];

        try {
            $this->log("⏳ 正在申請 SSL 憑證，這可能需要 30-60 秒...");
            $this->log("使用網站 ID: {$siteId}");
            $this->log("SSL 申請參數: " . json_encode($data));
            $result = $this->btApiRequest('/acme?action=apply_cert_api', $data);
            
            if (isset($result['status']) && $result['status'] === true) {
                $this->log("✅ SSL 憑證申請成功：{$domain}");
                
                // 等待憑證生效
                sleep(10);
                
                // 驗證憑證是否成功安裝
                if ($this->checkSSLStatus($domain)) {
                    // 強制 HTTPS
                    $this->forceHTTPS($siteId, $domain);
                    return ['success' => true, 'result' => $result];
                } else {
                    $this->log("⚠️ SSL 憑證申請成功但安裝驗證失敗");
                    return ['success' => false, 'message' => 'SSL安裝驗證失敗', 'result' => $result];
                }
            } else {
                $errorMsg = $result['msg'] ?? '未知錯誤';
                $this->log("⚠️ SSL 憑證申請失敗：{$errorMsg}");
                return ['success' => false, 'message' => $errorMsg, 'result' => $result];
            }
        } catch (Exception $e) {
            $this->log("⚠️ SSL 設置失敗：" . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 檢查 SSL 憑證狀態
     */
    private function checkSSLStatus($domain)
    {
        $this->log("🔍 檢查 SSL 憑證狀態：{$domain}");

        // 嘗試使用 www.domain 格式
        $siteName = "www.{$domain}";
        $data = [
            'siteName' => $siteName
        ];
        
        $this->log("嘗試檢查網站: {$siteName}");

        try {
            $result = $this->btApiRequest('/site?action=GetSSL', $data);
            
            if (isset($result['status']) && $result['status'] === true) {
                if (isset($result['cert_data']['notAfter'])) {
                    $this->log("✅ SSL 憑證有效，到期時間：{$result['cert_data']['notAfter']}");
                    return true;
                } else {
                    $this->log("⚠️ SSL 憑證狀態未知");
                    return false;
                }
            } else {
                $this->log("⚠️ 尚未配置 SSL 憑證");
                return false;
            }
        } catch (Exception $e) {
            $this->log("⚠️ 檢查 SSL 狀態失敗：" . $e->getMessage());
            return false;
        }
    }

    /**
     * 強制 HTTPS
     */
    private function forceHTTPS($siteId, $domain)
    {
        $this->log("🔒 設置強制 HTTPS");

        $siteName = "www.{$domain}";
        $data = [
            'siteName' => $siteName,  // 使用 www.domain 格式
            'type' => 'redirect',
            'action' => 'set_https_redirect',
            'id' => $siteId
        ];

        try {
            $result = $this->btApiRequest('/site?action=SetHttpsRedirect', $data);
            
            if (isset($result['status']) && $result['status'] === true) {
                $this->log("✅ 強制 HTTPS 設置成功");
            } else {
                $this->log("⚠️ 強制 HTTPS 設置失敗");
            }
        } catch (Exception $e) {
            $this->log("⚠️ 強制 HTTPS 設置失敗：" . $e->getMessage());
        }
    }

    /**
     * 設置 WordPress 偽靜態規則
     */
    public function setupWordPressRewrite($domain, $customAdminUrl = 'wp-admin')
    {
        $this->log("⚙️ 設置 WordPress Nginx 偽靜態規則");

        // WordPress 偽靜態規則（支援自訂管理後台網址）
        $rewriteRule = "location /
{
     try_files \$uri \$uri/ /index.php?\$args;
}";

        // 只有當自訂管理後台網址不是預設的 wp-admin 時，才新增重定向規則
        if ($customAdminUrl !== 'wp-admin') {
            $rewriteRule .= "

# 自訂管理後台重定向
location ^~ /{$customAdminUrl} {
    rewrite ^/{$customAdminUrl}/?(.*)$ /wp-admin/$1 last;
}

# 隱藏原始 wp-admin 路徑
location ^~ /wp-admin {
    return 301 \$scheme://\$host/{$customAdminUrl}/;
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

        // 使用正確的檔案路徑和參數格式（參考官方代碼格式）
        $data = [
            'path' => "/www/server/panel/vhost/rewrite/{$domain}.conf",  // domain.tw.conf
            'data' => $rewriteRule,
            'encoding' => 'utf-8'
        ];

        try {
            $result = $this->btApiRequest('/files?action=SaveFileBody', $data);
            
            if (isset($result['status']) && $result['status'] === true) {
                $this->log("✅ WordPress 偽靜態規則設置成功");
                return ['success' => true, 'result' => $result];
            } else {
                $errorMsg = $result['msg'] ?? '未知錯誤';
                throw new Exception("設置偽靜態規則失敗：{$errorMsg}");
            }
        } catch (Exception $e) {
            $this->log("⚠️ 設置偽靜態規則失敗：" . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}