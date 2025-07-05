<?php
/**
 * 修復 wp-config.php 中重複定義常數的問題
 * 使用方式: php fix-wp-config-constants.php [domain]
 */

require_once __DIR__ . '/config-manager.php';

if ($argc < 2) {
    echo "使用方式: php fix-wp-config-constants.php [domain]\n";
    echo "例如: php fix-wp-config-constants.php yaoguo.tw\n";
    exit(1);
}

$domain = $argv[1];
$config = ConfigManager::getInstance();

// 取得部署設定
$server_host = $config->get('deployment.server_host');
$ssh_user = $config->get('deployment.ssh_user');
$ssh_port = $config->get('deployment.ssh_port') ?: 22;
$ssh_key_path = $config->get('deployment.ssh_key_path');

if (empty($server_host)) {
    echo "錯誤: 未設定伺服器主機\n";
    exit(1);
}

// WordPress 安裝路徑
$document_root = "/www/wwwroot/www.{$domain}";
$wp_config_path = "{$document_root}/wp-config.php";

echo "=== 修復 WordPress 常數重複定義問題 ===\n";
echo "網域: {$domain}\n";
echo "伺服器: {$server_host}\n";
echo "wp-config.php 路徑: {$wp_config_path}\n\n";

/**
 * 執行 SSH 指令
 */
function executeSSH($host, $user, $port, $key_path, $command)
{
    $ssh_cmd = "ssh";
    
    if (!empty($key_path) && file_exists($key_path)) {
        $ssh_cmd .= " -i " . escapeshellarg($key_path);
    }
    
    if (!empty($port)) {
        $ssh_cmd .= " -p {$port}";
    }
    
    $ssh_cmd .= " -o StrictHostKeyChecking=no {$user}@{$host}";
    $ssh_cmd .= " " . escapeshellarg($command);
    
    $output = [];
    $return_code = 0;
    
    exec($ssh_cmd . ' 2>&1', $output, $return_code);
    
    return [
        'return_code' => $return_code,
        'output' => implode("\n", $output),
        'command' => $command
    ];
}

try {
    echo "1. 檢查 wp-config.php 是否存在...\n";
    $check_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, 
        "test -f {$wp_config_path} && echo 'exists' || echo 'not found'");
    
    if (strpos($check_result['output'], 'not found') !== false) {
        echo "錯誤: wp-config.php 檔案不存在於 {$wp_config_path}\n";
        exit(1);
    }
    
    echo "✓ wp-config.php 檔案存在\n\n";
    
    echo "2. 檢查重複的常數定義...\n";
    $grep_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "grep -n 'define.*WP_ADMIN_DIR\\|define.*ADMIN_COOKIE_PATH\\|define.*SHORTPIXEL_API_KEY' {$wp_config_path} || true");
    
    if (!empty($grep_result['output'])) {
        echo "發現的常數定義:\n";
        echo $grep_result['output'] . "\n\n";
    }
    
    echo "3. 備份原始 wp-config.php...\n";
    $backup_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "cp {$wp_config_path} {$wp_config_path}.backup-" . date('Y-m-d-H-i-s'));
    
    if ($backup_result['return_code'] === 0) {
        echo "✓ 備份完成\n\n";
    } else {
        echo "警告: 備份失敗，但繼續執行\n\n";
    }
    
    echo "4. 移除重複的常數定義並重新新增正確的版本...\n";
    
    // 取得自訂管理後台網址
    $custom_admin_url = $config->get('wordpress_security.custom_admin_url', 'wp-admin');
    
    // 建立修復腳本
    $fix_script = "
# 移除所有相關的 define 語句
sed -i '/define.*WP_ADMIN_DIR/d' {$wp_config_path}
sed -i '/define.*ADMIN_COOKIE_PATH/d' {$wp_config_path}
sed -i '/define.*SHORTPIXEL_API_KEY/d' {$wp_config_path}

# 移除可能的安全性設定註釋行
sed -i '/\/\/ WordPress 安全性設定/d' {$wp_config_path}

# 新增正確的常數定義到檔案末尾（在 <?php 標記之後）
cat >> {$wp_config_path} << 'EOF'

// WordPress 安全性設定
if (!defined('WP_ADMIN_DIR')) {
    define('WP_ADMIN_DIR', '{$custom_admin_url}');
}
if (!defined('ADMIN_COOKIE_PATH')) {
    define('ADMIN_COOKIE_PATH', SITECOOKIEPATH . '{$custom_admin_url}/');
}
if (!defined('SHORTPIXEL_API_KEY')) {
    define('SHORTPIXEL_API_KEY', '4pSSVVJnUXIywAJirTal');
}
EOF
";
    
    $fix_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, $fix_script);
    
    if ($fix_result['return_code'] === 0) {
        echo "✓ 常數定義修復完成\n\n";
    } else {
        echo "錯誤: 修復失敗\n";
        echo "錯誤訊息: " . $fix_result['output'] . "\n";
        exit(1);
    }
    
    echo "5. 驗證修復結果...\n";
    $verify_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "grep -A 10 '// WordPress 安全性設定' {$wp_config_path} || echo '未找到安全性設定'");
    
    echo "修復後的常數定義:\n";
    echo $verify_result['output'] . "\n\n";
    
    echo "6. 檢查 PHP 語法錯誤...\n";
    $syntax_check = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "php -l {$wp_config_path}");
    
    if (strpos($syntax_check['output'], 'No syntax errors') !== false) {
        echo "✓ PHP 語法檢查通過\n\n";
    } else {
        echo "警告: PHP 語法檢查失敗\n";
        echo $syntax_check['output'] . "\n\n";
    }
    
    echo "=== 修復完成 ===\n";
    echo "網站應該已經可以正常運作，不再出現常數重複定義的警告。\n";
    echo "如果問題仍然存在，請檢查是否有其他外掛或主題也定義了相同的常數。\n";
    
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "\n";
    exit(1);
}