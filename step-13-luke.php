<?php
/**
 * 步驟 13: 匯入全域 JSON 模板到 Elementor (Luke API 模式)
 * 將 global 目錄中的 *-ai.json 檔案匯入到 Elementor elementor_library
 */

// 載入處理後的資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

/**
 * Luke 模式的 SSH 執行函數（支援密碼認證）
 */
function executeSSHWithPassword($host, $user, $port, $password, $command, $max_retries = 3)
{
    $ssh_cmd = "sshpass -p " . escapeshellarg($password) . " ssh";
    
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

class GlobalTemplateImporter {
    private $deployer;
    private $job_id;
    private $global_templates_dir;
    private $config;
    
    public function __construct($deployer, $job_id, $config) {
        $this->deployer = $deployer;
        $this->job_id = $job_id;
        $this->global_templates_dir = DEPLOY_BASE_PATH . "/temp/{$job_id}/layout/global";
        $this->config = $config;
    }
    
    public function importGlobalTemplates() {
        
        // 檢查全域模板目錄是否存在
        if (!is_dir($this->global_templates_dir)) {
            $this->deployer->log("錯誤: 全域模板目錄不存在: {$this->global_templates_dir}");
            return false;
        }
        
        // 取得所有 *-ai.json 檔案
        $ai_template_files = glob($this->global_templates_dir . "/*-ai.json");
        
        if (empty($ai_template_files)) {
            $this->deployer->log("警告: 沒有找到任何 *-ai.json 全域模板檔案");
            return false;
        }
        
        $this->deployer->log("找到 " . count($ai_template_files) . " 個全域模板檔案");
        
        $imported_count = 0;
        
        foreach ($ai_template_files as $index => $template_file) {
            $template_name = basename($template_file, '-ai.json');
            
            // 在處理每個模板間加入延遲，避免連線過於頻繁
            if ($index > 0) {
                $this->deployer->log("等待 3 秒後處理下一個模板...");
                sleep(3);
            }
            
            if ($this->importSingleTemplate($template_file, $template_name)) {
                $imported_count++;
            }
        }
        
        $this->deployer->log("成功匯入 {$imported_count} 個全域模板到 Elementor");
        return $imported_count > 0;
    }
    
    private function importSingleTemplate($template_file, $template_name) {
        $this->deployer->log("處理模板: {$template_name}");
        
        // 讀取 JSON 檔案
        $template_json = file_get_contents($template_file);
        if ($template_json === false) {
            $this->deployer->log("錯誤: 無法讀取模板檔案: {$template_file}");
            return false;
        }
        
        // 驗證 JSON 格式
        $template_data = json_decode($template_json, true);
        if ($template_data === null) {
            $this->deployer->log("錯誤: 無效的 JSON 格式: {$template_file}");
            return false;
        }
        
        // 確定模板類型
        $template_type = $this->getTemplateType($template_name, $template_data);
        
        // 準備 WordPress 文章資料
        $post_data = array(
            'post_title' => $template_name,
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'elementor_library',
            'meta_input' => array(
                '_elementor_template_type' => $template_type,
                '_elementor_data' => $template_json,
                '_elementor_version' => '3.0.0',
                '_elementor_edit_mode' => 'builder'
            )
        );
        
        // 透過 WP-CLI 匯入模板
        return $this->createTemplateViaWPCLI($post_data, $template_name, $template_type);
    }
    
    private function getTemplateType($template_name, $template_data = null) {
        // 優先從 JSON 資料中取得類型
        if ($template_data && isset($template_data['type'])) {
            $json_type = $template_data['type'];
            
            // 將 JSON 中的類型對應到 Elementor 的模板類型
            switch ($json_type) {
                case 'error-404':
                    return 'error-404';
                case 'archive':
                    return 'archive';
                case 'single-post':
                case 'single':
                    return 'single';
                case 'header':
                    return 'header';
                case 'footer':
                    return 'footer';
                default:
                    // 如果 JSON 類型無法識別，則使用檔名判斷
                    break;
            }
        }
        
        // 根據模板名稱決定類型（備用方案）
        if (strpos($template_name, 'header') !== false) {
            return 'header';
        } elseif (strpos($template_name, 'footer') !== false) {
            return 'footer';
        } elseif (strpos($template_name, 'archive') !== false) {
            return 'archive';
        } elseif (strpos($template_name, 'single') !== false) {
            return 'single';
        } elseif (strpos($template_name, '404') !== false) {
            return '404';
        } else {
            return 'section'; // 預設為 section
        }
    }
    
    private function createTemplateViaWPCLI($post_data, $template_name, $template_type) {
        // 取得 Luke 部署設定
        $server_host = $this->config->get('luke_deployment.ssh_host');
        $ssh_user = $this->config->get('luke_deployment.ssh_user');
        $ssh_port = $this->config->get('luke_deployment.ssh_port') ?: 22;
        $ssh_password = $this->config->get('luke_deployment.ssh_password'); // Luke 模式使用密碼認證
        
        // 取得網站根目錄和使用者資訊
        $work_dir = DEPLOY_BASE_PATH . '/temp/' . $this->job_id;
        $processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);
        $domain = $processed_data['confirmed_data']['domain'];
        $user_email = $processed_data['confirmed_data']['user_email'] ?? 'admin@' . $domain;
        $document_root = "/var/www/{$domain}/html"; // Luke 模式路徑格式
        
        // 準備遠端檔案路徑
        $remote_temp_file = "{$document_root}/wp-content/uploads/temp/{$template_name}-ai.json";
        
        // 先在本地建立臨時檔案（確保 JSON 是單行格式）
        $local_temp_file = sys_get_temp_dir() . "/elementor_template_{$template_name}_" . uniqid() . ".json";
        $json_data = $post_data['meta_input']['_elementor_data'];
        
        // 確保 JSON 是單行格式（移除多餘的空白和換行）
        $json_array = json_decode($json_data, true);
        if ($json_array === null) {
            $this->deployer->log("錯誤: 無效的 JSON 格式");
            return false;
        }
        
        // 重新編碼為單行 JSON（不使用 JSON_PRETTY_PRINT）
        $minified_json = json_encode($json_array, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if (file_put_contents($local_temp_file, $minified_json) === false) {
            $this->deployer->log("錯誤: 無法建立本地臨時檔案");
            return false;
        }
        
        // 確保遠端 temp 目錄存在
        $create_dir_cmd = "mkdir -p {$document_root}/wp-content/uploads/temp";
        $dir_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $create_dir_cmd);
        
        // 使用 scp 上傳檔案到遠端（Luke 密碼模式）
        $this->deployer->log("上傳 JSON 檔案到遠端: {$remote_temp_file}");
        $scp_cmd = "sshpass -p " . escapeshellarg($ssh_password) . " scp";
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
        
        // 使用 WP-CLI Elementor library import 指令匯入模板
        $this->deployer->log("使用 WP-CLI Elementor library import 匯入模板: {$template_name}");
        $import_cmd = "cd {$document_root} && wp elementor library import {$remote_temp_file} --user=" . escapeshellarg($user_email) . " --allow-root";
        
        $import_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $import_cmd);
        
        $this->deployer->log("匯入結果: " . $import_result['output']);
        
        // 檢查匯入是否成功
        if ($import_result['return_code'] === 0 && strpos($import_result['output'], 'Success') !== false) {
            $post_id = null;
            
            // 嘗試從輸出中提取文章 ID（多種格式）
            if (preg_match('/Success: Imported post ID (\d+)/', $import_result['output'], $matches)) {
                $post_id = $matches[1];
                $this->deployer->log("成功匯入模板，文章 ID: {$post_id}");
            } elseif (preg_match('/(\d+) item\(s\) has been imported/', $import_result['output'], $matches)) {
                // WP-CLI 回傳格式：Success: 1 item(s) has been imported.
                $this->deployer->log("模板匯入成功，正在查詢文章 ID...");
                $post_id = $this->findLatestImportedTemplate($server_host, $ssh_user, $ssh_port, $ssh_password, $document_root, $template_name);
            }
            
            if ($post_id) {
                $this->deployer->log("找到匯入的模板文章 ID: {$post_id}");
                // 設定為預設模板（如果需要）
                $this->setAsDefaultTemplate($server_host, $ssh_user, $ssh_port, $ssh_password, $document_root, $post_id, $template_type);
            } else {
                $this->deployer->log("警告: 無法取得模板文章 ID，跳過條件設定");
            }
            
            $success = true;
        } else {
            $this->deployer->log("錯誤: 模板匯入失敗");
            $this->deployer->log("返回代碼: " . $import_result['return_code']);
            $this->deployer->log("錯誤輸出: " . $import_result['output']);
            $success = false;
        }
        
        // 清理遠端臨時檔案
        $cleanup_cmd = "rm -f {$remote_temp_file}";
        executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $cleanup_cmd);
        
        if ($success) {
            $this->deployer->log("成功匯入模板: {$template_name}" . (isset($post_id) ? " (ID: {$post_id})" : ""));
        }
        
        return $success;
    }
    
    /**
     * 查詢最新匯入的模板文章 ID
     */
    private function findLatestImportedTemplate($server_host, $ssh_user, $ssh_port, $ssh_password, $document_root, $template_name) {
        $this->deployer->log("嘗試查詢匹配的模板: {$template_name}");
        
        // 首先嘗試直接獲取最新的模板ID，然後檢查標題
        $ids_cmd = "cd {$document_root} && wp post list --post_type=elementor_library --post_status=publish --orderby=date --order=DESC --number=5 --format=ids --allow-root";
        $ids_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $ids_cmd);
        
        if ($ids_result['return_code'] === 0) {
            $ids = trim($ids_result['output']);
            // 清理 WP-CLI 系統訊息
            $ids = $this->cleanWPCLIOutput($ids);
            
            if (!empty($ids)) {
                $id_list = array_filter(explode(' ', $ids), function($id) {
                    return is_numeric(trim($id)) && trim($id) > 0;
                });
                
                if (!empty($id_list)) {
                    $this->deployer->log("找到最近的模板IDs: " . implode(', ', $id_list));
                    
                    // 檢查每個ID的標題
                    foreach ($id_list as $post_id) {
                        $post_id = trim($post_id);
                        if (is_numeric($post_id) && $post_id > 0) {
                            $title_cmd = "cd {$document_root} && wp post get {$post_id} --field=post_title --allow-root";
                            $title_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $title_cmd);
                            
                            if ($title_result['return_code'] === 0) {
                                $post_title = trim($this->cleanWPCLIOutput($title_result['output']));
                                $this->deployer->log("檢查模板 ID {$post_id}: '{$post_title}'");
                                
                                if ($this->isTemplateNameMatch($template_name, $post_title)) {
                                    $this->deployer->log("找到匹配的模板: {$post_title} (ID: {$post_id})");
                                    return $post_id;
                                }
                            }
                        }
                    }
                    
                    // 如果沒有找到匹配的，返回最新的ID
                    $latest_id = trim($id_list[0]);
                    if (is_numeric($latest_id) && $latest_id > 0) {
                        $this->deployer->log("使用最新的模板 ID: {$latest_id}");
                        return $latest_id;
                    }
                }
            }
        }
        
        // 備用方案：使用 CSV 查詢（保留原有邏輯作為備用）
        $query_cmd = "cd {$document_root} && wp post list --post_type=elementor_library --post_status=publish --orderby=date --order=DESC --format=csv --fields=ID,post_title --allow-root";
        
        $query_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $query_cmd);
        
        if ($query_result['return_code'] === 0) {
            $lines = explode("\n", trim($query_result['output']));
            // 移除空行
            $lines = array_filter($lines, function($line) {
                return !empty(trim($line));
            });
            
            // 跳過 CSV 標題行
            if (count($lines) > 1) {
                for ($i = 1; $i < count($lines); $i++) {
                    $fields = str_getcsv($lines[$i], ',', '"', '\\');
                    if (count($fields) >= 2) {
                        $post_id = trim($fields[0]);
                        $post_title = trim($fields[1]);
                        
                        // 驗證ID是否為有效數字
                        if (!is_numeric($post_id) || $post_id <= 0) {
                            continue; // 跳過無效的ID
                        }
                        
                        // 使用統一的匹配方法
                        if ($this->isTemplateNameMatch($template_name, $post_title)) {
                            $this->deployer->log("找到匹配的模板: {$post_title} (ID: {$post_id}) [匹配模板: {$template_name}]");
                            return $post_id;
                        }
                    }
                }
            }
        }
        
        // 如果按標題匹配失敗，直接使用 --format=ids 獲取最新的模板ID
        $this->deployer->log("按標題匹配失敗，嘗試獲取最新的 Elementor 模板...");
        
        $ids_cmd = "cd {$document_root} && wp post list --post_type=elementor_library --post_status=publish --orderby=date --order=DESC --number=1 --format=ids --allow-root";
        $ids_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $ids_cmd);
        
        if ($ids_result['return_code'] === 0) {
            $id = trim($ids_result['output']);
            if (is_numeric($id) && $id > 0) {
                $this->deployer->log("使用 --format=ids 獲取的最新模板 ID: {$id}");
                return $id;
            } else {
                $this->deployer->log("警告: --format=ids 回傳的ID無效: '{$id}'");
            }
        } else {
            $this->deployer->log("錯誤: --format=ids 命令執行失敗: " . $ids_result['output']);
        }
        
        // 如果 --format=ids 也失敗，嘗試用 CSV 格式
        $csv_cmd = "cd {$document_root} && wp post list --post_type=elementor_library --post_status=publish --orderby=date --order=DESC --number=1 --format=csv --fields=ID --allow-root";
        $csv_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $csv_cmd);
        
        if ($csv_result['return_code'] === 0) {
            $clean_output = $this->cleanWPCLIOutput($csv_result['output']);
            $lines = explode("\n", trim($clean_output));
            // 移除空行和標題行
            $lines = array_filter($lines, function($line) {
                $line = trim($line);
                return !empty($line) && $line !== 'ID' && is_numeric($line);
            });
            
            // 取第一個有效的數字ID
            foreach ($lines as $line) {
                $line = trim($line);
                if (is_numeric($line) && $line > 0) {
                    $this->deployer->log("從 CSV 中找到有效的模板 ID: {$line}");
                    return $line;
                }
            }
        }
        
        $this->deployer->log("無法找到匹配的模板文章 ID");
        return null;
    }
    
    /**
     * 清理 WP-CLI 輸出中的系統訊息
     */
    private function cleanWPCLIOutput($output) {
        // 移除常見的 WP-CLI 系統訊息
        $patterns = [
            '/WP-CLI user-role 指令已註冊\s*/',
            '/WP-CLI.*指令已註冊\s*/',
            '/^Warning:.*$/m',
            '/^Notice:.*$/m'
        ];
        
        foreach ($patterns as $pattern) {
            $output = preg_replace($pattern, '', $output);
        }
        
        return trim($output);
    }
    
    /**
     * 檢查模板名稱是否匹配
     */
    private function isTemplateNameMatch($template_name, $post_title) {
        // 策略1: 精確匹配
        if (strtolower($post_title) === strtolower($template_name)) {
            return true;
        }
        
        // 策略2: 包含匹配（原模板名包含文章標題）
        if (stripos($template_name, $post_title) !== false) {
            return true;
        }
        
        // 策略3: 包含匹配（文章標題包含模板名）
        if (stripos($post_title, $template_name) !== false) {
            return true;
        }
        
        // 策略4: 去除數字和特殊字符的匹配（如 404error001 vs 404）
        $clean_template_name = preg_replace('/[^a-zA-Z]/', '', $template_name);
        $clean_post_title = preg_replace('/[^a-zA-Z]/', '', $post_title);
        if (!empty($clean_template_name) && !empty($clean_post_title) && 
            stripos($clean_template_name, $clean_post_title) !== false) {
            return true;
        }
        
        return false;
    }
    
    private function setAsDefaultTemplate($server_host, $ssh_user, $ssh_port, $ssh_password, $document_root, $post_id, $template_type) {
        $this->deployer->log("設定 {$template_type} 為預設全域模板 (ID: {$post_id})");
        
        // 0. 驗證模板是否存在
        $verify_cmd = "cd {$document_root} && wp post get {$post_id} --field=post_title --allow-root";
        $verify_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $verify_cmd);
        
        if (!empty($verify_result['output']) && $verify_result['return_code'] === 0) {
            $this->deployer->log("✓ 驗證模板存在: " . trim($verify_result['output']));
        } else {
            $this->deployer->log("⚠️ 模板驗證失敗，跳過條件設定");
            return;
        }
        
        // 1. 設定模板類型（確保使用正確的 Elementor 類型值）
        $elementor_type = $this->getElementorTemplateType($template_type);
        $update_type_cmd = "cd {$document_root} && wp post meta update {$post_id} _elementor_template_type {$elementor_type} --allow-root";
        $type_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $update_type_cmd);
        
        if (strpos($type_result['output'], 'Success') !== false) {
            $this->deployer->log("✓ 成功設定模板類型: {$template_type}");
        } else {
            $this->deployer->log("⚠️ 設定模板類型失敗: " . $type_result['output']);
        }
        
        // 2. 根據模板類型設定正確的使用條件
        $conditions = $this->getTemplateConditions($template_type);
        $conditions_json = json_encode($conditions);
        $this->deployer->log("設定條件: {$conditions_json}");
        
        // 先刪除舊的條件（如果存在）
        $delete_conditions_cmd = "cd {$document_root} && wp post meta delete {$post_id} _elementor_conditions --allow-root";
        executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $delete_conditions_cmd);
        
        // 設定新的條件（使用 --format=json 讓 WordPress 正確序列化）
        $update_conditions_cmd = "cd {$document_root} && echo " . escapeshellarg($conditions_json) . " | wp post meta update {$post_id} _elementor_conditions --format=json --allow-root";
        $conditions_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $update_conditions_cmd);
        
        if (strpos($conditions_result['output'], 'Success') !== false) {
            $this->deployer->log("✓ 成功設定模板條件: {$conditions_json}");
        } else {
            $this->deployer->log("⚠️ 設定模板條件失敗: " . $conditions_result['output']);
        }
        
        // 3. 確保模板為發布狀態
        $publish_cmd = "cd {$document_root} && wp post update {$post_id} --post_status=publish --allow-root";
        $publish_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $publish_cmd);
        
        if (strpos($publish_result['output'], 'Success') !== false) {
            $this->deployer->log("✓ 模板已設為發布狀態");
        } else {
            $this->deployer->log("⚠️ 設定發布狀態失敗: " . $publish_result['output']);
        }
        
        // 4. 驗證設定結果
        $verify_conditions_cmd = "cd {$document_root} && wp post meta get {$post_id} _elementor_conditions --allow-root";
        $verify_conditions_result = executeSSHWithPassword($server_host, $ssh_user, $ssh_port, $ssh_password, $verify_conditions_cmd);
        
        if (!empty($verify_conditions_result['output'])) {
            $this->deployer->log("✓ 條件設定驗證: " . trim($verify_conditions_result['output']));
        }
    }
    
    private function getTemplateConditions($template_type) {
        // 根據模板類型返回正確的 Elementor 條件格式（基於實際資料庫格式）
        switch ($template_type) {
            case 'header':
            case 'footer':
                return ['include/general'];  // 整個網站
            case 'single':
                return ['include/singular/post'];  // 文章
            case 'archive':
                return ['include/archive'];  // 全部文章（文章列表）
            case '404':
            case 'error-404':
                return ['include/singular/not_found404'];  // 404 錯誤頁面
            default:
                return ['include/general'];  // 預設為整個網站
        }
    }
    
    private function getElementorTemplateType($template_type) {
        // 確保使用 Elementor 支援的模板類型
        // Elementor 接受的類型：header, footer, single, archive, error-404 等
        $valid_types = ['header', 'footer', 'single', 'archive', 'error-404', 'page', 'section'];
        
        if (in_array($template_type, $valid_types)) {
            return $template_type;
        }
        
        // 如果不是有效類型，返回 section 作為預設
        return 'section';
    }
}

// 主要執行邏輯
$deployer->log("=== 步驟 13: 匯入全域 JSON 模板到 Elementor (Luke API 模式) ===");

$importer = new GlobalTemplateImporter($deployer, $job_id, $config);
$success = $importer->importGlobalTemplates();

if ($success) {
    $deployer->log("=== 步驟 13 完成 (Luke API 模式) ===");
} else {
    $deployer->log("=== 步驟 13 失敗 (Luke API 模式) ===");
    exit(1);
}
?>