<?php
/**
 * 步驟 06: WordPress 安裝
 * 在 BT.cn 建立的網站目錄中安裝 WordPress
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$database_info = json_decode(file_get_contents($work_dir . '/database_config.json'), true);
$website_info = json_decode(file_get_contents($work_dir . '/bt_website.json'), true);

$domain = $processed_data['confirmed_data']['domain'];
$document_root = $website_info['document_root']; // /www/wwwroot/www.{domain}

$deployer->log("開始 WordPress 安裝: {$domain}");
$deployer->log("安裝目錄: {$document_root}");

// 取得部署設定
$server_host = $config->get('deployment.server_host');
$ssh_user = $config->get('deployment.ssh_user');
$ssh_port = $config->get('deployment.ssh_port') ?: 22;
$ssh_key_path = $config->get('deployment.ssh_key_path');

// WordPress 安裝參數（整合兩個配置檔案）
// Administrator 角色：使用 deploy-config.json 的設定
$admin_user = $config->get('deployment.admin_user');        // contentatw@gmail.com
$admin_password = $config->get('deployment.admin_password'); // 82b15dc192ae
$admin_email = $config->get('deployment.admin_user');       // contentatw@gmail.com (管理員信箱)

// 網站資訊：使用 processed_data.json 的設定
$site_title = $processed_data['confirmed_data']['website_name'] ?: $domain;
$site_description = $processed_data['confirmed_data']['website_description'] ?: '由 Contenta AI 自動生成的網站';

// Limited Admin 用戶資訊（後續步驟使用）
$user_email = $processed_data['confirmed_data']['user_email']; // eric791206@gmail.com

$deployer->log("WordPress 安裝配置:");
$deployer->log("  管理員帳號: {$admin_user}");
$deployer->log("  網站標題: {$site_title}");
$deployer->log("  用戶信箱: {$user_email} (將在後續步驟建立 Limited Admin)");

if (empty($server_host)) {
    $deployer->log("跳過 WordPress 安裝 - 未設定伺服器主機");
    return ['status' => 'skipped', 'reason' => 'no_server_config'];
}

/**
 * 生成隨機密碼
 */
function generateRandomPassword($length = 12)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * 執行 SSH 指令
 */
function executeSSH($host, $user, $port, $key_path, $command)
{
    $ssh_cmd = "ssh";
    
    if (!empty($key_path) && file_exists($key_path)) {
        $ssh_cmd .= " -i " . escapeshellarg($key_path);
    }
    
    if (!empty($port)) {
        $ssh_cmd .= " -p {$port}";
    }
    
    $ssh_cmd .= " -o StrictHostKeyChecking=no {$user}@{$host}";
    $ssh_cmd .= " " . escapeshellarg($command);
    
    $output = [];
    $return_code = 0;
    
    exec($ssh_cmd . ' 2>&1', $output, $return_code);
    
    return [
        'return_code' => $return_code,
        'output' => implode("\n", $output),
        'command' => $command
    ];
}

/**
 * 檢查並安裝 WP-CLI
 */
function ensureWPCLI($host, $user, $port, $key_path, $deployer)
{
    // 檢查 WP-CLI 是否存在
    $check_result = executeSSH($host, $user, $port, $key_path, 'which wp');
    
    if ($check_result['return_code'] === 0) {
        $deployer->log("WP-CLI 已安裝: " . trim($check_result['output']));
        return true;
    }
    
    $deployer->log("WP-CLI 未安裝，正在安裝...");
    
    // 安裝 WP-CLI
    $install_commands = [
        'cd /tmp',
        'curl -O https://raw.githubusercontent.com/wp-cli/wp-cli/v2.10.0/utils/wp-cli.phar',
        'php wp-cli.phar --info',
        'chmod +x wp-cli.phar',
        'sudo mv wp-cli.phar /usr/local/bin/wp'
    ];
    
    foreach ($install_commands as $cmd) {
        $result = executeSSH($host, $user, $port, $key_path, $cmd);
        if ($result['return_code'] !== 0 && strpos($cmd, 'sudo') !== false) {
            // 嘗試不使用 sudo
            $cmd_no_sudo = str_replace('sudo ', '', $cmd);
            $result = executeSSH($host, $user, $port, $key_path, $cmd_no_sudo);
        }
        
        if ($result['return_code'] !== 0) {
            throw new Exception("WP-CLI 安裝失敗: {$cmd} - " . $result['output']);
        }
    }
    
    $deployer->log("WP-CLI 安裝完成");
    return true;
}

/**
 * 檢查 WordPress 是否已安裝
 */
function checkWordPressInstalled($host, $user, $port, $key_path, $document_root)
{
    $check_cmd = "cd {$document_root} && test -f wp-config.php && wp core is-installed --allow-root 2>/dev/null";
    $result = executeSSH($host, $user, $port, $key_path, $check_cmd);
    return $result['return_code'] === 0;
}

try {
    $deployer->log("檢查伺服器連線...");
    
    // 測試 SSH 連線
    $test_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, 'echo "SSH connection successful"');
    if ($test_result['return_code'] !== 0) {
        throw new Exception("SSH 連線失敗: " . $test_result['output']);
    }
    $deployer->log("SSH 連線成功");
    
    // 確保 WP-CLI 可用
    ensureWPCLI($server_host, $ssh_user, $ssh_port, $ssh_key_path, $deployer);
    
    // 檢查是否已安裝 WordPress
    if (checkWordPressInstalled($server_host, $ssh_user, $ssh_port, $ssh_key_path, $document_root)) {
        $deployer->log("WordPress 已安裝，跳過安裝步驟");
        $action = 'existing';
    } else {
        $deployer->log("開始 WordPress 安裝流程...");
        $action = 'installed';
        
        // 確保目錄存在
        $deployer->log("準備安裝目錄...");
        $mkdir_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, "mkdir -p {$document_root}");
        if ($mkdir_result['return_code'] !== 0) {
            throw new Exception("無法建立目錄: " . $mkdir_result['output']);
        }
        
        // WordPress 安裝步驟
        $wp_steps = [
            // 1. 下載 WordPress
            [
                'name' => '下載 WordPress',
                'command' => "cd {$document_root} && wp core download --locale=zh_TW --allow-root --force"
            ],
            
            // 2. 建立配置檔案
            [
                'name' => '建立 wp-config.php',
                'command' => "cd {$document_root} && wp core config" .
                    " --dbname=" . escapeshellarg($database_info['database']) .
                    " --dbuser=" . escapeshellarg($database_info['username']) .
                    " --dbpass=" . escapeshellarg($database_info['password']) .
                    " --dbhost=" . escapeshellarg($database_info['host']) .
                    " --dbcharset=" . escapeshellarg($database_info['charset']) .
                    " --dbcollate=" . escapeshellarg($database_info['collate']) .
                    " --dbprefix=" . escapeshellarg($database_info['table_prefix']) .
                    " --allow-root --force"
            ],
            
            // 3. 安裝 WordPress
            [
                'name' => '執行 WordPress 安裝',
                'command' => "cd {$document_root} && wp core install" .
                    " --url=https://www.{$domain}" .
                    " --title=" . escapeshellarg($site_title) .
                    " --admin_user=" . escapeshellarg($admin_user) .
                    " --admin_password=" . escapeshellarg($admin_password) .
                    " --admin_email=" . escapeshellarg($admin_email) .
                    " --allow-root"
            ],
            
            // 4. 基本設定
            [
                'name' => '設定網站描述',
                'command' => "cd {$document_root} && wp option update blogdescription " . escapeshellarg($site_description) . " --allow-root"
            ],
            
            [
                'name' => '設定時區',
                'command' => "cd {$document_root} && wp option update timezone_string 'Asia/Taipei' --allow-root"
            ],
            
            [
                'name' => '設定語言',
                'command' => "cd {$document_root} && wp option update WPLANG 'zh_TW' --allow-root"
            ],
            
            [
                'name' => '設定固定網址',
                'command' => "cd {$document_root} && wp option update permalink_structure '/%postname%/' --allow-root"
            ],
            
            // 5. 清理預設內容
            [
                'name' => '清理預設文章',
                'command' => "cd {$document_root} && wp post delete 1 --force --allow-root 2>/dev/null || true"
            ],
            
            [
                'name' => '清理預設頁面',
                'command' => "cd {$document_root} && wp post delete 2 --force --allow-root 2>/dev/null || true"
            ],
            
            [
                'name' => '清理預設留言',
                'command' => "cd {$document_root} && wp comment delete 1 --force --allow-root 2>/dev/null || true"
            ],
            
            // 6. 新增 WordPress 安全性設定（在安裝完成後）
            [
                'name' => '新增 WordPress 安全性設定',
                'command' => "cd {$document_root} && " .
                    "cat >> wp-config.php << 'EOF'" . PHP_EOL .
                    "" . PHP_EOL .
                    "if (!defined('SHORTPIXEL_API_KEY')) {" . PHP_EOL .
                    "    define('SHORTPIXEL_API_KEY', '4pSSVVJnUXIywAJirTal');" . PHP_EOL .
                    "}" . PHP_EOL .
                    "EOF"
            ]
        ];
        
        // 執行安裝步驟
        foreach ($wp_steps as $index => $step) {
            $step_num = $index + 1;
            $total_steps = count($wp_steps);
            $deployer->log("步驟 {$step_num}/{$total_steps}: {$step['name']}");
            
            $result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $step['command']);
            
            if ($result['return_code'] !== 0) {
                // 某些步驟失敗不致命
                if (strpos($step['command'], 'delete') !== false || strpos($step['name'], '清理') !== false) {
                    $deployer->log("警告: {$step['name']} 失敗，但繼續進行 - " . $result['output']);
                } else {
                    throw new Exception("{$step['name']} 失敗: " . $result['output']);
                }
            } else {
                if (!empty($result['output'])) {
                    $deployer->log("✓ " . trim($result['output']));
                }
            }
        }
        
        // 設定檔案權限
        $deployer->log("設定檔案權限...");
        $permission_commands = [
            "chown -R www:www {$document_root} 2>/dev/null || chown -R apache:apache {$document_root} 2>/dev/null || true",
            "find {$document_root} -type d -exec chmod 755 {} \\; 2>/dev/null || true",
            "find {$document_root} -type f -exec chmod 644 {} \\; 2>/dev/null || true",
            "chmod 600 {$document_root}/wp-config.php 2>/dev/null || true"
        ];
        
        foreach ($permission_commands as $cmd) {
            executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cmd);
        }
    }
    
    // 驗證安裝
    $deployer->log("驗證 WordPress 安裝...");
    $verify_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, 
        "cd {$document_root} && wp core version --allow-root");
    
    if ($verify_result['return_code'] === 0) {
        $wp_version = trim($verify_result['output']);
        $deployer->log("WordPress 版本: {$wp_version}");
    } else {
        $wp_version = 'unknown';
        $deployer->log("無法取得 WordPress 版本");
    }
    
    // 取得自訂管理後台網址
    $custom_admin_url = $config->get('wordpress_security.custom_admin_url', 'wp-admin');
    
    // 儲存安裝資訊
    $wordpress_info = [
        'domain' => $domain,
        'site_name' => "www.{$domain}",
        'document_root' => $document_root,
        'wp_version' => $wp_version,
        'site_url' => "https://www.{$domain}",
        'admin_url' => "https://www.{$domain}/{$custom_admin_url}",
        'custom_admin_url' => $custom_admin_url,
        
        // Administrator 角色 (從 deploy-config.json)
        'admin_user' => $admin_user,        // contentatw@gmail.com
        'admin_password' => $admin_password, // 82b15dc192ae
        'admin_email' => $admin_email,       // contentatw@gmail.com
        
        // Limited Admin 角色資訊 (後續步驟使用)
        'limited_admin_email' => $user_email, // eric791206@gmail.com
        'limited_admin_password' => $config->get('deployment.limited_admin_password') ?: generateRandomPassword(),
        
        'action' => $action,
        'installed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/wordpress_install.json', json_encode($wordpress_info, JSON_PRETTY_PRINT));
    
    $deployer->log("WordPress 安裝完成");
    $deployer->log("網站網址: https://www.{$domain}");
    $deployer->log("管理後台: https://www.{$domain}/{$custom_admin_url}");
    $deployer->log("管理員帳號: {$admin_user}");
    
    return ['status' => 'success', 'action' => $action, 'wordpress_info' => $wordpress_info];
    
} catch (Exception $e) {
    $deployer->log("WordPress 安裝失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}