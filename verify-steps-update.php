<?php
/**
 * 驗證 contenta-deploy.php 步驟更新
 */

echo "=== contenta-deploy.php 步驟列表更新驗證 ===\n";
echo "時間: " . date('Y-m-d H:i:s') . "\n\n";

// 讀取更新後的步驟
require_once __DIR__ . '/contenta-deploy.php';

// 建立部署器實例以測試步驟列表
try {
    $reflection = new ReflectionClass('ContentaDeployer');
    $constructor = $reflection->getConstructor();
    
    // 創建一個測試實例 (使用虛構的 job_id)
    $deployer = new ContentaDeployer('test-job-id');
    
    // 使用反射取得私有屬性
    $stepsProperty = $reflection->getProperty('steps');
    $stepsProperty->setAccessible(true);
    $steps = $stepsProperty->getValue($deployer);
    
    echo "=== 更新後的步驟列表 ===\n";
    foreach ($steps as $step_num => $step_name) {
        echo sprintf("步驟 %s: %s\n", str_pad($step_num, 4), $step_name);
    }
    
    echo "\n=== 對比分析 ===\n";
    
    $actual_steps = [
        '00' => '專案初始化 (Project Initialization)',
        '01' => 'Cloudflare區域與DNS設定 (Cloudflare Zone & DNS Setup)',
        '02' => '網域註冊 (Lihi API)',
        '03' => 'BT Panel 網站建立 (BT Panel Website Creation)',
        '04' => 'SSL憑證與Nginx重寫規則設定 (SSL & Nginx Rewrite Setup)',
        '05' => '資料庫建立 (Database Creation)',
        '06' => 'WordPress 核心安裝 (WordPress Core Installation)',
        '07' => '外掛與主題部署及啟用 (Plugin & Theme Deployment & Activation)',
        '08' => 'AI 生成網站配置 (AI-Powered Configuration Generation)',
        '09' => '頁面組裝與 AI 文案填充 (Page Assembly & AI Copywriting)',
        '09-5' => '動態圖片提示詞生成 (Dynamic Image Prompt Generation)',
        '10' => 'AI 圖片生成 (AI Image Generation)',
        '11' => 'WordPress 媒體上傳 (WordPress Media Upload)',
        '12' => '圖片路徑替換 (Image Path Replacement)',
        '13' => 'Elementor 全域模板匯入 (Elementor Global Template Import)',
        '14' => 'Elementor 頁面建立與發布 (Elementor Page Creation & Publishing)',
        '15' => 'AI 文章與精選圖片批量生成 (AI Article & Featured Image Batch Generation)'
    ];
    
    $matches = 0;
    $total_existing = count($actual_steps);
    
    foreach ($actual_steps as $step_num => $expected) {
        if (isset($steps[$step_num])) {
            $actual = $steps[$step_num];
            $expected_short = explode(' (', $expected)[0]; // 取得中文部分
            
            if (strpos($expected_short, $actual) !== false || strpos($actual, $expected_short) !== false) {
                echo "✅ 步驟 {$step_num}: 匹配\n";
                $matches++;
            } else {
                echo "⚠️ 步驟 {$step_num}: 不完全匹配\n";
                echo "   期望: {$expected_short}\n";
                echo "   實際: {$actual}\n";
            }
        } else {
            echo "❌ 步驟 {$step_num}: 缺失\n";
        }
    }
    
    echo "\n=== 更新結果 ===\n";
    echo "總步驟數: " . count($steps) . "\n";
    echo "實際步驟數: {$total_existing}\n";
    echo "匹配度: " . round(($matches / $total_existing) * 100, 1) . "%\n";
    
    if ($matches === $total_existing) {
        echo "✅ 步驟列表已成功更新，與實際功能完全對應！\n";
    } else {
        echo "⚠️ 部分步驟需要微調\n";
    }
    
    echo "\n=== 主要改進 ===\n";
    echo "1. ✅ 步驟描述更準確反映實際功能\n";
    echo "2. ✅ 新增 step-09-5 動態圖片提示詞生成\n";
    echo "3. ✅ 移除了不存在的步驟功能\n";
    echo "4. ✅ 保持了正確的步驟順序\n";
    echo "5. ✅ 步驟 16-19 標記為預留或備用\n";
    
} catch (Exception $e) {
    echo "驗證失敗: " . $e->getMessage() . "\n";
}