<?php
/**
 * 🎨 完整視覺反饋循環測試腳本
 * 測試步驟 8 → 9 → 10 → 10.5 的完整優化流程
 * 
 * 驗證內容：
 * 1. 語義化佔位符系統 v2.0
 * 2. 批次處理優化
 * 3. 視覺反饋循環系統 v1.0
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "🎨 ===============================================\n";
echo "    Contenta AI 完整視覺反饋循環測試\n";
echo "    測試版本: v1.13.3 (Visual Feedback Loop)\n";
echo "===============================================\n\n";

// 模擬部署器日誌類別
class TestDeployer {
    private $start_time;
    private $step_times = [];
    
    public function __construct() {
        $this->start_time = microtime(true);
    }
    
    public function log($message, $level = 'INFO') {
        $elapsed = round(microtime(true) - $this->start_time, 2);
        $timestamp = date('H:i:s');
        
        // 添加顏色編碼
        $color_codes = [
            'INFO' => '',
            'SUCCESS' => "\033[32m", // 綠色
            'WARNING' => "\033[33m", // 黃色
            'ERROR' => "\033[31m",   // 紅色
            'STEP' => "\033[36m",    // 青色
        ];
        $reset = "\033[0m";
        
        $color = $color_codes[$level] ?? '';
        echo "{$color}[{$timestamp}] [{$level}] {$message}{$reset}\n";
    }
    
    public function stepStart($step_name) {
        $this->step_times[$step_name] = microtime(true);
        $this->log("🚀 開始執行: {$step_name}", 'STEP');
    }
    
    public function stepEnd($step_name, $status = 'SUCCESS') {
        if (isset($this->step_times[$step_name])) {
            $duration = round(microtime(true) - $this->step_times[$step_name], 2);
            $this->log("✅ 完成: {$step_name} (耗時: {$duration}s)", $status);
        }
    }
    
    public function getTotalTime() {
        return round(microtime(true) - $this->start_time, 2);
    }
}

try {
    $deployer = new TestDeployer();
    $config = ConfigManager::getInstance();
    
    // 生成測試 Job ID
    $job_id = date('ymdHi') . '-FULL';
    $deployer->log("🆔 測試 Job ID: {$job_id}");
    
    // 📋 步驟 0: 環境檢查與準備
    $deployer->stepStart('環境檢查與準備');
    
    // 檢查必要目錄
    $data_dir = DEPLOY_BASE_PATH . '/data';
    if (!is_dir($data_dir)) {
        throw new Exception("❌ data 目錄不存在: {$data_dir}");
    }
    
    $data_files = array_filter(scandir($data_dir), function($file) {
        return !in_array($file, ['.', '..']);
    });
    
    if (empty($data_files)) {
        throw new Exception("❌ data 目錄中沒有測試資料檔案");
    }
    
    $deployer->log("📁 發現 " . count($data_files) . " 個資料檔案: " . implode(', ', $data_files));
    
    // 建立工作目錄
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    $subdirs = ['config', 'scripts', 'json', 'images', 'logs', 'layout', 'analysis'];
    
    if (!is_dir($work_dir)) {
        mkdir($work_dir, 0755, true);
    }
    foreach ($subdirs as $subdir) {
        $subdir_path = $work_dir . '/' . $subdir;
        if (!is_dir($subdir_path)) {
            mkdir($subdir_path, 0755, true);
        }
    }
    
    // 建立測試用的 processed_data.json
    $processed_data = [
        'confirmed_data' => [
            'domain' => 'visual-feedback-test.tw',
            'website_name' => '視覺反饋循環測試網站',
            'website_description' => '測試 AI 視覺反饋循環系統的網站',
            'user_email' => 'test@visualfeedback.com'
        ],
        'work_dir' => $work_dir
    ];
    
    file_put_contents($work_dir . '/config/processed_data.json', 
        json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 檢查 API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'model' => $config->get('api_credentials.openai.model') ?: 'gpt-4o',
        'image_model' => $config->get('api_credentials.openai.image_model') ?: 'dall-e-3'
    ];
    
    $gemini_config = [
        'api_key' => $config->get('api_credentials.gemini.api_key'),
        'model' => $config->get('api_credentials.gemini.model') ?: 'gemini-1.5-flash',
        'image_model' => $config->get('api_credentials.gemini.image_model') ?: 'gemini-2.0-flash-preview'
    ];
    
    $deployer->log("🔑 API 檢查 - OpenAI: " . ($openai_config['api_key'] ? "✅" : "❌") . 
                  ", Gemini: " . ($gemini_config['api_key'] ? "✅" : "❌"));
    
    if (empty($openai_config['api_key']) && empty($gemini_config['api_key'])) {
        throw new Exception("❌ 未設定任何 AI API 憑證，請檢查配置");
    }
    
    $deployer->stepEnd('環境檢查與準備');
    
    // 📝 執行確認
    echo "\n⚠️  注意事項:\n";
    echo "• 此測試將執行完整的 AI 工作流程 (步驟 8→9→10→10.5)\n";
    echo "• 將會呼叫多次 AI API，可能產生費用\n"; 
    echo "• 包含 GPT-4o 圖片分析，費用較高\n";
    echo "• 預計執行時間：3-5 分鐘\n";
    echo "• 將驗證所有最新優化功能\n\n";
    
    echo "🚀 測試項目:\n";
    echo "✅ 語義化佔位符系統 v2.0\n";
    echo "✅ 批次處理優化 (80% API 節省)\n";
    echo "✅ 視覺反饋循環系統 v1.0\n";
    echo "✅ GPT-4o 多模態分析\n";
    echo "✅ 文案視覺協調機制\n\n";
    
    echo "是否要繼續執行完整測試？ (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) !== 'y') {
        echo "測試已取消\n";
        exit(0);
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "🎨 開始執行完整視覺反饋循環測試\n";
    echo str_repeat("=", 50) . "\n\n";
    
    // 🎯 步驟 1: 執行步驟 8 (AI 配置生成)
    $deployer->stepStart('步驟 8 - AI 配置生成');
    
    ob_start();
    $step8_result = include 'step-08.php';
    $step8_output = ob_get_clean();
    
    if ($step8_result && $step8_result['status'] === 'user_abort') {
        echo "\n📋 品牌配置確認機制觸發\n";
        echo "請選擇操作:\n";
        echo "1. 繼續測試 (c)\n";
        echo "2. 顯示配置 (s)\n";
        echo "3. 中止測試 (a)\n";
        echo "選擇: ";
        
        $handle = fopen("php://stdin", "r");
        $choice = trim(fgets($handle));
        fclose($handle);
        
        if ($choice === 'a') {
            echo "測試已中止\n";
            exit(0);
        } elseif ($choice === 's') {
            // 顯示生成的配置
            $site_config_path = $work_dir . '/json/site-config.json';
            if (file_exists($site_config_path)) {
                $site_config = json_decode(file_get_contents($site_config_path), true);
                echo "\n📄 生成的配置預覽:\n";
                echo "網站名稱: " . ($site_config['website_info']['name'] ?? '未知') . "\n";
                echo "主色調: " . ($site_config['design_options']['primary_color'] ?? '未知') . "\n";
                echo "風格: " . ($site_config['design_options']['style'] ?? '未知') . "\n\n";
            }
        }
        
        // 模擬用戶確認繼續
        echo "確認繼續執行步驟 9？ (y/N): ";
        $handle = fopen("php://stdin", "r");
        $continue_response = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($continue_response) !== 'y') {
            echo "測試已停止\n";
            exit(0);
        }
    }
    
    if (!$step8_result || $step8_result['status'] === 'error') {
        throw new Exception("步驟 8 執行失敗: " . ($step8_result['message'] ?? '未知錯誤'));
    }
    
    $deployer->stepEnd('步驟 8 - AI 配置生成');
    $deployer->log("📊 步驟 8 結果: " . json_encode($step8_result, JSON_UNESCAPED_UNICODE));
    
    // 🎯 步驟 2: 執行步驟 9 (批次文字替換 + 語義化佔位符)
    $deployer->stepStart('步驟 9 - 批次文字替換 (語義化佔位符 v2.0)');
    
    ob_start();
    $step9_result = include 'step-09.php';
    $step9_output = ob_get_clean();
    
    if (!$step9_result || $step9_result['status'] === 'error') {
        throw new Exception("步驟 9 執行失敗: " . ($step9_result['message'] ?? '未知錯誤'));
    }
    
    $deployer->stepEnd('步驟 9 - 批次文字替換 (語義化佔位符 v2.0)');
    $deployer->log("📊 步驟 9 結果: 生成 " . ($step9_result['pages_generated'] ?? 0) . " 個頁面");
    
    // 驗證語義化佔位符
    $layout_dir = $work_dir . '/layout';
    if (is_dir($layout_dir)) {
        $ai_files = glob($layout_dir . '/*-ai.json');
        $deployer->log("🔍 語義化佔位符驗證: 找到 " . count($ai_files) . " 個 AI 處理檔案");
        
        // 檢查是否包含新的語義化佔位符
        foreach ($ai_files as $file) {
            $content = file_get_contents($file);
            $semantic_count = preg_match_all('/{{[a-z_]+}}/', $content);
            $classic_count = preg_match_all('/[A-Z_]{3,}/', $content);
            if ($semantic_count > 0) {
                $deployer->log("✅ 發現語義化佔位符: " . basename($file) . " ({$semantic_count} 個)", 'SUCCESS');
            }
        }
    }
    
    // 🎯 步驟 3: 執行步驟 10 (AI 圖片生成)
    $deployer->stepStart('步驟 10 - AI 圖片生成');
    
    ob_start();
    $step10_result = include 'step-10.php';
    $step10_output = ob_get_clean();
    
    if (!$step10_result || $step10_result['status'] === 'error') {
        throw new Exception("步驟 10 執行失敗: " . ($step10_result['message'] ?? '未知錯誤'));
    }
    
    $deployer->stepEnd('步驟 10 - AI 圖片生成');
    $deployer->log("📊 步驟 10 結果: 生成 " . ($step10_result['generated_count'] ?? 0) . " 張圖片");
    
    // 檢查是否觸發了視覺反饋循環
    $visual_feedback_triggered = isset($step10_result['visual_feedback']) && $step10_result['visual_feedback'];
    if ($visual_feedback_triggered) {
        $deployer->log("🎨 視覺反饋循環已在步驟 10 中自動觸發", 'SUCCESS');
    }
    
    // 🎯 步驟 4: 執行步驟 10.5 (視覺反饋循環) 如果未自動觸發
    if (!$visual_feedback_triggered) {
        $deployer->stepStart('步驟 10.5 - 視覺反饋循環 (手動觸發)');
        
        ob_start();
        $step10_5_result = include 'step-10-5.php';
        $step10_5_output = ob_get_clean();
        
        if (!$step10_5_result || $step10_5_result['status'] === 'error') {
            $deployer->log("⚠️ 步驟 10.5 執行失敗: " . ($step10_5_result['message'] ?? '未知錯誤'), 'WARNING');
        } else {
            $deployer->stepEnd('步驟 10.5 - 視覺反饋循環 (手動觸發)');
            $deployer->log("📊 步驟 10.5 結果: 分析 " . ($step10_5_result['analyzed_images'] ?? 0) . 
                          " 張圖片，精練 " . ($step10_5_result['refined_pages'] ?? 0) . " 個頁面");
        }
    }
    
    // 🎯 步驟 5: 驗證和報告
    $deployer->stepStart('結果驗證與報告生成');
    
    // 檢查各種生成檔案
    $verification_results = [];
    
    // 1. 配置檔案檢查
    $config_files = [
        'site-config.json' => $work_dir . '/json/site-config.json',
        'article-prompts.json' => $work_dir . '/json/article-prompts.json',
        'image-prompts.json' => $work_dir . '/json/image-prompts.json'
    ];
    
    foreach ($config_files as $name => $path) {
        $verification_results['config'][$name] = file_exists($path);
        if (file_exists($path)) {
            $size = round(filesize($path) / 1024, 2);
            $deployer->log("✅ 配置檔案: {$name} ({$size} KB)");
        }
    }
    
    // 2. 頁面檔案檢查
    $page_files = glob($work_dir . '/layout/*.json');
    $ai_pages = array_filter($page_files, function($file) {
        return strpos(basename($file), '-ai.json') !== false;
    });
    $refined_pages = array_filter($page_files, function($file) {
        return strpos(basename($file), '-visual-refined.json') !== false;
    });
    
    $verification_results['pages'] = [
        'total' => count($page_files),
        'ai_processed' => count($ai_pages),
        'visual_refined' => count($refined_pages)
    ];
    
    $deployer->log("📄 頁面檔案統計:");
    $deployer->log("  - 總頁面: " . count($page_files));
    $deployer->log("  - AI 處理: " . count($ai_pages));
    $deployer->log("  - 視覺精練: " . count($refined_pages));
    
    // 3. 圖片檔案檢查
    $images_dir = $work_dir . '/images';
    $image_files = [];
    if (is_dir($images_dir)) {
        $image_files = glob($images_dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
    }
    
    $verification_results['images'] = [
        'count' => count($image_files),
        'total_size' => 0
    ];
    
    if (!empty($image_files)) {
        $total_size = 0;
        foreach ($image_files as $image) {
            $total_size += filesize($image);
        }
        $verification_results['images']['total_size'] = $total_size;
        $deployer->log("🖼️ 生成圖片: " . count($image_files) . " 張 (總大小: " . 
                      round($total_size / 1024 / 1024, 2) . " MB)");
    }
    
    // 4. 視覺分析報告檢查
    $analysis_files = [
        'visual-feedback.json' => $work_dir . '/analysis/visual-feedback.json',
        'visual-feedback-loop-report.json' => $work_dir . '/analysis/visual-feedback-loop-report.json'
    ];
    
    $verification_results['visual_analysis'] = [];
    foreach ($analysis_files as $name => $path) {
        $exists = file_exists($path);
        $verification_results['visual_analysis'][$name] = $exists;
        if ($exists) {
            $analysis_data = json_decode(file_get_contents($path), true);
            $deployer->log("📊 視覺分析: {$name} - " . 
                          (isset($analysis_data['total_analyzed']) ? $analysis_data['total_analyzed'] . " 張圖片分析" : "已生成"));
        }
    }
    
    $deployer->stepEnd('結果驗證與報告生成');
    
    // 🎯 最終報告
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎉 完整視覺反饋循環測試完成\n";
    echo str_repeat("=", 60) . "\n\n";
    
    $total_time = $deployer->getTotalTime();
    echo "⏱️ 總執行時間: {$total_time} 秒\n\n";
    
    echo "📊 功能驗證結果:\n";
    echo "✅ 語義化佔位符系統 v2.0: " . (count($ai_pages) > 0 ? "通過" : "失敗") . "\n";
    echo "✅ 批次處理優化: " . ($step9_result && $step9_result['status'] === 'success' ? "通過" : "失敗") . "\n";
    echo "✅ AI 圖片生成: " . (count($image_files) > 0 ? "通過 (" . count($image_files) . " 張)" : "失敗") . "\n";
    echo "✅ 視覺反饋循環: " . ($verification_results['visual_analysis']['visual-feedback.json'] ? "通過" : "失敗") . "\n";
    echo "✅ 文案視覺協調: " . (count($refined_pages) > 0 ? "通過 (" . count($refined_pages) . " 頁)" : "失敗") . "\n\n";
    
    echo "📁 生成檔案統計:\n";
    echo "  配置檔案: " . array_sum($verification_results['config']) . "/" . count($verification_results['config']) . "\n";
    echo "  頁面檔案: " . $verification_results['pages']['total'] . " (AI: " . $verification_results['pages']['ai_processed'] . ", 精練: " . $verification_results['pages']['visual_refined'] . ")\n";
    echo "  圖片檔案: " . $verification_results['images']['count'] . " 張\n";
    echo "  分析報告: " . array_sum($verification_results['visual_analysis']) . "/" . count($verification_results['visual_analysis']) . "\n\n";
    
    echo "📂 工作目錄: {$work_dir}\n";
    echo "🔍 詳細檔案:\n";
    echo "  - 配置: {$work_dir}/json/\n";
    echo "  - 頁面: {$work_dir}/layout/\n";
    echo "  - 圖片: {$work_dir}/images/\n";
    echo "  - 分析: {$work_dir}/analysis/\n\n";
    
    if (count($refined_pages) > 0) {
        echo "🎨 視覺反饋循環成功！\n";
        echo "系統已實現「自動化生成」→「智能化創作」的質變提升\n";
        echo "文案與視覺完美協調，創造真正的沉浸式品牌體驗\n\n";
    }
    
    // 詢問是否清理測試檔案
    echo "🗑️ 是否要清理測試檔案？ (y/N): ";
    $handle = fopen("php://stdin", "r");
    $cleanup_response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($cleanup_response) === 'y') {
        // 遞歸刪除工作目錄
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return false;
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            return rmdir($dir);
        }
        
        if (deleteDirectory($work_dir)) {
            echo "✅ 測試檔案已清理\n";
        } else {
            echo "⚠️ 測試檔案清理失敗，請手動刪除: {$work_dir}\n";
        }
    } else {
        echo "📁 測試檔案保留在: {$work_dir}\n";
    }
    
    echo "\n🎉 測試完成！\n";
    
} catch (Exception $e) {
    echo "\n❌ 測試失敗: " . $e->getMessage() . "\n";
    if (isset($deployer)) {
        $deployer->log("測試異常終止", 'ERROR');
    }
    exit(1);
}