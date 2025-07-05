<?php
/**
 * 步驟 02: 網域註冊 (Lihi API)
 * 透過 Lihi API 註冊網域，使用 Cloudflare 名稱伺服器
 */

// 載入依賴檢查
require_once DEPLOY_BASE_PATH . '/includes/step-dependencies.php';

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

// 檢查前置條件
$dep_check = checkStepDependencies('02', $work_dir, $deployer, $job_id);
if ($dep_check['status'] !== 'ok') {
    return $dep_check;
}

$processed_data_file = $work_dir . '/config/processed_data.json';
$cloudflare_info_file = $work_dir . '/cloudflare_info.json';

$processed_data = json_decode(file_get_contents($processed_data_file), true);
$cloudflare_info = json_decode(file_get_contents($cloudflare_info_file), true);

$domain = $processed_data['confirmed_data']['domain'];
$nameservers = $cloudflare_info['nameservers'];

$deployer->log("開始網域註冊: {$domain}");

// 取得 API 憑證
$api_key = $config->get('api_credentials.lihi_domain.api_key');
$endpoint = $config->get('api_credentials.lihi_domain.endpoint');

if (empty($api_key)) {
    $deployer->log("跳過網域註冊 - 未設定 Lihi API 憑證");
    return ['status' => 'skipped', 'reason' => 'no_api_credentials'];
}

// 除錯：顯示 API 設定（隱藏部分金鑰）
$masked_key = substr($api_key, 0, 10) . '...' . substr($api_key, -4);
$deployer->log("使用 Lihi API: {$endpoint}");
$deployer->log("API Key: {$masked_key}");

if (empty($nameservers) || count($nameservers) < 2) {
    throw new Exception("未找到 Cloudflare 名稱伺服器資訊");
}

/**
 * Lihi API 網域註冊請求
 */
function registerDomainLihi($domain, $nameservers, $api_key, $endpoint)
{
    $url = $endpoint;  // endpoint 已經包含完整 URL
    
    // 準備名稱伺服器字串（逗號分隔）
    $nameserver_string = implode(',', $nameservers);
    
    // 準備 POST 資料
    $post_data = [
        'name' => $domain,
        'nameserver' => $nameserver_string,
        'domainPromoter' => 'digital'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: x-api-key:{$api_key}",
        "Content-Type: application/x-www-form-urlencoded"
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
 * 檢查網域狀態 - 注意：Lihi API 不提供狀態檢查端點
 * 此函數已停用，直接進行網域註冊
 */
function checkDomainStatus($domain, $api_key, $endpoint)
{
    // Lihi API 不提供狀態檢查端點，直接返回未找到狀態
    return [
        'http_code' => 404,
        'response' => 'Status check not available'
    ];
}

try {
    $deployer->log("名稱伺服器: " . implode(', ', $nameservers));
    
    // 直接註冊網域 - Lihi API 不提供狀態檢查
    $deployer->log("開始註冊網域 {$domain}...");
    $deployer->log("註冊端點: " . $endpoint);
    $register_result = registerDomainLihi($domain, $nameservers, $api_key, $endpoint);
    
    $deployer->log("API 回應 HTTP 狀態: {$register_result['http_code']}");
    $deployer->log("API 回應內容: " . $register_result['response']);
    
    if ($register_result['http_code'] === 200) {
        $response_data = json_decode($register_result['response'], true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            // JSON 回應
            if (isset($response_data['success']) && $response_data['success']) {
                $deployer->log("網域註冊成功");
                $action = 'registered';
                
            } elseif (isset($response_data['error'])) {
                $deployer->log("網域註冊失敗: " . $response_data['error']);
                $action = 'failed';
                
            } else {
                $deployer->log("網域註冊回應格式未知");
                $action = 'unknown';
            }
            
        } else {
            // 非 JSON 回應，可能是成功訊息
            if (strpos($register_result['response'], 'success') !== false || 
                strpos($register_result['response'], '成功') !== false) {
                $deployer->log("網域註冊成功（文字回應）");
                $action = 'registered';
                $response_data = ['message' => $register_result['response']];
                
            } else {
                $deployer->log("網域註冊狀態未明: " . $register_result['response']);
                $action = 'unknown';
                $response_data = ['message' => $register_result['response']];
            }
        }
        
    } else {
        throw new Exception("網域註冊 API 請求失敗: HTTP {$register_result['http_code']}");
    }
    
    // 儲存網域註冊結果
    $domain_info = [
        'domain' => $domain,
        'status' => $action,
        'nameservers' => $nameservers,
        'registered_at' => date('Y-m-d H:i:s'),
        'api_response' => $response_data ?? null,
        'raw_response' => $register_result['response'],
        'post_data' => $register_result['post_data']
    ];
    
    file_put_contents($work_dir . '/domain_registration.json', json_encode($domain_info, JSON_PRETTY_PRINT));
    
    $deployer->log("網域註冊流程完成");
    
    // 等待 DNS 傳播
    if ($action === 'registered') {
        $deployer->log("等待 DNS 傳播 (30秒)...");
        sleep(30);
    }
    
    return [
        'status' => 'success', 
        'action' => $action,
        'domain' => $domain,
        'nameservers' => $nameservers,
        'domain_info' => $domain_info
    ];
    
} catch (Exception $e) {
    $deployer->log("網域註冊過程發生錯誤: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}