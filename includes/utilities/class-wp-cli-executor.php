<?php
/**
 * WP-CLI 執行器
 * 
 * 專門處理 WordPress CLI 命令的執行與結果解析
 * 
 * @package Contenta
 * @version 1.0.0
 */

// 在 CLI 環境中不需要 ABSPATH 檢查
// if (!defined('ABSPATH')) {
//     exit;
// }

class WP_CLI_Executor 
{
    private $config;
    private $document_root;
    private $ssh_user;
    private $server_host;
    private $ssh_port;
    private $ssh_key_path;
    
    /**
     * 建構函數
     * 
     * @param object $config 配置物件
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->document_root = '';
        $this->ssh_user = $config->get('deployment.ssh_user');
        $this->server_host = $config->get('deployment.server_host');
        $this->ssh_port = $config->get('deployment.ssh_port') ?: 22;
        $this->ssh_key_path = $config->get('deployment.ssh_key_path');
    }
    
    /**
     * 設定 WordPress 文檔根目錄
     * 
     * @param string $document_root WordPress 安裝目錄
     */
    public function set_document_root($document_root)
    {
        $this->document_root = $document_root;
    }
    
    /**
     * 執行 WP-CLI 命令
     * 
     * @param string $command WP-CLI 命令（不包含 wp 前綴）
     * @param array $options 額外選項
     * @return array 執行結果
     */
    public function execute($command, $options = [])
    {
        // 構建完整的 WP-CLI 命令
        $wp_command = "cd {$this->document_root} && wp {$command} --allow-root";
        
        // 添加額外選項
        foreach ($options as $key => $value) {
            if (is_bool($value) && $value) {
                $wp_command .= " --{$key}";
            } elseif (!is_bool($value)) {
                $wp_command .= " --{$key}=" . escapeshellarg($value);
            }
        }
        
        return $this->execute_ssh($wp_command);
    }
    
    /**
     * 上傳媒體檔案並返回 Attachment ID
     * 
     * @param string $local_file_path 本地檔案路徑
     * @param string $title 媒體標題
     * @param string $alt_text 替代文字
     * @return array 包含 attachment_id 的結果
     */
    public function upload_media($local_file_path, $title = '', $alt_text = '')
    {
        if (!file_exists($local_file_path)) {
            return [
                'return_code' => 1,
                'error' => "本地檔案不存在: {$local_file_path}",
                'attachment_id' => null
            ];
        }
        
        // 首先將檔案上傳到遠端伺服器的臨時目錄
        $remote_temp_path = "/tmp/" . basename($local_file_path);
        $scp_result = $this->scp_upload($local_file_path, $remote_temp_path);
        
        if ($scp_result['return_code'] !== 0) {
            return [
                'return_code' => 1,
                'error' => "SCP 上傳失敗: " . $scp_result['output'],
                'attachment_id' => null
            ];
        }
        
        // 使用 wp media import 命令匯入媒體
        $import_command = "media import {$remote_temp_path} --porcelain";
        
        if (!empty($title)) {
            $import_command .= " --title=" . escapeshellarg($title);
        }
        
        if (!empty($alt_text)) {
            $import_command .= " --alt=" . escapeshellarg($alt_text);
        }
        
        $result = $this->execute($import_command);
        
        // 清理遠端臨時檔案
        $this->execute_ssh("rm -f {$remote_temp_path}");
        
        if ($result['return_code'] === 0) {
            // --porcelain 參數會讓 wp media import 只輸出 attachment ID
            $attachment_id = intval(trim($result['output']));
            $result['attachment_id'] = $attachment_id;
        } else {
            $result['attachment_id'] = null;
        }
        
        return $result;
    }
    
    /**
     * 建立新文章並返回 Post ID
     * 
     * @param array $post_data 文章資料
     * @return array 包含 post_id 的結果
     */
    public function create_post($post_data)
    {
        $content = $post_data['content'] ?? '';
        
        // 對於長內容，使用 stdin 方式更安全
        if (!empty($content) && strlen($content) > 1000) {
            // 先建立沒有內容的文章
            $params = [
                'post_title' => $post_data['title'] ?? '',
                'post_status' => $post_data['status'] ?? 'publish',
                'post_type' => $post_data['type'] ?? 'post',
                'post_category' => $post_data['category'] ?? '',
                'tags_input' => $post_data['tags'] ?? '',
                'meta_input' => $post_data['meta'] ?? '',
                'porcelain' => true
            ];
            
            $result = $this->execute("post create", $params);
            
            if ($result['return_code'] === 0) {
                $post_id = intval(trim($result['output']));
                $result['post_id'] = $post_id;
                
                // 然後更新內容
                $update_params = [
                    'post_content' => $content
                ];
                $update_result = $this->execute("post update {$post_id}", $update_params);
                
                if ($update_result['return_code'] !== 0) {
                    $result['error'] = "文章建立成功但內容更新失敗: " . $update_result['output'];
                }
            }
        } else {
            // 短內容直接建立
            $params = [
                'post_title' => $post_data['title'] ?? '',
                'post_content' => $content,
                'post_status' => $post_data['status'] ?? 'publish',
                'post_type' => $post_data['type'] ?? 'post',
                'post_category' => $post_data['category'] ?? '',
                'tags_input' => $post_data['tags'] ?? '',
                'meta_input' => $post_data['meta'] ?? '',
                'porcelain' => true
            ];
            
            $result = $this->execute("post create", $params);
            
            if ($result['return_code'] === 0) {
                $post_id = intval(trim($result['output']));
                $result['post_id'] = $post_id;
            }
        }
        
        // 確保 post_id 設定正確
        if ($result['return_code'] === 0 && !isset($result['post_id'])) {
            $post_id = intval(trim($result['output']));
            $result['post_id'] = $post_id;
        } elseif ($result['return_code'] !== 0) {
            $result['post_id'] = null;
        }
        
        return $result;
    }
    
    /**
     * 設定文章精選圖片
     * 
     * @param int $post_id 文章 ID
     * @param int $attachment_id 附件 ID
     * @return array 執行結果
     */
    public function set_featured_image($post_id, $attachment_id)
    {
        $command = "post meta add {$post_id} _thumbnail_id {$attachment_id}";
        return $this->execute($command);
    }
    
    /**
     * 透過 SCP 上傳檔案
     * 
     * @param string $local_path 本地路徑
     * @param string $remote_path 遠端路徑
     * @return array 執行結果
     */
    private function scp_upload($local_path, $remote_path)
    {
        $scp_cmd = "scp";
        
        if (!empty($this->ssh_key_path) && file_exists($this->ssh_key_path)) {
            $scp_cmd .= " -i " . escapeshellarg($this->ssh_key_path);
        }
        
        if (!empty($this->ssh_port)) {
            $scp_cmd .= " -P {$this->ssh_port}";
        }
        
        $scp_cmd .= " -o StrictHostKeyChecking=no";
        $scp_cmd .= " " . escapeshellarg($local_path);
        $scp_cmd .= " {$this->ssh_user}@{$this->server_host}:" . escapeshellarg($remote_path);
        
        $output = [];
        $return_code = 0;
        
        exec($scp_cmd . ' 2>&1', $output, $return_code);
        
        return [
            'return_code' => $return_code,
            'output' => implode("\n", $output),
            'command' => $scp_cmd
        ];
    }
    
    /**
     * 執行 SSH 命令
     * 
     * @param string $command 要執行的命令
     * @return array 執行結果
     */
    private function execute_ssh($command)
    {
        $ssh_cmd = "ssh";
        
        if (!empty($this->ssh_key_path) && file_exists($this->ssh_key_path)) {
            $ssh_cmd .= " -i " . escapeshellarg($this->ssh_key_path);
        }
        
        if (!empty($this->ssh_port)) {
            $ssh_cmd .= " -p {$this->ssh_port}";
        }
        
        $ssh_cmd .= " -o StrictHostKeyChecking=no";
        $ssh_cmd .= " {$this->ssh_user}@{$this->server_host}";
        $ssh_cmd .= " " . escapeshellarg($command);
        
        $output = [];
        $return_code = 0;
        
        exec($ssh_cmd . ' 2>&1', $output, $return_code);
        
        return [
            'return_code' => $return_code,
            'output' => implode("\n", $output),
            'command' => $command
        ];
    }
    
    /**
     * 檢查 WP-CLI 是否可用
     * 
     * @return bool WP-CLI 是否可用
     */
    public function is_available()
    {
        $result = $this->execute("--version");
        return $result['return_code'] === 0;
    }
    
    /**
     * 獲取 WordPress 資訊
     * 
     * @return array WordPress 資訊
     */
    public function get_wp_info()
    {
        $result = $this->execute("core version --extra");
        return [
            'available' => $result['return_code'] === 0,
            'info' => $result['output']
        ];
    }
    
    /**
     * 檢查分類是否存在
     * 
     * @param string $slug 分類 slug
     * @return bool 分類是否存在
     */
    public function category_exists($slug)
    {
        // 使用 term list 來檢查分類是否存在，這比 term get 更可靠
        $result = $this->execute("term list category --format=csv --fields=slug");
        
        if ($result['return_code'] === 0) {
            $lines = explode("\n", trim($result['output']));
            // 跳過 CSV 標題行
            array_shift($lines);
            
            foreach ($lines as $line) {
                if (trim($line) === $slug) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 建立新分類
     * 
     * @param string $name 分類名稱
     * @param string $slug 分類 slug
     * @param string $description 分類描述
     * @return array 執行結果，包含 term_id
     */
    public function create_category($name, $slug, $description = '')
    {
        $options = [
            'description' => $description,
            'slug' => $slug,
            'porcelain' => true
        ];
        
        $result = $this->execute("term create category " . escapeshellarg($name), $options);
        
        if ($result['return_code'] === 0) {
            $term_id = intval(trim($result['output']));
            $result['term_id'] = $term_id;
        } else {
            $result['term_id'] = null;
        }
        
        return $result;
    }
    
    /**
     * 取得分類 ID（透過 slug）
     * 
     * @param string $slug 分類 slug
     * @return int|null 分類 ID 或 null
     */
    public function get_category_id($slug)
    {
        // 使用 term list 來取得分類 ID，更可靠
        $result = $this->execute("term list category --format=csv --fields=term_id,slug");
        
        if ($result['return_code'] === 0) {
            $lines = explode("\n", trim($result['output']));
            // 跳過 CSV 標題行
            array_shift($lines);
            
            foreach ($lines as $line) {
                $parts = str_getcsv($line, ',', '"', '\\');
                if (isset($parts[1]) && trim($parts[1]) === $slug) {
                    return intval($parts[0]);
                }
            }
        }
        
        return null;
    }
    
    /**
     * 檢查選單是否存在
     * 
     * @param string $menu_name 選單名稱或 slug
     * @return bool 選單是否存在
     */
    public function menu_exists($menu_name)
    {
        $result = $this->execute("menu list --format=csv --fields=name,slug");
        if ($result['return_code'] === 0) {
            $lines = explode("\n", trim($result['output']));
            foreach ($lines as $line) {
                if (strpos($line, $menu_name) !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * 建立新選單
     * 
     * @param string $menu_name 選單名稱
     * @return array 執行結果，包含 menu_id
     */
    public function create_menu($menu_name)
    {
        $result = $this->execute("menu create " . escapeshellarg($menu_name) . " --porcelain");
        
        if ($result['return_code'] === 0) {
            $menu_id = intval(trim($result['output']));
            $result['menu_id'] = $menu_id;
        } else {
            $result['menu_id'] = null;
        }
        
        return $result;
    }
    
    /**
     * 取得選單 ID
     * 
     * @param string $menu_name 選單名稱
     * @return int|null 選單 ID 或 null
     */
    public function get_menu_id($menu_name)
    {
        $result = $this->execute("menu list --format=csv --fields=term_id,name");
        if ($result['return_code'] === 0) {
            $lines = explode("\n", trim($result['output']));
            foreach ($lines as $line) {
                $parts = str_getcsv($line, ',', '"', '\\');
                if (isset($parts[1]) && $parts[1] === $menu_name) {
                    return intval($parts[0]);
                }
            }
        }
        return null;
    }
    
    /**
     * 新增頁面到選單
     * 
     * @param int $menu_id 選單 ID
     * @param int $page_id 頁面 ID
     * @param string $title 選單項目標題
     * @param int $parent_id 父級選單項目 ID（可選）
     * @return array 執行結果
     */
    public function add_page_to_menu($menu_id, $page_id, $title, $parent_id = 0)
    {
        $options = [
            'title' => $title,
            'link' => '',  // 讓 WP-CLI 自動產生連結
            'position' => 0  // 讓 WordPress 自動決定位置
        ];
        
        if ($parent_id > 0) {
            $options['parent-id'] = $parent_id;
        }
        
        $result = $this->execute("menu item add-post {$menu_id} {$page_id}", $options);
        return $result;
    }
    
    /**
     * 設定選單位置（如主選單、頁尾選單等）
     * 
     * @param int $menu_id 選單 ID
     * @param string $location 選單位置（如 'primary', 'footer' 等）
     * @return array 執行結果
     */
    public function assign_menu_location($menu_id, $location)
    {
        $result = $this->execute("menu location assign {$menu_id} {$location}");
        return $result;
    }
    
    /**
     * 取得可用的選單位置
     * 
     * @return array 可用的選單位置列表
     */
    public function get_menu_locations()
    {
        $result = $this->execute("menu location list --format=csv --fields=location,description");
        $locations = [];
        
        if ($result['return_code'] === 0) {
            $lines = explode("\n", trim($result['output']));
            array_shift($lines); // 移除標題行
            
            foreach ($lines as $line) {
                if (!empty($line)) {
                    $parts = str_getcsv($line, ',', '"', '\\');
                    if (isset($parts[0])) {
                        $location = $parts[0];
                        $description = $parts[1] ?? '';
                        $locations[$location] = $description;
                    }
                }
            }
        }
        
        return $locations;
    }
    
    /**
     * 取得頁面 ID（透過 slug）
     * 
     * @param string $slug 頁面 slug
     * @return int|null 頁面 ID 或 null
     */
    public function get_page_id($slug)
    {
        $result = $this->execute("post list --post_type=page --name={$slug} --format=csv --fields=ID");
        if ($result['return_code'] === 0) {
            $lines = explode("\n", trim($result['output']));
            if (count($lines) >= 2) {
                return intval($lines[1]); // 跳過標題行
            }
        }
        return null;
    }
}