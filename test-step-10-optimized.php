<?php
/**
 * 測試優化版圖片生成
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== 優化版圖片生成測試 ===\n\n";

// 模擬部署器日誌類別
class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

try {
    // 載入配置
    $config = ConfigManager::getInstance();
    $deployer = new MockDeployer();
    
    // 使用現有的測試任務
    $job_id = '2506302336-TEST';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    
    echo "📋 任務資訊:\n";
    echo "Job ID: {$job_id}\n";
    echo "工作目錄: {$work_dir}\n\n";
    
    // 檢查必要檔案
    $required_files = [
        $work_dir . '/config/processed_data.json',
        $work_dir . '/json/image-prompts.json'
    ];
    
    foreach ($required_files as $file) {
        if (!file_exists($file)) {
            throw new Exception("❌ 必要檔案不存在: {$file}");
        }
        echo "✅ 檔案存在: " . basename($file) . "\n";
    }
    
    // 檢查 API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key')
    ];
    
    echo "\n🔑 API 憑證檢查:\n";
    echo "OpenAI: " . ($openai_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n";
    
    if (!$openai_config['api_key']) {
        echo "\n⚠️  警告: OpenAI API 金鑰未設定，無法進行圖片生成\n";
        echo "請設定後再執行測試\n";
        exit(1);
    }
    
    echo "\n🚀 準備執行優化版圖片生成...\n";
    echo "⏰ 預估時間: 5-10 分鐘 (僅生成關鍵圖片)\n";
    echo "💰 預估費用: $0.20-0.50 (僅生成 5-8 張圖片)\n\n";
    
    echo "是否要繼續執行圖片生成？ (y/N): ";
    $handle = fopen("php://stdin", "r");
    $continue_response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($continue_response) !== 'y') {
        echo "操作已取消\n";
        exit(0);
    }
    
    echo "\n🔧 開始執行優化版圖片生成...\n\n";
    
    // 執行優化版圖片生成
    ob_start();
    $result = include 'step-10-optimized.php';
    $output = ob_get_clean();
    
    echo $output;
    
    // 檢查結果
    if ($result && isset($result['status'])) {
        if ($result['status'] === 'success') {
            echo "\n✅ 圖片生成成功！\n\n";
            
            echo "📊 生成統計:\n";
            echo "原始提示數量: " . ($result['total_prompts'] ?? 0) . "\n";
            echo "實際生成/複製: " . ($result['generated_count'] ?? 0) . "\n";
            echo "節省比例: " . round((1 - ($result['generated_count'] ?? 0) / ($result['total_prompts'] ?? 1)) * 100, 1) . "%\n";
            
            // 檢查生成的圖片檔案
            $images_dir = $result['images_dir'] ?? '';
            if ($images_dir && is_dir($images_dir)) {
                echo "\n📁 生成的圖片檔案:\n";
                $files = scandir($images_dir);
                $image_files = array_filter($files, function($file) {
                    return $file !== '.' && $file !== '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
                });
                
                foreach ($image_files as $file) {
                    $file_path = $images_dir . '/' . $file;
                    $size = filesize($file_path);
                    echo "  📷 {$file} (" . formatFileSize($size) . ")\n";
                }
                
                echo "\n📄 檢查生成報告:\n";
                $report_file = $images_dir . '/generation-report.json';
                if (file_exists($report_file)) {
                    $report = json_decode(file_get_contents($report_file), true);
                    echo "  ✅ 報告檔案已生成\n";
                    echo "  📊 詳細統計: " . json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
            
        } else {
            echo "\n❌ 圖片生成失敗\n";
            echo "錯誤訊息: " . ($result['message'] ?? '未知錯誤') . "\n";
        }
    } else {
        echo "\n❌ 圖片生成異常，無回傳結果\n";
    }
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

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