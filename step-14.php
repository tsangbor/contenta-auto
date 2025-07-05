<?php
/**
 * 步驟 14: 生成頁面
 * 根據組合的頁面-ai.json檔案，匯入到主機的頁面
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

/**
 * 執行 SSH 指令（含重試機制）
 */
function executeSSH($host, $user, $port, $key_path, $command, $max_retries = 3)
{
    $ssh_cmd = "ssh";
    
    if (!empty($key_path) && file_exists($key_path)) {
        $ssh_cmd .= " -i " . escapeshellarg($key_path);
    }
    
    if (!empty($port)) {
        $ssh_cmd .= " -p {$port}";
    }
    
    // 增加連線選項以提高穩定性
    $ssh_cmd .= " -o StrictHostKeyChecking=no";
    $ssh_cmd .= " -o ConnectTimeout=30";
    $ssh_cmd .= " -o ServerAliveInterval=60";
    $ssh_cmd .= " -o ServerAliveCountMax=3";
    $ssh_cmd .= " {$user}@{$host}";
    $ssh_cmd .= " " . escapeshellarg($command);
    
    $last_error = '';
    
    for ($retry = 0; $retry < $max_retries; $retry++) {
        if ($retry > 0) {
            error_log("SSH 重試第 {$retry} 次: {$command}");
            sleep(2 + $retry); // 漸進式延遲
        }
        
        $output = [];
        $return_code = 0;
        
        exec($ssh_cmd . ' 2>&1', $output, $return_code);
        $output_str = implode("\n", $output);
        
        // 檢查是否為連線錯誤
        if ($return_code === 0 || !preg_match('/connection|closed|timeout/i', $output_str)) {
            return [
                'return_code' => $return_code,
                'output' => $output_str,
                'command' => $command
            ];
        }
        
        $last_error = $output_str;
    }
    
    // 所有重試都失敗
    return [
        'return_code' => 255,
        'output' => "SSH 連線失敗 (已重試 {$max_retries} 次): {$last_error}",
        'command' => $command
    ];
}

class PageGenerator {
    private $deployer;
    private $job_id;
    private $layout_dir;
    private $config;
    private $wp_cli;
    
    public function __construct($deployer, $job_id, $config) {
        $this->deployer = $deployer;
        $this->job_id = $job_id;
        $this->layout_dir = DEPLOY_BASE_PATH . "/temp/{$job_id}/layout";
        $this->config = $config;
        
        // 載入 WP_CLI_Executor
        require_once DEPLOY_BASE_PATH . '/includes/utilities/class-wp-cli-executor.php';
        $this->wp_cli = new WP_CLI_Executor($config);
        
        // 設定文檔根目錄
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
        $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
        $domain = $processed_data['confirmed_data']['domain'];
        $document_root = "/www/wwwroot/www.{$domain}";
        $this->wp_cli->set_document_root($document_root);
    }
    
    public function generatePages() {
        
        // 檢查 site-config.json 是否存在
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $this->job_id;
        $site_config_file = $work_dir . '/json/site-config.json';
        if (!file_exists($site_config_file)) {
            $this->deployer->log("錯誤: site-config.json 檔案不存在: {$site_config_file}");
            return false;
        }
        
        // 載入網站配置
        $site_config = json_decode(file_get_contents($site_config_file), true);
        if (empty($site_config) || !isset($site_config['page_list'])) {
            $this->deployer->log("錯誤: site-config.json 為空或缺少 page_list");
            return false;
        }
        
        // 取得頁面清單
        $page_list = $site_config['page_list'];
        
        $this->deployer->log("找到 " . count($page_list) . " 個頁面需要生成");
        
        $created_count = 0;
        
        $index = 0;
        foreach ($page_list as $page_slug => $page_title) {
            // 在處理每個頁面間加入延遲，避免連線過於頻繁
            if ($index > 0) {
                $this->deployer->log("等待 2 秒後處理下一個頁面...");
                sleep(2);
            }
            
            $page_info = [
                'slug' => $page_slug,
                'title' => $page_title
            ];
            
            if ($this->createSinglePage($page_info)) {
                $created_count++;
            }
            $index++;
        }
        
        $this->deployer->log("成功生成 {$created_count} 個頁面");
        
        // 設定首頁
        if ($created_count > 0) {
            $this->setHomePage();
        }
        
        // 建立主選單並新增頁面
        if ($created_count > 0) {
            $this->createMainMenu($page_list);
        }
        
        return $created_count > 0;
    }
    
    private function createSinglePage($page_info) {
        $page_title = $page_info['title'];
        $page_slug = $page_info['slug'];
        
        $this->deployer->log("處理頁面: {$page_title} (slug: {$page_slug})");
        
        // 查找對應的 -ai.json 檔案
        $page_json_file = $this->layout_dir . "/{$page_slug}-ai.json";
        if (!file_exists($page_json_file)) {
            $this->deployer->log("錯誤: 找不到頁面模板檔案: {$page_json_file}");
            return false;
        }
        
        // 讀取並驗證 JSON 檔案
        $page_json = file_get_contents($page_json_file);
        if ($page_json === false) {
            $this->deployer->log("錯誤: 無法讀取頁面模板檔案: {$page_json_file}");
            return false;
        }
        
        // 驗證 JSON 格式
        $page_data = json_decode($page_json, true);
        if ($page_data === null) {
            $this->deployer->log("錯誤: 無效的 JSON 格式: {$page_json_file}");
            return false;
        }
        
        // 透過 WP-CLI 建立頁面
        return $this->createPageViaWPCLI($page_title, $page_slug, $page_json);
    }
    
    private function createPageViaWPCLI($page_title, $page_slug, $page_json) {
        // 取得部署設定
        $server_host = $this->config->get('deployment.server_host');
        $ssh_user = $this->config->get('deployment.ssh_user');
        $ssh_port = $this->config->get('deployment.ssh_port') ?: 22;
        $ssh_key_path = $this->config->get('deployment.ssh_key_path');
        
        // 取得網站根目錄和使用者資訊
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $this->job_id;
        $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
        $domain = $processed_data['confirmed_data']['domain'];
        $user_email = $processed_data['confirmed_data']['user_email'] ?? 'admin@' . $domain;
        $document_root = "/www/wwwroot/www.{$domain}";
        
        // 準備遠端檔案路徑
        $remote_temp_file = "{$document_root}/wp-content/uploads/temp/{$page_slug}-ai.json";
        
        // 先在本地建立臨時檔案（確保 JSON 是單行格式）
        $local_temp_file = sys_get_temp_dir() . "/elementor_page_{$page_slug}_" . uniqid() . ".json";
        
        // 確保 JSON 是單行格式（移除多餘的空白和換行）
        $json_array = json_decode($page_json, true);
        if ($json_array === null) {
            $this->deployer->log("錯誤: 無效的 JSON 格式");
            return false;
        }
        
        // 提取 Elementor 需要的 content 部分（WP-CLI 只需要 content 陣列，不需要完整頁面結構）
        $elementor_data = isset($json_array['content']) ? $json_array['content'] : $json_array;
        
        // 重新編碼為單行 JSON（不使用 JSON_PRETTY_PRINT）
        $minified_json = json_encode($elementor_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($local_temp_file, $minified_json) === false) {
            $this->deployer->log("錯誤: 無法建立本地臨時檔案");
            return false;
        }
        
        // 確保遠端 temp 目錄存在
        $create_dir_cmd = "mkdir -p {$document_root}/wp-content/uploads/temp";
        $dir_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $create_dir_cmd);
        
        // 使用 scp 上傳檔案到遠端
        $this->deployer->log("上傳 JSON 檔案到遠端: {$remote_temp_file}");
        $scp_cmd = "scp";
        if (!empty($ssh_key_path) && file_exists($ssh_key_path)) {
            $scp_cmd .= " -i " . escapeshellarg($ssh_key_path);
        }
        if (!empty($ssh_port)) {
            $scp_cmd .= " -P {$ssh_port}";
        }
        $scp_cmd .= " -o StrictHostKeyChecking=no";
        $scp_cmd .= " " . escapeshellarg($local_temp_file);
        $scp_cmd .= " {$ssh_user}@{$server_host}:{$remote_temp_file}";
        
        $output = [];
        $return_code = 0;
        exec($scp_cmd . ' 2>&1', $output, $return_code);
        
        // 清理本地臨時檔案
        unlink($local_temp_file);
        
        if ($return_code !== 0) {
            $this->deployer->log("錯誤: 無法上傳檔案到遠端: " . implode("\n", $output));
            return false;
        }
        
        // 第一步：建立 WordPress 頁面
        $this->deployer->log("建立 WordPress 頁面: {$page_title}");
        $create_page_cmd = "cd {$document_root} && wp post create --post_type=page --post_title=" . escapeshellarg($page_title) . " --post_name=" . escapeshellarg($page_slug) . " --post_status=publish --user=" . escapeshellarg($user_email) . " --porcelain --allow-root";
        
        $create_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $create_page_cmd);
        
        if ($create_result['return_code'] !== 0) {
            $this->deployer->log("錯誤: 無法建立頁面: " . $create_result['output']);
            return false;
        }
        
        // 取得頁面 ID
        $page_id = trim($create_result['output']);
        if (!is_numeric($page_id)) {
            $this->deployer->log("錯誤: 無法取得頁面 ID: " . $create_result['output']);
            return false;
        }
        
        $this->deployer->log("成功建立頁面，ID: {$page_id}");
        
        // 第二步：匯入 Elementor 資料
        $this->deployer->log("匯入 Elementor 資料到頁面: {$page_id}");
        $import_cmd = "cd {$document_root} && cat {$remote_temp_file} | wp post meta update {$page_id} _elementor_data --format=json --allow-root";
        
        $import_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $import_cmd);
        
        if ($import_result['return_code'] !== 0) {
            $this->deployer->log("錯誤: 無法匯入 Elementor 資料: " . $import_result['output']);
            return false;
        }
        
        // 第三步：設定 Elementor 相關 meta
        $meta_updates = [
            "_elementor_edit_mode" => "builder",
            "_elementor_template_type" => "wp-page",
            "_elementor_version" => "3.0.0"
        ];
        
        foreach ($meta_updates as $meta_key => $meta_value) {
            $update_meta_cmd = "cd {$document_root} && wp post meta update {$page_id} {$meta_key} " . escapeshellarg($meta_value) . " --allow-root";
            $meta_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $update_meta_cmd);
            
            if ($meta_result['return_code'] !== 0) {
                $this->deployer->log("警告: 無法更新 meta {$meta_key}: " . $meta_result['output']);
            }
        }
        
        // 第四步：驗證匯入結果
        $verify_cmd = "cd {$document_root} && wp post get {$page_id} --field=post_title --allow-root";
        $verify_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $verify_cmd);
        
        if ($verify_result['return_code'] === 0 && !empty($verify_result['output'])) {
            $this->deployer->log("✅ 頁面驗證成功: " . trim($verify_result['output']));
        } else {
            $this->deployer->log("⚠️ 頁面驗證失敗");
        }
        
        // 清理遠端臨時檔案
        $cleanup_cmd = "rm -f {$remote_temp_file}";
        executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cleanup_cmd);
        
        $this->deployer->log("成功建立並匯入頁面: {$page_title} (ID: {$page_id})");
        return true;
    }
    
    /**
     * 設定網站首頁為 home 頁面
     */
    private function setHomePage() {
        $this->deployer->log("開始設定網站首頁");
        
        // 取得部署設定
        $server_host = $this->config->get('deployment.server_host');
        $ssh_user = $this->config->get('deployment.ssh_user');
        $ssh_port = $this->config->get('deployment.ssh_port') ?: 22;
        $ssh_key_path = $this->config->get('deployment.ssh_key_path');
        
        // 取得網站根目錄
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $this->job_id;
        $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
        $domain = $processed_data['confirmed_data']['domain'];
        $document_root = "/www/wwwroot/www.{$domain}";
        
        // 第一步：查詢 home 頁面的 ID
        $this->deployer->log("查詢 home 頁面 ID");
        $find_home_cmd = "cd {$document_root} && wp post list --post_type=page --name=home --format=csv --fields=ID --allow-root";
        $find_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $find_home_cmd);
        
        if ($find_result['return_code'] !== 0) {
            $this->deployer->log("錯誤: 無法查詢 home 頁面: " . $find_result['output']);
            return false;
        }
        
        // 解析查詢結果
        $lines = explode("\n", trim($find_result['output']));
        if (count($lines) < 2) {
            $this->deployer->log("錯誤: 找不到 home 頁面");
            return false;
        }
        
        $home_page_id = trim($lines[1]); // 跳過 CSV 標題行
        if (!is_numeric($home_page_id)) {
            $this->deployer->log("錯誤: home 頁面 ID 無效: {$home_page_id}");
            return false;
        }
        
        $this->deployer->log("找到 home 頁面 ID: {$home_page_id}");
        
        // 第二步：設定 WordPress 閱讀設定
        $this->deployer->log("設定 WordPress 閱讀設定");
        
        // 設定 show_on_front 為 page（使用靜態頁面作為首頁）
        $set_show_on_front_cmd = "cd {$document_root} && wp option update show_on_front page --allow-root";
        $show_on_front_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $set_show_on_front_cmd);
        
        if ($show_on_front_result['return_code'] !== 0) {
            $this->deployer->log("警告: 無法設定 show_on_front: " . $show_on_front_result['output']);
        } else {
            $this->deployer->log("✅ 成功設定首頁顯示方式為靜態頁面");
        }
        
        // 設定 page_on_front 為 home 頁面 ID
        $set_page_on_front_cmd = "cd {$document_root} && wp option update page_on_front {$home_page_id} --allow-root";
        $page_on_front_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $set_page_on_front_cmd);
        
        if ($page_on_front_result['return_code'] !== 0) {
            $this->deployer->log("錯誤: 無法設定首頁: " . $page_on_front_result['output']);
            return false;
        } else {
            $this->deployer->log("✅ 成功設定 home 頁面為網站首頁");
        }
        
        // 第三步：檢查是否有 blog 頁面，如果有則設為文章頁面
        $find_blog_cmd = "cd {$document_root} && wp post list --post_type=page --name=blog --format=csv --fields=ID --allow-root";
        $blog_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $find_blog_cmd);
        
        if ($blog_result['return_code'] === 0) {
            $blog_lines = explode("\n", trim($blog_result['output']));
            if (count($blog_lines) >= 2) {
                $blog_page_id = trim($blog_lines[1]);
                if (is_numeric($blog_page_id)) {
                    $this->deployer->log("找到 blog 頁面 ID: {$blog_page_id}，設定為文章頁面");
                    
                    $set_page_for_posts_cmd = "cd {$document_root} && wp option update page_for_posts {$blog_page_id} --allow-root";
                    $page_for_posts_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $set_page_for_posts_cmd);
                    
                    if ($page_for_posts_result['return_code'] === 0) {
                        $this->deployer->log("✅ 成功設定 blog 頁面為文章頁面");
                    } else {
                        $this->deployer->log("警告: 無法設定文章頁面: " . $page_for_posts_result['output']);
                    }
                }
            }
        }
        
        // 第四步：驗證設定結果
        $verify_cmd = "cd {$document_root} && wp option get show_on_front --allow-root && echo '---' && wp option get page_on_front --allow-root";
        $verify_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $verify_cmd);
        
        if ($verify_result['return_code'] === 0) {
            $this->deployer->log("✅ 首頁設定驗證結果: " . str_replace("\n", " | ", trim($verify_result['output'])));
        }
        
        $this->deployer->log("網站首頁設定完成");
        return true;
    }
    
    /**
     * 建立主選單並新增頁面
     * 
     * @param array $page_list 頁面清單 array('slug' => 'title')
     */
    private function createMainMenu($page_list) {
        $this->deployer->log("開始建立網站主選單");
        
        $menu_name = "主選單";
        
        // 檢查主選單是否已存在
        if ($this->wp_cli->menu_exists($menu_name)) {
            $this->deployer->log("主選單已存在，跳過建立");
            $menu_id = $this->wp_cli->get_menu_id($menu_name);
        } else {
            // 建立新選單
            $this->deployer->log("建立新的主選單");
            $create_result = $this->wp_cli->create_menu($menu_name);
            
            if ($create_result['return_code'] !== 0) {
                $this->deployer->log("錯誤: 無法建立主選單: " . $create_result['output']);
                return false;
            }
            
            $menu_id = $create_result['menu_id'];
            $this->deployer->log("✅ 成功建立主選單，ID: {$menu_id}");
        }
        
        if (!$menu_id) {
            $this->deployer->log("錯誤: 無法取得主選單 ID");
            return false;
        }
        
        // 定義選單項目順序（首頁優先）
        $menu_order = ['home', 'about', 'service', 'blog', 'contact'];
        $added_pages = [];
        
        // 按照指定順序新增頁面到選單
        foreach ($menu_order as $page_slug) {
            if (isset($page_list[$page_slug])) {
                $page_title = $page_list[$page_slug];
                
                // 取得頁面 ID
                $page_id = $this->wp_cli->get_page_id($page_slug);
                
                if ($page_id) {
                    $add_result = $this->wp_cli->add_page_to_menu($menu_id, $page_id, $page_title);
                    
                    if ($add_result['return_code'] === 0) {
                        $this->deployer->log("✅ 新增頁面到選單: {$page_title} (ID: {$page_id})");
                        $added_pages[] = $page_slug;
                    } else {
                        $this->deployer->log("⚠️ 新增頁面到選單失敗: {$page_title} - " . $add_result['output']);
                    }
                } else {
                    $this->deployer->log("⚠️ 找不到頁面 ID: {$page_slug}");
                }
            }
        }
        
        // 新增其他未在順序中的頁面
        foreach ($page_list as $page_slug => $page_title) {
            if (!in_array($page_slug, $added_pages)) {
                $page_id = $this->wp_cli->get_page_id($page_slug);
                
                if ($page_id) {
                    $add_result = $this->wp_cli->add_page_to_menu($menu_id, $page_id, $page_title);
                    
                    if ($add_result['return_code'] === 0) {
                        $this->deployer->log("✅ 新增其他頁面到選單: {$page_title} (ID: {$page_id})");
                    } else {
                        $this->deployer->log("⚠️ 新增其他頁面到選單失敗: {$page_title} - " . $add_result['output']);
                    }
                }
            }
        }
        
        // 設定選單到主要位置（先查詢可用位置）
        $this->deployer->log("查詢可用的選單位置");
        $available_locations = $this->wp_cli->get_menu_locations();
        
        if (!empty($available_locations)) {
            $this->deployer->log("找到可用的選單位置: " . implode(', ', array_keys($available_locations)));
            
            // 優先嘗試主要位置
            $primary_locations = ['primary', 'main', 'header', 'primary-menu', 'top', 'navigation'];
            $assigned = false;
            
            foreach ($primary_locations as $location) {
                if (isset($available_locations[$location])) {
                    $this->deployer->log("嘗試設定選單到位置: {$location}");
                    $assign_result = $this->wp_cli->assign_menu_location($menu_id, $location);
                    
                    if ($assign_result['return_code'] === 0) {
                        $this->deployer->log("✅ 成功設定主選單到位置: {$location}");
                        $assigned = true;
                        break;
                    } else {
                        $this->deployer->log("⚠️ 設定選單位置失敗: {$location} - " . $assign_result['output']);
                    }
                }
            }
            
            // 如果優先位置都無法設定，嘗試第一個可用位置
            if (!$assigned && !empty($available_locations)) {
                $first_location = array_keys($available_locations)[0];
                $this->deployer->log("嘗試設定選單到第一個可用位置: {$first_location}");
                $assign_result = $this->wp_cli->assign_menu_location($menu_id, $first_location);
                
                if ($assign_result['return_code'] === 0) {
                    $this->deployer->log("✅ 成功設定主選單到位置: {$first_location}");
                    $assigned = true;
                } else {
                    $this->deployer->log("⚠️ 設定第一個可用位置也失敗: " . $assign_result['output']);
                }
            }
            
            if (!$assigned) {
                $this->deployer->log("⚠️ 無法設定選單到任何位置，但選單已建立成功");
            }
        } else {
            $this->deployer->log("⚠️ 找不到任何可用的選單位置，但選單已建立成功");
        }
        
        $this->deployer->log("主選單建立完成");
        return true;
    }
}

/**
 * 清理標題以產生適當的 slug
 */
function sanitize_title($title) {
    // 移除特殊字符並轉換為小寫
    $slug = strtolower($title);
    $slug = preg_replace('/[^a-z0-9\-_]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'page';
}

// 主要執行邏輯
$deployer->log("=== 步驟 14: 生成頁面 ===");

$generator = new PageGenerator($deployer, $job_id, $config);
$success = $generator->generatePages();

if ($success) {
    $deployer->log("=== 步驟 14 完成 ===");
} else {
    $deployer->log("=== 步驟 14 失敗 ===");
    exit(1);
}
?>