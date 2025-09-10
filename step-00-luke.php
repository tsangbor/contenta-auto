<?php
/**
 * 步驟 00: 初始化與環境檢查 (Luke API 模式)
 * 1. 複製 step-00.php 的基礎邏輯（建立 job_id、目錄等）
 * 2. 移除所有與寶塔 API 相關的連線測試
 * 3. 新增：檢查 Luke API 的可用性
 */

$deployer->log("=== 步驟 00: 初始化與環境檢查 (Luke API 模式) ===");

// === 階段 1: 檢查 Luke API 設定與連線 ===
$deployer->log("🔐 階段 1: 檢查 Luke API 設定與連線");

// 從設定檔讀取 Luke 部署設定
$luke_config = $config->get('luke_deployment');
if (!$luke_config) {
    throw new Exception("Luke API 設定不存在，請檢查 config/deploy-config.json 中的 luke_deployment 區塊");
}

// 驗證必要的設定欄位
$required_luke_fields = ['api_endpoint', 'api_user', 'api_password', 'ssh_host', 'ssh_user', 'ssh_password'];
foreach ($required_luke_fields as $field) {
    if (empty($luke_config[$field])) {
        throw new Exception("Luke API 設定缺少必要欄位: {$field}");
    }
}

$deployer->log("✅ Luke API 設定驗證通過");
$deployer->log("  - API 端點: " . $luke_config['api_endpoint']);
$deployer->log("  - SSH 主機: " . $luke_config['ssh_host']);

// 測試 Luke API 連線可用性
$deployer->log("測試 Luke API 連線...");
try {
    // 發起簡單的 HEAD 請求檢查服務可用性
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $luke_config['api_endpoint']);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        $deployer->log("⚠ Luke API 連線測試警告: " . $curl_error);
    } elseif ($http_code >= 200 && $http_code < 500) {
        $deployer->log("✅ Luke API 端點可達 (HTTP {$http_code})");
    } else {
        $deployer->log("⚠ Luke API 回應異常 (HTTP {$http_code})");
    }
} catch (Exception $e) {
    $deployer->log("⚠ Luke API 連線測試失敗: " . $e->getMessage());
    $deployer->log("將繼續執行，但建議檢查網路連線");
}

$deployer->log("✅ Luke API 連線檢查完成");

// === 階段 2: 處理 Job 資料 ===
$deployer->log("📋 階段 2: 處理 Job 資料");

// 遞迴刪除目錄的輔助函數
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $items = array_diff(scandir($dir), array('.', '..'));
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
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

$deployer->log("基本配置驗證通過");

// 生成資料庫配置檔案（Luke 模式）
$deployer->log("生成資料庫配置檔案...");

// 從現有設定檔獲取預設管理員密碼
$default_admin = $config->get('deployment.admin_password') ?? 'default_password';

// 生成資料庫相關配置（Luke API 會自動處理）
$db_config = [
    'db_name' => preg_replace('/[^a-zA-Z0-9_]/', '_', $domain), // 清理網域名稱作為資料庫名
    'db_user' => preg_replace('/[^a-zA-Z0-9_]/', '_', $domain),
    'db_password' => bin2hex(random_bytes(16)), // 隨機密碼，Luke API 會覆蓋
    'db_host' => 'localhost',
    'table_prefix' => 'wp_'
];

// 儲存資料庫配置
$db_config_file = $work_dir . '/config/db-config.json';
file_put_contents($db_config_file, json_encode($db_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("  資料庫名稱: " . $db_config['db_name']);
$deployer->log("  資料庫用戶: " . $db_config['db_user']);

// 生成網站配置檔案（Luke 模式）
$deployer->log("生成網站配置檔案...");

$site_config = [
    'domain' => $domain,
    'website_name' => $website_name,
    'website_description' => $website_description,
    'user_email' => $user_email,
    'admin_password' => $default_admin,
    'web_root' => str_replace('{domain}', $domain, $luke_config['web_root_template']),
    'ssl_enabled' => true,
    'deployment_mode' => 'luke'
];

// 儲存網站配置
$site_config_file = $work_dir . '/config/site-config.json';
file_put_contents($site_config_file, json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("  網站目錄: " . $site_config['web_root']);
$deployer->log("  網域名稱: " . $domain);

// 工作目錄結構總結
$deployer->log("工作目錄結構:");
$deployer->log("  主目錄: {$work_dir}");
$deployer->log("  配置目錄: {$work_dir}/config");
$deployer->log("  腳本目錄: {$work_dir}/scripts");
$deployer->log("  JSON目錄: {$work_dir}/json");
$deployer->log("  圖片目錄: {$work_dir}/images");
$deployer->log("  日誌目錄: {$work_dir}/logs");

// === 階段 3: 處理 Job 目錄中的檔案 ===
$deployer->log("🗂️  階段 3: 處理 Job 目錄中的檔案");

// 檢查 Job 資料目錄
$job_data_dir = DEPLOY_DATA_PATH . '/' . $job_id;
if (is_dir($job_data_dir)) {
    $deployer->log("處理 Job 檔案目錄: {$job_data_dir}");
    
    $files = scandir($job_data_dir);
    $processed_count = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $file_path = $job_data_dir . '/' . $file;
        
        if (is_file($file_path)) {
            $deployer->log("  發現檔案: {$file}");
            
            // 根據檔案類型進行處理
            $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            
            switch ($file_extension) {
                case 'json':
                    $deployer->log("    JSON 檔案: 複製到工作目錄");
                    copy($file_path, $work_dir . '/json/' . $file);
                    break;
                    
                case 'docx':
                case 'doc':
                    $deployer->log("    Word 文檔: 複製到工作目錄");
                    copy($file_path, $work_dir . '/' . $file);
                    break;
                    
                case 'pdf':
                    $deployer->log("    PDF 文檔: 複製到工作目錄");
                    copy($file_path, $work_dir . '/' . $file);
                    break;
                    
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                    $deployer->log("    圖片檔案: 複製到圖片目錄");
                    copy($file_path, $work_dir . '/images/' . $file);
                    break;
                    
                default:
                    $deployer->log("    其他檔案: 複製到工作目錄");
                    copy($file_path, $work_dir . '/' . $file);
                    break;
            }
            
            $processed_count++;
        }
    }
    
    $deployer->log("檔案處理完成，共處理 {$processed_count} 個檔案");
} else {
    $deployer->log("未找到 Job 檔案目錄: {$job_data_dir}");
}

// 儲存處理後的資料供其他步驟使用
$processed_data = [
    'job_id' => $job_id,
    'confirmed_data' => $confirmed_data,
    'work_dir' => $work_dir,
    'db_config' => $db_config,
    'site_config' => $site_config,
    'luke_config' => $luke_config,
    'deployment_mode' => 'luke',
    'created_at' => date('Y-m-d H:i:s')
];

$processed_data_file = $work_dir . '/config/processed_data.json';
file_put_contents($processed_data_file, json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("參數設定完成");

return [
    'status' => 'success',
    'work_dir' => $work_dir,
    'domain' => $domain,
    'website_name' => $website_name,
    'deployment_mode' => 'luke'
];