<?php
/**
 * 步驟 17: 最終配置與設定
 * 
 * 核心職責：完成網站的最終配置設定，包括用戶資料更新和其他必要的匯入動作
 * 
 * 執行工作流：
 * 1. 更新管理員用戶的自我介紹欄位
 * 2. 執行其他必要的配置匯入
 * 3. 最終檢查和優化
 * 
 * @package Contenta
 * @version 1.0
 */

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

$deployer->log("=== 步驟 17: 最終配置與設定 ===");
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
    
    // 初始化 WP-CLI 執行器
    $wp_cli = new WP_CLI_Executor($config);
    $wp_cli->set_document_root($document_root);
    
    // 檢查 WP-CLI 可用性
    $deployer->log("檢查 WP-CLI 可用性...");
    if (!$wp_cli->is_available()) {
        throw new Exception("WP-CLI 不可用");
    }
    
    $wp_info = $wp_cli->get_wp_info();
    $deployer->log("WordPress 資訊: " . ($wp_info['available'] ? '正常' : '異常'));
    
    // 1. 更新管理員用戶的自我介紹欄位
    $deployer->log("1. 更新管理員用戶自我介紹欄位...");
    $admin_bio_result = updateAdminBiography($wp_cli, $processed_data, $deployer);
    
    if ($admin_bio_result['success']) {
        $deployer->log("管理員自我介紹更新成功");
    } else {
        $deployer->log("管理員自我介紹更新失敗: " . $admin_bio_result['error']);
    }
    
    // 2. 其他配置設定（預留擴展空間）
    $deployer->log("2. 執行其他配置設定...");
    $other_configs_result = executeOtherConfigurations($wp_cli, $processed_data, $deployer);
    
    // 3. 切換主題顏色樣式
    $deployer->log("3. 切換主題顏色樣式...");
    $theme_color_result = switchThemeColors($wp_cli, $work_dir, $deployer);
    
    // 4. 設定wp-content目錄權限
    $deployer->log("4. 設定wp-content目錄權限...");
    $permissions_result = setWpContentPermissions($document_root, $deployer);
    
    // 5. 最終檢查
    $deployer->log("5. 執行最終檢查...");
    $final_check_result = performFinalCheck($wp_cli, $processed_data, $deployer);
    
    // 儲存步驟結果
    $step_result = [
        'step' => '17',
        'title' => '最終配置與設定',
        'status' => 'success',
        'message' => '最終配置完成',
        'domain' => $domain,
        'admin_bio_updated' => $admin_bio_result['success'],
        'other_configs' => $other_configs_result,
        'theme_color_switched' => $theme_color_result,
        'permissions_set' => $permissions_result,
        'final_check' => $final_check_result,
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/step-17-result.json', json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("步驟 17: 最終配置與設定 - 完成");
    
    return ['status' => 'success', 'result' => $step_result];
    
} catch (Exception $e) {
    $deployer->log("步驟 17 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}

/**
 * 更新管理員用戶的自我介紹欄位
 */
function updateAdminBiography($wp_cli, $processed_data, $deployer)
{
    try {
        $confirmed_data = $processed_data['confirmed_data'] ?? [];
        
        // 取得網站相關資訊來構建自我介紹
        $website_name = $confirmed_data['website_name'] ?? '';
        $website_description = $confirmed_data['website_description'] ?? '';
        $brand_personality = $confirmed_data['brand_personality'] ?? '';
        $unique_value = $confirmed_data['unique_value'] ?? '';
        $service_categories = $confirmed_data['service_categories'] ?? [];
        
        // 構建自我介紹內容
        $bio_content = buildAdminBiography(
            $website_name,
            $website_description,
            $brand_personality,
            $unique_value,
            $service_categories
        );
        
        $deployer->log("準備更新用戶自我介紹: " . substr($bio_content, 0, 100) . "...");
        
        // 取得用戶email（從job配置）
        $user_email = $confirmed_data['user_email'] ?? '';
        if (empty($user_email)) {
            throw new Exception("未設定用戶email");
        }
        
        $deployer->log("查找用戶: {$user_email}");
        
        // 根據email查找用戶ID
        $user_search_result = $wp_cli->execute("user get {$user_email} --field=ID");
        
        if ($user_search_result['return_code'] !== 0) {
            throw new Exception("找不到用戶 {$user_email}: " . $user_search_result['output']);
        }
        
        $user_id = trim($user_search_result['output']);
        $deployer->log("找到用戶ID: {$user_id}");
        
        // 更新用戶的自我介紹欄位 - 使用 user update 而非 user meta update
        $escaped_bio = escapeshellarg($bio_content);
        $update_result = $wp_cli->execute("user update {$user_id} --description={$escaped_bio}");
        
        if ($update_result['return_code'] !== 0) {
            throw new Exception("更新用戶自我介紹失敗: " . $update_result['output']);
        }
        
        $deployer->log("用戶自我介紹更新成功 (用戶ID: {$user_id})");
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'bio_length' => strlen($bio_content)
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * 構建管理員自我介紹內容
 */
function buildAdminBiography($website_name, $website_description, $brand_personality, $unique_value, $service_categories)
{
    $bio_parts = [];
    
    // 基本介紹
    if (!empty($website_name)) {
        $bio_parts[] = "歡迎來到{$website_name}！";
    }
    
    // 網站描述
    if (!empty($website_description)) {
        $bio_parts[] = $website_description;
    }
    
    // 獨特價值
    if (!empty($unique_value)) {
        $bio_parts[] = "我們的特色：{$unique_value}";
    }
    
    // 服務類別
    if (!empty($service_categories) && is_array($service_categories)) {
        $services_text = implode('、', $service_categories);
        $bio_parts[] = "主要服務包括：{$services_text}。";
    }
    
    // 品牌個性
    if (!empty($brand_personality)) {
        $bio_parts[] = "我們以{$brand_personality}的方式為您服務。";
    }
    
    // 結尾
    $bio_parts[] = "期待與您一同踏上這段美好的旅程！";
    
    return implode("\n\n", array_filter($bio_parts));
}

/**
 * 執行其他配置設定
 */
function executeOtherConfigurations($wp_cli, $processed_data, $deployer)
{
    $results = [];
    
    try {
        // 這裡可以添加其他配置設定
        // 例如：主題設定、外掛配置等
        
        $deployer->log("執行其他配置設定...");
        
        // 設定時區
        $timezone_result = $wp_cli->execute("option update timezone_string 'Asia/Taipei'");
        $results['timezone'] = $timezone_result['return_code'] === 0;
        
        // 設定日期格式
        $date_format_result = $wp_cli->execute("option update date_format 'Y年n月j日'");
        $results['date_format'] = $date_format_result['return_code'] === 0;
        
        // 設定時間格式
        $time_format_result = $wp_cli->execute("option update time_format 'H:i'");
        $results['time_format'] = $time_format_result['return_code'] === 0;
        
        // Elementor 相關設定
        $deployer->log("設定 Elementor 相關選項...");
        
        // 設定 CSS 輸出方式為外部檔案
        $elementor_css_result = $wp_cli->execute("option update elementor_css_print_method 'external'");
        $results['elementor_css_print_method'] = $elementor_css_result['return_code'] === 0;
        if ($results['elementor_css_print_method']) {
            $deployer->log("✅ Elementor CSS 輸出方式設定為外部檔案");
        } else {
            $deployer->log("❌ Elementor CSS 輸出方式設定失敗");
        }
        
        // 關閉圖片最佳化載入
        $elementor_image_loading_result = $wp_cli->execute("option update elementor_optimized_image_loading 0");
        $results['elementor_optimized_image_loading'] = $elementor_image_loading_result['return_code'] === 0;
        if ($results['elementor_optimized_image_loading']) {
            $deployer->log("✅ Elementor 圖片最佳化載入已關閉");
        } else {
            $deployer->log("❌ Elementor 圖片最佳化載入設定失敗");
        }
        
        // 關閉 Gutenberg 最佳化載入
        $elementor_gutenberg_result = $wp_cli->execute("option update elementor_optimized_gutenberg_loading 0");
        $results['elementor_optimized_gutenberg_loading'] = $elementor_gutenberg_result['return_code'] === 0;
        if ($results['elementor_optimized_gutenberg_loading']) {
            $deployer->log("✅ Elementor Gutenberg 最佳化載入已關閉");
        } else {
            $deployer->log("❌ Elementor Gutenberg 最佳化載入設定失敗");
        }
        
        // 關閉背景圖片延遲載入
        $elementor_lazy_bg_result = $wp_cli->execute("option update elementor_lazy_load_background_images 0");
        $results['elementor_lazy_load_background_images'] = $elementor_lazy_bg_result['return_code'] === 0;
        if ($results['elementor_lazy_load_background_images']) {
            $deployer->log("✅ Elementor 背景圖片延遲載入已關閉");
        } else {
            $deployer->log("❌ Elementor 背景圖片延遲載入設定失敗");
        }
        
        // 關閉元素快取
        $elementor_cache_result = $wp_cli->execute("option update elementor_element_cache_ttl 'disable'");
        $results['elementor_element_cache_ttl'] = $elementor_cache_result['return_code'] === 0;
        if ($results['elementor_element_cache_ttl']) {
            $deployer->log("✅ Elementor 元素快取已停用");
        } else {
            $deployer->log("❌ Elementor 元素快取設定失敗");
        }
        
        // 清除 Elementor CSS 快取
        $elementor_flush_result = $wp_cli->execute("elementor flush-css");
        $results['elementor_flush_css'] = $elementor_flush_result['return_code'] === 0;
        if ($results['elementor_flush_css']) {
            $deployer->log("✅ Elementor CSS 快取已清除");
        } else {
            $deployer->log("❌ Elementor CSS 快取清除失敗");
        }
        
        $deployer->log("其他配置設定完成");
        
    } catch (Exception $e) {
        $deployer->log("其他配置設定部分失敗: " . $e->getMessage());
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

/**
 * 設定wp-content目錄權限
 */
function setWpContentPermissions($document_root, $deployer)
{
    $results = [];
    
    try {
        $wp_content_path = $document_root . '/wp-content';
        
        $deployer->log("設定wp-content目錄權限: {$wp_content_path}");
        
        // 檢查wp-content目錄是否存在，如果不存在則透過 SSH 檢查
        $deployer->log("檢查目錄: {$wp_content_path}");
        
        // 透過 SSH 檢查遠端目錄是否存在
        $check_cmd = "test -d {$wp_content_path} && echo 'exists' || echo 'not_exists'";
        $check_output = [];
        $check_return = 0;
        exec("ssh root@jp3.contenta.tw '{$check_cmd}' 2>&1", $check_output, $check_return);
        
        $remote_check_result = trim(implode("\n", $check_output));
        $deployer->log("遠端目錄檢查結果: {$remote_check_result}");
        
        if ($remote_check_result !== 'exists') {
            throw new Exception("wp-content目錄不存在於遠端伺服器: {$wp_content_path}");
        }
        
        // 使用chown和chmod指令設定權限
        $commands = [
            "chown -R www:www {$wp_content_path}",
            "chmod -R 755 {$wp_content_path}"
        ];
        
        foreach ($commands as $command) {
            $deployer->log("執行指令: {$command}");
            
            // 透過 SSH 執行權限設定命令
            $ssh_command = "ssh root@jp3.contenta.tw '{$command}' 2>&1";
            $output = [];
            $return_var = 0;
            exec($ssh_command, $output, $return_var);
            
            if ($return_var === 0) {
                $deployer->log("指令執行成功");
                $results[$command] = true;
            } else {
                $error_message = implode("\n", $output);
                $deployer->log("指令執行失敗: {$error_message}");
                $results[$command] = false;
                $results['error'] = $error_message;
            }
        }
        
        // 驗證權限設定結果
        $stat_cmd = "stat -c '%a' {$wp_content_path}";
        $stat_output = [];
        $stat_return = 0;
        exec("ssh root@jp3.contenta.tw '{$stat_cmd}' 2>&1", $stat_output, $stat_return);
        
        $current_perms = trim(implode("\n", $stat_output));
        $deployer->log("wp-content目錄當前權限: {$current_perms}");
        
        $results['success'] = true;
        $results['final_permissions'] = $current_perms;
        
    } catch (Exception $e) {
        $deployer->log("設定wp-content權限失敗: " . $e->getMessage());
        $results['success'] = false;
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

/**
 * 切換主題顏色樣式
 */
function switchThemeColors($wp_cli, $work_dir, $deployer)
{
    $results = [];
    
    try {
        // 讀取網站配置檔案中的顏色方案
        $site_config_file = $work_dir . '/json/site-config.json';
        
        if (!file_exists($site_config_file)) {
            throw new Exception("找不到網站配置檔案: {$site_config_file}");
        }
        
        $site_config = json_decode(file_get_contents($site_config_file), true);
        $color_scheme = $site_config['website_info']['color_scheme'] ?? 'expert-theme-1';
        
        $deployer->log("目標顏色方案: {$color_scheme}");
        
        // 執行主題顏色切換命令
        $switch_result = $wp_cli->execute("theme colors switch {$color_scheme}");
        
        if ($switch_result['return_code'] === 0) {
            $deployer->log("主題顏色切換成功: {$color_scheme}");
            $results['success'] = true;
            $results['color_scheme'] = $color_scheme;
            $results['output'] = $switch_result['output'];
        } else {
            $deployer->log("主題顏色切換失敗: " . $switch_result['output']);
            $results['success'] = false;
            $results['error'] = $switch_result['output'];
            $results['color_scheme'] = $color_scheme;
        }
        
    } catch (Exception $e) {
        $deployer->log("主題顏色切換失敗: " . $e->getMessage());
        $results['success'] = false;
        $results['error'] = $e->getMessage();
    }
    
    return $results;
}

/**
 * 執行最終檢查
 */
function performFinalCheck($wp_cli, $processed_data, $deployer)
{
    $check_results = [];
    
    try {
        $deployer->log("執行最終系統檢查...");
        
        // 檢查WordPress版本
        $wp_version_result = $wp_cli->execute("core version");
        $check_results['wp_version'] = $wp_version_result['return_code'] === 0 ? trim($wp_version_result['output']) : 'unknown';
        
        // 檢查啟用的主題
        $theme_result = $wp_cli->execute("theme status --format=json");
        if ($theme_result['return_code'] === 0) {
            $themes = json_decode($theme_result['output'], true);
            foreach ($themes as $theme_name => $theme_info) {
                if (isset($theme_info['status']) && $theme_info['status'] === 'active') {
                    $check_results['active_theme'] = $theme_name;
                    break;
                }
            }
        }
        
        // 檢查啟用的外掛數量
        $plugins_result = $wp_cli->execute("plugin list --status=active --format=count");
        $check_results['active_plugins_count'] = $plugins_result['return_code'] === 0 ? intval(trim($plugins_result['output'])) : 0;
        
        // 檢查文章數量
        $posts_result = $wp_cli->execute("post list --post_status=publish --format=count");
        $check_results['published_posts'] = $posts_result['return_code'] === 0 ? intval(trim($posts_result['output'])) : 0;
        
        // 檢查頁面數量
        $pages_result = $wp_cli->execute("post list --post_type=page --post_status=publish --format=count");
        $check_results['published_pages'] = $pages_result['return_code'] === 0 ? intval(trim($pages_result['output'])) : 0;
        
        $deployer->log("最終檢查完成:");
        $deployer->log("- WordPress版本: " . $check_results['wp_version']);
        $deployer->log("- 啟用主題: " . ($check_results['active_theme'] ?? 'unknown'));
        $deployer->log("- 啟用外掛: " . $check_results['active_plugins_count'] . "個");
        $deployer->log("- 已發布文章: " . $check_results['published_posts'] . "篇");
        $deployer->log("- 已發布頁面: " . $check_results['published_pages'] . "個");
        
    } catch (Exception $e) {
        $deployer->log("最終檢查部分失敗: " . $e->getMessage());
        $check_results['error'] = $e->getMessage();
    }
    
    return $check_results;
}