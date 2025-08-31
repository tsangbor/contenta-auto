<?php
/**
 * 步驟 18: Elementor Kit 全域設定匯入
 * 
 * 核心職責：匯入 Elementor Kit 的全域設定，包括全域樣式、字型、色彩等配置
 * 
 * 執行工作流：
 * 1. 檢查 Elementor Kit 檔案是否存在
 * 2. 解壓縮 Kit 檔案到臨時目錄
 * 3. 使用 WP-CLI 匯入全域設定
 * 4. 清理臨時檔案
 * 5. 驗證匯入結果
 * 
 * @package Contenta
 * @version 1.0
 */

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
    
    $ssh_cmd .= " -o StrictHostKeyChecking=no";
    $ssh_cmd .= " -o ConnectTimeout=30";
    $ssh_cmd .= " {$user}@{$host}";
    $ssh_cmd .= " " . escapeshellarg($command);
    
    $output = [];
    $return_code = 0;
    exec($ssh_cmd . ' 2>&1', $output, $return_code);
    
    return [
        'output' => implode("\n", $output),
        'return_code' => $return_code
    ];
}

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);

// 嘗試載入網站資訊
$website_info_file = $work_dir . '/wordpress_install.json';
if (!file_exists($website_info_file)) {
    $website_info_file = $work_dir . '/bt_website.json';
}

if (!file_exists($website_info_file)) {
    $deployer->log("錯誤: 找不到網站資訊檔案");
    return ['status' => 'error', 'message' => '找不到網站資訊檔案'];
}

$website_info = json_decode(file_get_contents($website_info_file), true);
$domain = $processed_data['confirmed_data']['domain'];
$document_root = $website_info['document_root'];

$deployer->log("=== 步驟 18: Elementor Kit 全域設定匯入 ===");
$deployer->log("目標網域: {$domain}");
$deployer->log("WordPress 目錄: {$document_root}");

try {
    // 載入必要的類別
    $base_path = defined('DEPLOY_BASE_PATH') ? DEPLOY_BASE_PATH : __DIR__;
    $wp_cli_executor_file = $base_path . '/includes/utilities/class-wp-cli-executor.php';
    
    if (!file_exists($wp_cli_executor_file)) {
        throw new Exception("找不到 WP_CLI_Executor 類別檔案: {$wp_cli_executor_file}");
    }
    
    require_once $wp_cli_executor_file;
    
    // 載入配置
    require_once $base_path . '/config-manager.php';
    $config = ConfigManager::getInstance();
    
    // 初始化 WP-CLI 執行器
    $wp_cli = new WP_CLI_Executor($config);
    $wp_cli->set_document_root($document_root);
    
    // 檢查 WP-CLI 可用性
    $deployer->log("檢查 WP-CLI 可用性...");
    if (!$wp_cli->is_available()) {
        throw new Exception("WP-CLI 不可用");
    }
    
    // 定義 Elementor Kit 檔案路徑
    $kit_zip_path = $base_path . '/zip/contenta-kit.zip';
    
    // 檢查 Kit 檔案是否存在
    if (!file_exists($kit_zip_path)) {
        throw new Exception("找不到 Elementor Kit 檔案: {$kit_zip_path}");
    }
    
    $deployer->log("找到 Elementor Kit 檔案: {$kit_zip_path}");
    
    // 取得 SSH 連接參數
    $ssh_user = $config->get('deployment.ssh_user');
    $server_host = $config->get('deployment.server_host');
    $ssh_port = $config->get('deployment.ssh_port') ?: 22;
    $ssh_key_path = $config->get('deployment.ssh_key_path');
    
    // 定義遠端臨時目錄
    $remote_temp_dir = "{$document_root}/wp-content/uploads/temp";
    $remote_kit_path = "{$remote_temp_dir}/contenta-kit.zip";
    
    // 創建遠端臨時目錄
    $create_dir_cmd = "mkdir -p {$remote_temp_dir}";
    $dir_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $create_dir_cmd);
    if ($dir_result['return_code'] !== 0) {
        throw new Exception("無法創建遠端臨時目錄: " . $dir_result['output']);
    }
    
    // 上傳 Kit 檔案到遠端
    $deployer->log("上傳 Elementor Kit 到遠端伺服器...");
    $scp_cmd = "scp";
    
    if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
        $scp_cmd .= " -i " . escapeshellarg($ssh_key_path);
    }
    $scp_cmd .= " -P {$ssh_port}";
    $scp_cmd .= " -o StrictHostKeyChecking=no";
    $scp_cmd .= " " . escapeshellarg($kit_zip_path);
    $scp_cmd .= " {$ssh_user}@{$server_host}:{$remote_kit_path}";
    
    $output = [];
    $return_code = 0;
    exec($scp_cmd . ' 2>&1', $output, $return_code);
    
    if ($return_code !== 0) {
        throw new Exception("無法上傳 Kit 檔案到遠端: " . implode("\n", $output));
    }
    
    $deployer->log("成功上傳 Kit 檔案到遠端: {$remote_kit_path}");
    
    // 檢查 Elementor 外掛是否已安裝
    $elementor_installed = $wp_cli->execute('plugin is-installed elementor');
    if ($elementor_installed['return_code'] !== 0) {
        $deployer->log("Elementor 外掛未安裝，正在安裝...");
        $install_result = $wp_cli->execute('plugin install elementor --activate');
        if ($install_result['return_code'] !== 0) {
            throw new Exception("無法安裝 Elementor 外掛: " . $install_result['output']);
        }
        $deployer->log("✅ Elementor 外掛已安裝並啟用");
    } else {
        // 檢查 Elementor 外掛是否啟用
        $elementor_check = $wp_cli->execute('plugin is-active elementor');
        if ($elementor_check['return_code'] !== 0) {
            $deployer->log("Elementor 外掛未啟用，嘗試啟用...");
            $activate_result = $wp_cli->execute('plugin activate elementor');
            if ($activate_result['return_code'] !== 0) {
                throw new Exception("無法啟用 Elementor 外掛: " . $activate_result['output']);
            }
            $deployer->log("✅ Elementor 外掛已啟用");
        } else {
            $deployer->log("✅ Elementor 外掛已啟用");
        }
    }
    
    // 使用 WP-CLI 匯入 Elementor Kit (僅匯入全域設定)
    $import_command = sprintf(
        'elementor kit import %s --user=1 --allow-root',
        escapeshellarg($remote_kit_path)
    );
    
    $deployer->log("執行 Kit 匯入指令: wp {$import_command}");
    $import_result = $wp_cli->execute($import_command);
    
    if ($import_result['return_code'] !== 0) {
        throw new Exception("Kit 匯入失敗: " . $import_result['output']);
    }
    
    $deployer->log("✅ Elementor Kit 全域設定匯入成功");
    $deployer->log("匯入結果: " . trim($import_result['output']));
    
    // 清理遠端臨時檔案
    $deployer->log("清理遠端臨時檔案...");
    
    $cleanup_cmd = "rm -f {$remote_kit_path}";
    $cleanup_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cleanup_cmd);
    
    if ($cleanup_result['return_code'] === 0) {
        $deployer->log("✅ 遠端臨時檔案清理完成");
    } else {
        $deployer->log("⚠️ 遠端臨時檔案清理失敗，請手動刪除: {$remote_kit_path}");
    }
    
    // 清除快取
    $deployer->log("清除 Elementor CSS 快取...");
    $flush_css_result = $wp_cli->execute('elementor flush_css --allow-root');
    if ($flush_css_result['return_code'] === 0) {
        $deployer->log("✅ Elementor CSS 快取已清除");
    } else {
        $deployer->log("⚠️ 清除 Elementor CSS 快取失敗: " . $flush_css_result['output']);
    }
    
    $deployer->log("清除 WordPress 快取...");
    $cache_flush_result = $wp_cli->execute('cache flush --allow-root');
    if ($cache_flush_result['return_code'] === 0) {
        $deployer->log("✅ WordPress 快取已清除");
    } else {
        $deployer->log("⚠️ 清除 WordPress 快取失敗: " . $cache_flush_result['output']);
    }
    
    // 驗證匯入結果 - 檢查是否有全域樣式設定
    $global_check = $wp_cli->execute('option get elementor_scheme_color');
    if ($global_check['return_code'] === 0) {
        $deployer->log("✅ 全域色彩設定已匯入");
    }
    
    $font_check = $wp_cli->execute('option get elementor_scheme_typography');
    if ($font_check['return_code'] === 0) {
        $deployer->log("✅ 全域字型設定已匯入");
    }
    
    $deployer->log("=== 步驟 18 完成：Elementor Kit 全域設定匯入 ===");
    
    return [
        'status' => 'success',
        'message' => 'Elementor Kit 全域設定匯入成功',
        'details' => [
            'kit_file' => basename($remote_kit_path),
            'import_output' => trim($import_result['output'])
        ]
    ];
    
} catch (Exception $e) {
    $error_message = "步驟 18 錯誤: " . $e->getMessage();
    $deployer->log($error_message);
    
    // 清理可能留下的遠端臨時檔案
    if (isset($remote_kit_path) && isset($server_host)) {
        $deployer->log("清理錯誤時留下的遠端臨時檔案...");
        $cleanup_cmd = "rm -f {$remote_kit_path}";
        executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cleanup_cmd);
    }
    
    return [
        'status' => 'error',
        'message' => $error_message
    ];
}

?>