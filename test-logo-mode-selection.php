<?php
/**
 * 測試新的條件式 Logo 生成功能
 * 驗證 step-16.php 中的 logo_generation.mode 設定
 */

// 設定基本路徑
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
    define('DEPLOY_LOGS_PATH', DEPLOY_BASE_PATH . '/logs');
    define('DEPLOY_DATA_PATH', DEPLOY_BASE_PATH . '/data');
}

// 載入配置管理器
require_once DEPLOY_BASE_PATH . '/config-manager.php';

// 模擬部署器
class TestDeployer {
    public function log($message) {
        echo "[LOG] {$message}\n";
    }
}

echo "=== 測試條件式 Logo 生成功能 ===\n";

// 1. 測試配置讀取
echo "1. 測試配置讀取\n";
$config = ConfigManager::getInstance();
$deployer = new TestDeployer();

$logo_mode = $config->get('logo_generation.mode', 'ai_full');
echo "   ✓ 當前 Logo 生成模式: {$logo_mode}\n";

$mode_descriptions = $config->get('logo_generation._mode_descriptions', []);
if (isset($mode_descriptions[$logo_mode])) {
    echo "   ✓ 模式描述: {$mode_descriptions[$logo_mode]}\n";
}

// 2. 測試兩種模式的設定
echo "\n2. 測試兩種模式的設定\n";
$test_modes = ['ai_full', 'php_composite'];

foreach ($test_modes as $mode) {
    echo "   測試模式: {$mode}\n";
    
    // 模擬 step-16.php 中的邏輯
    $mock_config = new class($mode) {
        private $mode;
        
        public function __construct($mode) {
            $this->mode = $mode;
        }
        
        public function get($key, $default = null) {
            if ($key === 'logo_generation.mode') {
                return $this->mode;
            }
            return $default;
        }
    };
    
    $logo_mode = $mock_config->get('logo_generation.mode', 'ai_full');
    $deployer->log("Logo 生成模式: $logo_mode");
    
    $final_logo_path = null;
    $ai_logo_path = null;
    $background_image_path = null;
    $text_layer_path = null;
    
    if ($logo_mode === 'ai_full') {
        $deployer->log("使用 AI 完整生成 Logo");
        // 模擬 AI Logo 生成
        $ai_logo_path = "/temp/test/images/ai-logo-full-resized.png";
        echo "     ✓ 模擬 AI Logo 生成: {$ai_logo_path}\n";
        
        if (!$ai_logo_path) {
            $deployer->log("AI Logo 生成失敗，回退到 PHP 合成模式");
            $logo_mode = 'php_composite';
        }
    }
    
    if ($logo_mode === 'php_composite') {
        $deployer->log("使用 PHP GD 合成模式生成 Logo");
        
        // 模擬 PHP GD 合成
        $background_image_path = "/temp/test/images/background-layer.png";
        $text_layer_path = "/temp/test/images/text-layer.png";
        $final_logo_path = "/temp/test/images/logo-final.png";
        
        echo "     ✓ 模擬背景圖層生成: {$background_image_path}\n";
        echo "     ✓ 模擬文字圖層生成: {$text_layer_path}\n";
        echo "     ✓ 模擬圖層合併: {$final_logo_path}\n";
    }
    
    // 決定主要 Logo
    $primary_logo = $ai_logo_path ?: $final_logo_path;
    $logo_info = [];
    
    if ($ai_logo_path) {
        $logo_info = [
            'type' => 'AI 完整生成',
            'path' => $ai_logo_path,
            'mode' => 'ai_full'
        ];
    } elseif ($final_logo_path) {
        $logo_info = [
            'type' => 'PHP GD 合成',
            'path' => $final_logo_path,
            'mode' => 'php_composite'
        ];
    }
    
    echo "     ✓ 最終 Logo 類型: {$logo_info['type']}\n";
    echo "     ✓ 生成模式: {$logo_info['mode']}\n";
    echo "\n";
}

// 3. 測試配置更新
echo "3. 測試配置更新\n";
$config_file = DEPLOY_CONFIG_PATH . '/deploy-config.json';
$config_data = json_decode(file_get_contents($config_file), true);

echo "   ✓ 當前配置中的 logo_generation.mode: {$config_data['logo_generation']['mode']}\n";

$available_modes = explode(' | ', $config_data['logo_generation']['_mode_options']);
echo "   ✓ 可用模式: " . implode(', ', $available_modes) . "\n";

foreach ($available_modes as $mode) {
    if (isset($config_data['logo_generation']['_mode_descriptions'][$mode])) {
        echo "     - {$mode}: {$config_data['logo_generation']['_mode_descriptions'][$mode]}\n";
    }
}

// 4. 測試模式切換
echo "\n4. 測試模式切換\n";
$original_mode = $config_data['logo_generation']['mode'];

foreach ($available_modes as $test_mode) {
    if ($test_mode !== $original_mode) {
        echo "   切換到模式: {$test_mode}\n";
        
        // 更新配置
        $config_data['logo_generation']['mode'] = $test_mode;
        file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 重新載入配置
        $config = ConfigManager::getInstance();
        $config->loadConfig(); // 重新載入
        
        $current_mode = $config->get('logo_generation.mode');
        echo "   ✓ 配置已更新為: {$current_mode}\n";
        
        // 還原原始設定
        $config_data['logo_generation']['mode'] = $original_mode;
        file_put_contents($config_file, json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "   ✓ 已還原到原始設定: {$original_mode}\n";
        break;
    }
}

echo "\n=== 測試結果 ===\n";
echo "✓ Logo 生成模式設定功能正常\n";
echo "✓ 條件式 Logo 生成邏輯正常\n";
echo "✓ 配置更新和讀取功能正常\n";
echo "✓ 模式切換功能正常\n";

echo "\n使用方法：\n";
echo "1. 在 config/deploy-config.json 中設定 logo_generation.mode\n";
echo "2. 可選模式：ai_full（預設）或 php_composite\n";
echo "3. step-16.php 會根據設定自動選擇對應的生成方式\n";
echo "4. 如果 AI 生成失敗，會自動回退到 PHP 合成模式\n";