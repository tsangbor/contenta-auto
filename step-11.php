<?php
/**
 * 步驟 11: WordPress 圖片上傳與路徑替換
 * 上傳 AI 生成的圖片到 WordPress 並取得媒體路徑，替換所有 JSON 版型檔案中的圖片路徑
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['domain'];
$mapping_file = $work_dir . '/image-mapping.json';

$deployer->log("開始執行步驟 11: WordPress 圖片上傳與路徑替換");

try {
    // 1. 檢查圖片生成結果
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        throw new Exception("圖片目錄不存在: $images_dir");
    }

    $deployer->log("開始上傳圖片到 WordPress: $domain");

    // 2. 取得 SSH 連線資訊
    $ssh_host = $config->get('deployment.server_host');
    $ssh_user = $config->get('deployment.ssh_user');
    $ssh_key = $config->get('deployment.ssh_key_path');

    // 3. 掃描生成的圖片檔案
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

    // 4. 建立圖片上傳映射表（按頁面分組）
    $image_mapping = [];
    
    foreach ($image_files as $image_file) {
        $local_path = $images_dir . '/' . $image_file;
        $remote_path = "/www/wwwroot/www.$domain/wp-content/uploads/ai-generated/" . $image_file;
        $original_name = str_replace(['.png', '.jpg', '.jpeg', '.gif'], '', $image_file);
        
        // 直接上傳圖片，不檢查是否已存在
        
        $deployer->log("上傳圖片: $image_file");

        // 4.2 在遠端建立目錄
        $mkdir_cmd = "ssh -i '$ssh_key' $ssh_user@$ssh_host 'mkdir -p /www/wwwroot/www.$domain/wp-content/uploads/ai-generated'";
        exec($mkdir_cmd, $output, $return_code);
        
        if ($return_code !== 0) {
            $deployer->log("警告: 建立目錄失敗");
        }

        // 4.3 上傳圖片
        $scp_cmd = "scp -i '$ssh_key' '$local_path' '$ssh_user@$ssh_host:$remote_path'";
        exec($scp_cmd, $output, $return_code);
        
        if ($return_code !== 0) {
            $deployer->log("圖片上傳失敗: $image_file");
            continue;
        }

        // 4.4 使用 WP-CLI 將圖片加入媒體庫
        $wp_cli_cmd = "ssh -i '$ssh_key' $ssh_user@$ssh_host 'cd /www/wwwroot/www.$domain && wp media import wp-content/uploads/ai-generated/$image_file --allow-root'";
        exec($wp_cli_cmd, $wp_output, $wp_return);
        
        if ($wp_return === 0 && !empty($wp_output)) {
            // 解析 WP-CLI 輸出獲取媒體 ID
            $wp_output_text = implode("\n", $wp_output);
            if (preg_match('/Imported file .* as attachment ID (\d+)/', $wp_output_text, $matches)) {
                $attachment_id = $matches[1];
                
                // 取得 WordPress 媒體 URL
                $url_cmd = "ssh -i '$ssh_key' $ssh_user@$ssh_host 'cd /www/wwwroot/www.$domain && wp post get $attachment_id --field=guid --allow-root'";
                exec($url_cmd, $url_output, $url_return);
                
                if ($url_return === 0 && !empty($url_output)) {
                    $wp_url = trim($url_output[0]);
                    
                    // 建立路徑映射（按頁面分組）
                    $page_name = extractPageNameFromImageFile($image_file);
                    $image_key = extractImageKeyFromImageFile($image_file);
                    
                    if (!isset($image_mapping[$page_name])) {
                        $image_mapping[$page_name] = [];
                    }
                    
                    // 儲存完整的圖片資訊（URL 和 attachment_id）
                    $image_mapping[$page_name][$image_key] = [
                        'url' => $wp_url,
                        'attachment_id' => intval($attachment_id)
                    ];
                    
                    $deployer->log("✅ 圖片上傳成功: $image_file -> $wp_url (ID: $attachment_id)");
                } else {
                    $deployer->log("❌ 無法取得圖片 URL: $image_file");
                }
            } else {
                $deployer->log("❌ 無法解析媒體 ID: $image_file");
            }
        } else {
            $deployer->log("❌ WP-CLI 匯入失敗: $image_file");
        }
        
        // 清理變數
        $output = [];
        $wp_output = [];
        $url_output = [];
        $check_output = [];
    }

    // 計算成功上傳的圖片總數
    $total_uploaded = 0;
    foreach ($image_mapping as $page_images) {
        $total_uploaded += count($page_images);
    }
    
    $deployer->log("圖片上傳完成，共 $total_uploaded 個成功");

    // 4.5 設定 wp-content/uploads 目錄權限
    $deployer->log("設定 wp-content/uploads 目錄權限");
    $chmod_cmd = "ssh -i '$ssh_key' $ssh_user@$ssh_host 'chmod -R 755 /www/wwwroot/www.$domain/wp-content/uploads && chown -R www:www /www/wwwroot/www.$domain/wp-content/uploads'";
    exec($chmod_cmd, $chmod_output, $chmod_return);
    
    if ($chmod_return === 0) {
        $deployer->log("✅ 目錄權限設定成功: 755 www:www");
    } else {
        $deployer->log("⚠️  目錄權限設定失敗");
    }

    // 5. 儲存圖片映射結果
    file_put_contents($mapping_file, json_encode($image_mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $deployer->log("圖片映射結果已儲存: $mapping_file");

    // 6. 儲存步驟結果
    $step_result = [
        'step' => '11',
        'title' => 'WordPress 圖片上傳與映射生成',
        'status' => 'success',
        'message' => "成功上傳 $total_uploaded 個圖片並生成映射",
        'image_count' => $total_uploaded,
        'image_mapping' => $image_mapping,
        'executed_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($work_dir . '/step-11-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $deployer->log("步驟 11: WordPress 圖片上傳與映射生成 - 完成");

    return ['status' => 'success', 'result' => $step_result];

} catch (Exception $e) {
    $deployer->log("步驟 11 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}


/**
 * 從圖片檔案名稱提取頁面名稱
 * 例如: home_hero-bg.png -> home
 * 例如: about_about-photo.png -> about
 */
function extractPageNameFromImageFile($image_file) {
    $basename = pathinfo($image_file, PATHINFO_FILENAME);
    $parts = explode('_', $basename);
    return $parts[0] ?? 'unknown';
}

/**
 * 從圖片檔案名稱提取圖片鍵值
 * 例如: home_hero-bg.png -> HERO_BG
 * 例如: about_about-photo.png -> ABOUT_PHOTO
 * 例如: home_hero-bg-6.png -> HERO_BG
 */
function extractImageKeyFromImageFile($image_file) {
    $basename = pathinfo($image_file, PATHINFO_FILENAME);
    $parts = explode('_', $basename, 2);
    if (count($parts) > 1) {
        // 移除數字後綴 (如 -6, -3 等)
        $key_part = preg_replace('/-\d+$/', '', $parts[1]);
        // 轉換為大寫並替換破折號為下劃線
        return strtoupper(str_replace('-', '_', $key_part));
    }
    return 'UNKNOWN';
}