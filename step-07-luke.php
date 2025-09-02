<?php
/**
 * 步驟 07: 資料庫最佳化和安全設定 (Luke API 模式)
 * - 最佳化資料庫
 * - 設定安全選項
 * - 啟用快取
 */

$deployer->log("=== 步驟 07: 資料庫最佳化和安全設定 (Luke API 模式) ===");

$work_dir = DEPLOY_DATA_PATH . '/' . $job_id;
$config_dir = $work_dir . '/config';

// 載入設定
$luke_api_config = $config->get('luke_api');

$site_id = null;
$status_file = $work_dir . '/deployment-status.json';
if (file_exists($status_file)) {
    $status = json_decode(file_get_contents($status_file), true);
    $site_id = $status['site_id'] ?? null;
}

if (!$site_id) {
    $deployer->log("⚠ 警告: 未找到站點 ID，跳過最佳化設定");
    return true;
}

// 1. 觸發資料庫最佳化
$deployer->log("觸發資料庫最佳化...");

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/database/optimize';

$request_data = [
    'site_id' => $site_id,
    'operations' => [
        'optimize_tables' => true,
        'clean_revisions' => true,
        'clean_transients' => true,
        'clean_spam' => true,
        'clean_trash' => true
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $luke_api_config['api_key'],
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 || $http_code === 202) {
    $result = json_decode($response, true);
    $deployer->log("✓ 資料庫最佳化已完成");
    
    if (isset($result['stats'])) {
        $stats = $result['stats'];
        if (isset($stats['tables_optimized'])) {
            $deployer->log("  - 最佳化表格: " . $stats['tables_optimized']);
        }
        if (isset($stats['revisions_deleted'])) {
            $deployer->log("  - 清理版本: " . $stats['revisions_deleted']);
        }
        if (isset($stats['space_saved'])) {
            $deployer->log("  - 節省空間: " . $stats['space_saved']);
        }
    }
} else {
    $deployer->log("⚠ 資料庫最佳化失敗 (HTTP {$http_code})");
}

// 2. 設定安全選項
$deployer->log("設定安全選項...");

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/security/configure';

$security_config = [
    'site_id' => $site_id,
    'settings' => [
        'disable_file_editing' => true,
        'disable_xmlrpc' => true,
        'block_author_scanning' => true,
        'hide_wp_version' => true,
        'limit_login_attempts' => true,
        'two_factor_auth' => false, // 可選
        'security_headers' => [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ],
        'firewall_rules' => [
            'block_bad_bots' => true,
            'block_suspicious_requests' => true,
            'rate_limiting' => true
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($security_config));
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
    $deployer->log("✓ 安全設定已完成");
} else {
    $deployer->log("⚠ 安全設定失敗 (HTTP {$http_code})");
}

// 3. 啟用快取
$deployer->log("啟用快取機制...");

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/cache/enable';

$cache_config = [
    'site_id' => $site_id,
    'cache_types' => [
        'page_cache' => true,
        'object_cache' => true,
        'browser_cache' => true,
        'database_cache' => false, // 可選
        'cdn_cache' => false // 可選
    ],
    'cache_settings' => [
        'page_cache_lifetime' => 3600, // 1 小時
        'browser_cache_lifetime' => 86400, // 1 天
        'exclude_logged_in' => true,
        'exclude_admin' => true,
        'minify_html' => true,
        'minify_css' => true,
        'minify_js' => true,
        'combine_css' => false,
        'combine_js' => false
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cache_config));
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
    $deployer->log("✓ 快取機制已啟用");
} else {
    $deployer->log("⚠ 快取設定失敗 (HTTP {$http_code})");
}

// 4. 設定備份排程
$deployer->log("設定自動備份...");

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/backup/schedule';

$backup_config = [
    'site_id' => $site_id,
    'schedule' => [
        'enabled' => true,
        'frequency' => 'daily', // daily, weekly, monthly
        'time' => '03:00', // 凌晨 3 點
        'retention_days' => 30,
        'backup_types' => [
            'database' => true,
            'files' => true,
            'uploads' => true,
            'themes' => true,
            'plugins' => true
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($backup_config));
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
    $deployer->log("✓ 自動備份已設定");
} else {
    $deployer->log("⚠ 備份設定失敗 (HTTP {$http_code})");
}

// 5. 建立初始備份
$deployer->log("建立初始備份...");

$api_url = rtrim($luke_api_config['api_url'], '/') . '/api/wordpress/backup/create';

$backup_request = [
    'site_id' => $site_id,
    'description' => 'Initial deployment backup',
    'types' => ['database', 'files']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($backup_request));
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
    $backup_id = $result['backup_id'] ?? null;
    $deployer->log("✓ 初始備份已建立" . ($backup_id ? " (ID: {$backup_id})" : ""));
} else {
    $deployer->log("⚠ 初始備份建立失敗 (HTTP {$http_code})");
}

// 6. 更新部署檢查清單
$checklist_file = $work_dir . '/deployment-checklist.json';
if (file_exists($checklist_file)) {
    $checklist = json_decode(file_get_contents($checklist_file), true);
    
    $checklist['finalization']['cache_enabled'] = true;
    $checklist['finalization']['security_hardened'] = true;
    $checklist['finalization']['backup_created'] = true;
    
    file_put_contents(
        $checklist_file,
        json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// 7. 更新部署狀態
$deployment_status = json_decode(
    file_get_contents($work_dir . '/deployment-status.json'),
    true
);

$deployment_status['status'] = 'optimization_completed';
$deployment_status['current_step'] = '07';
$deployment_status['database_optimized'] = true;
$deployment_status['security_configured'] = true;
$deployment_status['cache_enabled'] = true;
$deployment_status['backup_configured'] = true;
$deployment_status['timestamp'] = date('Y-m-d H:i:s');

file_put_contents(
    $work_dir . '/deployment-status.json',
    json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("=== 最佳化和安全設定完成 (Luke API 模式) ===");

return true;