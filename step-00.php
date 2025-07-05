<?php
/**
 * 步驟 00: 設定參數與載入配置
 * 處理 JSON 資料，設定相關參數與 API 憑證
 */

$deployer->log("開始處理 Job 資料...");

/**
 * 遞迴刪除目錄
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// 建立統一的工作目錄（整合 tmp 和 temp 功能）
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

$deployer->log("檢查工作目錄: {$work_dir}");

// 如果 job_id 資料夾已存在，先刪除
if (is_dir($work_dir)) {
    $deployer->log("發現現有 job_id 資料夾，正在清理...");
    if (deleteDirectory($work_dir)) {
        $deployer->log("舊資料夾清理完成");
    } else {
        throw new Exception("無法清理舊的 job_id 資料夾: {$work_dir}");
    }
}

// 建立新的 job_id 資料夾
$temp_base_dir = DEPLOY_BASE_PATH . '/temp';
if (!is_dir($temp_base_dir)) {
    mkdir($temp_base_dir, 0755, true);
}

if (!mkdir($work_dir, 0755, true)) {
    throw new Exception("無法建立工作目錄: {$work_dir}");
}

$deployer->log("工作目錄建立完成: {$work_dir}");

// 建立子目錄結構（統一管理所有類型檔案）
$subdirs = ['config', 'scripts', 'json', 'images', 'logs'];
foreach ($subdirs as $subdir) {
    $subdir_path = $work_dir . '/' . $subdir;
    if (!mkdir($subdir_path, 0755, true)) {
        throw new Exception("無法建立子目錄: {$subdir_path}");
    }
}

$deployer->log("子目錄結構建立完成: " . implode(', ', $subdirs));

// 驗證 Job 資料結構
$required_fields = ['confirmed_data'];
foreach ($required_fields as $field) {
    if (!isset($job_data[$field])) {
        throw new Exception("Job 資料缺少必要欄位: {$field}");
    }
}

$confirmed_data = $job_data['confirmed_data'];

// 驗證確認資料的必要欄位
$required_confirmed_fields = ['website_name', 'domain', 'user_email'];
foreach ($required_confirmed_fields as $field) {
    if (empty($confirmed_data[$field])) {
        throw new Exception("確認資料缺少必要欄位: {$field}");
    }
}

// 設定部署變數
$website_name = $confirmed_data['website_name'];
$website_description = $confirmed_data['website_description'] ?? '';
$domain = $confirmed_data['domain'];
$user_email = $confirmed_data['user_email'];

$deployer->log("網站名稱: {$website_name}");
$deployer->log("網域: {$domain}");
$deployer->log("用戶信箱: {$user_email}");

// 驗證配置
try {
    $config->validateConfig();
    $deployer->log("配置驗證通過");
} catch (Exception $e) {
    $deployer->log("配置驗證失敗: " . $e->getMessage());
    $deployer->log("請檢查 config/deploy-config.json 檔案");
    throw $e;
}

// 工作目錄已在上面建立，此處移除重複程式碼

// 儲存處理後的資料供其他步驟使用
$processed_data = [
    'website_name' => $website_name,
    'website_description' => $website_description,
    'domain' => $domain,
    'user_email' => $user_email,
    'work_dir' => $work_dir,
    'confirmed_data' => $confirmed_data
];

// 儲存到統一工作目錄
file_put_contents($work_dir . '/config/processed_data.json', json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 生成資料庫配置檔案（step-06 需要）
$deployer->log("生成資料庫配置檔案...");
$db_name = str_replace('.tw', '', $domain);  // 移除 .tw 後綴
$db_user = $db_name;  // 用戶名稱同資料庫名稱
$db_password = '82b15dc192ae';  // 統一密碼

$database_config = [
    'host' => 'localhost',
    'database' => $db_name,
    'username' => $db_user,
    'password' => $db_password,
    'table_prefix' => 'wp_',
    'charset' => 'utf8mb4',
    'collate' => 'utf8mb4_unicode_ci'
];

file_put_contents($work_dir . '/database_config.json', json_encode($database_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$deployer->log("  資料庫名稱: {$db_name}");
$deployer->log("  資料庫用戶: {$db_user}");

// 生成網站配置檔案（step-06 需要）
$deployer->log("生成網站配置檔案...");
$document_root = "/www/wwwroot/www.{$domain}";

$bt_website_config = [
    'domain' => $domain,
    'document_root' => $document_root,
    'subdomain' => "www.{$domain}",
    'site_name' => $website_name,
    'created_at' => date('Y-m-d H:i:s'),
    'php_version' => '8.1',
    'ssl_enabled' => true,
    'database' => [
        'name' => $db_name,
        'user' => $db_user,
        'password' => $db_password
    ]
];

file_put_contents($work_dir . '/bt_website.json', json_encode($bt_website_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$deployer->log("  網站目錄: {$document_root}");
$deployer->log("  網域名稱: {$domain}");

// 建立任務資訊檔案
$job_info = [
    'job_id' => $job_id,
    'website_name' => $website_name,
    'domain' => $domain,
    'user_email' => $user_email,
    'start_time' => date('Y-m-d H:i:s'),
    'work_directory' => $work_dir,
    'subdirectories' => [
        'config' => $work_dir . '/config',
        'scripts' => $work_dir . '/scripts',
        'json' => $work_dir . '/json',
        'images' => $work_dir . '/images',
        'logs' => $work_dir . '/logs'
    ]
];

file_put_contents($work_dir . '/config/job_info.json', json_encode($job_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("工作目錄結構:");
$deployer->log("  主目錄: {$work_dir}");
$deployer->log("  配置目錄: {$work_dir}/config");
$deployer->log("  腳本目錄: {$work_dir}/scripts");
$deployer->log("  JSON目錄: {$work_dir}/json");
$deployer->log("  圖片目錄: {$work_dir}/images");
$deployer->log("  日誌目錄: {$work_dir}/logs");

$deployer->log("參數設定完成");

return $processed_data;