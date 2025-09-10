<?php
/**
 * 步驟 11: WordPress 圖片批次上傳與路徑替換 (Luke API 模式)
 * 
 * 使用批次處理大幅提升圖片上傳效率：
 * 1. 使用 rsync 一次性同步整個圖片目錄到 Luke 遠端主機
 * 2. 使用 wp media import 批次匯入所有圖片到媒體庫
 * 3. 批量查詢所有圖片的 URL 和 attachment_id
 * 4. 建立完整的圖片映射表供後續步驟使用
 * 
 * Luke 模式特點：
 * - 使用 Luke 主機的 SSH 認證（SSH key 優先，密碼備用）
 * - 使用 Luke 路徑格式：/var/www/{domain}/html/
 * - 支援 Luke 部署配置
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['confirmed_data']['domain'];

$deployer->log("=== 步驟 11: WordPress 圖片批次上傳與路徑替換 (Luke API 模式) ===");
$deployer->log("開始執行步驟 11: WordPress 圖片上傳與路徑替換");

try {
    // === 階段 1: 檢查圖片生成結果 ===
    $deployer->log("🖼️ 階段 1: 檢查圖片生成結果");
    
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        throw new Exception("圖片目錄不存在: $images_dir");
    }

    $deployer->log("開始上傳圖片到 WordPress: $domain");

    // === 階段 2: 設定 Luke SSH 連線資訊 ===
    $deployer->log("🔗 階段 2: 設定 Luke SSH 連線資訊");
    
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
    $remote_web_root = "/var/www/{$domain}/html";
    $remote_images_dir = "{$remote_web_root}/wp-content/uploads/ai-generated";

    $deployer->log("遠端網站目錄: {$remote_web_root}");
    $deployer->log("遠端圖片目錄: {$remote_images_dir}");

    /**
     * 執行 SSH 指令
     */
    function executeSSH($host, $user, $auth_method, $auth_value, $port, $command, $deployer) {
        // 根據認證方式構建 SSH 命令
        if ($auth_method === 'key') {
            // 使用 SSH key 認證
            $ssh_cmd = "ssh -i " . escapeshellarg($auth_value) .
                       " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" .
                       " -p {$port} {$user}@{$host} " . escapeshellarg($command);
        } else {
            // 使用密碼認證
            $ssh_cmd = "sshpass -p " . escapeshellarg($auth_value) . 
                       " ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null" .
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
        
        return [
            'return_code' => $return_code,
            'output' => implode("\n", $filtered_output),
            'command' => $command
        ];
    }

    /**
     * 使用 rsync 同步目錄（支援雙重認證）
     */
    function rsyncDirectory($local_dir, $remote_dir, $host, $user, $auth_method, $auth_value, $port, $delete_excluded = false, $exclude_patterns = []) {
        // 根據認證方式構建 rsync 命令
        if ($auth_method === 'key') {
            $rsync_cmd = "rsync -avz --progress" .
                         " -e 'ssh -i " . escapeshellarg($auth_value) . " -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p {$port}'" .
                         " " . escapeshellarg($local_dir) . " {$user}@{$host}:" . escapeshellarg($remote_dir);
        } else {
            $rsync_cmd = "sshpass -p " . escapeshellarg($auth_value) . 
                         " rsync -avz --progress" .
                         " -e 'ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -p {$port}'" .
                         " " . escapeshellarg($local_dir) . " {$user}@{$host}:" . escapeshellarg($remote_dir);
        }
        
        if ($delete_excluded) {
            $rsync_cmd .= " --delete";
        }
        
        foreach ($exclude_patterns as $pattern) {
            $rsync_cmd .= " --exclude=" . escapeshellarg($pattern);
        }
        
        $output = [];
        $return_code = 0;
        exec($rsync_cmd . ' 2>&1', $output, $return_code);
        
        return [
            'return_code' => $return_code,
            'output' => implode("\n", $output),
            'command' => $rsync_cmd
        ];
    }

    // 設定認證方法和值
    $auth_method = $use_ssh_key ? 'key' : 'password';
    $auth_value = $use_ssh_key ? $ssh_key_path : $ssh_password;

    // === 階段 3: 掃描生成的圖片檔案 ===
    $deployer->log("📂 階段 3: 掃描生成的圖片檔案");
    
    $image_files = [];
    $files = scandir($images_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        if (preg_match('/\.(png|jpg|jpeg|gif)$/i', $file)) {
            $image_files[] = $file;
        }
    }

    $deployer->log("發現 " . count($image_files) . " 個圖片檔案");

    if (empty($image_files)) {
        $deployer->log("警告: 沒有找到任何圖片檔案");
        return ['status' => 'success', 'message' => '沒有圖片需要上傳'];
    }

    // === 階段 4: 建立遠端圖片目錄 ===
    $deployer->log("📁 階段 4: 建立遠端圖片目錄");
    
    $mkdir_cmd = "mkdir -p {$remote_images_dir}";
    $mkdir_result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, $mkdir_cmd, $deployer);
    
    if ($mkdir_result['return_code'] !== 0) {
        throw new Exception("無法建立遠端圖片目錄: " . $mkdir_result['output']);
    }
    
    $deployer->log("✅ 遠端圖片目錄準備完成");

    // === 階段 5: 批次上傳圖片到遠端 ===
    $deployer->log("📤 階段 5: 批次上傳圖片到遠端");
    $deployer->log("使用 rsync 批次上傳圖片到遠端...");
    
    // 使用 rsync 一次性同步整個圖片目錄
    $rsync_result = rsyncDirectory($images_dir . '/', $remote_images_dir, $ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, false);
    
    if ($rsync_result['return_code'] !== 0) {
        throw new Exception("圖片批次上傳失敗: " . $rsync_result['output']);
    }
    
    $deployer->log("✅ 批次上傳完成，共 " . count($image_files) . " 個圖片檔案");

    // === 階段 6: 批次匯入圖片到 WordPress 媒體庫 ===
    $deployer->log("💾 階段 6: 批次匯入圖片到 WordPress 媒體庫");
    $deployer->log("批次匯入圖片到 WordPress 媒體庫...");
    
    $wp_batch_import_cmd = "cd {$remote_web_root} && wp media import wp-content/uploads/ai-generated/* --porcelain --allow-root";
    $batch_result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, $wp_batch_import_cmd, $deployer);
    
    if ($batch_result['return_code'] !== 0) {
        throw new Exception("批次匯入失敗: " . $batch_result['output']);
    }
    
    // 解析批次匯入結果獲取所有 attachment IDs
    $attachment_ids = [];
    $batch_output = explode("\n", $batch_result['output']);
    foreach ($batch_output as $line) {
        $line = trim($line);
        if (is_numeric($line) && $line > 0) {
            $attachment_ids[] = intval($line);
        }
    }
    
    $deployer->log("✅ 批次匯入完成，獲得 " . count($attachment_ids) . " 個 attachment ID");

    // === 階段 7: 建立圖片映射表 ===
    $deployer->log("🗺️ 階段 7: 建立圖片映射表");
    
    if (empty($attachment_ids)) {
        throw new Exception("沒有成功匯入的圖片");
    }
    
    // 批量查詢所有圖片的 URL 和檔案資訊
    $image_mapping = [];
    
    foreach ($attachment_ids as $attachment_id) {
        // 獲取圖片 URL
        $url_cmd = "cd {$remote_web_root} && wp post meta get {$attachment_id} _wp_attached_file --allow-root";
        $url_result = executeSSH($ssh_host, $ssh_user, $auth_method, $auth_value, $ssh_port, $url_cmd, $deployer);
        
        if ($url_result['return_code'] === 0) {
            $attached_file = trim($url_result['output']);
            $image_url = "https://www.{$domain}/wp-content/uploads/" . $attached_file;
            
            // 獲取原始檔案名
            $filename = basename($attached_file);
            
            $image_mapping[] = [
                'attachment_id' => $attachment_id,
                'filename' => $filename,
                'url' => $image_url,
                'path' => $attached_file
            ];
        }
    }
    
    $deployer->log("✅ 圖片映射表建立完成，共 " . count($image_mapping) . " 筆記錄");

    // === 階段 8: 儲存圖片映射檔案 ===
    $deployer->log("💾 階段 8: 儲存圖片映射檔案");
    
    // 儲存映射表到 JSON 檔案
    $mapping_file = $work_dir . '/json/image-mapping.json';
    $mapping_data = [
        'total_images' => count($image_mapping),
        'upload_date' => date('Y-m-d H:i:s'),
        'domain' => $domain,
        'images' => $image_mapping
    ];
    
    file_put_contents($mapping_file, json_encode($mapping_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("圖片映射檔案已儲存: " . basename($mapping_file));

    // === 完成報告 ===
    $deployer->log("=== WordPress 圖片批次上傳完成 (Luke API 模式) ===");
    $deployer->log("📊 上傳統計:");
    $deployer->log("  • 圖片檔案: " . count($image_files));
    $deployer->log("  • 成功匯入: " . count($image_mapping));
    $deployer->log("  • 映射檔案: image-mapping.json");
    $deployer->log("🌐 網站: https://www.{$domain}");

    return [
        'status' => 'success',
        'total_images' => count($image_files),
        'imported_images' => count($image_mapping),
        'mapping_file' => $mapping_file,
        'image_mapping' => $image_mapping
    ];

} catch (Exception $e) {
    $deployer->log("步驟 11 失敗: " . $e->getMessage());
    throw $e;
}