<?php
/**
 * 測試認證整合功能
 */

// 模擬部署環境
define('DEPLOY_BASE_PATH', __DIR__);

// 簡單的 deployer 和 config 模擬
class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
}

class MockConfig {
    private $data;
    
    public function __construct() {
        if (file_exists('config/deploy-config.json')) {
            $this->data = json_decode(file_get_contents('config/deploy-config.json'), true);
        } else {
            $this->data = [];
        }
    }
    
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->data;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
}

$deployer = new MockDeployer();
$config = new MockConfig();

echo "🧪 測試認證整合功能\n";
echo "==================\n\n";

// 測試 1: 檢查認證管理器
echo "測試 1: 認證管理器基本功能\n";
try {
    require_once 'includes/class-auth-manager.php';
    $authManager = new AuthManager();
    
    echo "✅ 認證管理器載入成功\n";
    
    // 檢查是否需要更新
    $needsUpdate = $authManager->needsUpdate();
    echo "認證狀態: " . ($needsUpdate ? "需要更新" : "暫時有效") . "\n";
    
    // 取得當前認證
    $credentials = $authManager->getCredentials();
    if ($credentials) {
        echo "當前認證:\n";
        echo "  Cookie: " . (empty($credentials['cookie']) ? '未設定' : '已設定') . "\n";
        echo "  Token: " . (empty($credentials['token']) ? '未設定' : '已設定') . "\n";
        echo "  最後更新: " . ($credentials['last_updated']['cookie'] ?? '未知') . "\n";
    } else {
        echo "未找到認證資訊\n";
    }
    
} catch (Exception $e) {
    echo "❌ 認證管理器測試失敗: " . $e->getMessage() . "\n";
}

echo "\n";

// 測試 2: 檢查認證包裝器
echo "測試 2: 認證包裝器功能\n";
try {
    require_once 'includes/auth-wrapper.php';
    echo "✅ 認證包裝器載入成功\n";
    
    // 測試認證檢查函數
    $wrapper = new AuthWrapper($deployer, 'test');
    echo "✅ 認證包裝器實例化成功\n";
    
} catch (Exception $e) {
    echo "❌ 認證包裝器測試失敗: " . $e->getMessage() . "\n";
}

echo "\n";

// 測試 3: 檢查 Node.js 和 Playwright
echo "測試 3: 檢查執行環境\n";

// 檢查 Node.js
$nodeVersion = trim(shell_exec('node --version 2>/dev/null') ?: '');
if ($nodeVersion) {
    echo "✅ Node.js: $nodeVersion\n";
} else {
    echo "❌ Node.js 未安裝或不在 PATH 中\n";
}

// 檢查 auth-updater.js
if (file_exists('auth-updater.js')) {
    echo "✅ auth-updater.js 存在\n";
} else {
    echo "❌ auth-updater.js 不存在\n";
}

// 檢查 Playwright
if (file_exists('node_modules/playwright')) {
    echo "✅ Playwright 已安裝\n";
} else {
    echo "⚠️  Playwright 未安裝，需要執行: npm install\n";
}

echo "\n";

// 測試 4: 模擬認證更新流程（僅檢查，不實際執行）
echo "測試 4: 模擬認證更新檢查\n";

if ($nodeVersion && file_exists('auth-updater.js')) {
    echo "執行認證狀態檢查...\n";
    
    $checkCmd = 'node auth-updater.js --check 2>&1';
    $output = shell_exec($checkCmd);
    
    if ($output) {
        echo "認證檢查輸出: " . trim($output) . "\n";
    } else {
        echo "認證檢查無輸出\n";
    }
} else {
    echo "⚠️  環境不完整，跳過認證檢查\n";
}

echo "\n";

// 測試 5: 驗證設定檔
echo "測試 5: 驗證設定檔\n";

if (file_exists('config/deploy-config.json')) {
    echo "✅ 設定檔存在\n";
    
    $config_data = json_decode(file_get_contents('config/deploy-config.json'), true);
    if ($config_data) {
        echo "✅ 設定檔格式正確\n";
        
        // 檢查關鍵設定
        $btcn_config = $config_data['api_credentials']['btcn'] ?? null;
        if ($btcn_config) {
            echo "✅ BT Panel 設定存在\n";
            echo "  Panel URL: " . ($btcn_config['panel_url'] ?? '未設定') . "\n";
            echo "  Session Cookie: " . (empty($btcn_config['session_cookie']) ? '未設定' : '已設定') . "\n";
            echo "  HTTP Token: " . (empty($btcn_config['http_token']) ? '未設定' : '已設定') . "\n";
        } else {
            echo "⚠️  BT Panel 設定未找到\n";
        }
    } else {
        echo "❌ 設定檔格式錯誤\n";
    }
} else {
    echo "❌ 設定檔不存在\n";
}

echo "\n";

// 測試結論
echo "🎯 測試總結\n";
echo "==========\n";

$allGood = true;
$issues = [];

if (!$nodeVersion) {
    $issues[] = "Node.js 未安裝";
    $allGood = false;
}

if (!file_exists('auth-updater.js')) {
    $issues[] = "auth-updater.js 不存在";
    $allGood = false;
}

if (!file_exists('config/deploy-config.json')) {
    $issues[] = "設定檔不存在";
    $allGood = false;
}

if (!file_exists('node_modules/playwright')) {
    $issues[] = "Playwright 未安裝";
    $allGood = false;
}

if ($allGood) {
    echo "✅ 所有測試通過！系統已準備好使用認證整合功能\n\n";
    echo "📋 使用方法:\n";
    echo "1. 在任何需要 BT 主機操作的步驟開始處加入:\n";
    echo "   require_once DEPLOY_BASE_PATH . '/includes/auth-wrapper.php';\n";
    echo "   \$authResult = ensureBTAuth(\$deployer, 'step-name');\n";
    echo "   if (isset(\$authResult['status']) && \$authResult['status'] === 'error') {\n";
    echo "       return \$authResult;\n";
    echo "   }\n\n";
    echo "2. 使用 btAPI() 函數執行 BT Panel API 請求:\n";
    echo "   \$response = btAPI('site?action=AddSite', \$data);\n";
    echo "   checkBTResponse(\$response, '創建網站');\n\n";
    echo "3. 系統會自動處理認證更新和錯誤處理\n";
} else {
    echo "❌ 發現以下問題需要解決:\n";
    foreach ($issues as $issue) {
        echo "  - $issue\n";
    }
    echo "\n建議執行以下命令解決問題:\n";
    echo "  npm install  # 安裝 Playwright\n";
    echo "  cp config/deploy-config.example.json config/deploy-config.json  # 創建設定檔\n";
}

echo "\n";
?>