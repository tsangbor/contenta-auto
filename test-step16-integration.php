<?php
/**
 * 測試整合後的步驟16 Logo生成
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

// 模擬部署器
class MockDeployer {
    public function log($message) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

// 載入必要的函數
require_once __DIR__ . '/config-manager.php';

// 載入步驟16相關的函數文件
require_once __DIR__ . '/step-16-domain-logo.php';

class Step16IntegrationTester
{
    private $deployer;
    private $job_id;
    
    public function __construct($job_id = '2507171303-3269')
    {
        $this->deployer = new MockDeployer();
        $this->job_id = $job_id;
    }
    
    public function test()
    {
        echo "=== 步驟16整合測試 ===\n\n";
        
        try {
            // 載入job資料
            $job_dir = DEPLOY_BASE_PATH . '/data/' . $this->job_id;
            $job_data_file = $job_dir . '/' . $this->job_id . '.json';
            
            if (!file_exists($job_data_file)) {
                throw new Exception("Job配置檔案不存在: $job_data_file");
            }
            
            $job_data = json_decode(file_get_contents($job_data_file), true);
            $confirmed_data = $job_data['confirmed_data'];
            
            $website_name = $confirmed_data['website_name'];
            $color_scheme = $confirmed_data['color_scheme'];
            
            echo "測試網站: {$website_name}\n";
            echo "網域: {$confirmed_data['domain']}\n";
            echo "主色調: {$color_scheme['primary']} → {$color_scheme['secondary']}\n\n";
            
            // 建立測試目錄
            $images_dir = DEPLOY_BASE_PATH . '/temp/' . $this->job_id . '/images';
            if (!is_dir($images_dir)) {
                mkdir($images_dir, 0755, true);
            }
            
            // 測試generateCompleteLogoWithAI函數
            echo "開始測試 generateCompleteLogoWithAI 函數...\n\n";
            
            $logo_path = generateCompleteLogoWithAI($website_name, $color_scheme, $job_data, $images_dir, $this->deployer);
            
            if ($logo_path) {
                echo "✅ Logo生成成功！\n";
                echo "檔案路徑: $logo_path\n";
                
                if (file_exists($logo_path)) {
                    $file_size = round(filesize($logo_path) / 1024, 2);
                    echo "檔案大小: {$file_size} KB\n";
                    
                    // 檢查圖片尺寸
                    $image_info = getimagesize($logo_path);
                    if ($image_info) {
                        echo "圖片尺寸: {$image_info[0]}x{$image_info[1]}\n";
                    }
                } else {
                    echo "⚠️  檔案不存在\n";
                }
            } else {
                echo "❌ Logo生成失敗\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 測試失敗: {$e->getMessage()}\n";
        }
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $job_id = isset($argv[1]) ? $argv[1] : '2507171303-3269';
    $tester = new Step16IntegrationTester($job_id);
    $tester->test();
} else {
    echo "<pre>";
    $job_id = isset($_GET['job_id']) ? $_GET['job_id'] : '2507171303-3269';
    $tester = new Step16IntegrationTester($job_id);
    $tester->test();
    echo "</pre>";
}
?>