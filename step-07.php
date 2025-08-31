<?php
/**
 * 步驟 07: 外掛主題部署與啟用
 * 1. 使用 rsync 同步本機端 wp-content/plugins 和 wp-content/themes 到遠端主機
 *    - plugins: 不移除既有檔案，僅新增/更新
 *    - themes: 先清除多餘主題，再同步新主題
 * 2. 先啟用指定的 16 個外掛
 * 3. 最後啟用 hello-theme-child-master 主題
 * 4. 建立專用管理員帳號 (limited_admin 角色) - 從 wordpress_install.json 讀取
 * 5. 啟用 Elementor Pro 授權
 * 6. 啟用 FlyingPress 授權
 * 
 * 執行順序：rsync 同步 → 外掛啟用 → 主題啟用 → 帳號建立 → 授權啟用
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$wordpress_info = json_decode(file_get_contents($work_dir . '/wordpress_install.json'), true);

$domain = $processed_data['confirmed_data']['domain'];
$document_root = $wordpress_info['document_root']; // /www/wwwroot/www.{domain}

$deployer->log("開始外掛主題部署: {$domain}");
$deployer->log("WordPress 目錄: {$document_root}");

// 取得部署設定
$server_host = $config->get('deployment.server_host');
$ssh_user = $config->get('deployment.ssh_user');
$ssh_port = $config->get('deployment.ssh_port') ?: 22;
$ssh_key_path = $config->get('deployment.ssh_key_path');

// 本機端 wp-content 目錄設定
$local_wp_content_dir = DEPLOY_BASE_PATH . '/wp-content';
$local_plugins_dir = $local_wp_content_dir . '/plugins';
$local_themes_dir = $local_wp_content_dir . '/themes';

if (empty($server_host)) {
    $deployer->log("跳過外掛主題部署 - 未設定伺服器主機");
    return ['status' => 'skipped', 'reason' => 'no_server_config'];
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
 * 使用 rsync 同步目錄
 */
function rsyncDirectory($local_dir, $remote_dir, $host, $user, $port, $key_path, $delete_excluded = false, $exclude_patterns = [])
{
    $rsync_cmd = "rsync -avz --progress";
    
    // SSH 設定
    $ssh_options = "-o StrictHostKeyChecking=no";
    if (!empty($key_path) && file_exists($key_path)) {
        $ssh_options .= " -i " . escapeshellarg($key_path);
    }
    if (!empty($port)) {
        $ssh_options .= " -p {$port}";
    }
    
    $rsync_cmd .= " -e " . escapeshellarg("ssh {$ssh_options}");
    
    // 刪除排除的檔案（僅用於 themes）
    if ($delete_excluded) {
        $rsync_cmd .= " --delete";
    }
    
    // 排除模式
    foreach ($exclude_patterns as $pattern) {
        $rsync_cmd .= " --exclude=" . escapeshellarg($pattern);
    }
    
    // 確保本機目錄以 / 結尾，表示同步目錄內容而非目錄本身
    $local_dir = rtrim($local_dir, '/') . '/';
    
    $rsync_cmd .= " " . escapeshellarg($local_dir);
    $rsync_cmd .= " {$user}@{$host}:" . escapeshellarg($remote_dir);
    
    $output = [];
    $return_code = 0;
    
    exec($rsync_cmd . ' 2>&1', $output, $return_code);
    
    return [
        'return_code' => $return_code,
        'output' => implode("\n", $output),
        'command' => $rsync_cmd
    ];
}

/**
 * 檢查外掛是否已安裝
 */
function checkPluginsInstalled($host, $user, $port, $key_path, $document_root, $required_plugins)
{
    $installed_plugins = [];
    $missing_plugins = [];
    
    foreach ($required_plugins as $plugin) {
        $check_cmd = "cd {$document_root} && wp plugin is-installed {$plugin} --allow-root";
        $result = executeSSH($host, $user, $port, $key_path, $check_cmd);
        
        if ($result['return_code'] === 0) {
            $installed_plugins[] = $plugin;
        } else {
            $missing_plugins[] = $plugin;
        }
    }
    
    return [
        'installed' => $installed_plugins,
        'missing' => $missing_plugins
    ];
}

try {
    $deployer->log("檢查本機端 wp-content 目錄...");
    
    // 檢查本機端目錄是否存在
    if (!is_dir($local_plugins_dir)) {
        throw new Exception("本機端外掛目錄不存在: {$local_plugins_dir}");
    }
    
    if (!is_dir($local_themes_dir)) {
        throw new Exception("本機端主題目錄不存在: {$local_themes_dir}");
    }
    
    // 計算目錄大小
    $plugins_size = 0;
    $themes_size = 0;
    
    $plugins_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($local_plugins_dir));
    foreach ($plugins_iterator as $file) {
        if ($file->isFile()) {
            $plugins_size += $file->getSize();
        }
    }
    
    $themes_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($local_themes_dir));
    foreach ($themes_iterator as $file) {
        if ($file->isFile()) {
            $themes_size += $file->getSize();
        }
    }
    
    $deployer->log("找到本機端目錄:");
    $deployer->log("- 外掛目錄: {$local_plugins_dir} (" . round($plugins_size / 1024 / 1024, 2) . " MB)");
    $deployer->log("- 主題目錄: {$local_themes_dir} (" . round($themes_size / 1024 / 1024, 2) . " MB)");
    
    // 同步外掛到 wp-content/plugins
    $deployer->log("使用 rsync 同步外掛到 wp-content/plugins...");
    $remote_plugins_dir = "{$document_root}/wp-content/plugins";
    
    // 確保遠端 plugins 目錄存在
    $mkdir_plugins_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, "mkdir -p {$remote_plugins_dir}");
    if ($mkdir_plugins_result['return_code'] !== 0) {
        throw new Exception("無法建立遠端外掛目錄: " . $mkdir_plugins_result['output']);
    }
    
    // rsync 外掛目錄（不刪除既有檔案）
    $rsync_plugins_result = rsyncDirectory($local_plugins_dir, $remote_plugins_dir, $server_host, $ssh_user, $ssh_port, $ssh_key_path, false);
    
    if ($rsync_plugins_result['return_code'] !== 0) {
        throw new Exception("外掛 rsync 同步失敗: " . $rsync_plugins_result['output']);
    }
    $deployer->log("外掛 rsync 同步完成");
    
    // 設定外掛目錄權限
    $plugins_permission_commands = [
        "chown -R www:www {$remote_plugins_dir} 2>/dev/null || chown -R apache:apache {$remote_plugins_dir} 2>/dev/null || true",
        "find {$remote_plugins_dir} -type d -exec chmod 755 {} \\; 2>/dev/null || true",
        "find {$remote_plugins_dir} -type f -exec chmod 644 {} \\; 2>/dev/null || true"
    ];
    
    foreach ($plugins_permission_commands as $cmd) {
        executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cmd);
    }
    
    // 清理多餘主題並使用 rsync 同步主題到 wp-content/themes
    $deployer->log("清理多餘主題並使用 rsync 同步主題到 wp-content/themes...");
    $remote_themes_dir = "{$document_root}/wp-content/themes";
    
    // 確保遠端 themes 目錄存在
    $mkdir_themes_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, "mkdir -p {$remote_themes_dir}");
    if ($mkdir_themes_result['return_code'] !== 0) {
        throw new Exception("無法建立遠端主題目錄: " . $mkdir_themes_result['output']);
    }
    
    $theme_cleanup_commands = [
        // 先切換到預設主題（避免刪除正在使用的主題時出錯）
        "cd {$document_root} && wp theme activate twentytwentythree --allow-root 2>/dev/null || true",
        // 清空 themes 資料夾內的多餘主題目錄（預設主題和可能的舊主題）
        "cd {$remote_themes_dir} && find . -maxdepth 1 -type d -name 'twenty*' -exec rm -rf {} \\; 2>/dev/null || true"
    ];
    
    foreach ($theme_cleanup_commands as $cmd) {
        $result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cmd);
        // 清理指令失敗不中斷流程
    }
    $deployer->log("多餘主題清理完成");
    
    // rsync 主題目錄（清理模式，刪除目標目錄中不存在於來源的檔案）
    $rsync_themes_result = rsyncDirectory($local_themes_dir, $remote_themes_dir, $server_host, $ssh_user, $ssh_port, $ssh_key_path, true);
    
    if ($rsync_themes_result['return_code'] !== 0) {
        throw new Exception("主題 rsync 同步失敗: " . $rsync_themes_result['output']);
    }
    $deployer->log("主題 rsync 同步完成");
    
    // 在 rsync 完成後立即修復檔案系統權限問題
    $deployer->log("設定 WordPress 檔案系統方式為 direct...");
    $fs_method_cmd = "cd {$document_root} && wp config set FS_METHOD direct --allow-root";
    $fs_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $fs_method_cmd);
    if ($fs_result['return_code'] === 0) {
        $deployer->log("✓ FS_METHOD 設定為 direct 成功");
    } else {
        $deployer->log("⚠ FS_METHOD 設定失敗: " . $fs_result['output']);
    }
    
    // 設定檔案系統權限
    $deployer->log("設定檔案系統權限...");
    $permission_commands = [
        "chown -R www-data:www-data {$document_root}/ 2>/dev/null || chown -R www:www {$document_root}/ 2>/dev/null || chown -R apache:apache {$document_root}/ 2>/dev/null || true",
        "chmod -R 755 {$document_root}/",
        "chmod -R 775 {$document_root}/wp-content/"
    ];
    
    foreach ($permission_commands as $cmd) {
        $result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cmd);
        if ($result['return_code'] === 0) {
            $deployer->log("✓ 權限設定完成: " . explode(' ', $cmd)[2]);
        } else {
            $deployer->log("⚠ 權限設定警告: " . $result['output']);
        }
    }
    
    // 設定主題目錄權限
    $themes_permission_commands = [
        "chown -R www:www {$remote_themes_dir} 2>/dev/null || chown -R apache:apache {$remote_themes_dir} 2>/dev/null || true",
        "find {$remote_themes_dir} -type d -exec chmod 755 {} \\; 2>/dev/null || true",
        "find {$remote_themes_dir} -type f -exec chmod 644 {} \\; 2>/dev/null || true"
    ];
    
    foreach ($themes_permission_commands as $cmd) {
        executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cmd);
    }
    
    // 先啟用指定的外掛
    $deployer->log("啟用指定外掛...");
    $required_plugins = [
        "advanced-custom-fields",
        "auto-upload-images", 
        "better-search-replace",
        "contact-form-7",
        "elementor",
        "elementor-pro",
        "flying-press",
        "one-user-avatar",
        "performance-lab",
        "seo-by-rank-math",
        "shortpixel-image-optimiser",
        "google-site-kit",
        "ultimate-elementor",
        "insert-headers-and-footers"
    ];
    
    $activated_plugins = [];
    $failed_plugins = [];
    
    foreach ($required_plugins as $plugin) {
        $deployer->log("啟用外掛: {$plugin}");
        $activate_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$document_root} && wp plugin activate {$plugin} --allow-root");
        
        if ($activate_result['return_code'] === 0) {
            $activated_plugins[] = $plugin;
            $deployer->log("  ✓ {$plugin} 啟用成功");
        } else {
            $failed_plugins[] = $plugin;
            $deployer->log("  ✗ {$plugin} 啟用失敗: " . $activate_result['output']);
        }
    }
    
    $deployer->log("外掛啟用完成 - 成功: " . count($activated_plugins) . ", 失敗: " . count($failed_plugins));
    
    // 最後啟用 hello-theme-child-master 主題
    $deployer->log("啟用 hello-theme-child-master 主題...");
    $activate_theme_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "cd {$document_root} && wp theme activate hello-theme-child-master --allow-root");
    
    if ($activate_theme_result['return_code'] === 0) {
        $deployer->log("✓ hello-theme-child-master 主題啟用成功");
        $active_theme = 'hello-theme-child-master';
        
        // 設定 Hello Elementor 主題選項
        $deployer->log("設定 Hello Elementor 主題選項...");
        $hello_elementor_settings = [
            'hello_elementor_settings_description_meta_tag' => 'false',
            'hello_elementor_settings_skip_link' => 'false',
            'hello_elementor_settings_header_footer' => 'true',
            'hello_elementor_settings_page_title' => 'true',
            'hello_elementor_settings_hello_style' => 'false',
            'hello_elementor_settings_hello_theme' => 'false'
        ];
        
        $hello_settings_success = 0;
        $hello_settings_failed = 0;
        
        foreach ($hello_elementor_settings as $option_name => $option_value) {
            $update_option_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
                "cd {$document_root} && wp option update {$option_name} {$option_value} --allow-root");
            
            if ($update_option_result['return_code'] === 0) {
                $deployer->log("  ✓ {$option_name} = {$option_value}");
                $hello_settings_success++;
            } else {
                $deployer->log("  ✗ {$option_name} 設定失敗: " . $update_option_result['output']);
                $hello_settings_failed++;
            }
        }
        
        $deployer->log("Hello Elementor 設定完成 - 成功: {$hello_settings_success}, 失敗: {$hello_settings_failed}");
        
        // 禁用主題色彩覆蓋
        $deployer->log("禁用主題色彩覆蓋...");
        $color_override_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$document_root} && wp theme colors override disable --allow-root");
        
        if ($color_override_result['return_code'] === 0) {
            $deployer->log("✓ 主題色彩覆蓋已禁用");
        } else {
            $deployer->log("⚠️ 禁用主題色彩覆蓋失敗: " . $color_override_result['output']);
        }
        
        // 記錄 Hello Elementor 設定狀態
        $hello_elementor_configured = ($hello_settings_failed === 0);
    } else {
        $deployer->log("✗ hello-theme-child-master 主題啟用失敗: " . $activate_theme_result['output']);
        $deployer->log("嘗試啟用第一個可用主題...");
        
        // 如果 hello-theme-child-master 不存在，嘗試啟用第一個可用主題
        $fallback_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$document_root} && wp theme list --status=inactive --field=name --allow-root | head -1 | xargs wp theme activate --allow-root");
        
        if ($fallback_result['return_code'] === 0) {
            $deployer->log("✓ 備用主題啟用成功");
            $active_theme = 'fallback';
        } else {
            $deployer->log("✗ 無法啟用任何主題");
            $active_theme = 'none';
        }
        
        // 非 hello-theme-child-master 主題不進行 Hello Elementor 設定
        $hello_elementor_configured = false;
    }
    
    // 建立用戶使用者帳號，並設定為專用管理員
    $deployer->log("建立專用管理員帳號...");
    
    // 從 wordpress_install.json 讀取專用管理員資訊
    $wordpress_install_file = $work_dir . '/wordpress_install.json';
    if (file_exists($wordpress_install_file)) {
        $wordpress_install_data = json_decode(file_get_contents($wordpress_install_file), true);
        $limited_admin_email = $wordpress_install_data['limited_admin_email'] ?? null;
        $limited_admin_password = $wordpress_install_data['limited_admin_password'] ?? null;
        
        $deployer->log("從 wordpress_install.json 讀取管理員資訊");
        $deployer->log("管理員郵箱: {$limited_admin_email}");
    } else {
        $deployer->log("⚠️ wordpress_install.json 檔案不存在，使用配置檔案");
        $limited_admin_email = $config->get('wordpress_admin.limited_admin_email');
        $limited_admin_password = $config->get('wordpress_admin.limited_admin_password');
    }
    
    if (!empty($limited_admin_email) && !empty($limited_admin_password)) {
        $create_user_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$document_root} && wp user create " . escapeshellarg($limited_admin_email) . " " . 
            escapeshellarg($limited_admin_email) . " --role=limited_admin --user_pass=" . 
            escapeshellarg($limited_admin_password) . " --allow-root");
        
        if ($create_user_result['return_code'] === 0) {
            $deployer->log("✓ 專用管理員帳號建立成功: {$limited_admin_email}");
            $limited_admin_created = true;
            
            // 變更網站管理員 email
            $deployer->log("變更網站管理員 email 為: {$limited_admin_email}");
            $update_admin_email_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
                "cd {$document_root} && wp option update admin_email " . escapeshellarg($limited_admin_email) . " --allow-root");
            
            if ($update_admin_email_result['return_code'] === 0) {
                $deployer->log("✓ 網站管理員 email 更新成功: {$limited_admin_email}");
            } else {
                $deployer->log("⚠️ 網站管理員 email 更新失敗: " . $update_admin_email_result['output']);
            }
        } else {
            $deployer->log("✗ 專用管理員帳號建立失敗: " . $create_user_result['output']);
            $limited_admin_created = false;
        }
    } else {
        $deployer->log("⚠️ 跳過專用管理員建立 - 未設定帳號資訊");
        $limited_admin_created = false;
    }
    
    // 🔑 啟用 Elementor Pro 授權
    $deployer->log("🔑 啟用 Elementor Pro 授權...");
    $elementor_pro_license = $config->get('plugins.license_required.elementor-pro');
    
    if (!empty($elementor_pro_license)) {
        $elementor_license_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$document_root} && wp elementor-pro license activate " . 
            escapeshellarg($elementor_pro_license) . " --allow-root");
        
        if ($elementor_license_result['return_code'] === 0) {
            $deployer->log("✓ Elementor Pro 授權啟用成功");
            $elementor_license_activated = true;
        } else {
            $deployer->log("✗ Elementor Pro 授權啟用失敗: " . $elementor_license_result['output']);
            $elementor_license_activated = false;
        }
    } else {
        $deployer->log("⚠️ 跳過 Elementor Pro 授權 - 未設定授權金鑰");
        $elementor_license_activated = false;
    }
    
    // 🚀 啟用 FlyingPress 授權
    $deployer->log("🚀 啟用 FlyingPress 授權...");
    $flying_press_license = $config->get('plugins.license_required.flying-press');
    
    if (!empty($flying_press_license)) {
        $deployer->log("FlyingPress 授權金鑰: " . substr($flying_press_license, 0, 8) . "...");
        
        // 使用 WP-CLI 命令啟用 FlyingPress 授權
        $flying_press_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$document_root} && wp flying-press activate-license " . 
            escapeshellarg($flying_press_license) . " --allow-root");
        
        if ($flying_press_result['return_code'] === 0) {
            $deployer->log("✓ FlyingPress 授權啟用成功");
            $flying_press_license_activated = true;
        } else {
            $deployer->log("✗ FlyingPress 授權啟用失敗: " . $flying_press_result['output']);
            $flying_press_license_activated = false;
        }
    } else {
        $deployer->log("⚠️ 跳過 FlyingPress 授權 - 未設定授權金鑰");
        $flying_press_license_activated = false;
    }
    
    // 檢查已安裝的外掛
    $deployer->log("檢查已安裝的外掛...");
    $list_plugins_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, 
        "cd {$document_root} && wp plugin list --field=name --allow-root");
    
    if ($list_plugins_result['return_code'] === 0) {
        $installed_plugins = array_filter(explode("\n", trim($list_plugins_result['output'])));
        $deployer->log("發現 " . count($installed_plugins) . " 個外掛:");
        foreach ($installed_plugins as $plugin) {
            $deployer->log("  - {$plugin}");
        }
    } else {
        $installed_plugins = [];
        $deployer->log("無法取得外掛清單");
    }
    
    // 檢查已安裝的主題
    $deployer->log("檢查已安裝的主題...");
    $list_themes_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, 
        "cd {$document_root} && wp theme list --field=name --allow-root");
    
    if ($list_themes_result['return_code'] === 0) {
        $installed_themes = array_filter(explode("\n", trim($list_themes_result['output'])));
        $deployer->log("發現 " . count($installed_themes) . " 個主題:");
        foreach ($installed_themes as $theme) {
            $deployer->log("  - {$theme}");
        }
    } else {
        $installed_themes = [];
        $deployer->log("無法取得主題清單");
    }
    
    // rsync 同步完成，無需清理臨時檔案
    
    // 儲存部署結果
    $deployment_info = [
        'domain' => $domain,
        'document_root' => $document_root,
        'local_plugins_dir' => $local_plugins_dir,
        'local_themes_dir' => $local_themes_dir,
        'remote_plugins_dir' => $remote_plugins_dir,
        'remote_themes_dir' => $remote_themes_dir,
        'installed_plugins' => $installed_plugins,
        'installed_themes' => $installed_themes,
        'activated_plugins' => $activated_plugins,
        'failed_plugins' => $failed_plugins,
        'active_theme' => $active_theme,
        'required_plugins' => $required_plugins,
        'limited_admin_created' => $limited_admin_created,
        'limited_admin_email' => $limited_admin_email ?? null,
        'elementor_license_activated' => $elementor_license_activated,
        'flying_press_license_activated' => $flying_press_license_activated,
        'hello_elementor_configured' => $hello_elementor_configured,
        'sync_method' => 'rsync',
        'synced_sizes' => [
            'plugins' => round($plugins_size / 1024 / 1024, 2) . ' MB',
            'themes' => round($themes_size / 1024 / 1024, 2) . ' MB'
        ],
        'deployed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/plugins_themes_deployed.json', json_encode($deployment_info, JSON_PRETTY_PRINT));
    
    $deployer->log("外掛主題部署完成");
    $deployer->log("外掛數量: " . count($installed_plugins));
    $deployer->log("主題數量: " . count($installed_themes));
    $deployer->log("已啟用外掛: " . count($activated_plugins) . "/" . count($required_plugins));
    $deployer->log("啟用的主題: " . $active_theme);
    $deployer->log("專用管理員建立: " . ($limited_admin_created ? '成功' : '失敗'));
    $deployer->log("Elementor Pro 授權: " . ($elementor_license_activated ? '已啟用' : '未啟用'));
    $deployer->log("FlyingPress 授權: " . ($flying_press_license_activated ? '已啟用' : '未啟用'));
    $deployer->log("Hello Elementor 設定: " . ($hello_elementor_configured ? '已完成' : '未執行'));
    
    return ['status' => 'success', 'deployment_info' => $deployment_info];
    
} catch (Exception $e) {
    $deployer->log("外掛主題部署失敗: " . $e->getMessage());
    
    // rsync 方式無需清理臨時檔案
    
    return ['status' => 'error', 'message' => $e->getMessage()];
}