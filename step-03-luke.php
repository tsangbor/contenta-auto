<?php
/**
 * 步驟 03: 準備 WordPress 設定 (Luke API 模式)
 * - 準備 API 請求所需的參數
 * - 生成資料庫設定
 * - 準備 WordPress 設定檔
 */

$deployer->log("=== 步驟 03: 準備 WordPress 設定 (Luke API 模式) ===");

// 設定工作目錄
$work_dir = DEPLOY_DATA_PATH . '/' . $job_id;
$config_dir = $work_dir . '/config';

// 1. 準備 WordPress 基本設定
$deployer->log("準備 WordPress 基本設定...");

$confirmed_data = $job_data['confirmed_data'] ?? [];
$domain = $confirmed_data['domain'] ?? '';
$website_name = $confirmed_data['website_name'] ?? 'My Website';
$admin_email = $confirmed_data['admin_email'] ?? 'admin@' . $domain;

if (empty($domain)) {
    throw new Exception("網域名稱未設定");
}

// 2. 生成資料庫設定
$deployer->log("生成資料庫設定...");

// Luke API 模式下，資料庫由 API 自動分配
// 這裡只準備佔位符，實際值會在 API 回應後更新
$db_config = [
    'db_name' => 'wp_' . str_replace(['.', '-'], '_', $domain),
    'db_user' => 'user_' . substr(md5($domain), 0, 8),
    'db_password' => bin2hex(random_bytes(16)),
    'db_host' => 'localhost',
    'table_prefix' => 'wp_',
    'charset' => 'utf8mb4',
    'collate' => 'utf8mb4_unicode_ci'
];

// 儲存資料庫設定（供後續步驟使用）
file_put_contents(
    $config_dir . '/db-config.json',
    json_encode($db_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("✓ 資料庫設定已準備");

// 3. 準備 WordPress 安裝參數
$deployer->log("準備 WordPress 安裝參數...");

// 生成安全的管理員密碼
$admin_password = bin2hex(random_bytes(12));
$admin_username = 'admin_' . substr(md5($domain), 0, 6);

$wp_install_params = [
    'domain' => $domain,
    'site_title' => $website_name,
    'admin_username' => $admin_username,
    'admin_password' => $admin_password,
    'admin_email' => $admin_email,
    'language' => 'zh_TW',
    'timezone' => 'Asia/Taipei',
    'date_format' => 'Y年n月j日',
    'time_format' => 'H:i',
    'start_of_week' => '1',
    'permalink_structure' => '/%postname%/',
    'use_ssl' => true,
    'force_ssl_admin' => true
];

// 儲存安裝參數
file_put_contents(
    $config_dir . '/wp-install-params.json',
    json_encode($wp_install_params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("✓ WordPress 安裝參數已準備");
$deployer->log("  - 網站標題: {$website_name}");
$deployer->log("  - 管理員帳號: {$admin_username}");
$deployer->log("  - 管理員信箱: {$admin_email}");

// 4. 準備 Luke API 請求參數
$deployer->log("準備 Luke API 請求參數...");

$luke_api_config = $config->get('luke_api');
$api_request_params = [
    'action' => 'create_wordpress',
    'domain' => $domain,
    'site_config' => [
        'title' => $website_name,
        'admin_user' => $admin_username,
        'admin_pass' => $admin_password,
        'admin_email' => $admin_email,
        'language' => 'zh_TW',
        'multisite' => false,
        'ssl_enabled' => true,
        'force_ssl' => true
    ],
    'database_config' => [
        'create_new' => true,
        'db_prefix' => 'wp_',
        'charset' => 'utf8mb4'
    ],
    'php_config' => [
        'version' => '8.1',
        'max_execution_time' => 300,
        'memory_limit' => '256M',
        'upload_max_filesize' => '64M',
        'post_max_size' => '64M'
    ],
    'security_config' => [
        'disable_file_editing' => true,
        'disable_xmlrpc' => true,
        'block_author_scanning' => true,
        'hide_version' => true
    ],
    'optimization' => [
        'enable_cache' => true,
        'enable_gzip' => true,
        'enable_browser_cache' => true
    ]
];

// 儲存 API 請求參數
file_put_contents(
    $config_dir . '/luke-api-request.json',
    json_encode($api_request_params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("✓ Luke API 請求參數已準備");

// 5. 生成 WordPress 鹽值（Salts）
$deployer->log("生成 WordPress 安全鹽值...");

$salts = [];
$salt_keys = [
    'AUTH_KEY',
    'SECURE_AUTH_KEY',
    'LOGGED_IN_KEY',
    'NONCE_KEY',
    'AUTH_SALT',
    'SECURE_AUTH_SALT',
    'LOGGED_IN_SALT',
    'NONCE_SALT'
];

foreach ($salt_keys as $key) {
    $salts[$key] = base64_encode(random_bytes(64));
}

file_put_contents(
    $config_dir . '/wp-salts.json',
    json_encode($salts, JSON_PRETTY_PRINT)
);

$deployer->log("✓ WordPress 安全鹽值已生成");

// 6. 準備主題和外掛清單
$deployer->log("準備主題和外掛清單...");

// 從確認資料中獲取選擇的主題和外掛
$selected_theme = $confirmed_data['theme'] ?? 'hello-elementor';
$selected_plugins = $confirmed_data['plugins'] ?? [];

// 基本外掛清單
$base_plugins = [
    'elementor',
    'elementor-pro',
    'wpforms-lite',
    'all-in-one-seo-pack'
];

// 合併外掛清單
$all_plugins = array_unique(array_merge($base_plugins, $selected_plugins));

$theme_plugin_config = [
    'theme' => $selected_theme,
    'plugins' => $all_plugins,
    'auto_activate' => true,
    'auto_update' => false
];

file_put_contents(
    $config_dir . '/theme-plugin-config.json',
    json_encode($theme_plugin_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("✓ 主題和外掛清單已準備");
$deployer->log("  - 主題: {$selected_theme}");
$deployer->log("  - 外掛數量: " . count($all_plugins));

// 7. 建立部署檢查清單
$deployer->log("建立部署檢查清單...");

$deployment_checklist = [
    'pre_deployment' => [
        'api_connection' => false,
        'sftp_connection' => false,
        'domain_configured' => false,
        'ssl_ready' => false
    ],
    'wordpress_setup' => [
        'core_installed' => false,
        'database_created' => false,
        'config_uploaded' => false,
        'salts_configured' => false
    ],
    'customization' => [
        'theme_installed' => false,
        'plugins_installed' => false,
        'content_imported' => false,
        'menus_configured' => false
    ],
    'finalization' => [
        'cache_enabled' => false,
        'security_hardened' => false,
        'backup_created' => false,
        'monitoring_setup' => false
    ]
];

file_put_contents(
    $work_dir . '/deployment-checklist.json',
    json_encode($deployment_checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("✓ 部署檢查清單已建立");

// 8. 儲存認證資訊（加密）
$deployer->log("儲存認證資訊...");

$credentials = [
    'wordpress' => [
        'admin_url' => "https://{$domain}/wp-admin",
        'username' => $admin_username,
        'password' => $admin_password,
        'email' => $admin_email
    ],
    'database' => $db_config,
    'sftp' => [
        'host' => $luke_api_config['sftp_host'],
        'username' => $luke_api_config['sftp_user'],
        'password' => $luke_api_config['sftp_password'],
        'port' => $luke_api_config['sftp_port'] ?? 22,
        'root_path' => "/www/wwwroot/{$domain}"
    ]
];

// 儲存認證資訊（實際部署時應加密）
file_put_contents(
    $config_dir . '/credentials.json',
    json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

// 設定檔案權限
chmod($config_dir . '/credentials.json', 0600);

$deployer->log("✓ 認證資訊已安全儲存");

// 更新部署狀態
$deployment_status = json_decode(
    file_get_contents($work_dir . '/deployment-status.json'),
    true
);

$deployment_status['status'] = 'config_prepared';
$deployment_status['current_step'] = '03';
$deployment_status['config_ready'] = true;
$deployment_status['timestamp'] = date('Y-m-d H:i:s');

file_put_contents(
    $work_dir . '/deployment-status.json',
    json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("=== WordPress 設定準備完成 (Luke API 模式) ===");

return true;