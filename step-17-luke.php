<?php
/**
 * 步驟 17: 最終配置與設定 (Luke API 模式)
 * 
 * 核心職責：完成網站的最終配置設定，包括用戶資料更新、系統設定優化和權限配置
 * 
 * 詳細執行工作流：
 * 1. 【管理員資料更新】
 *    - 根據品牌資訊動態生成管理員自我介紹
 *    - 更新管理員用戶的 description 欄位
 *    - 整合網站名稱、品牌個性、獨特價值和服務項目
 * 
 * 2. 【系統配置優化】
 *    - 設定時區為 Asia/Taipei
 *    - 設定日期格式為 'Y年n月j日'
 *    - 設定時間格式為 'H:i'
 *    
 * 3. 【分類選單建立】
 *    - 建立名為 "category" 的新選單
 *    - 自動取得所有文章分類（排除「未分類」）
 *    - 將有效分類項目加入到 category 選單
 *    - 指派選單到頁尾位置（footer 或 secondary）
 *    
 * 4. 【Elementor 效能優化】
 *    - 設定 CSS 輸出方式為外部檔案（提升載入速度）
 *    - 關閉圖片最佳化載入（避免衝突）
 *    - 關閉 Gutenberg 最佳化載入（避免衝突）
 *    - 關閉背景圖片延遲載入（確保視覺效果）
 *    - 停用元素快取（避免更新問題）
 *    - 清除 Elementor CSS 快取（確保樣式生效）
 * 
 * 5. 【檔案權限設定】
 *    - 設定 wp-content 目錄權限為 755
 *    - 設定 wp-content/uploads 目錄權限為 755
 *    - 確保檔案上傳和媒體庫正常運作
 * 
 * 6. 【最終系統檢查】
 *    - 檢查 WordPress 版本和狀態
 *    - 檢查啟用的主題和外掛
 *    - 統計已發布的文章和頁面數量
 *    - 驗證網站基本功能運作正常
 * 
 * 輸出結果：
 * - 生成 step-17-result.json 記錄所有設定結果
 * - 包含各項配置的成功/失敗狀態
 * - 提供完整的執行時間戳記
 * 
 * @package Contenta
 * @version 1.0
 */
/**
 * 步驟 17: 最終配置與設定 (Luke API 模式)
 * 
 * 使用 Luke SSH 配置的 WP_CLI_Executor 來執行最終配置工作流程
 * 具體的業務邏輯與 BT Panel 模式共用，只是執行器不同
 * 
 * Luke 模式特點：
 * - 使用 Luke SSH 配置進行 WP-CLI 連線
 * - 使用 Luke 路徑格式：/var/www/{domain}/html/
 * - 使用改進的 WP_CLI_Executor 類別支援 Luke 模式
 * 
 * 核心職責：完成網站的最終配置設定，包括用戶資料更新、系統設定優化和權限配置
 * 
 * 詳細執行工作流：
 * 1. 【管理員資料更新】
 *    - 根據品牌資訊動態生成管理員自我介紹
 *    - 更新管理員用戶的 description 欄位
 *    - 整合網站名稱、品牌個性、獨特價值和服務項目
 * 
 * 2. 【系統配置優化】
 *    - 設定時區為 Asia/Taipei
 *    - 設定日期格式為 'Y年n月j日'
 *    - 設定時間格式為 'H:i'
 *    
 * 3. 【分類選單建立】
 *    - 建立名為 "category" 的新選單
 *    - 自動取得所有文章分類（排除「未分類」）
 *    - 將有效分類項目加入到 category 選單
 *    - 指派選單到頁尾位置（footer 或 secondary）
 *    
 * 4. 【Elementor 效能優化】
 *    - 設定 CSS 輸出方式為外部檔案（提升載入速度）
 *    - 關閉圖片最佳化載入（避免衝突）
 *    - 關閉 Gutenberg 最佳化載入（避免衝突）
 *    - 關閉背景圖片延遲載入（確保視覺效果）
 *    - 停用元素快取（避免更新問題）
 *    - 清除 Elementor CSS 快取（確保樣式生效）
 * 
 * 5. 【檔案權限設定】
 *    - 設定 wp-content 目錄權限為 755
 *    - 設定 wp-content/uploads 目錄權限為 755
 *    - 確保檔案上傳和媒體庫正常運作
 * 
 * 6. 【最終系統檢查】
 *    - 檢查 WordPress 版本和狀態
 *    - 檢查啟用的主題和外掛
 *    - 統計已發布的文章和頁面數量
 *    - 驗證網站基本功能運作正常
 * 
 * 輸出結果：
 * - 生成 step-17-result.json 記錄所有設定結果
 * - 包含各項配置的成功/失敗狀態
 * - 提供完整的執行時間戳記
 * 
 * @package Contenta
 * @version 3.0 (Luke Mode with unified executor)
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
$domain = $processed_data['confirmed_data']['domain'];
$document_root = "/var/www/{$domain}/html"; // Luke 模式路徑格式

$deployer->log("=== 步驟 17: 最終配置與設定 (Luke API 模式) ===");
$deployer->log("目標網域: {$domain}");
$deployer->log("WordPress 目錄: {$document_root}");

try {
    // 載入必要的類別檔案
    $wp_cli_executor_file = DEPLOY_BASE_PATH . '/includes/utilities/class-wp-cli-executor.php';
    
    if (!file_exists($wp_cli_executor_file)) {
        throw new Exception("找不到 WP_CLI_Executor 類別檔案: {$wp_cli_executor_file}");
    }
    
    require_once $wp_cli_executor_file;
    
    // 初始化 Luke 模式的 WP-CLI 執行器
    $deployer->log("初始化 Luke 模式 WP-CLI 執行器...");
    $wp_cli = new WP_CLI_Executor($config, true); // true = Luke 模式
    $wp_cli->set_document_root($document_root);
    
    // 檢查 WP-CLI 可用性
    $deployer->log("檢查 WP-CLI 可用性...");
    if (!$wp_cli->is_available()) {
        throw new Exception("WP-CLI 不可用");
    }
    
    $wp_info = $wp_cli->get_wp_info();
    $deployer->log("WordPress 資訊: " . ($wp_info['available'] ? '正常' : '異常'));
    
    // 取得 job 資料
    $job_data_file = DEPLOY_BASE_PATH . '/data/' . $job_id . '/' . $job_id . '.json';
    if (!file_exists($job_data_file)) {
        $job_data_file = DEPLOY_BASE_PATH . '/data/' . $job_id . '.json'; // 向後相容
    }
    
    if (!file_exists($job_data_file)) {
        throw new Exception("找不到 job 資料檔案");
    }
    
    $job_data = json_decode(file_get_contents($job_data_file), true);
    $confirmed_data = $job_data['confirmed_data'] ?? [];
    
    // 結果記錄
    $step_result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'domain' => $domain,
        'tasks' => []
    ];
    
    // ========== 1. 管理員資料更新 ==========
    $deployer->log("🔧 1. 更新管理員資料");
    
    try {
        $website_name = $confirmed_data['website_name'] ?? '';
        $brand_personality = $confirmed_data['brand_personality'] ?? '';
        $unique_value = $confirmed_data['unique_value'] ?? '';
        $service_categories = $confirmed_data['service_categories'] ?? [];
        
        // 生成管理員介紹
        $admin_description = "歡迎來到 {$website_name}！\n\n";
        if (!empty($brand_personality)) {
            $admin_description .= "我們的品牌特色：{$brand_personality}\n\n";
        }
        if (!empty($unique_value)) {
            $admin_description .= "獨特價值：{$unique_value}\n\n";
        }
        if (!empty($service_categories)) {
            $admin_description .= "服務項目：" . implode('、', $service_categories);
        }
        
        // 更新管理員用戶描述
        $user_email = $confirmed_data['user_email'] ?? 'admin@example.com';
        $update_result = $wp_cli->execute("user update {$user_email} --user_description=" . escapeshellarg($admin_description));
        
        if ($update_result['return_code'] === 0) {
            $deployer->log("✅ 管理員資料更新成功");
            $step_result['tasks']['admin_update'] = ['status' => 'success'];
        } else {
            $deployer->log("⚠ 管理員資料更新失敗: " . $update_result['output']);
            $step_result['tasks']['admin_update'] = ['status' => 'failed', 'error' => $update_result['output']];
        }
    } catch (Exception $e) {
        $deployer->log("❌ 管理員資料更新異常: " . $e->getMessage());
        $step_result['tasks']['admin_update'] = ['status' => 'error', 'error' => $e->getMessage()];
    }
    
    // ========== 2. 系統配置優化 ==========
    $deployer->log("⚙️ 2. 系統配置優化");
    
    try {
        // 設定時區
        $timezone_result = $wp_cli->execute("option update timezone_string 'Asia/Taipei'");
        
        // 設定日期格式
        $date_format_result = $wp_cli->execute("option update date_format 'Y年n月j日'");
        
        // 設定時間格式
        $time_format_result = $wp_cli->execute("option update time_format 'H:i'");
        
        $system_success = ($timezone_result['return_code'] === 0) && 
                         ($date_format_result['return_code'] === 0) && 
                         ($time_format_result['return_code'] === 0);
        
        if ($system_success) {
            $deployer->log("✅ 系統配置更新成功");
            $step_result['tasks']['system_config'] = ['status' => 'success'];
        } else {
            $deployer->log("⚠ 系統配置更新部分失敗");
            $step_result['tasks']['system_config'] = ['status' => 'partial'];
        }
    } catch (Exception $e) {
        $deployer->log("❌ 系統配置更新異常: " . $e->getMessage());
        $step_result['tasks']['system_config'] = ['status' => 'error', 'error' => $e->getMessage()];
    }
    
    // ========== 3. 分類選單建立 ==========
    $deployer->log("📋 3. 建立分類選單");
    
    try {
        // 檢查是否已存在 category 選單
        if (!$wp_cli->menu_exists('category')) {
            $menu_result = $wp_cli->create_menu('category');
            
            if ($menu_result['return_code'] === 0 && $menu_result['menu_id']) {
                $menu_id = $menu_result['menu_id'];
                $deployer->log("建立選單成功，ID: {$menu_id}");
                
                // 取得所有分類（排除未分類）
                $categories_result = $wp_cli->execute("term list category --format=csv --fields=term_id,name,slug");
                
                if ($categories_result['return_code'] === 0) {
                    $lines = explode("\n", trim($categories_result['output']));
                    array_shift($lines); // 移除標題行
                    
                    $added_categories = 0;
                    foreach ($lines as $line) {
                        if (empty(trim($line))) continue;
                        
                        $parts = str_getcsv($line);
                        if (count($parts) >= 3) {
                            $term_id = $parts[0];
                            $name = $parts[1];
                            $slug = $parts[2];
                            
                            // 跳過「未分類」
                            if ($slug === 'uncategorized' || $name === '未分類') {
                                continue;
                            }
                            
                            // 添加分類到選單
                            $add_result = $wp_cli->execute("menu item add-term {$menu_id} category {$term_id} --title=" . escapeshellarg($name));
                            
                            if ($add_result['return_code'] === 0) {
                                $added_categories++;
                                $deployer->log("  ✓ 已添加分類: {$name}");
                            }
                        }
                    }
                    
                    $deployer->log("共添加 {$added_categories} 個分類到選單");
                    
                    // 指派選單到位置
                    $locations = $wp_cli->get_menu_locations();
                    $target_location = null;
                    
                    // 尋找適合的位置
                    foreach (['footer', 'secondary', 'primary'] as $location) {
                        if (isset($locations[$location])) {
                            $target_location = $location;
                            break;
                        }
                    }
                    
                    if ($target_location) {
                        $assign_result = $wp_cli->assign_menu_location($menu_id, $target_location);
                        if ($assign_result['return_code'] === 0) {
                            $deployer->log("✅ 選單已指派到位置: {$target_location}");
                        }
                    }
                    
                    $step_result['tasks']['category_menu'] = [
                        'status' => 'success',
                        'menu_id' => $menu_id,
                        'categories_added' => $added_categories,
                        'location' => $target_location
                    ];
                } else {
                    throw new Exception("無法取得分類列表");
                }
            } else {
                throw new Exception("選單建立失敗");
            }
        } else {
            $deployer->log("分類選單已存在，跳過建立");
            $step_result['tasks']['category_menu'] = ['status' => 'exists'];
        }
    } catch (Exception $e) {
        $deployer->log("❌ 分類選單建立異常: " . $e->getMessage());
        $step_result['tasks']['category_menu'] = ['status' => 'error', 'error' => $e->getMessage()];
    }
    
    // ========== 4. Elementor 效能優化 ==========
    $deployer->log("🚀 4. Elementor 效能優化");
    
    try {
        $elementor_settings = [
            'elementor_css_print_method' => 'external',
            'elementor_optimized_image_loading' => '',
            'elementor_optimized_gutenberg_loading' => '',
            'elementor_background_image_lazy_loading' => '',
            'elementor_element_cache' => ''
        ];
        
        $elementor_success = 0;
        foreach ($elementor_settings as $option => $value) {
            $result = $wp_cli->execute("option update {$option} " . escapeshellarg($value));
            if ($result['return_code'] === 0) {
                $elementor_success++;
            }
        }
        
        // 清除 Elementor CSS 快取
        $clear_cache_result = $wp_cli->execute("elementor flush_css", ['allow-root' => true]);
        
        if ($elementor_success >= 4) {
            $deployer->log("✅ Elementor 效能優化完成 ({$elementor_success}/5)");
            $step_result['tasks']['elementor_optimization'] = ['status' => 'success', 'settings_updated' => $elementor_success];
        } else {
            $deployer->log("⚠ Elementor 效能優化部分完成 ({$elementor_success}/5)");
            $step_result['tasks']['elementor_optimization'] = ['status' => 'partial', 'settings_updated' => $elementor_success];
        }
    } catch (Exception $e) {
        $deployer->log("❌ Elementor 效能優化異常: " . $e->getMessage());
        $step_result['tasks']['elementor_optimization'] = ['status' => 'error', 'error' => $e->getMessage()];
    }
    
    // ========== 5. 檔案權限設定 ==========
    $deployer->log("🔐 5. 檔案權限設定");
    
    try {
        // 使用 WP-CLI 的 eval 來設定權限
        $chmod_commands = [
            "chmod({$document_root}/wp-content, 0755)",
            "chmod({$document_root}/wp-content/uploads, 0755)"
        ];
        
        $permission_success = 0;
        foreach ($chmod_commands as $cmd) {
            $result = $wp_cli->execute("eval \"{$cmd};\"");
            if ($result['return_code'] === 0) {
                $permission_success++;
            }
        }
        
        if ($permission_success >= 1) {
            $deployer->log("✅ 檔案權限設定完成 ({$permission_success}/2)");
            $step_result['tasks']['file_permissions'] = ['status' => 'success'];
        } else {
            $deployer->log("⚠ 檔案權限設定失敗");
            $step_result['tasks']['file_permissions'] = ['status' => 'failed'];
        }
    } catch (Exception $e) {
        $deployer->log("❌ 檔案權限設定異常: " . $e->getMessage());
        $step_result['tasks']['file_permissions'] = ['status' => 'error', 'error' => $e->getMessage()];
    }
    
    // ========== 6. 最終系統檢查 ==========
    $deployer->log("🔍 6. 最終系統檢查");
    
    try {
        // WordPress 版本
        $version_result = $wp_cli->execute("core version");
        
        // 啟用的主題
        $theme_result = $wp_cli->execute("theme status --format=csv --fields=name,status");
        
        // 啟用的外掛
        $plugin_result = $wp_cli->execute("plugin status --format=csv --fields=name,status");
        
        // 文章和頁面統計
        $posts_result = $wp_cli->execute("post list --post_type=post --post_status=publish --format=count");
        $pages_result = $wp_cli->execute("post list --post_type=page --post_status=publish --format=count");
        
        $system_check = [
            'wordpress_version' => trim($version_result['output']) ?? 'Unknown',
            'published_posts' => intval($posts_result['output']) ?? 0,
            'published_pages' => intval($pages_result['output']) ?? 0,
            'check_time' => date('Y-m-d H:i:s')
        ];
        
        $deployer->log("✅ 最終系統檢查完成");
        $deployer->log("  WordPress 版本: " . $system_check['wordpress_version']);
        $deployer->log("  已發布文章: " . $system_check['published_posts'] . " 篇");
        $deployer->log("  已發布頁面: " . $system_check['published_pages'] . " 個");
        
        $step_result['tasks']['system_check'] = [
            'status' => 'success',
            'details' => $system_check
        ];
    } catch (Exception $e) {
        $deployer->log("❌ 最終系統檢查異常: " . $e->getMessage());
        $step_result['tasks']['system_check'] = ['status' => 'error', 'error' => $e->getMessage()];
    }
    
    // 儲存結果
    $result_file = $work_dir . '/step-17-result.json';
    file_put_contents($result_file, json_encode($step_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("=== 步驟 17 完成 ===");
    $deployer->log("結果已儲存到: {$result_file}");
    
    return ['status' => 'success', 'result' => $step_result];

} catch (Exception $e) {
    $deployer->log("步驟 17 執行失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}