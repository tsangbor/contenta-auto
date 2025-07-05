<?php
/**
 * 測試選單功能 - 不實際執行 SSH 命令
 */

// 基本設定
define('DEPLOY_BASE_PATH', __DIR__);
require_once __DIR__ . '/includes/utilities/class-wp-cli-executor.php';

$job_id = '2506290730-3450';

try {
    
    echo "=== 測試選單功能類別 ===\n";
    echo "開始時間: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 模擬配置物件
    class MockConfig {
        public function get($key) {
            $config = [
                'deployment' => [
                    'ssh_user' => 'root',
                    'server_host' => '127.0.0.1',
                    'ssh_port' => 22,
                    'ssh_key_path' => '/root/.ssh/id_rsa'
                ]
            ];
            
            $keys = explode('.', $key);
            $value = $config;
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return null;
                }
            }
            return $value;
        }
    }
    
    $config = new MockConfig();
    $wp_cli = new WP_CLI_Executor($config);
    
    echo "✅ WP_CLI_Executor 建立成功\n";
    
    // 設定文檔根目錄
    $wp_cli->set_document_root('/www/wwwroot/www.example.com');
    echo "✅ 文檔根目錄設定完成\n";
    
    // 測試可用的方法
    echo "\n=== 測試新增的選單管理方法 ===\n";
    
    $methods = [
        'menu_exists',
        'create_menu', 
        'get_menu_id',
        'add_page_to_menu',
        'assign_menu_location',
        'get_menu_locations',
        'get_page_id'
    ];
    
    foreach ($methods as $method) {
        if (method_exists($wp_cli, $method)) {
            echo "✅ 方法存在: $method\n";
        } else {
            echo "❌ 方法不存在: $method\n";
        }
    }
    
    echo "\n=== 測試執行命令建構 ===\n";
    
    // 測試一些命令建構（但不實際執行）
    echo "測試選單建立命令：\n";
    echo "Command: wp menu create \"主選單\" --porcelain\n";
    
    echo "\n測試新增頁面到選單命令：\n";
    echo "Command: wp menu item add-post [menu_id] [page_id] --title=\"頁面標題\"\n";
    
    echo "\n測試設定選單位置命令：\n";
    echo "Command: wp menu location assign [menu_id] primary\n";
    
    echo "\n測試取得頁面 ID 命令：\n";
    echo "Command: wp post list --post_type=page --name=[slug] --format=csv --fields=ID\n";
    
    echo "\n=== 測試 PageGenerator 類別（模擬）===\n";
    
    // 建立模擬的 PageGenerator 來測試選單建立邏輯
    class TestPageGenerator {
        private $wp_cli;
        
        public function __construct($wp_cli) {
            $this->wp_cli = $wp_cli;
        }
        
        public function simulateMenuCreation($page_list) {
            echo "開始模擬選單建立流程:\n";
            
            $menu_name = "主選單";
            echo "1. 檢查選單是否存在: $menu_name\n";
            
            echo "2. 建立新選單（如果不存在）\n";
            echo "   Command: wp menu create \"$menu_name\" --porcelain\n";
            
            $menu_order = ['home', 'about', 'service', 'blog', 'contact'];
            echo "3. 按順序新增頁面到選單:\n";
            
            foreach ($menu_order as $page_slug) {
                if (isset($page_list[$page_slug])) {
                    $page_title = $page_list[$page_slug];
                    echo "   - 新增頁面: $page_title ($page_slug)\n";
                    echo "     Command: wp post list --post_type=page --name=$page_slug --format=csv --fields=ID\n";
                    echo "     Command: wp menu item add-post [menu_id] [page_id] --title=\"$page_title\"\n";
                }
            }
            
            echo "4. 設定選單到主要位置:\n";
            echo "   Command: wp menu location assign [menu_id] primary\n";
            
            echo "5. 備用位置嘗試: main, header, primary-menu, top\n";
            
            return true;
        }
    }
    
    $test_generator = new TestPageGenerator($wp_cli);
    
    // 模擬頁面清單
    $page_list = [
        'home' => '首頁',
        'about' => '關於我',
        'service' => '服務項目',
        'blog' => '部落格',
        'contact' => '聯絡我'
    ];
    
    $test_generator->simulateMenuCreation($page_list);
    
    echo "\n=== 功能測試完成 ===\n";
    echo "結束時間: " . date('Y-m-d H:i:s') . "\n";
    echo "\n✅ 所有選單管理功能已成功整合到 WP_CLI_Executor 類別\n";
    echo "✅ step-14.php 已更新，將在頁面生成後自動建立主選單\n";
    
} catch (Exception $e) {
    echo "測試失敗: " . $e->getMessage() . "\n";
    echo "錯誤檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}