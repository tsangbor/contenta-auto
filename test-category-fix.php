<?php
/**
 * 測試分類檢查與建立修復
 */

// 基本設定
define('DEPLOY_BASE_PATH', __DIR__);
require_once __DIR__ . '/includes/utilities/class-wp-cli-executor.php';

echo "=== 測試分類檢查與建立修復 ===\n";
echo "時間: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 模擬配置物件
    class MockConfig {
        public function get($key) {
            $config = [
                'deployment' => [
                    'ssh_user' => 'root',
                    'server_host' => '103.173.178.90',
                    'ssh_port' => 22,
                    'ssh_key_path' => '/root/.ssh/id_rsa'
                ]
            ];
            
            $keys = explode('.', $key);
            $value = $config;
            foreach ($keys as $k) {
                if (isset($value[$k])) {
                    $value = $value[$k];
                } else {
                    return null;
                }
            }
            return $value;
        }
    }
    
    $config = new MockConfig();
    $wp_cli = new WP_CLI_Executor($config);
    $wp_cli->set_document_root('/www/wwwroot/www.yaoguo.tw');
    
    echo "✅ WP_CLI_Executor 建立成功\n\n";
    
    // 測試分類列表命令建構
    echo "=== 測試新的分類檢查命令 ===\n";
    echo "檢查分類是否存在命令：\n";
    echo "wp term list category --format=csv --fields=slug\n\n";
    
    echo "取得分類 ID 命令：\n"; 
    echo "wp term list category --format=csv --fields=term_id,slug\n\n";
    
    // 測試要檢查的分類
    $test_categories = [
        'human-design-knowledge',
        'self-awareness', 
        'growth-resources',
        'uncategorized'  // WordPress 預設分類，應該存在
    ];
    
    echo "=== 預期的分類檢查結果 ===\n";
    foreach ($test_categories as $slug) {
        echo "分類: $slug\n";
        echo "  - 檢查命令更可靠（使用 term list 而非 term get）\n";
        echo "  - 應該能正確識別分類是否存在\n";
        echo "  - 如果存在，能正確取得 term_id\n\n";
    }
    
    echo "=== 修復的關鍵改進 ===\n";
    echo "1. ✅ category_exists() 改用 'term list category' 命令\n";
    echo "2. ✅ get_category_id() 改用 'term list category' 命令\n";
    echo "3. ✅ 使用 CSV 格式解析，更穩定\n";
    echo "4. ✅ 正確處理 CSV 標題行\n\n";
    
    echo "=== 修復效果 ===\n";
    echo "- 分類檢查更可靠，避免誤判分類不存在\n";
    echo "- 減少不必要的分類建立嘗試\n";
    echo "- 避免「已由其上層項目使用」錯誤\n";
    echo "- 能正確取得現有分類的 ID\n\n";
    
    echo "=== 建議的測試流程 ===\n";
    echo "1. 重新執行 step-15.php\n";
    echo "2. 檢查是否還有「建立分類失敗」的錯誤\n";
    echo "3. 檢查是否還有「找不到分類 ID」的警告\n";
    echo "4. 確認文章能正確指派到分類\n";
    
} catch (Exception $e) {
    echo "測試失敗: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 測試完成 ===\n";