<?php
/**
 * 步驟 02: 網域註冊 (Luke API 模式)
 * 透過 Lihi API 註冊網域，使用 Cloudflare 名稱伺服器
 * 
 * 此步驟與 BT 模式完全相同，只是執行環境不同
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
require_once DEPLOY_BASE_PATH . '/config-manager.php';
$config = ConfigManager::getInstance();

$lihi_api_key = $config->get('lihi.api_key');
if (!$lihi_api_key) {
    $deployer->log("錯誤: Lihi API 金鑰未設定");
    return ['status' => 'error', 'message' => 'Lihi API 金鑰未設定'];
}

// 註冊網域
require_once DEPLOY_BASE_PATH . '/includes/apis/lihi-api.php';
$lihi = new LihiAPI($lihi_api_key);

$deployer->log("準備註冊網域: {$domain}");
$deployer->log("使用名稱伺服器: " . implode(', ', $nameservers));

// 檢查網域是否可註冊
$check_result = $lihi->checkDomainAvailability($domain);
if (!$check_result['success']) {
    $deployer->log("錯誤: " . $check_result['message']);
    return ['status' => 'error', 'message' => $check_result['message']];
}

if (!$check_result['available']) {
    $deployer->log("錯誤: 網域 {$domain} 已被註冊或不可用");
    return ['status' => 'error', 'message' => "網域 {$domain} 已被註冊或不可用"];
}

// 執行網域註冊
$register_result = $lihi->registerDomain($domain, [
    'nameservers' => $nameservers,
    'auto_renew' => true,
    'privacy_protection' => true
]);

if (!$register_result['success']) {
    $deployer->log("錯誤: 網域註冊失敗 - " . $register_result['message']);
    return ['status' => 'error', 'message' => "網域註冊失敗: " . $register_result['message']];
}

$deployer->log("✅ 網域註冊成功: {$domain}");

// 儲存註冊資訊
$domain_info = [
    'domain' => $domain,
    'registered_at' => date('Y-m-d H:i:s'),
    'nameservers' => $nameservers,
    'registration_id' => $register_result['registration_id'] ?? null,
    'expires_at' => $register_result['expires_at'] ?? null
];

$domain_info_file = $work_dir . '/domain_registration.json';
file_put_contents($domain_info_file, json_encode($domain_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("註冊資訊已儲存到: {$domain_info_file}");

return [
    'status' => 'success',
    'message' => "網域 {$domain} 註冊成功",
    'data' => $domain_info
];
?>