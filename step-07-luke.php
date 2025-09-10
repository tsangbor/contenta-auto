<?php
/**
 * 步驟 07: 客製化內容部署 (Luke API 模式)
 * 透過 SSH 連線到 Luke 主機，部署客製化內容：
 * - 上傳準備好的 WordPress 檔案
 * - 部署主題和外掛
 * - 上傳媒體檔案
 * - 設定權限
 */

$deployer->log("=== 步驟 07: 客製化內容部署 (Luke API 模式) ===");

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data_file = $work_dir . '/config/processed_data.json';
$luke_site_file = $work_dir . '/luke_site.json';
$wordpress_info_file = $work_dir . '/wordpress_info.json';

// 檢查和載入必要檔案
$processed_data = json_decode(file_get_contents($processed_data_file), true);

// luke_site.json 是步驟 03 產生的
$luke_site = [];
if (file_exists($luke_site_file)) {
    $luke_site = json_decode(file_get_contents($luke_site_file), true);
}

// wordpress_info.json 是步驟 02 產生的，如果不存在就跳過檔案上傳
$wordpress_info = [];
$wordpress_dir = null;
if (file_exists($wordpress_info_file)) {
    $wordpress_info = json_decode(file_get_contents($wordpress_info_file), true);
    $wordpress_dir = $wordpress_info['wordpress_dir'];
} else {
    $deployer->log("⚠ wordpress_info.json 不存在，跳過 WordPress 檔案上傳");
}

$domain = $processed_data['confirmed_data']['domain'];

// 從設定檔載入 Luke SSH 配置
$luke_config = $config->get('luke_deployment');
$ssh_host = $luke_config['ssh_host'];
$ssh_user = $luke_config['ssh_user'];
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

// 檢查 SSH key
if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
    $deployer->log("使用 SSH key 認證: {$ssh_key_path}");
} else {
    $deployer->log("使用預設 SSH 認證");
}

// Luke 主機的實際路徑是 /var/www/{domain}/html/
$remote_web_root = "/var/www/{$domain}/html";

$deployer->log("網域: {$domain}");
$deployer->log("本地 WordPress 目錄: {$wordpress_dir}");
$deployer->log("遠端網站目錄: {$remote_web_root}");
$deployer->log("SSH 主機: {$ssh_host}");

// === 階段 1: 測試 SSH 連線 ===
$deployer->log("🔗 階段 1: 測試 SSH 連線");

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
 * 使用 rsync 透過 SSH 上傳檔案
 */
function uploadViaRsync($local_path, $remote_path, $host, $user, $port, $key_path, $deployer) {
    // 確保遠端路徑以 / 結尾
    if (substr($remote_path, -1) !== '/') {
        $remote_path .= '/';
    }
    
    // 構建 rsync 命令
    $rsync_cmd = "rsync -avz --progress --delete";
    
    // SSH 設定
    $ssh_options = "";
    if (!empty($key_path) && file_exists($key_path)) {
        $ssh_options .= " -i " . escapeshellarg($key_path);
    }
    if (!empty($port)) {
        $ssh_options .= " -p {$port}";
    }
    $ssh_options .= " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null";
    
    $rsync_cmd .= " -e 'ssh" . $ssh_options . "'";
    $rsync_cmd .= " {$local_path}/ {$user}@{$host}:{$remote_path}";
    
    $deployer->log("執行上傳指令: rsync");
    
    $output = [];
    $return_code = 0;
    
    exec($rsync_cmd . ' 2>&1', $output, $return_code);
    
    return [
        'return_code' => $return_code,
        'output' => implode("\n", $output)
    ];
}

// 測試基本 SSH 連線
$test_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, 'echo "SSH connection successful"');
if ($test_result['return_code'] !== 0) {
    throw new Exception("SSH 連線測試失敗: " . $test_result['output']);
}

$deployer->log("✅ SSH 連線測試成功");

// === 階段 2: 準備遠端目錄 ===
$deployer->log("📁 階段 2: 準備遠端目錄");

// 確保遠端網站目錄存在
$mkdir_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, "mkdir -p {$remote_web_root}");
if ($mkdir_result['return_code'] !== 0) {
    throw new Exception("無法建立遠端目錄: " . $mkdir_result['output']);
}

$deployer->log("✅ 遠端目錄準備完成");

// === 階段 3: 檢查工具可用性 ===
$deployer->log("🔧 階段 3: 檢查必要工具");

// 檢查 rsync 是否可用
exec('which rsync', $rsync_check, $rsync_code);
if ($rsync_code !== 0) {
    throw new Exception("需要 rsync 工具進行檔案同步");
}

$deployer->log("✅ 必要工具檢查完成");

// === 階段 4: 備份現有檔案（如果存在） ===
$deployer->log("💾 階段 4: 備份現有檔案");

$backup_cmd = "if [ -d {$remote_web_root} ]; then " .
              "cp -r {$remote_web_root} {$remote_web_root}_backup_" . date('YmdHis') . "; " .
              "echo 'Backup created'; " .
              "else echo 'No existing files to backup'; fi";

$backup_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $backup_cmd);
$deployer->log("備份結果: " . trim($backup_result['output']));

// === 階段 5: 上傳專案的 wp-content 檔案 ===
$deployer->log("📤 階段 5: 上傳專案的 wp-content 檔案");

// 本機端專案的 wp-content 目錄
$local_wp_content_dir = DEPLOY_BASE_PATH . '/wp-content';
$local_plugins_dir = $local_wp_content_dir . '/plugins';
$local_themes_dir = $local_wp_content_dir . '/themes';

$deployer->log("專案 wp-content 目錄: {$local_wp_content_dir}");

// 檢查本機端目錄是否存在
if (!is_dir($local_wp_content_dir)) {
    $deployer->log("⚠ 本機端 wp-content 目錄不存在: {$local_wp_content_dir}");
    goto skip_wordpress_upload;
}

// === 1. 同步外掛目錄 ===
if (is_dir($local_plugins_dir)) {
    $deployer->log("📦 同步外掛目錄...");
    
    // 使用 rsync 同步外掛（保留現有的，更新/新增）
    $plugins_cmd = "rsync -avz --progress";
    if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
        $plugins_cmd .= " -e 'ssh -i " . escapeshellarg($ssh_key_path) . " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p {$ssh_port}'";
    } else {
        $plugins_cmd .= " -e 'ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p {$ssh_port}'";
    }
    $plugins_cmd .= " {$local_plugins_dir}/ {$ssh_user}@{$ssh_host}:{$remote_web_root}/wp-content/plugins/";
    
    $deployer->log("執行: rsync {$local_plugins_dir}/ -> {$remote_web_root}/wp-content/plugins/");
    exec($plugins_cmd . ' 2>&1', $plugins_output, $plugins_code);
    
    if ($plugins_code === 0) {
        $deployer->log("✅ 外掛目錄同步完成");
    } else {
        $deployer->log("⚠ 外掛同步警告: " . implode("\n", $plugins_output));
    }
} else {
    $deployer->log("⚠ 本機端外掛目錄不存在: {$local_plugins_dir}");
}

// === 2. 同步主題目錄 ===
if (is_dir($local_themes_dir)) {
    $deployer->log("🎨 同步主題目錄...");
    
    // 先移除遠端的預設主題
    $deployer->log("清理預設主題...");
    $remove_themes = ['twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo'];
    foreach ($remove_themes as $theme) {
        $remove_cmd = "rm -rf {$remote_web_root}/wp-content/themes/{$theme}";
        $remove_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $remove_cmd);
        if ($remove_result['return_code'] === 0) {
            $deployer->log("  ✅ 移除主題: {$theme}");
        }
    }
    
    // 使用 rsync 同步主題
    $themes_cmd = "rsync -avz --progress --delete";
    if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
        $themes_cmd .= " -e 'ssh -i " . escapeshellarg($ssh_key_path) . " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p {$ssh_port}'";
    } else {
        $themes_cmd .= " -e 'ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p {$ssh_port}'";
    }
    $themes_cmd .= " {$local_themes_dir}/ {$ssh_user}@{$ssh_host}:{$remote_web_root}/wp-content/themes/";
    
    $deployer->log("執行: rsync {$local_themes_dir}/ -> {$remote_web_root}/wp-content/themes/");
    exec($themes_cmd . ' 2>&1', $themes_output, $themes_code);
    
    if ($themes_code === 0) {
        $deployer->log("✅ 主題目錄同步完成");
    } else {
        $deployer->log("⚠ 主題同步警告: " . implode("\n", $themes_output));
    }
} else {
    $deployer->log("⚠ 本機端主題目錄不存在: {$local_themes_dir}");
}

// === 3. 同步快取檔案 ===
$cache_files_dir = DEPLOY_BASE_PATH . '/cache_files';
if (is_dir($cache_files_dir)) {
    $deployer->log("💾 同步快取檔案...");
    
    $cache_files = ['advanced-cache.php', 'object-cache.php'];
    foreach ($cache_files as $cache_file) {
        $source_file = $cache_files_dir . '/' . $cache_file;
        if (file_exists($source_file)) {
            $scp_cmd = "scp -o StrictHostKeyChecking=no";
            if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
                $scp_cmd .= " -i " . escapeshellarg($ssh_key_path);
            }
            $scp_cmd .= sprintf(
                " -P %d %s %s@%s:%s/wp-content/%s",
                $ssh_port,
                escapeshellarg($source_file),
                $ssh_user,
                $ssh_host,
                $remote_web_root,
                $cache_file
            );
            
            exec($scp_cmd . ' 2>&1', $scp_output, $scp_code);
            
            if ($scp_code === 0) {
                $deployer->log("  ✅ 快取檔案 {$cache_file} 上傳成功");
            } else {
                $deployer->log("  ⚠ 快取檔案 {$cache_file} 上傳失敗");
            }
        }
    }
}


skip_wordpress_upload:

// === 階段 6: 處理 Job 檔案（如果有） ===
$deployer->log("📄 階段 6: 處理 Job 檔案");

$job_data_dir = DEPLOY_DATA_PATH . '/' . $job_id;
if (is_dir($job_data_dir)) {
    $deployer->log("發現 Job 資料目錄: {$job_data_dir}");
    
    // 建立遠端 uploads 目錄
    $uploads_cmd = "mkdir -p {$remote_web_root}/wp-content/uploads";
    executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $uploads_cmd);
    
    // 上傳 Job 檔案中的圖片到 uploads 目錄
    $files = scandir($job_data_dir);
    $uploaded_files = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $file_path = $job_data_dir . '/' . $file;
        if (is_file($file_path)) {
            $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            
            // 上傳圖片檔案
            if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $deployer->log("上傳圖片檔案: {$file}");
                
                // 使用 scp 上傳單一檔案
                $scp_cmd = "scp -o StrictHostKeyChecking=no";
                if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
                    $scp_cmd .= " -i " . escapeshellarg($ssh_key_path);
                }
                $scp_cmd .= " -P {$ssh_port} {$file_path} {$ssh_user}@{$ssh_host}:{$remote_web_root}/wp-content/uploads/";
                
                exec($scp_cmd . ' 2>&1', $scp_output, $scp_code);
                
                if ($scp_code === 0) {
                    $uploaded_files++;
                    $deployer->log("  ✅ {$file} 上傳成功");
                } else {
                    $deployer->log("  ⚠ {$file} 上傳失敗: " . implode("\n", $scp_output));
                }
            }
        }
    }
    
    $deployer->log("Job 檔案處理完成，共上傳 {$uploaded_files} 個檔案");
} else {
    $deployer->log("未找到 Job 資料目錄，跳過檔案上傳");
}

// === 階段 7: 設定檔案權限 ===
$deployer->log("🔐 階段 7: 設定檔案權限");

// Luke 系統使用域名作為使用者名稱
$site_user = $domain;

// 修正擁有者和權限
$permission_commands = [
    // 先修正整個 wp-content 目錄的擁有者
    "chown -R {$site_user}:www-data {$remote_web_root}/wp-content/",
    // 設定檔案權限
    "find {$remote_web_root} -type f -exec chmod 644 {} \\;",
    "find {$remote_web_root} -type d -exec chmod 755 {} \\;",
    // wp-content 目錄需要寫入權限給 www-data 群組
    "find {$remote_web_root}/wp-content -type d -exec chmod 775 {} \\;",
    // 保護 wp-config.php
    "chmod 600 {$remote_web_root}/wp-config.php"
];

foreach ($permission_commands as $cmd) {
    $perm_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $cmd);
    if ($perm_result['return_code'] !== 0) {
        $deployer->log("⚠ 權限設定警告: " . $perm_result['output']);
    }
}

$deployer->log("✅ 檔案權限設定完成");

// === 階段 8: 驗證部署結果 ===
$deployer->log("✅ 階段 8: 驗證部署結果");

// 檢查重要檔案是否存在
$check_files = [
    "{$remote_web_root}/wp-config.php",
    "{$remote_web_root}/wp-load.php", 
    "{$remote_web_root}/index.php"
];

$missing_files = [];
foreach ($check_files as $file) {
    $check_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, "test -f {$file} && echo 'exists' || echo 'missing'");
    $output = trim($check_result['output']);
    $deployer->log("檢查檔案 {$file}: 回應為 '{$output}'");
    // 檢查輸出是否包含 'exists'，忽略 SSH 警告訊息
    if (strpos($output, 'exists') === false) {
        $missing_files[] = basename($file);
    }
}

if (!empty($missing_files)) {
    throw new Exception("部署驗證失敗，缺少檔案: " . implode(', ', $missing_files));
}

$deployer->log("✅ 重要檔案驗證通過");

// 計算部署統計
$stats_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, 
    "find {$remote_web_root} -type f | wc -l && du -sh {$remote_web_root}");

$stats_lines = explode("\n", trim($stats_result['output']));
$file_count = trim($stats_lines[0] ?? '0');
$dir_size = trim($stats_lines[1] ?? '0');

$deployer->log("部署統計:");
$deployer->log("  檔案總數: {$file_count}");
$deployer->log("  目錄大小: {$dir_size}");

// === 階段 9A: WordPress 基本設定 ===
$deployer->log("⚙️ 階段 9A: WordPress 基本設定");

// 設定 WordPress 檔案系統方式為 direct
$deployer->log("設定 WordPress 檔案系統方式為 direct...");
$fs_method_cmd = "cd {$remote_web_root} && wp config set FS_METHOD direct --allow-root";
$fs_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $fs_method_cmd);

if ($fs_result['return_code'] === 0) {
    $deployer->log("✅ FS_METHOD 設定為 direct 成功");
} else {
    $deployer->log("⚠ FS_METHOD 設定失敗: " . $fs_result['output']);
}

// === 階段 9B: 啟用外掛 ===
$deployer->log("🔌 階段 9B: 啟用外掛");

// 先檢查遠端主機上有哪些外掛
$deployer->log("檢查遠端主機上的外掛列表...");
$plugin_list_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, 
    "cd {$remote_web_root} && wp plugin list --field=name --allow-root");
    
if ($plugin_list_result['return_code'] === 0) {
    $available_plugins = explode("\n", trim($plugin_list_result['output']));
    $available_plugins = array_filter($available_plugins); // 移除空行
    $deployer->log("遠端主機上可用的外掛: " . implode(', ', $available_plugins));
} else {
    $deployer->log("❌ 無法取得外掛列表: " . $plugin_list_result['output']);
    $available_plugins = [];
}

// 指定要啟用的外掛列表
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
    // 檢查外掛是否存在
    if (!in_array($plugin, $available_plugins)) {
        $failed_plugins[] = $plugin;
        $deployer->log("  ⏭️ 跳過 {$plugin} - 外掛檔案不存在");
        continue;
    }
    
    $deployer->log("啟用外掛: {$plugin}");
    $activate_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
        "cd {$remote_web_root} && wp plugin activate {$plugin} --allow-root");
    
    if ($activate_result['return_code'] === 0) {
        $activated_plugins[] = $plugin;
        $deployer->log("  ✅ {$plugin} 啟用成功");
    } else {
        $failed_plugins[] = $plugin;
        $deployer->log("  ❌ {$plugin} 啟用失敗: " . $activate_result['output']);
    }
}

$deployer->log("外掛啟用完成 - 成功: " . count($activated_plugins) . ", 失敗: " . count($failed_plugins));

// === 階段 9B-1: 停用 cache-enabler 外掛 ===
$deployer->log("🔧 階段 9B-1: 停用 cache-enabler 外掛");

$deactivate_cache_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
    "cd {$remote_web_root} && wp plugin deactivate cache-enabler --allow-root");

if ($deactivate_cache_result['return_code'] === 0) {
    $deployer->log("✅ cache-enabler 外掛已停用");
} else {
    $deployer->log("⚠️ cache-enabler 外掛停用失敗或不存在: " . $deactivate_cache_result['output']);
}

// === 階段 9C: 啟用主題 ===
$deployer->log("🎨 階段 9C: 啟用主題");

$active_theme = '';
$hello_elementor_configured = false;

// 啟用 hello-theme-child-master 主題
$deployer->log("啟用 hello-theme-child-master 主題...");
$activate_theme_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
    "cd {$remote_web_root} && wp theme activate hello-theme-child-master --allow-root");

if ($activate_theme_result['return_code'] === 0) {
    $active_theme = 'hello-theme-child-master';
    $deployer->log("✅ hello-theme-child-master 主題啟用成功");
    
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
        $update_option_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
            "cd {$remote_web_root} && wp option update {$option_name} {$option_value} --allow-root");
        
        if ($update_option_result['return_code'] === 0) {
            $hello_settings_success++;
            $deployer->log("  ✅ {$option_name} = {$option_value}");
        } else {
            $hello_settings_failed++;
            $deployer->log("  ❌ {$option_name} 設定失敗: " . $update_option_result['output']);
        }
    }
    
    $deployer->log("Hello Elementor 設定完成 - 成功: {$hello_settings_success}, 失敗: {$hello_settings_failed}");
    $hello_elementor_configured = ($hello_settings_success > 0);
    
} else {
    $deployer->log("❌ hello-theme-child-master 主題啟用失敗: " . $activate_theme_result['output']);
    $deployer->log("嘗試啟用第一個可用主題...");
    
    // 如果 hello-theme-child-master 不存在，嘗試啟用第一個可用主題
    $fallback_theme_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
        "cd {$remote_web_root} && wp theme list --status=inactive --field=name --allow-root | head -1 | xargs wp theme activate --allow-root");
    
    if ($fallback_theme_result['return_code'] === 0) {
        $deployer->log("✅ 備用主題啟用成功");
        $active_theme = 'fallback-theme';
    } else {
        $deployer->log("❌ 無法啟用任何主題");
        $active_theme = 'none';
    }
}

// === 階段 9D: 建立用戶管理 ===
$deployer->log("👤 階段 9D: 建立用戶管理");
$deployer->log("調試: 進入用戶管理階段");

// 從 wordpress_install.json 讀取管理員設定（如果存在）
$wordpress_install_file = $work_dir . '/wordpress_install.json';
$deployer->log("調試: 檢查檔案: {$wordpress_install_file}");
$limited_admin_created = false;

if (file_exists($wordpress_install_file)) {
    $deployer->log("調試: wordpress_install.json 檔案存在，準備讀取");
    $wordpress_install = json_decode(file_get_contents($wordpress_install_file), true);
    $deployer->log("調試: JSON 檔案解析完成");
    $limited_admin_email = $wordpress_install['limited_admin_email'] ?? '';
    $limited_admin_password = $wordpress_install['limited_admin_password'] ?? '';
    $deployer->log("調試: 管理員帳號資訊 - Email: " . ($limited_admin_email ? '已設定' : '未設定') . ", 密碼: " . ($limited_admin_password ? '已設定' : '未設定'));
    
    if (!empty($limited_admin_email) && !empty($limited_admin_password)) {
        $deployer->log("調試: 準備建立專用管理員帳號");
        $deployer->log("建立專用管理員帳號: {$limited_admin_email}");
        
        $create_user_cmd = "cd {$remote_web_root} && wp user create " . escapeshellarg($limited_admin_email) . " " . 
                          escapeshellarg($limited_admin_email) . " --role=administrator --user_pass=" . 
                          escapeshellarg($limited_admin_password) . " --allow-root";
        
        $deployer->log("調試: 準備執行 SSH 指令建立用戶");
        $deployer->log("調試: SSH 指令: " . str_replace($limited_admin_password, '***', $create_user_cmd));
        $create_user_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $create_user_cmd);
        $deployer->log("調試: SSH 指令執行完成，return_code: " . $create_user_result['return_code']);
        
        if ($create_user_result['return_code'] === 0) {
            $deployer->log("✅ 專用管理員帳號建立成功: {$limited_admin_email}");
            $limited_admin_created = true;
            
            // 更新管理員信箱
            $update_admin_email_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
                "cd {$remote_web_root} && wp option update admin_email " . escapeshellarg($limited_admin_email) . " --allow-root");
            
            if ($update_admin_email_result['return_code'] === 0) {
                $deployer->log("✅ 管理員信箱更新成功");
            } else {
                $deployer->log("⚠ 管理員信箱更新失敗: " . $update_admin_email_result['output']);
            }
        } else {
            $deployer->log("❌ 專用管理員帳號建立失敗: " . $create_user_result['output']);
        }
    } else {
        $deployer->log("⚠️ 跳過專用管理員建立 - 未設定帳號資訊");
    }
} else {
    $deployer->log("調試: wordpress_install.json 檔案不存在");
    $deployer->log("⚠️ 跳過專用管理員建立 - 找不到 wordpress_install.json");
}

$deployer->log("調試: 用戶管理階段完成，準備進入授權啟用階段");

// === 階段 9E: 授權啟用 ===
$deployer->log("🔑 階段 9E: 授權啟用");

$elementor_license_activated = false;
$flying_press_license_activated = false;

// Elementor Pro 授權
$deployer->log("🔑 啟用 Elementor Pro 授權...");
$elementor_license = $config->get('plugins.license_required.elementor-pro');

if (!empty($elementor_license)) {
    $elementor_license_cmd = "cd {$remote_web_root} && wp elementor-pro license activate " . 
                            escapeshellarg($elementor_license) . " --allow-root";
    
    $elementor_license_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $elementor_license_cmd);
    
    if ($elementor_license_result['return_code'] === 0) {
        $deployer->log("✅ Elementor Pro 授權啟用成功");
        $elementor_license_activated = true;
    } else {
        $deployer->log("❌ Elementor Pro 授權啟用失敗: " . $elementor_license_result['output']);
    }
} else {
    $deployer->log("⚠️ 跳過 Elementor Pro 授權 - 未設定授權金鑰");
}

// FlyingPress 授權
$deployer->log("🚀 啟用 FlyingPress 授權...");
$flying_press_license = $config->get('plugins.license_required.flying-press');

if (!empty($flying_press_license)) {
    $flying_press_cmd = "cd {$remote_web_root} && wp flying-press activate-license " . 
                       escapeshellarg($flying_press_license) . " --allow-root";
    
    $flying_press_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path, $flying_press_cmd);
    
    if ($flying_press_result['return_code'] === 0) {
        $deployer->log("✅ FlyingPress 授權啟用成功");
        $flying_press_license_activated = true;
    } else {
        $deployer->log("❌ FlyingPress 授權啟用失敗: " . $flying_press_result['output']);
    }
} else {
    $deployer->log("⚠️ 跳過 FlyingPress 授權 - 未設定授權金鑰");
}

// === 階段 9F: 最終驗證 ===
$deployer->log("✅ 階段 9F: 最終驗證");

// 驗證外掛狀態
$deployer->log("驗證外掛狀態...");
$plugin_list_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
    "cd {$remote_web_root} && wp plugin list --field=name --allow-root");

// 驗證主題狀態
$deployer->log("驗證主題狀態...");
$theme_list_result = executeSSH($ssh_host, $ssh_user, $ssh_port, $ssh_key_path,
    "cd {$remote_web_root} && wp theme list --field=name --allow-root");

// === 階段 10: 更新部署狀態 ===
$deployer->log("📊 階段 10: 更新部署狀態");

// 更新部署檢查清單
$checklist_file = $work_dir . '/deployment-checklist.json';
if (file_exists($checklist_file)) {
    $checklist = json_decode(file_get_contents($checklist_file), true);
    
    $checklist['customization']['content_uploaded'] = true;
    $checklist['finalization']['files_deployed'] = true;
    
    file_put_contents(
        $checklist_file,
        json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

// 更新部署狀態
$status_file = $work_dir . '/deployment-status.json';
$deployment_status = json_decode(file_get_contents($status_file), true);

$deployment_status['status'] = 'content_deployed';
$deployment_status['current_step'] = '07';
$deployment_status['files_uploaded'] = true;
$deployment_status['content_deployed'] = true;
$deployment_status['deployment_stats'] = [
    'file_count' => (int)$file_count,
    'directory_size' => $dir_size,
    'uploaded_media_files' => $uploaded_files ?? 0
];
$deployment_status['updated_at'] = date('Y-m-d H:i:s');

file_put_contents($status_file, json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 建立詳細部署摘要
$deployment_summary = [
    'status' => 'completed',
    'domain' => $domain,
    'site_url' => $luke_site['site_url'],
    'admin_url' => $luke_site['admin_url'],
    'deployment_mode' => 'luke',
    'ssh_host' => $ssh_host,
    'remote_path' => $remote_web_root,
    'local_source' => $wordpress_dir,
    'file_statistics' => [
        'total_files' => (int)$file_count,
        'directory_size' => $dir_size,
        'media_files_uploaded' => $uploaded_files ?? 0
    ],
    'plugin_deployment' => [
        'total_plugins' => count($required_plugins),
        'activated_successfully' => count($activated_plugins),
        'failed_to_activate' => count($failed_plugins),
        'activated_plugins' => $activated_plugins,
        'failed_plugins' => $failed_plugins
    ],
    'theme_deployment' => [
        'active_theme' => $active_theme,
        'hello_elementor_configured' => $hello_elementor_configured
    ],
    'user_management' => [
        'limited_admin_created' => $limited_admin_created
    ],
    'license_activation' => [
        'elementor_pro' => $elementor_license_activated,
        'flying_press' => $flying_press_license_activated
    ],
    'completed_at' => date('Y-m-d H:i:s')
];

$summary_file = $work_dir . '/deployment-summary.json';
file_put_contents($summary_file, json_encode($deployment_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("=== 外掛主題部署完成 (Luke API 模式) ===");
$deployer->log("🌐 網站 URL: " . $luke_site['site_url']);
$deployer->log("⚙️  管理後台: " . $luke_site['admin_url']);
$deployer->log("📁 遠端路徑: {$remote_web_root}");
$deployer->log("📊 部署統計: {$file_count} 個檔案，{$dir_size}");
$deployer->log("🔌 外掛啟用: " . count($activated_plugins) . "/" . count($required_plugins) . " 成功");
$deployer->log("🎨 啟用主題: " . $active_theme);
$deployer->log("👤 專用管理員: " . ($limited_admin_created ? '已建立' : '跳過'));
$deployer->log("🔑 Elementor Pro 授權: " . ($elementor_license_activated ? '已啟用' : '未啟用'));
$deployer->log("🚀 FlyingPress 授權: " . ($flying_press_license_activated ? '已啟用' : '未啟用'));
$deployer->log("⚙️ Hello Elementor 設定: " . ($hello_elementor_configured ? '已完成' : '未執行'));

return [
    'status' => 'success',
    'deployment_summary' => $deployment_summary,
    'activated_plugins' => $activated_plugins,
    'failed_plugins' => $failed_plugins,
    'active_theme' => $active_theme,
    'limited_admin_created' => $limited_admin_created,
    'elementor_license_activated' => $elementor_license_activated,
    'flying_press_license_activated' => $flying_press_license_activated,
    'hello_elementor_configured' => $hello_elementor_configured
];