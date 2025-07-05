<?php
/**
 * 測試 step-14.php - 頁面生成與選單建立
 */

// 基本設定
define('DEPLOY_BASE_PATH', __DIR__);
require_once __DIR__ . '/includes/step-dependencies.php';

$job_id = '2506290730-3450';

try {
    
    // 載入必要的類別
    require_once DEPLOY_BASE_PATH . '/includes/utilities/class-wp-cli-executor.php';
    
    // 模擬 deployer 物件
    class MockDeployer {
        public function log($message) {
            echo "[" . date('Y-m-d H:i:s') . "] $message\n";
        }
    }
    $deployer = new MockDeployer();
    
    // 讀取配置
    $config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (!file_exists($config_file)) {
        throw new Exception("配置檔案不存在: $config_file");
    }
    
    $config_data = json_decode(file_get_contents($config_file), true);
    
    // 建立配置物件
    class MockConfig {
        private $data;
        
        public function __construct($data) {
            $this->data = $data;
        }
        
        public function get($key) {
            $keys = explode('.', $key);
            $value = $this->data;
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
    $config = new MockConfig($config_data);
    
    echo "=== 測試 step-14.php 頁面生成與選單建立 ===\n";
    echo "Job ID: $job_id\n";
    echo "開始時間: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 檢查必要檔案
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    $site_config_file = $work_dir . '/json/site-config.json';
    
    if (!file_exists($site_config_file)) {
        echo "錯誤: site-config.json 檔案不存在\n";
        exit(1);
    }
    
    $site_config = json_decode(file_get_contents($site_config_file), true);
    if (empty($site_config['page_list'])) {
        echo "錯誤: site-config.json 中沒有 page_list\n";
        exit(1);
    }
    
    echo "找到頁面清單:\n";
    foreach ($site_config['page_list'] as $slug => $title) {
        echo "  - $slug: $title\n";
    }
    echo "\n";
    
    // 執行 step-14.php
    echo "=== 執行 step-14.php ===\n";
    
    // 設定必要變數
    $GLOBALS['deployer'] = $deployer;
    $GLOBALS['job_id'] = $job_id;
    $GLOBALS['config'] = $config;
    
    // 包含並執行 step-14.php
    ob_start();
    include DEPLOY_BASE_PATH . '/step-14.php';
    $output = ob_get_clean();
    
    echo $output;
    
    echo "\n=== 測試完成 ===\n";
    echo "結束時間: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "測試失敗: " . $e->getMessage() . "\n";
    echo "錯誤檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}