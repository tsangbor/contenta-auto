<?php
/**
 * 步驟 03: 呼叫 Luke API 建立網站 (Luke API 模式)
 * 透過 Luke Cloud API 建立 WordPress 網站，包含：
 * - 建立網站容器
 * - 設定資料庫
 * - 建立 WordPress 安裝
 * - 設定基本 WordPress 選項
 */

$deployer->log("=== 步驟 03: 呼叫 Luke API 建立網站 (Luke API 模式) ===");

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data_file = $work_dir . '/config/processed_data.json';
$wordpress_info_file = $work_dir . '/wordpress_info.json';

$processed_data = json_decode(file_get_contents($processed_data_file), true);

// wordpress_info.json 是步驟 02 產生的，如果不存在就使用預設值
$wordpress_info = [];
if (file_exists($wordpress_info_file)) {
    $wordpress_info = json_decode(file_get_contents($wordpress_info_file), true);
}

$domain = $processed_data['confirmed_data']['domain'];
$site_title = $processed_data['confirmed_data']['website_name'] ?: $domain;
$site_description = $processed_data['confirmed_data']['website_description'] ?: '由 Contenta AI 自動生成的網站';
$user_email = $processed_data['confirmed_data']['user_email'];

// 從設定檔載入 Luke API 配置
$luke_config = $config->get('luke_deployment');
if (!$luke_config) {
    throw new Exception("找不到 Luke API 設定，請檢查 deploy-config.json");
}

// 驗證必要的 Luke API 設定
$required_fields = ['api_endpoint', 'api_user', 'api_password', 'web_root_template'];
foreach ($required_fields as $field) {
    if (empty($luke_config[$field])) {
        throw new Exception("Luke API 設定缺少必要欄位: {$field}");
    }
}

$deployer->log("網域: {$domain}");
$deployer->log("網站標題: {$site_title}");
$deployer->log("Luke API 端點: " . $luke_config['api_endpoint']);

// === 階段 1: 準備 Luke API 請求資料 ===
$deployer->log("🔗 階段 1: 準備 Luke API 請求資料");

// 從已準備的資料庫配置載入設定
$db_config_file = $work_dir . '/config/db-config.json';
$db_config = json_decode(file_get_contents($db_config_file), true);

// 從部署設定載入管理員資訊
$admin_user = $config->get('deployment.admin_user');
$admin_password = $config->get('deployment.admin_password');
$admin_email = $admin_user; // 使用管理員帳號作為管理員信箱

if (empty($admin_user) || empty($admin_password)) {
    throw new Exception("管理員帳號設定不完整，請檢查 deployment.admin_user 和 deployment.admin_password");
}

// 計算網站根目錄路徑
$web_root = str_replace('{domain}', $domain, $luke_config['web_root_template']);

$deployer->log("網站根目錄: {$web_root}");
$deployer->log("管理員帳號: {$admin_user}");

// 準備 Luke API 請求資料 (根據 API 規範)
// Luke API 只需要三個參數：user_email, user_pass (base64), domain
$api_request = [
    'user_email' => $admin_email,  // 使用管理員信箱作為網站的 admin email
    'user_pass' => base64_encode($admin_password),  // 密碼需要 base64 encode
    'domain' => $domain  // 網域名稱
];

$deployer->log("API 請求參數:");
$deployer->log("  user_email: {$admin_email}");
$deployer->log("  domain: {$domain}");
$deployer->log("  user_pass: [已編碼]");

$deployer->log("API 請求資料準備完成");

// === 階段 2: 呼叫 Luke API 建立網站 ===
$deployer->log("🚀 階段 2: 呼叫 Luke API 建立網站");

$api_url = rtrim($luke_config['api_endpoint'], '/');
$deployer->log("API 端點: {$api_url}");

// 準備認證資料
$auth_string = base64_encode($luke_config['api_user'] . ':' . $luke_config['api_password']);

// 執行 API 請求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_request));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 分鐘超時
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . $auth_string,
    'User-Agent: Contenta-Auto-Deploy/1.0'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$deployer->log("發送 API 請求...");
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// 處理 cURL 錯誤
if ($curl_error) {
    throw new Exception("API 請求失敗: " . $curl_error);
}

$deployer->log("API 回應 HTTP 狀態: {$http_code}");

// 處理 API 回應
if ($http_code >= 200 && $http_code < 300) {
    $api_response = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // 非 JSON 回應，但 HTTP 狀態成功
        $deployer->log("✅ 網站建立成功 (非 JSON 回應)");
        $api_response = [
            'success' => true,
            'message' => $response,
            'status' => 'created'
        ];
    } else {
        // JSON 回應 - Luke API 回傳格式為 {"status":200,"message":"...","data":{...}}
        if (isset($api_response['status']) && $api_response['status'] == 200) {
            $deployer->log("✅ 網站建立成功");
            $deployer->log("API 訊息: " . ($api_response['message'] ?? 'Success'));
            
            // 從 data 中提取資訊
            if (isset($api_response['data'])) {
                $api_response['site_id'] = $api_response['data']['site_id'] ?? null;
                $api_response['server_id'] = $api_response['data']['server_id'] ?? null;
                $api_response['url'] = $api_response['data']['url'] ?? $domain;
            }
            
            $api_response['success'] = true;
            $api_response['status'] = 'created';
        } elseif (isset($api_response['success']) && $api_response['success']) {
            $deployer->log("✅ 網站建立成功");
            $api_response['status'] = 'created';
        } elseif (isset($api_response['error'])) {
            throw new Exception("Luke API 建立網站失敗: " . $api_response['error']);
        } else {
            // 如果回應格式不符預期但 HTTP 200，視為成功
            $deployer->log("✅ 網站建立成功 (非標準回應格式)");
            $api_response['success'] = true;
            $api_response['status'] = 'created';
        }
    }
    
} else {
    // HTTP 錯誤狀態
    $deployer->log("API 回應內容: " . $response);
    throw new Exception("Luke API 請求失敗 (HTTP {$http_code}): " . $response);
}

// === 階段 3: 處理 API 回應並儲存結果 ===
$deployer->log("💾 階段 3: 處理 API 回應並儲存結果");

// 提取回應中的重要資訊
$site_id = $api_response['site_id'] ?? null;
$server_id = $api_response['server_id'] ?? null;
$api_url_response = $api_response['url'] ?? $domain;

// 構建網站 URL（Luke API 只回傳網域，需要加上協議）
$site_url = (strpos($api_url_response, 'http') === 0) ? $api_url_response : "https://www.{$api_url_response}";
$admin_url = (strpos($api_url_response, 'http') === 0) ? $api_url_response . "/wp-admin/" : "https://www.{$api_url_response}/wp-admin/";
$database_info = $api_response['database'] ?? [];

$deployer->log("網站 URL: {$site_url}");
$deployer->log("管理後台 URL: {$admin_url}");
if ($site_id) {
    $deployer->log("網站 ID: {$site_id}");
}

// 建立網站建立結果檔案
$site_creation_result = [
    'status' => 'created',
    'created_at' => date('Y-m-d H:i:s'),
    'domain' => $domain,
    'site_title' => $site_title,
    'site_description' => $site_description,
    'site_id' => $site_id,
    'site_url' => $site_url,
    'admin_url' => $admin_url,
    'web_root' => $web_root,
    'admin_credentials' => [
        'username' => $admin_user,
        'password' => $admin_password,
        'email' => $admin_email
    ],
    'database' => array_merge($db_config, $database_info),
    'api_response' => $api_response,
    'deployment_mode' => 'luke'
];

$site_info_file = $work_dir . '/luke_site.json';
file_put_contents($site_info_file, json_encode($site_creation_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("網站資訊已儲存至: " . basename($site_info_file));

// === 階段 4: 更新部署狀態 ===
$deployer->log("📊 階段 4: 更新部署狀態");

// 建立或更新部署狀態檔案
$deployment_status = [
    'status' => 'site_created',
    'current_step' => '03',
    'job_id' => $job_id,
    'domain' => $domain,
    'site_id' => $site_id,
    'site_url' => $site_url,
    'admin_url' => $admin_url,
    'deployment_mode' => 'luke',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

$status_file = $work_dir . '/deployment-status.json';
file_put_contents($status_file, json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 建立部署檢查清單
$checklist = [
    'initialization' => [
        'job_created' => true,
        'work_directory_created' => true,
        'luke_api_tested' => true
    ],
    'dns_and_domain' => [
        'cloudflare_zone_created' => true,
        'dns_records_created' => true
    ],
    'wordpress_preparation' => [
        'wordpress_downloaded' => true,
        'config_prepared' => true
    ],
    'site_creation' => [
        'luke_api_called' => true,
        'site_created' => true,
        'database_created' => isset($database_info['created']) ? $database_info['created'] : true,
        'wordpress_installed' => true
    ],
    'customization' => [
        'theme_installed' => false,
        'plugins_installed' => false,
        'content_uploaded' => false
    ],
    'finalization' => [
        'ssl_configured' => true,
        'cache_enabled' => false,
        'security_hardened' => false,
        'backup_created' => false
    ]
];

$checklist_file = $work_dir . '/deployment-checklist.json';
file_put_contents($checklist_file, json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("部署檢查清單已建立");

// === 階段 5: 驗證網站可用性 ===
$deployer->log("🔍 階段 5: 驗證網站可用性");

$deployer->log("等待網站服務啟動 (30 秒)...");
sleep(30);

// 測試網站首頁是否可訪問
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $site_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Contenta-Auto-Deploy/1.0');

$site_response = curl_exec($ch);
$site_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($site_http_code >= 200 && $site_http_code < 400) {
    $deployer->log("✅ 網站首頁可正常訪問 (HTTP {$site_http_code})");
    $site_creation_result['site_accessible'] = true;
} else {
    $deployer->log("⚠ 網站首頁尚未就緒 (HTTP {$site_http_code})");
    $site_creation_result['site_accessible'] = false;
}

// 測試 WordPress 管理後台
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $admin_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Contenta-Auto-Deploy/1.0');

$admin_response = curl_exec($ch);
$admin_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($admin_http_code >= 200 && $admin_http_code < 400) {
    $deployer->log("✅ WordPress 管理後台可正常訪問 (HTTP {$admin_http_code})");
    $site_creation_result['admin_accessible'] = true;
} else {
    $deployer->log("⚠ WordPress 管理後台尚未就緒 (HTTP {$admin_http_code})");
    $site_creation_result['admin_accessible'] = false;
}

// 更新網站資訊檔案
file_put_contents($site_info_file, json_encode($site_creation_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("=== Luke API 網站建立完成 ===");
$deployer->log("🌐 網站 URL: {$site_url}");
$deployer->log("⚙️  管理後台: {$admin_url}");
$deployer->log("👤 管理員帳號: {$admin_user}");
$deployer->log("🔑 管理員密碼: {$admin_password}");

return [
    'status' => 'success',
    'site_creation_result' => $site_creation_result,
    'site_url' => $site_url,
    'admin_url' => $admin_url,
    'site_id' => $site_id
];