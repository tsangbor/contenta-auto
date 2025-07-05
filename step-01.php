<?php
/**
 * 步驟 01: Cloudflare 建立網站和 DNS 設定
 * 透過 Cloudflare API 建立新網站，設定 DNS 記錄
 */

// 載入依賴檢查
require_once DEPLOY_BASE_PATH . '/includes/step-dependencies.php';

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

// 檢查前置條件
$dep_check = checkStepDependencies('01', $work_dir, $deployer, $job_id);
if ($dep_check['status'] !== 'ok') {
    return $dep_check;
}

$processed_data_file = $work_dir . '/config/processed_data.json';
$processed_data = json_decode(file_get_contents($processed_data_file), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("開始 Cloudflare 建立網站: {$domain}");

// 取得 API 憑證
$cf_email = $config->get('api_credentials.cloudflare.email');
$cf_api_key = $config->get('api_credentials.cloudflare.api_token'); // 使用 api_token 而非 api_key
$cf_endpoint = $config->get('api_credentials.cloudflare.endpoint');
$server_ip = $config->get('deployment.server_ip');

if (empty($cf_email) || empty($cf_api_key)) {
    $deployer->log("跳過 Cloudflare 設定 - 未設定 API 憑證");
    $deployer->log("檢查: email = " . ($cf_email ?: '未設定') . ", api_token = " . ($cf_api_key ? '已設定' : '未設定'));
    return ['status' => 'skipped', 'reason' => 'no_api_credentials'];
}

if (empty($server_ip)) {
    throw new Exception("未設定伺服器 IP 位址 (deployment.server_ip)");
}

/**
 * Cloudflare API 請求
 */
function cloudflareRequest($endpoint, $email, $api_key, $method = 'GET', $data = null)
{
    $url = 'https://api.cloudflare.com/client/v4/' . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Auth-Email: {$email}",
        "X-Auth-Key: {$api_key}",
        "Content-Type: application/json"
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $http_code, 'response' => json_decode($response, true)];
}

/**
 * 建立 Cloudflare Zone
 */
function createCloudflareZone($domain, $email, $api_key)
{
    $data = [
        'name' => $domain,
        'jump_start' => true
    ];
    
    return cloudflareRequest('zones', $email, $api_key, 'POST', $data);
}

/**
 * 取得 Zone 資訊
 */
function getZoneInfo($domain, $email, $api_key)
{
    return cloudflareRequest("zones?name={$domain}", $email, $api_key, 'GET', null);
}

/**
 * 新增 DNS 記錄
 */
function addDNSRecord($zone_id, $type, $name, $content, $email, $api_key)
{
    $data = [
        'type' => $type,
        'name' => $name,
        'content' => $content,
        'ttl' => 1 // Auto
    ];
    
    return cloudflareRequest("zones/{$zone_id}/dns_records", $email, $api_key, 'POST', $data);
}

try {
    // 檢查 Zone 是否已存在
    $deployer->log("檢查 Cloudflare Zone 是否存在...");
    $zone_result = getZoneInfo($domain, $cf_email, $cf_api_key);
    
    if ($zone_result['http_code'] === 200 && 
        isset($zone_result['response']['success']) && 
        $zone_result['response']['success'] && 
        !empty($zone_result['response']['result'])) {
        
        $zone_data = $zone_result['response']['result'][0];
        $zone_id = $zone_data['id'];
        $nameservers = $zone_data['name_servers'];
        
        $deployer->log("Zone 已存在: {$zone_id}");
        
    } else {
        // 建立新的 Cloudflare Zone
        $deployer->log("建立新的 Cloudflare Zone...");
        $create_result = createCloudflareZone($domain, $cf_email, $cf_api_key);
        
        if ($create_result['http_code'] === 200 && 
            isset($create_result['response']['success']) && 
            $create_result['response']['success']) {
            
            $zone_data = $create_result['response']['result'];
            $zone_id = $zone_data['id'];
            $nameservers = $zone_data['name_servers'];
            
            $deployer->log("Zone 建立成功: {$zone_id}");
            $deployer->log("名稱伺服器: " . implode(', ', $nameservers));
            
        } else {
            $error_msg = isset($create_result['response']['errors']) 
                ? json_encode($create_result['response']['errors']) 
                : 'Unknown error';
            throw new Exception("Zone 建立失敗: {$error_msg}");
        }
    }
    
    // 新增 DNS 記錄
    $dns_records = [
        ['type' => 'A', 'name' => $domain, 'content' => $server_ip],
        ['type' => 'CNAME', 'name' => "www.{$domain}", 'content' => $domain]
    ];
    
    $deployer->log("新增 DNS 記錄...");
    $successful_records = [];
    
    foreach ($dns_records as $record) {
        $deployer->log("新增 {$record['type']} 記錄: {$record['name']} -> {$record['content']}");
        
        $dns_result = addDNSRecord($zone_id, $record['type'], $record['name'], $record['content'], $cf_email, $cf_api_key);
        
        if ($dns_result['http_code'] === 200 && 
            isset($dns_result['response']['success']) && 
            $dns_result['response']['success']) {
            
            $deployer->log("DNS 記錄新增成功: {$record['name']}");
            $successful_records[] = $record;
            
        } else {
            $error_msg = isset($dns_result['response']['errors']) 
                ? json_encode($dns_result['response']['errors']) 
                : 'Unknown error';
            $deployer->log("DNS 記錄新增失敗: {$record['name']} - {$error_msg}");
            
            // DNS 記錄失敗不中斷流程，可能是記錄已存在
        }
    }
    
    // 儲存 Cloudflare 資訊（供下一步使用）
    $cloudflare_info = [
        'domain' => $domain,
        'zone_id' => $zone_id,
        'nameservers' => $nameservers,
        'server_ip' => $server_ip,
        'dns_records' => $successful_records,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/cloudflare_info.json', json_encode($cloudflare_info, JSON_PRETTY_PRINT));
    
    $deployer->log("Cloudflare 設定完成");
    $deployer->log("Zone ID: {$zone_id}");
    $deployer->log("名稱伺服器: " . implode(', ', $nameservers));
    
    return [
        'status' => 'success', 
        'zone_id' => $zone_id, 
        'nameservers' => $nameservers,
        'cloudflare_info' => $cloudflare_info
    ];
    
} catch (Exception $e) {
    $deployer->log("Cloudflare 設定失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}