<?php
/**
 * 診斷偽靜態路徑問題
 * 使用方式: php diagnose-rewrite-path.php [domain]
 */

// 定義基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

require_once __DIR__ . '/config-manager.php';

if ($argc < 2) {
    echo "使用方式: php diagnose-rewrite-path.php [domain]\n";
    echo "例如: php diagnose-rewrite-path.php yaoguo.tw\n";
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

echo "=== 偽靜態路徑診斷 ===\n";
echo "網域: {$domain}\n";
echo "伺服器: {$server_host}\n\n";

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
    echo "1. 檢查偽靜態目錄是否存在...\n";
    $check_dir = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path, 
        "ls -la /www/server/panel/vhost/rewrite/ 2>&1 | head -20");
    
    if ($check_dir['return_code'] === 0) {
        echo "✓ 偽靜態目錄存在\n";
        echo "目錄內容:\n";
        echo $check_dir['output'] . "\n\n";
    } else {
        echo "✗ 偽靜態目錄不存在或無法訪問\n";
        echo "錯誤: " . $check_dir['output'] . "\n\n";
    }
    
    echo "2. 檢查可能的偽靜態配置檔案...\n";
    $possible_files = [
        "{$domain}.conf",
        "www.{$domain}.conf",
        str_replace('.', '_', $domain) . ".conf",
        "www_" . str_replace('.', '_', $domain) . ".conf"
    ];
    
    foreach ($possible_files as $file) {
        $check_file = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
            "test -f /www/server/panel/vhost/rewrite/{$file} && echo '存在' || echo '不存在'");
        
        echo "   檢查 {$file}: " . trim($check_file['output']) . "\n";
    }
    
    echo "\n3. 搜尋與網域相關的配置檔案...\n";
    $search_result = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "find /www/server/panel/vhost/rewrite/ -name '*{$domain}*' -type f 2>/dev/null");
    
    if (!empty($search_result['output'])) {
        echo "找到相關檔案:\n";
        echo $search_result['output'] . "\n";
    } else {
        echo "未找到相關檔案\n";
    }
    
    echo "\n4. 檢查 BT Panel 網站配置...\n";
    $nginx_conf = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "ls -la /www/server/panel/vhost/nginx/ | grep '{$domain}' || echo '未找到 nginx 配置'");
    
    echo "Nginx 配置檢查:\n";
    echo $nginx_conf['output'] . "\n";
    
    echo "\n5. 檢查網站目錄...\n";
    $site_dir = executeSSH($server_host, $ssh_user, $ssh_port, $ssh_key_path,
        "ls -la /www/wwwroot/ | grep '{$domain}' || echo '未找到網站目錄'");
    
    echo "網站目錄檢查:\n";
    echo $site_dir['output'] . "\n";
    
    echo "\n=== 診斷建議 ===\n";
    echo "1. 如果偽靜態目錄不存在，需要在 BT Panel 中建立\n";
    echo "2. 確認檔案名稱格式是否正確\n";
    echo "3. 可能需要先在 BT Panel 中為網站啟用偽靜態\n";
    echo "4. 檢查 BT Panel 中網站的名稱格式\n";
    
} catch (Exception $e) {
    echo "診斷失敗: " . $e->getMessage() . "\n";
    exit(1);
}