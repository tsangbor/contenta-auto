<?php
/**
 * 測試步驟16: AI Logo生成 (v2.0)
 * 測試AI背景生成 + PHP文字圖層合併方式
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}

echo "=== 步驟16 AI Logo生成測試 (v2.0) ===\n\n";

// 模擬部署器日誌類別
class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

try {
    $deployer = new MockDeployer();
    
    // 使用現有的job_id
    $job_id = '2506290730-3450';
    
    // 檢查必要檔案
    $deployer->log("檢查必要檔案...");
    
    $job_data_file = DEPLOY_BASE_PATH . '/data/' . $job_id . '.json';
    if (!file_exists($job_data_file)) {
        throw new Exception("Job配置檔案不存在: $job_data_file");
    }
    
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    $processed_data_file = $work_dir . '/config/processed_data.json';
    if (!file_exists($processed_data_file)) {
        throw new Exception("Processed data檔案不存在: $processed_data_file");
    }
    
    $font_file = DEPLOY_BASE_PATH . '/logo/font/PottaOne-Regular.ttf';
    if (!file_exists($font_file)) {
        throw new Exception("字體檔案不存在: $font_file");
    }
    
    $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
    if (!file_exists($deploy_config_file)) {
        throw new Exception("部署配置檔案不存在: $deploy_config_file");
    }
    
    $deployer->log("所有必要檔案檢查完成 ✓");
    
    // 檢查PHP GD擴展
    if (!extension_loaded('gd')) {
        throw new Exception("PHP GD擴展未安裝");
    }
    $deployer->log("PHP GD擴展已安裝 ✓");
    
    // 載入並顯示配置
    $job_data = json_decode(file_get_contents($job_data_file), true);
    $full_website_name = $job_data['confirmed_data']['website_name'] ?? 'Test Website';
    $website_name = strpos($full_website_name, ' - ') !== false 
        ? trim(explode(' - ', $full_website_name)[0]) 
        : $full_website_name;
    $color_scheme = $job_data['confirmed_data']['color_scheme'] ?? [];
    
    $deployer->log("完整網站名稱: $full_website_name");
    $deployer->log("Logo文字: $website_name");
    $deployer->log("主色彩 (文字): " . ($color_scheme['primary'] ?? 'N/A'));
    $deployer->log("次色彩 (背景): " . ($color_scheme['secondary'] ?? 'N/A'));
    
    // 檢查images目錄
    $images_dir = $work_dir . '/images';
    if (!is_dir($images_dir)) {
        $deployer->log("Images目錄不存在，將會在執行過程中創建");
    } else {
        $deployer->log("Images目錄已存在: $images_dir");
    }
    
    // 執行步驟16
    $deployer->log("\n開始執行步驟16 (v2.0)...");
    $deployer->log("新流程: AI背景生成 → PHP文字圖層 → 圖層合併");
    
    $start_time = microtime(true);
    
    // 包含步驟16檔案
    ob_start();
    $result = include DEPLOY_BASE_PATH . '/step-16.php';
    $output = ob_get_clean();
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    // 顯示結果
    if ($result && $result['status'] === 'success') {
        $deployer->log("\n=== 執行成功 ===");
        $deployer->log("執行時間: {$execution_time} 秒");
        
        $step_result = $result['result'];
        $deployer->log("最終Logo檔案: " . $step_result['logo_path']);
        $deployer->log("背景圖層: " . $step_result['background_layer']);
        $deployer->log("文字圖層: " . $step_result['text_layer']);
        $deployer->log("檔案格式: " . $step_result['format']);
        $deployer->log("圖片尺寸: " . $step_result['dimensions']);
        $deployer->log("使用顏色:");
        $deployer->log("  - 主色彩 (文字): " . $step_result['colors_used']['primary']);
        $deployer->log("  - 次色彩 (背景): " . $step_result['colors_used']['secondary']);
        
        // 檢查生成的檔案
        $files_to_check = [
            $step_result['logo_path'] => '最終Logo',
            $images_dir . '/' . $step_result['background_layer'] => '背景圖層',
            $images_dir . '/' . $step_result['text_layer'] => '文字圖層'
        ];
        
        $deployer->log("\n檢查生成的檔案:");
        foreach ($files_to_check as $file_path => $description) {
            if (file_exists($file_path)) {
                $file_size = filesize($file_path);
                $deployer->log("✅ $description: " . basename($file_path) . " (" . formatFileSize($file_size) . ")");
            } else {
                $deployer->log("❌ $description: 檔案不存在 - " . basename($file_path));
            }
        }
        
        // 顯示所有images目錄內容
        if (is_dir($images_dir)) {
            $deployer->log("\nImages目錄內容:");
            $files = glob($images_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $file_size = filesize($file);
                    $deployer->log("  - " . basename($file) . " (" . formatFileSize($file_size) . ")");
                }
            }
        }
        
    } else {
        $deployer->log("\n=== 執行失敗 ===");
        $deployer->log("錯誤: " . ($result['message'] ?? '未知錯誤'));
    }
    
    // 顯示步驟輸出
    if (!empty($output)) {
        $deployer->log("\n=== 步驟輸出 ===");
        echo $output;
    }
    
} catch (Exception $e) {
    echo "測試失敗: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * 格式化檔案大小
 */
function formatFileSize($size)
{
    $units = ['B', 'KB', 'MB'];
    $unit = 0;
    
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    
    return round($size, 1) . ' ' . $units[$unit];
}

echo "\n=== 測試完成 ===\n";
echo "\n新版本特點:\n";
echo "1. AI生成750x200背景圖示\n";
echo "2. PHP GD生成文字圖層\n";
echo "3. 智能圖層合併\n";
echo "4. 所有檔案儲存至 temp/{job_id}/images 目錄\n";
echo "5. 完整的代理支援\n";
echo "6. 容錯備用方案\n";