<?php
/**
 * 步驟 00: 初始化部署環境 (Luke API 模式)
 * - 檢查必要的工具和檔案
 * - 設定基本環境
 * - 驗證 Luke API 連線
 */

// Luke API 模式不需要寶塔相關的類別
// 可以在這裡載入 Luke API 相關的類別（如果有的話）

$deployer->log("=== 步驟 00: 初始化部署環境 (Luke API 模式) ===");

// 1. 檢查系統需求
$deployer->log("檢查系統需求...");

// 檢查 PHP 版本
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    throw new Exception("需要 PHP 7.4 或更高版本，當前版本: " . PHP_VERSION);
}
$deployer->log("✓ PHP 版本: " . PHP_VERSION);

// 檢查必要的 PHP 擴展
$required_extensions = ['curl', 'json', 'zip', 'ssh2'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    // SSH2 是可選的，其他是必需的
    $critical_missing = array_diff($missing_extensions, ['ssh2']);
    if (!empty($critical_missing)) {
        throw new Exception("缺少必要的 PHP 擴展: " . implode(', ', $critical_missing));
    }
    if (in_array('ssh2', $missing_extensions)) {
        $deployer->log("⚠ 警告: 缺少 ssh2 擴展，將使用備用方案");
    }
}
$deployer->log("✓ PHP 擴展檢查完成");

// 2. 檢查 Luke API 設定
$deployer->log("檢查 Luke API 設定...");

$luke_api_config = $config->get('luke_api');
if (!$luke_api_config) {
    throw new Exception("未找到 Luke API 設定，請檢查 config/deploy-config.json");
}

// 驗證必要的 API 設定
$required_fields = ['api_url', 'api_key', 'sftp_host', 'sftp_user', 'sftp_password'];
foreach ($required_fields as $field) {
    if (empty($luke_api_config[$field])) {
        throw new Exception("Luke API 設定缺少必要欄位: {$field}");
    }
}

$deployer->log("✓ Luke API 設定已載入");
$deployer->log("  - API URL: " . $luke_api_config['api_url']);
$deployer->log("  - SFTP Host: " . $luke_api_config['sftp_host']);

// 3. 測試 Luke API 連線
$deployer->log("測試 Luke API 連線...");

try {
    $test_url = rtrim($luke_api_config['api_url'], '/') . '/api/health';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $luke_api_config['api_key'],
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $deployer->log("✓ Luke API 連線成功");
    } else {
        // 不是致命錯誤，繼續執行
        $deployer->log("⚠ Luke API 健康檢查失敗 (HTTP {$http_code})，但將繼續執行");
    }
} catch (Exception $e) {
    $deployer->log("⚠ Luke API 連線測試失敗: " . $e->getMessage());
}

// 4. 建立必要的目錄
$deployer->log("建立必要的目錄...");

$work_dir = DEPLOY_DATA_PATH . '/' . $job_id;
$required_dirs = [
    $work_dir,
    $work_dir . '/config',
    $work_dir . '/backup',
    $work_dir . '/temp',
    $work_dir . '/logs'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            throw new Exception("無法建立目錄: {$dir}");
        }
        $deployer->log("✓ 建立目錄: {$dir}");
    }
}

// 5. 初始化部署狀態
$deployer->log("初始化部署狀態...");

$deployment_status = [
    'job_id' => $job_id,
    'mode' => 'luke',
    'start_time' => date('Y-m-d H:i:s'),
    'status' => 'initializing',
    'current_step' => '00',
    'api_url' => $luke_api_config['api_url'],
    'sftp_host' => $luke_api_config['sftp_host']
];

file_put_contents(
    $work_dir . '/deployment-status.json',
    json_encode($deployment_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

$deployer->log("✓ 部署狀態已初始化");

// 6. 檢查必要的命令列工具
$deployer->log("檢查命令列工具...");

$required_commands = ['curl', 'tar', 'unzip'];
$optional_commands = ['rsync', 'scp'];

foreach ($required_commands as $cmd) {
    $result = shell_exec("which {$cmd} 2>/dev/null");
    if (empty($result)) {
        throw new Exception("缺少必要的命令列工具: {$cmd}");
    }
}
$deployer->log("✓ 必要的命令列工具已就緒");

foreach ($optional_commands as $cmd) {
    $result = shell_exec("which {$cmd} 2>/dev/null");
    if (empty($result)) {
        $deployer->log("⚠ 可選工具 {$cmd} 未安裝，將使用備用方案");
    }
}

// 7. 載入並驗證 Job 資料
$deployer->log("驗證 Job 資料...");

$confirmed_data = $job_data['confirmed_data'] ?? [];
$required_job_fields = ['domain', 'website_name'];

foreach ($required_job_fields as $field) {
    if (empty($confirmed_data[$field])) {
        throw new Exception("Job 資料缺少必要欄位: {$field}");
    }
}

$deployer->log("✓ Job 資料驗證完成");
$deployer->log("  - 網站名稱: " . $confirmed_data['website_name']);
$deployer->log("  - 網域: " . $confirmed_data['domain']);

// 8. 準備環境變數
$deployer->log("設定環境變數...");

$env_vars = [
    'LUKE_API_MODE' => 'true',
    'DEPLOYMENT_JOB_ID' => $job_id,
    'DEPLOYMENT_DOMAIN' => $confirmed_data['domain'],
    'DEPLOYMENT_WORK_DIR' => $work_dir
];

foreach ($env_vars as $key => $value) {
    putenv("{$key}={$value}");
}

$deployer->log("✓ 環境變數已設定");

// 完成初始化
$deployer->log("=== Luke API 模式初始化完成 ===");

return true;