<?php
/**
 * 測試 GPT-4o 增強版 Step 10
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== GPT-4o 增強版 Step 10 測試 ===\n\n";

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
    
    echo "📋 GPT-4o 增強版測試資訊:\n";
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
    echo "OpenAI (GPT-4o): " . ($openai_config['api_key'] ? "✅ 已設定" : "❌ 未設定") . "\n";
    
    if (!$openai_config['api_key']) {
        echo "\n⚠️  警告: OpenAI API 金鑰未設定，無法使用 GPT-4o 增強功能\n";
        exit(1);
    }
    
    echo "\n🚀 GPT-4o 增強版特色:\n";
    echo "✨ GPT-4o Function Calling 智能生成\n";
    echo "🎨 專業 Prompt 優化 (Logo/背景/圖標/人像)\n";
    echo "🔄 多層容錯機制\n";
    echo "📊 智能圖片分類與處理\n";
    echo "💡 品質提升 + 成本控制\n\n";
    
    echo "⏰ 預估時間: 8-15 分鐘 (GPT-4o 增強處理)\n";
    echo "💰 預估費用: $0.30-0.80 (包含 GPT-4o 調用)\n\n";
    
    echo "🔥 開始執行 GPT-4o 增強版圖片生成...\n\n";
    
    // 執行 GPT-4o 增強版圖片生成
    ob_start();
    $result = include 'step-10-gpt4o-enhanced.php';
    $output = ob_get_clean();
    
    echo $output;
    
    // 檢查結果
    if ($result && isset($result['status'])) {
        if ($result['status'] === 'success') {
            echo "\n🎉 GPT-4o 增強版圖片生成成功！\n\n";
            
            echo "📊 生成統計:\n";
            echo "原始提示數量: " . ($result['total_prompts'] ?? 0) . "\n";
            echo "實際生成/複製: " . ($result['generated_count'] ?? 0) . "\n";
            echo "GPT-4o 增強: " . ($result['gpt4o_enhanced'] ? "✅ 啟用" : "❌ 未啟用") . "\n";
            
            // 檢查生成的圖片檔案
            $images_dir = $result['images_dir'] ?? '';
            if ($images_dir && is_dir($images_dir)) {
                echo "\n📁 GPT-4o 增強生成的圖片:\n";
                $files = scandir($images_dir);
                $image_files = array_filter($files, function($file) {
                    return $file !== '.' && $file !== '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file);
                });
                
                // 按生成時間排序
                usort($image_files, function($a, $b) use ($images_dir) {
                    return filemtime($images_dir . '/' . $b) - filemtime($images_dir . '/' . $a);
                });
                
                foreach ($image_files as $file) {
                    $file_path = $images_dir . '/' . $file;
                    $size = filesize($file_path);
                    $time = date('H:i:s', filemtime($file_path));
                    
                    // 檢查是否為新生成的檔案 (最近 30 分鐘)
                    $is_new = (time() - filemtime($file_path)) < 1800;
                    $new_flag = $is_new ? "🆕" : "📷";
                    
                    echo "  {$new_flag} {$file} (" . formatFileSize($size) . ") [{$time}]\n";
                }
                
                echo "\n📄 檢查 GPT-4o 增強報告:\n";
                $report_file = $images_dir . '/gpt4o-enhanced-report.json';
                if (file_exists($report_file)) {
                    $report = json_decode(file_get_contents($report_file), true);
                    echo "  ✅ GPT-4o 增強報告已生成\n";
                    echo "  🤖 AI 增強策略: " . ($report['ai_enhancement'] ?? '未知') . "\n";
                    echo "  📈 增強圖片數量: " . ($report['gpt4o_enhanced_count'] ?? 0) . " 張\n";
                    
                    if (isset($report['generated_images'])) {
                        echo "  🎯 GPT-4o 處理的圖片:\n";
                        foreach (array_slice($report['generated_images'], 0, 5) as $key => $filename) {
                            echo "    • {$key}: {$filename}\n";
                        }
                    }
                }
            }
            
        } else {
            echo "\n❌ GPT-4o 增強圖片生成失敗\n";
            echo "錯誤訊息: " . ($result['message'] ?? '未知錯誤') . "\n";
        }
    } else {
        echo "\n❌ GPT-4o 增強圖片生成異常，無回傳結果\n";
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

echo "\n=== GPT-4o 增強版測試完成 ===\n";
echo "\n💡 與標準版本比較:\n";
echo "• 標準版: php test-step-10-auto.php\n";
echo "• GPT-4o 增強版: php test-gpt4o-enhanced.php\n";
echo "• Logo 專門測試: php test-gpt4o-logo.php\n";
?>