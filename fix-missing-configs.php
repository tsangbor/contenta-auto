<?php
/**
 * 修復缺失的配置檔案
 * 為現有的 job-id 生成 database_config.json 和 bt_website.json
 */

// 基本設定
define('DEPLOY_BASE_PATH', __DIR__);

$job_id = '2506290730-3450';

echo "=== 修復缺失的配置檔案 ===\n";
echo "Job ID: {$job_id}\n";
echo "時間: " . date('Y-m-d H:i:s') . "\n\n";

try {
    
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    
    // 檢查工作目錄是否存在
    if (!is_dir($work_dir)) {
        throw new Exception("工作目錄不存在: {$work_dir}");
    }
    
    // 讀取已有的 processed_data.json
    $processed_data_file = $work_dir . '/config/processed_data.json';
    if (!file_exists($processed_data_file)) {
        throw new Exception("processed_data.json 不存在: {$processed_data_file}");
    }
    
    $processed_data = json_decode(file_get_contents($processed_data_file), true);
    $domain = $processed_data['confirmed_data']['domain'] ?? $processed_data['domain'];
    
    if (empty($domain)) {
        throw new Exception("無法從 processed_data.json 中取得 domain");
    }
    
    echo "目標網域: {$domain}\n\n";
    
    // 1. 生成 database_config.json
    echo "1. 生成 database_config.json\n";
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
    
    $database_config_file = $work_dir . '/database_config.json';
    file_put_contents($database_config_file, json_encode($database_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "  ✅ 已生成: {$database_config_file}\n";
    echo "  資料庫名稱: {$db_name}\n";
    echo "  資料庫用戶: {$db_user}\n\n";
    
    // 2. 生成 bt_website.json
    echo "2. 生成 bt_website.json\n";
    $document_root = "/www/wwwroot/www.{$domain}";
    $website_name = $processed_data['website_name'] ?? "腰言豁眾 - 自我能量探索與人類圖諮詢";
    
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
    
    $bt_website_file = $work_dir . '/bt_website.json';
    file_put_contents($bt_website_file, json_encode($bt_website_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "  ✅ 已生成: {$bt_website_file}\n";
    echo "  網站目錄: {$document_root}\n";
    echo "  網站名稱: {$website_name}\n\n";
    
    // 3. 驗證檔案
    echo "3. 驗證生成的檔案\n";
    $files_to_check = [
        'database_config.json' => $database_config_file,
        'bt_website.json' => $bt_website_file
    ];
    
    foreach ($files_to_check as $name => $file_path) {
        if (file_exists($file_path)) {
            $size = filesize($file_path);
            echo "  ✅ {$name}: 存在 ({$size} bytes)\n";
            
            // 驗證 JSON 格式
            $json_data = json_decode(file_get_contents($file_path), true);
            if ($json_data === null) {
                echo "  ❌ {$name}: JSON 格式無效\n";
            } else {
                echo "  ✅ {$name}: JSON 格式正確\n";
            }
        } else {
            echo "  ❌ {$name}: 不存在\n";
        }
    }
    
    echo "\n=== 修復完成 ===\n";
    echo "現在可以重新執行 step-06.php，應該不會再出現檔案缺失的錯誤。\n";
    
} catch (Exception $e) {
    echo "修復失敗: " . $e->getMessage() . "\n";
    exit(1);
}