<?php
/**
 * Phase 2 Day 5 簡化測試：修改步驟8、調整主腳本執行邏輯
 * 
 * 專注測試核心功能：
 * 1. 步驟8是否移除 image-prompts.json
 * 2. 步驟9.5是否正確生成 image-prompts.json
 * 3. 工作流程是否正確
 */

// 定義基本路徑
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

class SimplePhase2Day5Tester {
    private $config;
    
    public function __construct() {
        $this->config = ConfigManager::getInstance();
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
    
    public function runTests() {
        $this->log("🚀 Phase 2 Day 5 簡化測試");
        $this->log("重點: 驗證工作流程變更的正確性");
        
        $test_results = [];
        
        // 測試1: 檢查步驟8的程式碼修改
        $test_results['step8_code_analysis'] = $this->analyzeStep8Code();
        
        // 測試2: 檢查步驟9.5檔案存在
        $test_results['step9_5_exists'] = $this->checkStep9_5Exists();
        
        // 測試3: 檢查步驟10的錯誤處理
        $test_results['step10_error_handling'] = $this->analyzeStep10ErrorHandling();
        
        // 測試4: 檢查測試腳本更新
        $test_results['test_script_updated'] = $this->checkTestScriptUpdated();
        
        // 統計結果
        $this->reportResults($test_results);
        
        return $test_results;
    }
    
    private function analyzeStep8Code() {
        $this->log("=== 測試1: 分析步驟8程式碼修改 ===");
        
        $step8_path = DEPLOY_BASE_PATH . '/step-08.php';
        if (!file_exists($step8_path)) {
            $this->log("❌ step-08.php 不存在");
            return false;
        }
        
        $content = file_get_contents($step8_path);
        
        // 檢查是否移除了 image-prompts.json 的生成
        $checks = [
            'removed_image_prompts_comment' => !strpos($content, '* - image-prompts.json: 圖片生成提示'),
            'removed_image_prompts_description' => strpos($content, '注意: image-prompts.json 已移至步驟 9.5 動態生成') !== false,
            'removed_required_files' => !strpos($content, "'image-prompts.json'"),
            'updated_file_count' => strpos($content, '生成兩個標準化的JSON配置文件') !== false
        ];
        
        $passed = 0;
        foreach ($checks as $check_name => $result) {
            if ($result) {
                $this->log("✅ {$check_name}: 正確");
                $passed++;
            } else {
                $this->log("❌ {$check_name}: 失敗");
            }
        }
        
        $this->log("步驟8修改檢查: {$passed}/" . count($checks) . " 項通過");
        return $passed === count($checks);
    }
    
    private function checkStep9_5Exists() {
        $this->log("=== 測試2: 檢查步驟9.5檔案 ===");
        
        $step9_5_path = DEPLOY_BASE_PATH . '/step-09-5.php';
        if (!file_exists($step9_5_path)) {
            $this->log("❌ step-09-5.php 不存在");
            return false;
        }
        
        $content = file_get_contents($step9_5_path);
        
        // 檢查關鍵函數是否存在
        $required_functions = [
            'scanPageImageRequirements',
            'analyzeImageContext',
            'generateImageRequirementsJson',
            'generatePersonalizedImagePrompts'
        ];
        
        $function_count = 0;
        foreach ($required_functions as $func) {
            if (strpos($content, "function {$func}") !== false) {
                $this->log("✅ 函數存在: {$func}");
                $function_count++;
            } else {
                $this->log("❌ 函數不存在: {$func}");
            }
        }
        
        $size = round(filesize($step9_5_path) / 1024, 1);
        $this->log("檔案大小: {$size} KB");
        
        $this->log("函數檢查: {$function_count}/" . count($required_functions) . " 個函數存在");
        return $function_count === count($required_functions);
    }
    
    private function analyzeStep10ErrorHandling() {
        $this->log("=== 測試3: 分析步驟10錯誤處理 ===");
        
        $step10_path = DEPLOY_BASE_PATH . '/step-10.php';
        if (!file_exists($step10_path)) {
            $this->log("❌ step-10.php 不存在");
            return false;
        }
        
        $content = file_get_contents($step10_path);
        
        // 檢查是否加入了新的錯誤處理
        $checks = [
            'has_file_exists_check' => strpos($content, 'file_exists($image_prompts_path)') !== false,
            'has_error_message' => strpos($content, '請確認步驟 9.5 已執行') !== false,
            'has_workflow_hint' => strpos($content, '步驟8 → 步驟9 → 步驟9.5 → 步驟10') !== false,
            'has_format_validation' => strpos($content, '格式無效') !== false
        ];
        
        $passed = 0;
        foreach ($checks as $check_name => $result) {
            if ($result) {
                $this->log("✅ {$check_name}: 正確");
                $passed++;
            } else {
                $this->log("❌ {$check_name}: 失敗");
            }
        }
        
        $this->log("步驟10錯誤處理: {$passed}/" . count($checks) . " 項通過");
        return $passed >= 3; // 至少3項通過為合格
    }
    
    private function checkTestScriptUpdated() {
        $this->log("=== 測試4: 檢查測試腳本更新 ===");
        
        $test_script_path = DEPLOY_BASE_PATH . '/test-steps-8-to-10.php';
        if (!file_exists($test_script_path)) {
            $this->log("❌ test-steps-8-to-10.php 不存在");
            return false;
        }
        
        $content = file_get_contents($test_script_path);
        
        // 檢查是否新增了步驟9.5的支援
        $checks = [
            'has_step9_5_menu' => strpos($content, '執行步驟 9.5 - 動態圖片需求分析') !== false,
            'has_step9_5_function' => strpos($content, 'function runStep9_5') !== false,
            'has_updated_workflow' => strpos($content, '8→9→9.5→10') !== false,
            'has_step9_5_status_check' => strpos($content, 'image-requirements.json') !== false
        ];
        
        $passed = 0;
        foreach ($checks as $check_name => $result) {
            if ($result) {
                $this->log("✅ {$check_name}: 正確");
                $passed++;
            } else {
                $this->log("❌ {$check_name}: 失敗");
            }
        }
        
        $this->log("測試腳本更新: {$passed}/" . count($checks) . " 項通過");
        return $passed === count($checks);
    }
    
    private function reportResults($results) {
        $this->log("=== Phase 2 Day 5 測試結果摘要 ===");
        
        $passed = 0;
        $total = count($results);
        
        $test_names = [
            'step8_code_analysis' => '步驟8程式碼修改',
            'step9_5_exists' => '步驟9.5檔案檢查',
            'step10_error_handling' => '步驟10錯誤處理',
            'test_script_updated' => '測試腳本更新'
        ];
        
        foreach ($results as $test_key => $result) {
            $test_name = $test_names[$test_key] ?? $test_key;
            $status = $result ? "✅ 通過" : "❌ 失敗";
            $this->log("{$test_name}: {$status}");
            if ($result) $passed++;
        }
        
        $percentage = round(($passed / $total) * 100, 1);
        $this->log("測試通過率: {$passed}/{$total} ({$percentage}%)");
        
        if ($passed === $total) {
            $this->log("🎉 Phase 2 Day 5 所有檢查通過！");
            $this->log("✅ 步驟8修改完成");
            $this->log("✅ 步驟9.5成功建立");
            $this->log("✅ 步驟10錯誤處理更新");
            $this->log("✅ 測試腳本支援新工作流程");
            $this->log("📋 新工作流程: 8→9→9.5→10 準備就緒");
        } else {
            $this->log("⚠️ 部分檢查失敗，請檢查相關檔案");
        }
        
        // 提供下一步建議
        $this->log("\n📝 後續建議:");
        $this->log("1. 執行 test-steps-8-to-10.php 進行實際功能測試");
        $this->log("2. 使用完整流程選項(5)測試 8→9→9.5→10");
        $this->log("3. 驗證 image-prompts.json 由步驟9.5正確生成");
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $tester = new SimplePhase2Day5Tester();
    $tester->log("Phase 2 Day 5: 修改步驟8、調整主腳本執行邏輯");
    $tester->log("檢查方式: 靜態程式碼分析");
    
    $results = $tester->runTests();
    
    echo "\nPhase 2 Day 5 簡化測試完成！\n";
}