<?php
/**
 * 步驟 13: 匯入全域 JSON 模板到 Elementor
 * 將 global 目錄中的 *-ai.json 檔案匯入到 Elementor elementor_library
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
        
        // 使用 WP-CLI Elementor library import 指令匯入模板
        $this->deployer->log("使用 WP-CLI Elementor library import 匯入模板: {$template_name}");
        $import_cmd = "cd {$document_root} && wp elementor library import {$remote_temp_file} --user=" . escapeshellarg($user_email) . " --allow-root";
        
        $import_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $import_cmd);
        
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
                $post_id = $this->findLatestImportedTemplate($server_host, $ssh_user, $ssh_port, $ssh_key_path, $document_root, $template_name);
            }
            
            if ($post_id) {
                $this->deployer->log("找到匯入的模板文章 ID: {$post_id}");
                // 設定為預設模板（如果需要）
                $this->setAsDefaultTemplate($server_host, $ssh_user, $ssh_port, $ssh_key_path, $document_root, $post_id, $template_type);
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
        executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $cleanup_cmd);
        
        if ($success) {
            $this->deployer->log("成功匯入模板: {$template_name}" . (isset($post_id) ? " (ID: {$post_id})" : ""));
        }
        
        return $success;
    }
    
    /**
     * 查詢最新匯入的模板文章 ID
     */
    private function findLatestImportedTemplate($server_host, $ssh_user, $ssh_port, $ssh_key_path, $document_root, $template_name) {
        // 使用 WP-CLI 查詢最新的 elementor_library 文章，按標題匹配
        $query_cmd = "cd {$document_root} && wp post list --post_type=elementor_library --post_status=publish --orderby=date --order=DESC --format=csv --fields=ID,post_title --allow-root";
        
        $query_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $query_cmd);
        
        if ($query_result['return_code'] === 0) {
            $lines = explode("\n", trim($query_result['output']));
            
            // 跳過 CSV 標題行
            if (count($lines) > 1) {
                for ($i = 1; $i < count($lines); $i++) {
                    $fields = str_getcsv($lines[$i], ',', '"', '\\');
                    if (count($fields) >= 2) {
                        $post_id = $fields[0];
                        $post_title = $fields[1];
                        
                        // 檢查標題是否匹配模板名稱（多種匹配策略）
                        $matches = false;
                        
                        // 策略1: 精確匹配
                        if (strtolower($post_title) === strtolower($template_name)) {
                            $matches = true;
                        }
                        
                        // 策略2: 包含匹配（原模板名包含文章標題）
                        if (!$matches && stripos($template_name, $post_title) !== false) {
                            $matches = true;
                        }
                        
                        // 策略3: 包含匹配（文章標題包含模板名）
                        if (!$matches && stripos($post_title, $template_name) !== false) {
                            $matches = true;
                        }
                        
                        // 策略4: 去除數字和特殊字符的匹配（如 404error001 vs 404）
                        if (!$matches) {
                            $clean_template_name = preg_replace('/[^a-zA-Z]/', '', $template_name);
                            $clean_post_title = preg_replace('/[^a-zA-Z]/', '', $post_title);
                            if (!empty($clean_template_name) && !empty($clean_post_title) && 
                                stripos($clean_template_name, $clean_post_title) !== false) {
                                $matches = true;
                            }
                        }
                        
                        if ($matches) {
                            $this->deployer->log("找到匹配的模板: {$post_title} (ID: {$post_id}) [匹配模板: {$template_name}]");
                            return $post_id;
                        }
                    }
                }
            }
        }
        
        // 如果按標題匹配失敗，返回最新的 elementor_library 文章 ID
        $latest_cmd = "cd {$document_root} && wp post list --post_type=elementor_library --post_status=publish --orderby=date --order=DESC --number=1 --format=csv --fields=ID --allow-root";
        
        $latest_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $latest_cmd);
        
        if ($latest_result['return_code'] === 0) {
            $lines = explode("\n", trim($latest_result['output']));
            if (count($lines) > 1) {
                $post_id = trim($lines[1]); // 跳過 CSV 標題
                $this->deployer->log("使用最新的 Elementor 模板 ID: {$post_id}");
                return $post_id;
            }
        }
        
        $this->deployer->log("無法找到匹配的模板文章 ID");
        return null;
    }
    
    private function setAsDefaultTemplate($server_host, $ssh_user, $ssh_port, $ssh_key_path, $document_root, $post_id, $template_type) {
        $this->deployer->log("設定 {$template_type} 為預設全域模板 (ID: {$post_id})");
        
        // 0. 驗證模板是否存在
        $verify_cmd = "cd {$document_root} && wp post get {$post_id} --field=post_title --allow-root";
        $verify_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $verify_cmd);
        
        if (!empty($verify_result['output']) && $verify_result['return_code'] === 0) {
            $this->deployer->log("✓ 驗證模板存在: " . trim($verify_result['output']));
        } else {
            $this->deployer->log("⚠️ 模板驗證失敗，跳過條件設定");
            return;
        }
        
        // 1. 設定模板類型（確保使用正確的 Elementor 類型值）
        $elementor_type = $this->getElementorTemplateType($template_type);
        $update_type_cmd = "cd {$document_root} && wp post meta update {$post_id} _elementor_template_type {$elementor_type} --allow-root";
        $type_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $update_type_cmd);
        
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
        executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $delete_conditions_cmd);
        
        // 設定新的條件（使用 --format=json 讓 WordPress 正確序列化）
        $update_conditions_cmd = "cd {$document_root} && echo " . escapeshellarg($conditions_json) . " | wp post meta update {$post_id} _elementor_conditions --format=json --allow-root";
        $conditions_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $update_conditions_cmd);
        
        if (strpos($conditions_result['output'], 'Success') !== false) {
            $this->deployer->log("✓ 成功設定模板條件: {$conditions_json}");
        } else {
            $this->deployer->log("⚠️ 設定模板條件失敗: " . $conditions_result['output']);
        }
        
        // 3. 確保模板為發布狀態
        $publish_cmd = "cd {$document_root} && wp post update {$post_id} --post_status=publish --allow-root";
        $publish_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $publish_cmd);
        
        if (strpos($publish_result['output'], 'Success') !== false) {
            $this->deployer->log("✓ 模板已設為發布狀態");
        } else {
            $this->deployer->log("⚠️ 設定發布狀態失敗: " . $publish_result['output']);
        }
        
        // 4. 驗證設定結果
        $verify_conditions_cmd = "cd {$document_root} && wp post meta get {$post_id} _elementor_conditions --allow-root";
        $verify_conditions_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $verify_conditions_cmd);
        
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
$deployer->log("=== 步驟 13: 匯入全域 JSON 模板到 Elementor ===");

$importer = new GlobalTemplateImporter($deployer, $job_id, $config);
$success = $importer->importGlobalTemplates();

if ($success) {
    $deployer->log("=== 步驟 13 完成 ===");
} else {
    $deployer->log("=== 步驟 13 失敗 ===");
    exit(1);
}
?>