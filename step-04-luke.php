<?php
/**
 * 步驟 04: 透過 Luke API 建立 WordPress 站點
 * - 發送 API 請求建立站點
 * - 等待站點建立完成
 * - 獲取站點存取資訊
 */

$deployer->log("=== 步驟 04: 透過 Luke API 建立 WordPress 站點 ===");

// 設定工作目錄
$work_dir = DEPLOY_DATA_PATH . '/' . $job_id;
$config_dir = $work_dir . '/config';

// 載入設定
$luke_api_config = $config->get('luke_api');
$api_request = json_decode(
    file_get_contents($config_dir . '/luke-api-request.json'),
    true
);

// 1. 發送建立站點請求
$deployer->log("發送建立站點請求到 Luke API...");

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/create';
$api_key = $luke_api_config['api_key'];

// 準備請求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_request));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json',
    'Accept: application/json'
]);

// 發送請求
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    throw new Exception("Luke API 請求失敗: " . $curl_error);
}

if ($http_code !== 200 && $http_code !== 201 && $http_code !== 202) {
    $error_msg = "Luke API 回應錯誤 (HTTP {$http_code})";
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error'])) {
            $error_msg .= ": " . $error_data['error'];
        }
    }
    throw new Exception($error_msg);
}

// 解析回應
$response_data = json_decode($response, true);
if (!$response_data) {
    throw new Exception("無法解析 Luke API 回應");
}

$deployer->log("✓ 建立站點請求已發送");

// 儲存 API 回應
file_put_contents(
    $config_dir . '/luke-api-response.json',
    json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// 2. 檢查任務狀態
$task_id = $response_data['task_id'] ?? null;
$site_id = $response_data['site_id'] ?? null;

if (!$task_id && !$site_id) {
    // 如果沒有任務 ID，可能是同步建立，直接檢查站點狀態
    if (isset($response_data['status']) && $response_data['status'] === 'completed') {
        $deployer->log("✓ 站點已成功建立（同步模式）");
    } else {
        throw new Exception("API 回應缺少必要的任務或站點 ID");
    }
} else if ($task_id) {
    // 非同步模式，需要輪詢任務狀態
    $deployer->log("任務 ID: {$task_id}");
    $deployer->log("等待站點建立完成...");
    
    $max_wait_time = 300; // 最多等待 5 分鐘
    $check_interval = 10; // 每 10 秒檢查一次
    $elapsed_time = 0;
    
    while ($elapsed_time < $max_wait_time) {
        sleep($check_interval);
        $elapsed_time += $check_interval;
        
        // 查詢任務狀態
        $status_url = rtrim($luke_api_config['api_url'], '/') . '/api/task/status/' . $task_id;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $status_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Accept: application/json'
        ]);
        
        $status_response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($status_code === 200) {
            $status_data = json_decode($status_response, true);
            $task_status = $status_data['status'] ?? 'unknown';
            $progress = $status_data['progress'] ?? 0;
            
            $deployer->log("任務狀態: {$task_status} (進度: {$progress}%)");
            
            if ($task_status === 'completed' || $task_status === 'success') {
                $deployer->log("✓ 站點建立完成");
                $site_id = $status_data['site_id'] ?? $site_id;
                break;
            } elseif ($task_status === 'failed' || $task_status === 'error') {
                $error_msg = $status_data['error'] ?? '未知錯誤';
                throw new Exception("站點建立失敗: " . $error_msg);
            }
        }
    }
    
    if ($elapsed_time >= $max_wait_time) {
        throw new Exception("站點建立逾時（超過 {$max_wait_time} 秒）");
    }
}

// 3. 獲取站點詳細資訊
$deployer->log("獲取站點詳細資訊...");

if ($site_id) {
    $site_info_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/site/' . $site_id;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $site_info_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Accept: application/json'
    ]);
    
    $site_response = curl_exec($ch);
    $site_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($site_code === 200) {
        $site_data = json_decode($site_response, true);
        
        // 儲存站點資訊
        file_put_contents(
            $config_dir . '/site-info.json',
            json_encode($site_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        
        $deployer->log("✓ 站點資訊已獲取");
        
        // 更新資料庫設定（使用實際分配的值）
        if (isset($site_data['database'])) {
            $db_config = json_decode(
                file_get_contents($config_dir . '/db-config.json'),
                true
            );
            
            $db_config['db_name'] = $site_data['database']['name'] ?? $db_config['db_name'];
            $db_config['db_user'] = $site_data['database']['user'] ?? $db_config['db_user'];
            $db_config['db_password'] = $site_data['database']['password'] ?? $db_config['db_password'];
            $db_config['db_host'] = $site_data['database']['host'] ?? $db_config['db_host'];
            
            file_put_contents(
                $config_dir . '/db-config.json',
                json_encode($db_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            
            $deployer->log("✓ 資料庫設定已更新");
        }
        
        // 更新 SFTP 設定（如果有提供）
        if (isset($site_data['sftp'])) {
            $credentials = json_decode(
                file_get_contents($config_dir . '/credentials.json'),
                true
            );
            
            $credentials['sftp']['username'] = $site_data['sftp']['username'] ?? $credentials['sftp']['username'];
            $credentials['sftp']['password'] = $site_data['sftp']['password'] ?? $credentials['sftp']['password'];
            $credentials['sftp']['port'] = $site_data['sftp']['port'] ?? $credentials['sftp']['port'];
            $credentials['sftp']['root_path'] = $site_data['sftp']['path'] ?? $credentials['sftp']['root_path'];
            
            file_put_contents(
                $config_dir . '/credentials.json',
                json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            
            $deployer->log("✓ SFTP 設定已更新");
        }
    }
}

// 4. 驗證站點可訪問性
$deployer->log("驗證站點可訪問性...");

$domain = $api_request['domain'];
$site_url = "https://{$domain}";

// 等待 DNS 生效和站點就緒
$max_attempts = 30;
$attempt = 0;
$site_ready = false;

while ($attempt < $max_attempts && !$site_ready) {
    $attempt++;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $site_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 || $http_code === 301 || $http_code === 302) {
        $site_ready = true;
        $deployer->log("✓ 站點已可訪問 (HTTP {$http_code})");
        break;
    }
    
    $deployer->log("站點尚未就緒，等待中... (嘗試 {$attempt}/{$max_attempts})");
    sleep(10);
}

if (!$site_ready) {
    $deployer->log("⚠ 警告: 站點可能尚未完全就緒，但將繼續部署流程");
}

// 5. 更新部署檢查清單
$checklist_file = $work_dir . '/deployment-checklist.json';
if (file_exists($checklist_file)) {
    $checklist = json_decode(file_get_contents($checklist_file), true);
    
    $checklist['pre_deployment']['api_connection'] = true;
    $checklist['pre_deployment']['domain_configured'] = true;
    $checklist['pre_deployment']['ssl_ready'] = true;
    $checklist['wordpress_setup']['core_installed'] = true;
    $checklist['wordpress_setup']['database_created'] = true;
    
    file_put_contents(
        $checklist_file,
        json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// 6. 更新部署狀態
$deployment_status = json_decode(
    file_get_contents($work_dir . '/deployment-status.json'),
    true
);

$deployment_status['status'] = 'wordpress_created';
$deployment_status['current_step'] = '04';
$deployment_status['site_id'] = $site_id;
$deployment_status['site_url'] = $site_url;
$deployment_status['wordpress_ready'] = true;
$deployment_status['timestamp'] = date('Y-m-d H:i:s');

file_put_contents(
    $work_dir . '/deployment-status.json',
    json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// 輸出站點資訊
$deployer->log("=== WordPress 站點建立成功 ===");
$deployer->log("站點 URL: {$site_url}");
$deployer->log("站點 ID: " . ($site_id ?? 'N/A'));
$deployer->log("管理後台: {$site_url}/wp-admin");

// 儲存站點摘要
$site_summary = [
    'site_id' => $site_id,
    'domain' => $domain,
    'url' => $site_url,
    'admin_url' => "{$site_url}/wp-admin",
    'created_at' => date('Y-m-d H:i:s'),
    'status' => 'active'
];

file_put_contents(
    $work_dir . '/site-summary.json',
    json_encode($site_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("=== 步驟 04 完成 (Luke API 模式) ===");

return true;