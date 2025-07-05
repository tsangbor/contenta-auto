<?php
/**
 * 修復 Step-07 配置問題
 * 檢查並新增缺少的配置項目
 */

// 定義基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

require_once __DIR__ . '/config-manager.php';

echo "=== Step-07 配置檢查與修復 ===\n\n";

$config = ConfigManager::getInstance();
$config_file = DEPLOY_CONFIG_PATH . '/deploy-config.json';

if (!file_exists($config_file)) {
    echo "❌ 配置檔案不存在: {$config_file}\n";
    echo "請先複製 deploy-config-example.json 到 deploy-config.json\n";
    exit(1);
}

// 讀取現有配置
$current_config = json_decode(file_get_contents($config_file), true);
if (!$current_config) {
    echo "❌ 配置檔案格式錯誤，無法解析 JSON\n";
    exit(1);
}

echo "✓ 配置檔案載入成功\n\n";

// 檢查問題 1: wordpress_admin 配置 (檢查 wordpress_install.json 和配置檔案)
echo "1. 檢查 WordPress 管理員配置...\n";

// 首先檢查是否有任何 job 的 wordpress_install.json 檔案
$temp_dirs = glob(DEPLOY_BASE_PATH . '/temp/*', GLOB_ONLYDIR);
$found_wordpress_install = false;

foreach ($temp_dirs as $temp_dir) {
    $wordpress_install_file = $temp_dir . '/wordpress_install.json';
    if (file_exists($wordpress_install_file)) {
        $wordpress_data = json_decode(file_get_contents($wordpress_install_file), true);
        if (isset($wordpress_data['limited_admin_email']) && isset($wordpress_data['limited_admin_password'])) {
            echo "✓ 找到 wordpress_install.json 檔案: " . basename($temp_dir) . "\n";
            echo "  Email: " . $wordpress_data['limited_admin_email'] . "\n";
            echo "  Password: " . str_repeat('*', strlen($wordpress_data['limited_admin_password'])) . "\n";
            $found_wordpress_install = true;
            break;
        }
    }
}

if (!$found_wordpress_install) {
    echo "⚠️ 未找到包含 limited_admin 資訊的 wordpress_install.json 檔案\n";
    echo "這通常表示步驟 06 (WordPress 安裝) 尚未執行或執行失敗\n";
    
    // 檢查配置檔案作為備用
    $wordpress_admin = $config->get('wordpress_admin');
    if (empty($wordpress_admin) || 
        empty($config->get('wordpress_admin.limited_admin_email')) || 
        empty($config->get('wordpress_admin.limited_admin_password'))) {
        
        echo "❌ 配置檔案中也缺少 wordpress_admin 配置\n";
        echo "修復中...\n";
        
        // 新增 wordpress_admin 配置作為備用
        $current_config['wordpress_admin'] = [
            'limited_admin_email' => 'client@example.com',
            'limited_admin_password' => 'SecurePassword123!',
            '_comment' => '專用管理員帳號設定，用於客戶管理網站（備用方案）'
        ];
        
        echo "✓ 已新增 wordpress_admin 配置範例（備用方案）\n";
        echo "建議先執行步驟 06 (WordPress 安裝) 來生成正確的管理員資訊\n\n";
    } else {
        echo "✓ 配置檔案中的 wordpress_admin 配置正常（備用方案）\n";
        echo "  Email: " . $config->get('wordpress_admin.limited_admin_email') . "\n";
        echo "  Password: " . str_repeat('*', strlen($config->get('wordpress_admin.limited_admin_password'))) . "\n\n";
    }
} else {
    echo "\n";
}

// 檢查問題 2: plugins.license_required 配置
echo "2. 檢查外掛授權配置...\n";
$plugins_license = $config->get('plugins.license_required');

if (empty($plugins_license)) {
    echo "❌ 缺少 plugins.license_required 配置\n";
    echo "修復中...\n";
    
    // 新增 plugins 配置
    $current_config['plugins'] = [
        'license_required' => [
            'elementor-pro' => 'your_elementor_pro_license_key',
            'flying-press' => 'your_flying_press_license_key',
            '_comment' => '付費外掛授權金鑰設定'
        ]
    ];
    
    echo "✓ 已新增 plugins.license_required 配置範例\n";
    echo "請編輯配置檔案，填入正確的授權金鑰\n\n";
} else {
    echo "✓ plugins.license_required 配置存在\n";
    
    $elementor_license = $config->get('plugins.license_required.elementor-pro');
    $flying_press_license = $config->get('plugins.license_required.flying-press');
    
    echo "  Elementor Pro: " . (empty($elementor_license) ? '❌ 未設定' : '✓ 已設定') . "\n";
    echo "  FlyingPress: " . (empty($flying_press_license) ? '❌ 未設定' : '✓ 已設定') . "\n\n";
}

// 儲存修復後的配置
if (isset($current_config['wordpress_admin']) || isset($current_config['plugins'])) {
    $backup_file = $config_file . '.backup.' . date('Y-m-d-H-i-s');
    copy($config_file, $backup_file);
    echo "📋 建立備份檔案: " . basename($backup_file) . "\n";
    
    file_put_contents($config_file, json_encode($current_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✓ 配置檔案已更新\n\n";
}

// 提供解決方案建議
echo "=== 解決方案建議 ===\n\n";

echo "🔧 問題 1 解決方案 - 專用管理員建立:\n";
echo "編輯 config/deploy-config.json，確保包含:\n";
echo "{\n";
echo "  \"wordpress_admin\": {\n";
echo "    \"limited_admin_email\": \"實際的客戶郵箱\",\n";
echo "    \"limited_admin_password\": \"安全的密碼\"\n";
echo "  }\n";
echo "}\n\n";

echo "🔧 問題 2 解決方案 - FlyingPress 授權:\n";
echo "1. 檢查網域是否可以正常訪問\n";
echo "2. 確認 FlyingPress 外掛已正確安裝\n";
echo "3. 檢查 SSL 憑證是否有效\n";
echo "4. 確認授權金鑰正確\n\n";

echo "編輯 config/deploy-config.json，確保包含:\n";
echo "{\n";
echo "  \"plugins\": {\n";
echo "    \"license_required\": {\n";
echo "      \"elementor-pro\": \"正確的Elementor Pro授權金鑰\",\n";
echo "      \"flying-press\": \"正確的FlyingPress授權金鑰\"\n";
echo "    }\n";
echo "  }\n";
echo "}\n\n";

echo "🧪 測試建議:\n";
echo "手動測試 FlyingPress API:\n";
echo "curl -X POST https://www.{你的網域}/wp-json/flying-press/activate-license/ \\\n";
echo "     -H 'Content-Type: application/json' \\\n";
echo "     -d '{\"license_key\":\"你的授權金鑰\"}'\n\n";

echo "=== 修復完成 ===\n";