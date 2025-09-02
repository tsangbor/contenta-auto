<?php
/**
 * 步驟 06: 安裝和設定主題與外掛 (Luke API 模式)
 * - 透過 API 安裝主題和外掛
 * - 啟用必要的外掛
 * - 進行基本設定
 */

$deployer->log("=== 步驟 06: 安裝和設定主題與外掛 (Luke API 模式) ===");

// 在 Luke API 模式下，主題和外掛的安裝可能已經在步驟 04 中完成
// 這個步驟主要用於額外的設定和驗證

$work_dir = DEPLOY_DATA_PATH . '/' . $job_id;
$config_dir = $work_dir . '/config';

// 載入設定
$luke_api_config = $config->get('luke_api');
$theme_plugin_config = json_decode(
    file_get_contents($config_dir . '/theme-plugin-config.json'),
    true
);

$site_id = null;
$status_file = $work_dir . '/deployment-status.json';
if (file_exists($status_file)) {
    $status = json_decode(file_get_contents($status_file), true);
    $site_id = $status['site_id'] ?? null;
}

if (!$site_id) {
    $deployer->log("⚠ 警告: 未找到站點 ID，跳過主題和外掛設定");
    return true;
}

// 1. 透過 API 安裝額外的外掛
$deployer->log("檢查並安裝額外的外掛...");

$plugins_to_install = $theme_plugin_config['plugins'] ?? [];
if (!empty($plugins_to_install)) {
    $api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/plugins/install';
    
    $request_data = [
        'site_id' => $site_id,
        'plugins' => $plugins_to_install,
        'activate' => true
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $luke_api_config['api_key'],
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 || $http_code === 202) {
        $result = json_decode($response, true);
        $installed = $result['installed'] ?? [];
        $failed = $result['failed'] ?? [];
        
        if (!empty($installed)) {
            $deployer->log("✓ 已安裝外掛: " . implode(', ', $installed));
        }
        if (!empty($failed)) {
            $deployer->log("⚠ 安裝失敗: " . implode(', ', $failed));
        }
    } else {
        $deployer->log("⚠ 外掛安裝請求失敗 (HTTP {$http_code})");
    }
}

// 2. 設定主題
$deployer->log("設定主題...");

$theme_name = $theme_plugin_config['theme'] ?? 'hello-elementor';
$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/theme/activate';

$request_data = [
    'site_id' => $site_id,
    'theme' => $theme_name
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $luke_api_config['api_key'],
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 || $http_code === 204) {
    $deployer->log("✓ 主題 '{$theme_name}' 已啟用");
} else {
    $deployer->log("⚠ 無法啟用主題 (HTTP {$http_code})");
}

// 3. 設定基本選項
$deployer->log("設定基本 WordPress 選項...");

$site_options = [
    'blogname' => $job_data['confirmed_data']['website_name'] ?? 'My Website',
    'blogdescription' => $job_data['confirmed_data']['website_description'] ?? '',
    'timezone_string' => 'Asia/Taipei',
    'date_format' => 'Y年n月j日',
    'time_format' => 'H:i',
    'start_of_week' => '1',
    'permalink_structure' => '/%postname%/',
    'default_comment_status' => 'closed',
    'default_ping_status' => 'closed'
];

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/options/update';

$request_data = [
    'site_id' => $site_id,
    'options' => $site_options
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $luke_api_config['api_key'],
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 || $http_code === 204) {
    $deployer->log("✓ WordPress 選項已更新");
} else {
    $deployer->log("⚠ 無法更新選項 (HTTP {$http_code})");
}

// 4. 更新部署檢查清單
$checklist_file = $work_dir . '/deployment-checklist.json';
if (file_exists($checklist_file)) {
    $checklist = json_decode(file_get_contents($checklist_file), true);
    
    $checklist['customization']['theme_installed'] = true;
    $checklist['customization']['plugins_installed'] = true;
    
    file_put_contents(
        $checklist_file,
        json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// 5. 更新部署狀態
$deployment_status = json_decode(
    file_get_contents($work_dir . '/deployment-status.json'),
    true
);

$deployment_status['status'] = 'theme_plugins_configured';
$deployment_status['current_step'] = '06';
$deployment_status['theme_configured'] = true;
$deployment_status['plugins_configured'] = true;
$deployment_status['timestamp'] = date('Y-m-d H:i:s');

file_put_contents(
    $work_dir . '/deployment-status.json',
    json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("=== 主題與外掛設定完成 (Luke API 模式) ===");

return true;