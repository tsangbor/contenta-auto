<?php
/**
 * 步驟 06: WordPress 基本設定 (Luke API 模式)
 * 
 * Luke 模式特點：
 * - 跳過 WordPress 安裝（由 Luke API 已完成）
 * - 執行基本設定和檔案權限配置
 * - 使用 Luke 主機的 SSH 認證（SSH key 優先，密碼備用）
 * - 使用 Luke 路徑格式：/var/www/{domain}/html/
 * - 檔案權限設定為 {domain}:www-data
 * - 儲存 limited_admin_email 資訊
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("=== 步驟 06: WordPress 基本設定 (Luke API 模式) ===");
$deployer->log("網域: {$domain}");

try {
    // === 階段 1: 設定 Luke SSH 連線資訊 ===
    $deployer->log("🔗 階段 1: 設定 Luke SSH 連線資訊");
    
    // 從 Luke 配置讀取 SSH 資訊
    $luke_config = $config->get('luke_deployment');
    $ssh_host = $luke_config['ssh_host'];
    $ssh_user = $luke_config['ssh_user'];
    $ssh_password = $luke_config['ssh_password'] ?? '';
    $ssh_port = $luke_config['ssh_port'] ?? 22;

    // 檢查 SSH key 路徑
    $ssh_key_path = '';
    if (!empty($luke_config['ssh_key_path'])) {
        $ssh_key_path = str_replace('~', $_SERVER['HOME'], $luke_config['ssh_key_path']);
    } else {
        // 嘗試使用預設的 SSH key
        $default_keys = ['~/.ssh/id_rsa'];
        foreach ($default_keys as $key) {
            $expanded_key = str_replace('~', $_SERVER['HOME'], $key);
            if (file_exists($expanded_key)) {
                $ssh_key_path = $expanded_key;
                $deployer->log("使用預設 SSH key: {$key}");
                break;
            }
        }
    }

    // 決定認證方式
    $use_ssh_key = !empty($ssh_key_path) && file_exists($ssh_key_path);
    $use_password = !empty($ssh_password);

    if (!$use_ssh_key && !$use_password) {
        throw new Exception("Luke SSH 設定不完整（需要密碼或 SSH key）");
    }

    if ($use_ssh_key) {
        $deployer->log("使用 SSH key 認證: {$ssh_key_path}");
    } else {
        $deployer->log("使用密碼認證");
    }

    // Luke 主機的路徑格式
    $document_root = "/var/www/{$domain}/html";
    $deployer->log("WordPress 目錄: {$document_root}");

    /**
     * 執行 SSH 指令（含重試機制）
     */
    function executeSSH($host, $user, $auth_method, $auth_value, $port, $command, $deployer, $max_retries = 3) {
        $last_error = '';
        
        for ($retry = 0; $retry < $max_retries; $retry++) {
            if ($retry > 0) {
                $deployer->log("SSH 重試第 {$retry} 次: {$command}");
                sleep(2 + $retry); // 漸進式延遲
            }
            
            // 根據認證方式構建 SSH 命令
            if ($auth_method === 'key') {
                $ssh_cmd = "ssh -i " . escapeshellarg($auth_value) .
                           " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" .
                           " -o ConnectTimeout=30 -o ServerAliveInterval=60 -o ServerAliveCountMax=3" .
                           " -p {$port} {$user}@{$host} " . escapeshellarg($command);
            } else {
                $ssh_cmd = "sshpass -p " . escapeshellarg($auth_value) . 
                           " ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" .
                           " -o ConnectTimeout=30 -o ServerAliveInterval=60 -o ServerAliveCountMax=3" .
                           " -p {$port} {$user}@{$host} " . escapeshellarg($command);
            }
            
            $output = [];
            $return_code = 0;
            exec($ssh_cmd . ' 2>&1', $output, $return_code);
            
            // 過濾掉 SSH 警告訊息
            $filtered_output = [];
            foreach ($output as $line) {
                // 跳過 SSH 主機金鑰警告訊息
                if (strpos($line, 'Warning: Permanently added') !== false) {
                    continue;
                }
                $filtered_output[] = $line;
            }
            $output_str = implode("\n", $filtered_output);
            
            // 檢查是否為連線錯誤
            if ($return_code === 0 || !preg_match('/connection|closed|timeout/i', $output_str)) {
                return [
                    'return_code' => $return_code,
                    'output' => $output_str,
                    'command' => $command
                ];
            }
            
            $last_error = $output_str;
        }
        
        // 所有重試都失敗
        return [
            'return_code' => 255,
            'output' => "SSH 連線失敗 (已重試 {$max_retries} 次): {$last_error}",
            'command' => $command
        ];
    }

    // 設定認證方法和值
    $auth_method = $use_ssh_key ? 'key' : 'password';
    $auth_value = $use_ssh_key ? $ssh_key_path : $ssh_password;

    // === 階段 2: 驗證 WordPress 安裝 ===
    $deployer->log("🔍 階段 2: 驗證 WordPress 安裝狀態");
    
    // 檢查 WordPress 是否已安裝
    $wp_check = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, 
        "test -f {$document_root}/wp-config.php && echo 'installed' || echo 'not_installed'", $deployer);
    
    if ($wp_check['return_code'] !== 0 || trim($wp_check['output']) !== 'installed') {
        throw new Exception("WordPress 未安裝或 wp-config.php 不存在，請先執行 Luke API 建立網站");
    }
    
    $deployer->log("✅ WordPress 已安裝，繼續執行基本設定");

    // 檢查 WP-CLI 是否可用
    $wp_cli_check = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port,
        "cd {$document_root} && wp --version --allow-root", $deployer);
        
    if ($wp_cli_check['return_code'] !== 0) {
        throw new Exception("WP-CLI 不可用: " . $wp_cli_check['output']);
    }
    
    $deployer->log("✅ WP-CLI 可用: " . trim($wp_cli_check['output']));

    // === 階段 3: WordPress 基本設定 ===
    $deployer->log("⚙️ 階段 3: WordPress 基本設定");

    // 網站資訊：使用 processed_data.json 的設定
    $site_title = $processed_data['confirmed_data']['website_name'] ?: $domain;
    $site_description = $processed_data['confirmed_data']['website_description'] ?: '由 Contenta AI 自動生成的網站';
    $user_email = $processed_data['confirmed_data']['user_email'];

    $deployer->log("網站設定資訊:");
    $deployer->log("  網站標題: {$site_title}");
    $deployer->log("  網站描述: {$site_description}");
    $deployer->log("  用戶信箱: {$user_email}");

    // 基本設定指令清單
    $basic_settings = [
        [
            'name' => '設定網站首頁網址',
            'command' => "cd {$document_root} && wp option update home https://www.{$domain} --allow-root"
        ],
        [
            'name' => '設定 WordPress 網址',
            'command' => "cd {$document_root} && wp option update siteurl https://www.{$domain} --allow-root"
        ],
        [
            'name' => '設定網站標題',
            'command' => "cd {$document_root} && wp option update blogname " . escapeshellarg($site_title) . " --allow-root"
        ],
        [
            'name' => '設定網站描述', 
            'command' => "cd {$document_root} && wp option update blogdescription " . escapeshellarg($site_description) . " --allow-root"
        ],
        [
            'name' => '設定時區',
            'command' => "cd {$document_root} && wp option update timezone_string 'Asia/Taipei' --allow-root"
        ],
        [
            'name' => '設定日期格式',
            'command' => "cd {$document_root} && wp option update date_format 'Y年n月j日' --allow-root"
        ],
        [
            'name' => '設定時間格式', 
            'command' => "cd {$document_root} && wp option update time_format 'H:i' --allow-root"
        ],
        [
            'name' => '啟用使用者註冊',
            'command' => "cd {$document_root} && wp option update users_can_register 0 --allow-root"
        ],
        [
            'name' => '設定預設角色',
            'command' => "cd {$document_root} && wp option update default_role 'subscriber' --allow-root"
        ],
        [
            'name' => '設定評論顯示',
            'command' => "cd {$document_root} && wp option update default_comment_status 'closed' --allow-root"
        ],
        [
            'name' => '設定引用顯示',
            'command' => "cd {$document_root} && wp option update default_ping_status 'closed' --allow-root"
        ],
        [
            'name' => '設定每頁文章數',
            'command' => "cd {$document_root} && wp option update posts_per_page 10 --allow-root"
        ]
    ];

    $settings_success = 0;
    $settings_failed = 0;

    foreach ($basic_settings as $setting) {
        $deployer->log("執行: " . $setting['name']);
        $result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, $setting['command'], $deployer);
        
        if ($result['return_code'] === 0) {
            $settings_success++;
            $deployer->log("  ✅ " . $setting['name'] . " 完成");
        } else {
            $settings_failed++;
            $deployer->log("  ❌ " . $setting['name'] . " 失敗: " . $result['output']);
        }
    }

    $deployer->log("基本設定完成 - 成功: {$settings_success}, 失敗: {$settings_failed}");

    // === 階段 4: 設定檔案權限 (Luke 模式: {domain}:www-data) ===
    $deployer->log("🔒 階段 4: 設定檔案權限");

    // Luke 主機使用 {domain}:www-data 權限格式
    $permission_commands = [
        "chown -R {$domain}:www-data {$document_root}",
        "find {$document_root} -type d -exec chmod 755 {} \\;", 
        "find {$document_root} -type f -exec chmod 644 {} \\;",
        "chmod 600 {$document_root}/wp-config.php"
    ];

    $permission_success = 0;
    $permission_failed = 0;

    foreach ($permission_commands as $cmd) {
        $deployer->log("執行權限指令: " . $cmd);
        $result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, $cmd, $deployer);
        
        if ($result['return_code'] === 0) {
            $permission_success++;
            $deployer->log("  ✅ 權限設定成功");
        } else {
            $permission_failed++;
            $deployer->log("  ⚠ 權限設定警告: " . $result['output']);
        }
    }

    $deployer->log("檔案權限設定完成 - 成功: {$permission_success}, 警告: {$permission_failed}");

    // === 階段 5: 驗證 WordPress 狀態 ===
    $deployer->log("✅ 階段 5: 驗證 WordPress 狀態");

    // 取得 WordPress 版本
    $wp_version_result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port,
        "cd {$document_root} && wp core version --allow-root", $deployer);
        
    $wp_version = ($wp_version_result['return_code'] === 0) ? trim($wp_version_result['output']) : 'unknown';

    // 檢查重要檔案是否存在
    $check_files = [
        "{$document_root}/wp-config.php",
        "{$document_root}/wp-load.php", 
        "{$document_root}/index.php"
    ];

    $missing_files = [];
    foreach ($check_files as $file) {
        $check_result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, 
            "test -f {$file} && echo 'exists' || echo 'missing'", $deployer);
        $output = trim($check_result['output']);
        
        if (strpos($output, 'exists') === false) {
            $missing_files[] = basename($file);
        }
    }

    if (!empty($missing_files)) {
        throw new Exception("重要檔案缺失: " . implode(', ', $missing_files));
    }

    $deployer->log("✅ 重要檔案驗證通過");

    // === 階段 6: 儲存安裝資訊 ===
    $deployer->log("💾 階段 6: 儲存 WordPress 安裝資訊");

    /**
     * 生成隨機密碼
     */
    function generateRandomPassword($length = 12) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $password;
    }

    // 取得管理後台 URL 設定
    $custom_admin_url = $config->get('wordpress_security.custom_admin_url', 'wp-admin');
    
    // 管理員資訊：使用 deploy-config.json 的固定設定（與 BT Panel 模式一致）
    $admin_user = $config->get('deployment.admin_user');        // contentatw@gmail.com
    $admin_password = $config->get('deployment.admin_password'); // 82b15dc192ae
    $admin_email = $config->get('deployment.admin_user');       // contentatw@gmail.com (管理員信箱)
    
    // 儲存 WordPress 安裝資訊（適用於 Luke 模式）
    $wordpress_info = [
        'domain' => $domain,
        'site_name' => "www.{$domain}",
        'document_root' => $document_root,
        'wp_version' => $wp_version,
        'site_title' => $site_title,
        'site_description' => $site_description,
        'site_url' => "https://www.{$domain}",
        'admin_url' => "https://www.{$domain}/{$custom_admin_url}",
        'custom_admin_url' => $custom_admin_url,
        
        // 管理員資訊：使用固定的 contentatw@gmail.com 帳號
        'admin_user' => $admin_user,
        'admin_password' => $admin_password,
        'admin_email' => $admin_email,
        
        // Limited Admin 角色資訊（後續步驟使用）
        'limited_admin_email' => $user_email,
        'limited_admin_password' => $config->get('deployment.limited_admin_password') ?: generateRandomPassword(),
        
        'action' => 'installed',
        'installed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/wordpress_install.json', json_encode($wordpress_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("WordPress 安裝資訊已儲存: wordpress_install.json");

    // === 完成報告 ===
    $deployer->log("=== 步驟 06: WordPress 基本設定完成 (Luke API 模式) ===");
    $deployer->log("🌐 網站網址: https://www.{$domain}");
    $deployer->log("⚙️  管理後台: https://www.{$domain}/{$custom_admin_url}");
    $deployer->log("📁 WordPress 目錄: {$document_root}");
    $deployer->log("🔒 檔案權限: {$domain}:www-data");
    $total_settings = $settings_success + $settings_failed;
    $deployer->log("📊 基本設定: {$settings_success}/{$total_settings} 成功");
    $deployer->log("👤 Limited Admin: {$user_email}");

    return [
        'status' => 'success',
        'wordpress_info' => $wordpress_info,
        'settings_applied' => $settings_success,
        'settings_failed' => $settings_failed,
        'permission_success' => $permission_success,
        'permission_failed' => $permission_failed
    ];

} catch (Exception $e) {
    $deployer->log("步驟 06 失敗: " . $e->getMessage());
    throw $e;
}