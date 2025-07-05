<?php
/**
 * 測試 AI 參數功能
 * 驗證系統是否正確讀取並使用 image-prompts.json 中的 AI 參數
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== AI 參數功能測試 ===\n\n";

try {
    // 載入配置
    $config = ConfigManager::getInstance();
    
    // 載入圖片提示資料
    $job_id = '2506302336-TEST';
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    $image_prompts_path = $work_dir . '/json/image-prompts.json';
    
    if (!file_exists($image_prompts_path)) {
        throw new Exception("❌ 圖片提示檔案不存在: {$image_prompts_path}");
    }
    
    $image_prompts = json_decode(file_get_contents($image_prompts_path), true);
    
    echo "📸 分析 image-prompts.json 中的 AI 參數:\n\n";
    
    $ai_usage = ['openai' => 0, 'gemini' => 0, 'other' => 0, 'missing' => 0];
    
    foreach ($image_prompts as $key => $config_data) {
        $ai_service = $config_data['ai'] ?? null;
        
        if ($ai_service === 'openai') {
            $ai_usage['openai']++;
            echo "  🟦 {$key}: OpenAI DALL-E 3\n";
        } elseif ($ai_service === 'gemini') {
            $ai_usage['gemini']++;
            echo "  🟩 {$key}: Google Gemini Imagen\n";
        } elseif ($ai_service) {
            $ai_usage['other']++;
            echo "  🟨 {$key}: {$ai_service} (未支援)\n";
        } else {
            $ai_usage['missing']++;
            echo "  ⚪ {$key}: 無 AI 參數 (將使用預設 OpenAI)\n";
        }
    }
    
    echo "\n📊 AI 服務使用統計:\n";
    echo "OpenAI DALL-E 3: {$ai_usage['openai']} 張\n";
    echo "Google Gemini: {$ai_usage['gemini']} 張\n";
    echo "其他服務: {$ai_usage['other']} 張\n";
    echo "缺少參數: {$ai_usage['missing']} 張\n";
    
    // 檢查 API 憑證
    echo "\n🔑 API 憑證檢查:\n";
    $openai_key = $config->get('api_credentials.openai.api_key');
    $gemini_key = $config->get('api_credentials.gemini.api_key');
    
    echo "OpenAI: " . ($openai_key ? "✅ 已設定" : "❌ 未設定") . "\n";
    echo "Gemini: " . ($gemini_key ? "✅ 已設定" : "❌ 未設定") . "\n";
    
    // 分析成本效益
    echo "\n💰 成本效益分析:\n";
    
    $openai_cost = $ai_usage['openai'] * 0.04; // $0.04 per image
    $gemini_cost = $ai_usage['gemini'] * 0.02; // $0.02 per image (估計)
    $total_cost = $openai_cost + $gemini_cost;
    
    echo "OpenAI 成本: $" . number_format($openai_cost, 2) . " ({$ai_usage['openai']} 張 × $0.04)\n";
    echo "Gemini 成本: $" . number_format($gemini_cost, 2) . " ({$ai_usage['gemini']} 張 × $0.02)\n";
    echo "總估計成本: $" . number_format($total_cost, 2) . "\n";
    
    // 如果全部使用 OpenAI 的成本
    $all_openai_cost = count($image_prompts) * 0.04;
    $savings = $all_openai_cost - $total_cost;
    $savings_percent = ($savings / $all_openai_cost) * 100;
    
    echo "全用 OpenAI 成本: $" . number_format($all_openai_cost, 2) . "\n";
    echo "混合使用節省: $" . number_format($savings, 2) . " (" . number_format($savings_percent, 1) . "%)\n";
    
    // 檢查程式碼是否正確實作
    echo "\n🔧 程式碼實作檢查:\n";
    
    $step_10_optimized = file_get_contents('step-10-optimized.php');
    $step_10_new = file_get_contents('step-10-new.php');
    
    // 檢查是否有讀取 AI 參數
    $optimized_has_ai_param = strpos($step_10_optimized, '$ai_service = $image_config[\'ai\']') !== false;
    $new_has_ai_param = strpos($step_10_new, '$ai_service = $image_config[\'ai\']') !== false;
    
    echo "step-10-optimized.php AI 參數支援: " . ($optimized_has_ai_param ? "✅" : "❌") . "\n";
    echo "step-10-new.php AI 參數支援: " . ($new_has_ai_param ? "✅" : "❌") . "\n";
    
    // 檢查是否有 Gemini 函數
    $optimized_has_gemini = strpos($step_10_optimized, 'generateImageWithGemini') !== false;
    $new_has_gemini = strpos($step_10_new, 'generateImageWithGemini') !== false;
    
    echo "step-10-optimized.php Gemini 支援: " . ($optimized_has_gemini ? "✅" : "❌") . "\n";
    echo "step-10-new.php Gemini 支援: " . ($new_has_gemini ? "✅" : "❌") . "\n";
    
    // 建議
    echo "\n💡 建議:\n";
    
    if ($ai_usage['gemini'] > 0 && !$gemini_key) {
        echo "⚠️  您的 image-prompts.json 中有 {$ai_usage['gemini']} 張圖片指定使用 Gemini，但未設定 API 金鑰\n";
        echo "   請在 config/deploy-config.json 中設定 Gemini API 憑證\n";
    }
    
    if ($ai_usage['other'] > 0) {
        echo "⚠️  有 {$ai_usage['other']} 張圖片使用了不支援的 AI 服務\n";
        echo "   建議修改為 'openai' 或 'gemini'\n";
    }
    
    if ($optimized_has_ai_param && $optimized_has_gemini) {
        echo "✅ step-10-optimized.php 已完整支援 AI 參數功能\n";
    } else {
        echo "❌ step-10-optimized.php 需要更新以支援 AI 參數\n";
    }
    
    echo "\n=== 測試完成 ===\n";
    
} catch (Exception $e) {
    echo "❌ 執行過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}
?>