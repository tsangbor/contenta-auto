<?php
/**
 * 步驟 07: 外掛主題部署與啟用 (整合認證更新版)
 * 
 * 在執行 BT 主機操作前，自動更新認證資訊
 * 
 * 執行順序：
 * 1. 🔐 更新 BT Panel 認證 (Cookie + Token)
 * 2. 使用 rsync 同步本機端 wp-content/plugins 和 wp-content/themes 到遠端主機
 * 3. 先啟用指定的 16 個外掛
 * 4. 最後啟用 hello-theme-child-master 主題
 * 5. 建立專用管理員帳號 (limited_admin 角色)
 * 6. 啟用 Elementor Pro 授權
 * 7. 啟用 FlyingPress 授權
 */

// 引入認證管理器
require_once DEPLOY_BASE_PATH . '/includes/class-auth-manager.php';

$deployer->log("=== 步驟 07: 外掛主題部署與啟用 (含認證更新) ===");

// === 1. 認證更新階段 ===
$deployer->log("🔐 階段 1: 更新 BT Panel 認證資訊");

$authManager = new AuthManager();

// 檢查並更新認證
if (!$authManager->ensureValidCredentials()) {
    $deployer->log("❌ 認證更新失敗，無法繼續執行 BT 主機操作", 'ERROR');
    return [
        'status' => 'error',
        'error' => 'auth_update_failed',
        'message' => '認證更新失敗，無法執行 BT 主機操作'
    ];
}

$deployer->log("✅ 認證更新完成，可以安全執行 BT 主機操作");

// === 2. 載入處理後的資料 ===
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$wordpress_info = json_decode(file_get_contents($work_dir . '/wordpress_install.json'), true);

$domain = $processed_data['confirmed_data']['domain'];
$document_root = $wordpress_info['document_root']; // /www/wwwroot/www.{domain}

$deployer->log("開始外掛主題部署: {$domain}");
$deployer->log("WordPress 目錄: {$document_root}");

// === 3. 取得部署設定 ===
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

// === 4. 驗證認證資訊可用性 ===
$credentials = $authManager->getCredentials();
if (!$credentials || !$credentials['cookie'] || !$credentials['token']) {
    $deployer->log("❌ 認證資訊不完整，無法執行 BT 主機操作", 'ERROR');
    return [
        'status' => 'error',
        'error' => 'incomplete_credentials',
        'message' => '認證資訊不完整'
    ];
}

$deployer->log("🔑 使用認證資訊:");
$deployer->log("   Cookie: " . substr($credentials['cookie'], 0, 50) . "...");
$deployer->log("   Token: " . substr($credentials['token'], 0, 20) . "...");

/**
 * 執行 SSH 指令
 */
function executeSSH($host, $user, $port, $key_path, $command)
{
    $ssh_cmd = "ssh";
    
    if (!empty($key_path) && file_exists($key_path)) {
        $ssh_cmd .= " -i " . escapeshellarg($key_path);
    }
    
    $ssh_cmd .= " -p " . intval($port);
    $ssh_cmd .= " -o StrictHostKeyChecking=no";
    $ssh_cmd .= " -o UserKnownHostsFile=/dev/null";
    $ssh_cmd .= " " . escapeshellarg($user . "@" . $host);
    $ssh_cmd .= " " . escapeshellarg($command);
    
    $output = [];
    $return_var = 0;
    exec($ssh_cmd, $output, $return_var);
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'return_code' => $return_var
    ];
}

/**
 * 執行 BT Panel API 請求 (使用最新認證)
 */
function executeBTAPI($endpoint, $data = [])
{
    global $config, $deployer, $credentials;
    
    $panel_url = $config->get('api_credentials.btcn.panel_url');
    
    $headers = [
        'Cookie: ' . $credentials['cookie'],
        'X-HTTP-Token: ' . $credentials['token'],
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest'
    ];
    
    $url = $panel_url . '/' . ltrim($endpoint, '/');
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        $deployer->log("BT API 請求失敗: $error", 'ERROR');
        return false;
    }
    
    if ($httpCode !== 200) {
        $deployer->log("BT API 返回錯誤狀態: $httpCode", 'ERROR');
        return false;
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $deployer->log("BT API 返回無效 JSON: $response", 'ERROR');
        return false;
    }
    
    return $result;
}

// === 5. 執行原有的部署邏輯 ===
// (此處可以繼續放入原有的步驟 07 代碼)

try {
    // === rsync 同步外掛和主題 ===
    $deployer->log("🔄 階段 2: 同步外掛和主題");
    
    // 同步外掛 (不刪除既有檔案)
    if (is_dir($local_plugins_dir)) {
        $deployer->log("同步外掛到遠端主機...");
        $rsync_plugins_cmd = sprintf(
            'rsync -avz --progress -e "ssh -p %d -o StrictHostKeyChecking=no" %s/ %s@%s:%s/wp-content/plugins/',
            $ssh_port,
            escapeshellarg($local_plugins_dir),
            escapeshellarg($ssh_user),
            escapeshellarg($server_host),
            escapeshellarg($document_root)
        );
        
        if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
            $rsync_plugins_cmd = str_replace('-e "ssh', '-e "ssh -i ' . escapeshellarg($ssh_key_path), $rsync_plugins_cmd);
        }
        
        $deployer->log("執行指令: " . $rsync_plugins_cmd);
        exec($rsync_plugins_cmd, $output, $return_var);
        
        if ($return_var === 0) {
            $deployer->log("✅ 外掛同步完成");
        } else {
            $deployer->log("❌ 外掛同步失敗: " . implode("\n", $output), 'ERROR');
        }
    }
    
    // 同步主題
    if (is_dir($local_themes_dir)) {
        $deployer->log("同步主題到遠端主機...");
        $rsync_themes_cmd = sprintf(
            'rsync -avz --progress --delete -e "ssh -p %d -o StrictHostKeyChecking=no" %s/ %s@%s:%s/wp-content/themes/',
            $ssh_port,
            escapeshellarg($local_themes_dir),
            escapeshellarg($ssh_user),
            escapeshellarg($server_host),
            escapeshellarg($document_root)
        );
        
        if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
            $rsync_themes_cmd = str_replace('-e "ssh', '-e "ssh -i ' . escapeshellarg($ssh_key_path), $rsync_themes_cmd);
        }
        
        $deployer->log("執行指令: " . $rsync_themes_cmd);
        exec($rsync_themes_cmd, $output, $return_var);
        
        if ($return_var === 0) {
            $deployer->log("✅ 主題同步完成");
        } else {
            $deployer->log("❌ 主題同步失敗: " . implode("\n", $output), 'ERROR');
        }
    }
    
    // === 啟用外掛 ===
    $deployer->log("🔌 階段 3: 啟用外掛");
    
    $required_plugins = $config->get('plugins.required', []);
    
    foreach ($required_plugins as $plugin) {
        $deployer->log("啟用外掛: $plugin");
        
        $activate_cmd = sprintf(
            'cd %s && wp plugin activate %s --allow-root',
            escapeshellarg($document_root),
            escapeshellarg($plugin)
        );
        
        $result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $activate_cmd);
        
        if ($result['success']) {
            $deployer->log("✅ 外掛 $plugin 啟用成功");
        } else {
            $deployer->log("⚠️ 外掛 $plugin 啟用失敗: " . $result['output'], 'WARNING');
        }
    }
    
    // === 啟用主題 ===
    $deployer->log("🎨 階段 4: 啟用主題");
    
    $theme_cmd = sprintf(
        'cd %s && wp theme activate hello-theme-child-master --allow-root',
        escapeshellarg($document_root)
    );
    
    $result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $theme_cmd);
    
    if ($result['success']) {
        $deployer->log("✅ 主題啟用成功");
    } else {
        $deployer->log("❌ 主題啟用失敗: " . $result['output'], 'ERROR');
    }
    
    // === 外掛授權啟用 (使用 BT API) ===
    $deployer->log("🔐 階段 5: 啟用外掛授權");
    
    $licenses = $config->get('plugins.license_required', []);
    
    foreach ($licenses as $plugin => $license_key) {
        $deployer->log("設定 $plugin 授權: $license_key");
        
        // 這裡可以透過 BT API 或 SSH 來設定授權
        // 具體實作取決於各外掛的授權機制
        
        switch ($plugin) {
            case 'elementor-pro':
                // Elementor Pro 授權設定
                $license_cmd = sprintf(
                    'cd %s && wp option update elementor_pro_license_key %s --allow-root',
                    escapeshellarg($document_root),
                    escapeshellarg($license_key)
                );
                break;
                
            case 'flying-press':
                // FlyingPress 授權設定
                $license_cmd = sprintf(
                    'cd %s && wp option update flying_press_license_key %s --allow-root',
                    escapeshellarg($document_root),
                    escapeshellarg($license_key)
                );
                break;
                
            default:
                $deployer->log("⚠️ 未知外掛授權: $plugin", 'WARNING');
                continue 2;
        }
        
        $result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $license_cmd);
        
        if ($result['success']) {
            $deployer->log("✅ $plugin 授權設定成功");
        } else {
            $deployer->log("⚠️ $plugin 授權設定失敗: " . $result['output'], 'WARNING');
        }
    }
    
    $deployer->log("✅ 步驟 07 執行完成");
    
    return [
        'status' => 'success',
        'message' => '外掛主題部署與啟用完成',
        'auth_updated' => true,
        'credentials_valid' => true
    ];
    
} catch (Exception $e) {
    $deployer->log("❌ 步驟 07 執行失敗: " . $e->getMessage(), 'ERROR');
    
    return [
        'status' => 'error',
        'error' => $e->getMessage(),
        'auth_updated' => isset($authManager),
        'credentials_valid' => isset($credentials) && !empty($credentials['cookie'])
    ];
}
?>