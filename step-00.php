<?php
/**
 * 步驟 00: 設定參數與載入配置
 * 1. 處理 JSON 資料，設定相關參數與 API 憑證
 * 2. 自動更新 BT Panel 認證 (Cookie + Token)
 */

$deployer->log("=== 步驟 00: 設定參數與載入配置 ===");

// === 階段 1: 更新 BT Panel 認證 ===
$deployer->log("🔐 階段 1: 更新 BT Panel 認證資訊");

// 引入認證管理器
require_once DEPLOY_BASE_PATH . '/includes/class-auth-manager.php';

// 從配置檔案讀取認證模式，環境變數可以覆蓋
$config_file = 'config/deploy-config.json';
$config_data = json_decode(file_get_contents($config_file), true);
$auth_mode = getenv('CONTENTA_AUTH_MODE') ?: ($config_data['api_credentials']['btcn']['auth_mode'] ?? 'auto'); // 預設為自動模式

// 更新認證管理器以使用新的配置參數
class Step00AuthManager extends AuthManager {
    private $configPath;
    
    public function __construct($configPath = 'config/deploy-config.json') {
        parent::__construct($configPath);
        $this->configPath = $configPath;
    }
    
    protected function getLoginCredentials() {
        $config_data = json_decode(file_get_contents($this->configPath), true);
        $btcn_config = $config_data['api_credentials']['btcn'] ?? [];
        
        return [
            'username' => $btcn_config['panel_login'] ?? getenv('BTPANEL_USERNAME') ?? 'tsangbor',
            'password' => $btcn_config['panel_password'] ?? getenv('BTPANEL_PASSWORD') ?? 'XSW2cde',
            'login_url' => $btcn_config['panel_auth'] ?? 'https://jp3.contenta.tw:8888/btpanel'
        ];
    }
}

$authManager = new Step00AuthManager('config/deploy-config.json');

// 檢查認證是否需要更新
$needsUpdate = $authManager->needsUpdate();
$deployer->log("認證狀態檢查: " . ($needsUpdate ? "需要更新" : "仍然有效"));

// 在 auto 模式下，總是實際驗證認證是否真的有效
if (!$needsUpdate && $auth_mode === 'auto') {
    $deployer->log("進行實際 API 驗證...");
    
    // 取得現有認證
    $credentials = $authManager->getCredentials();
    
    if (!$credentials || !$credentials['cookie'] || !$credentials['token']) {
        $deployer->log("⚠️ 認證資訊不完整，需要更新", 'WARNING');
        $needsUpdate = true;
    } else {
        // 實際測試 API 是否可用
        $test_url = $config_data['api_credentials']['btcn']['panel_url'] . '/ajax?action=get_load_average';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $test_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Cookie: ' . $credentials['cookie'],
                'X-HTTP-Token: ' . $credentials['token'],
                'Content-Type: application/x-www-form-urlencoded',
                'X-Requested-With: XMLHttpRequest'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['status']) && $result['status'] === false && $result['code'] === -8888) {
                $deployer->log("⚠️ API 回應認證已失效: {$result['msg']}", 'WARNING');
                $needsUpdate = true;
            } else {
                $deployer->log("✅ API 驗證成功，認證有效");
            }
        } else {
            $deployer->log("⚠️ 無法連接 API，需要更新認證", 'WARNING');
            $needsUpdate = true;
        }
    }
}

// 更新認證（如果需要的話）
if ($needsUpdate) {
    if ($auth_mode === 'manual') {
        // 手動模式
        $deployer->log("使用手動認證模式");
        $deployer->log("請從瀏覽器開發者工具取得以下資訊：");
        $deployer->log("1. 登入 BT Panel");
        $deployer->log("2. 開啟開發者工具 (F12) > Network 標籤");
        $deployer->log("3. 找到任何 API 請求的 Request Headers");
        
        // 檢查是否有提供認證資訊（從環境變數或配置檔案）
        // 支援多種欄位名稱以保持向後相容
        $manual_cookie = getenv('BTPANEL_COOKIE') ?: 
                        ($config_data['api_credentials']['btcn']['manual_cookie'] ?? 
                         $config_data['api_credentials']['btcn']['session_cookie'] ?? 
                         $config_data['api_credentials']['btcn']['auth']['cookie'] ?? null);
        
        $manual_token = getenv('BTPANEL_TOKEN') ?: 
                       ($config_data['api_credentials']['btcn']['manual_token'] ?? 
                        $config_data['api_credentials']['btcn']['http_token'] ?? 
                        $config_data['api_credentials']['btcn']['auth']['token'] ?? null);
        
        if ($manual_cookie && $manual_token) {
            $deployer->log("使用手動提供的認證資訊");
        } else {
            $deployer->log("請在 config/deploy-config.json 的 btcn 區塊中設定:");
            $deployer->log('  "auth_mode": "manual",');
            $deployer->log('  "manual_cookie": "request_token=xxxxx",');
            $deployer->log('  "manual_token": "xxxxx"');
            $deployer->log("");
            $deployer->log("或使用環境變數:");
            $deployer->log("export BTPANEL_COOKIE='request_token=xxxxx'");
            $deployer->log("export BTPANEL_TOKEN='xxxxx'");
            return [
                'status' => 'error',
                'error' => 'manual_auth_required',
                'message' => '手動模式需要設定認證資訊'
            ];
        }
        
        // 更新配置檔案
        $config_file = 'config/deploy-config.json';
        $config = json_decode(file_get_contents($config_file), true);
        
        $config['api_credentials']['btcn']['auth']['cookie'] = $manual_cookie;
        $config['api_credentials']['btcn']['auth']['token'] = $manual_token;
        $config['api_credentials']['btcn']['_last_updated'] = [
            'cookie' => date('Y-m-d H:i:s'),
            'token' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $deployer->log("✅ 手動認證資訊已更新");
        
    } else {
        // 自動模式
        $deployer->log("使用自動認證模式 (Playwright)");
        if (!$authManager->updateCredentials(true)) {
            $deployer->log("❌ BT Panel 認證更新失敗，無法繼續部署", 'ERROR');
            $deployer->log("提示：您可以使用手動模式繞過此問題");
            $deployer->log("CONTENTA_AUTH_MODE=manual BTPANEL_COOKIE='xxx' BTPANEL_TOKEN='xxx' php contenta-deploy.php ...");
            return [
                'status' => 'error',
                'error' => 'bt_auth_failed',
                'message' => 'BT Panel 認證更新失敗'
            ];
        }
    }
} else {
    $deployer->log("認證仍然有效，跳過更新");
}

// 再次驗證更新後的認證
if ($auth_mode === 'auto') {
    $credentials = $authManager->getCredentials();
    if ($credentials && $credentials['cookie'] && $credentials['token']) {
        $deployer->log("✅ BT Panel 認證更新完成");
    } else {
        $deployer->log("❗ BT Panel 認證可能未正確更新", 'WARNING');
    }
} else {
    $deployer->log("✅ BT Panel 認證處理完成");
}

// === 階段 2: 處理 Job 資料 ===
$deployer->log("📋 階段 2: 處理 Job 資料");

/**
 * 遞迴刪除目錄
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// 建立統一的工作目錄（整合 tmp 和 temp 功能）
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;

$deployer->log("檢查工作目錄: {$work_dir}");

// 如果 job_id 資料夾已存在，先刪除
if (is_dir($work_dir)) {
    $deployer->log("發現現有 job_id 資料夾，正在清理...");
    if (deleteDirectory($work_dir)) {
        $deployer->log("舊資料夾清理完成");
    } else {
        throw new Exception("無法清理舊的 job_id 資料夾: {$work_dir}");
    }
}

// 建立新的 job_id 資料夾
$temp_base_dir = DEPLOY_BASE_PATH . '/temp';
if (!is_dir($temp_base_dir)) {
    mkdir($temp_base_dir, 0755, true);
}

if (!mkdir($work_dir, 0755, true)) {
    throw new Exception("無法建立工作目錄: {$work_dir}");
}

$deployer->log("工作目錄建立完成: {$work_dir}");

// 建立子目錄結構（統一管理所有類型檔案）
$subdirs = ['config', 'scripts', 'json', 'images', 'logs'];
foreach ($subdirs as $subdir) {
    $subdir_path = $work_dir . '/' . $subdir;
    if (!mkdir($subdir_path, 0755, true)) {
        throw new Exception("無法建立子目錄: {$subdir_path}");
    }
}

$deployer->log("子目錄結構建立完成: " . implode(', ', $subdirs));

// 驗證 Job 資料結構
$required_fields = ['confirmed_data'];
foreach ($required_fields as $field) {
    if (!isset($job_data[$field])) {
        throw new Exception("Job 資料缺少必要欄位: {$field}");
    }
}

$confirmed_data = $job_data['confirmed_data'];

// 驗證確認資料的必要欄位
$required_confirmed_fields = ['website_name', 'domain', 'user_email'];
foreach ($required_confirmed_fields as $field) {
    if (empty($confirmed_data[$field])) {
        throw new Exception("確認資料缺少必要欄位: {$field}");
    }
}

// 設定部署變數
$website_name = $confirmed_data['website_name'];
$website_description = $confirmed_data['website_description'] ?? '';
$domain = $confirmed_data['domain'];
$user_email = $confirmed_data['user_email'];

$deployer->log("網站名稱: {$website_name}");
$deployer->log("網域: {$domain}");
$deployer->log("用戶信箱: {$user_email}");

// 驗證配置
try {
    // 如果 deployer 有 config 物件，使用它來驗證
    if (isset($deployer->config) && is_object($deployer->config) && method_exists($deployer->config, 'validateConfig')) {
        $deployer->config->validateConfig();
        $deployer->log("配置驗證通過");
    } else {
        // 基本配置驗證
        $config_file = 'config/deploy-config.json';
        if (!file_exists($config_file)) {
            throw new Exception("配置檔案不存在: {$config_file}");
        }
        
        $config_data = json_decode(file_get_contents($config_file), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("配置檔案 JSON 格式錯誤: " . json_last_error_msg());
        }
        
        $deployer->log("基本配置驗證通過");
    }
} catch (Exception $e) {
    $deployer->log("配置驗證失敗: " . $e->getMessage());
    $deployer->log("請檢查 config/deploy-config.json 檔案");
    throw $e;
}

// 工作目錄已在上面建立，此處移除重複程式碼

// 儲存處理後的資料供其他步驟使用
$processed_data = [
    'website_name' => $website_name,
    'website_description' => $website_description,
    'domain' => $domain,
    'user_email' => $user_email,
    'work_dir' => $work_dir,
    'confirmed_data' => $confirmed_data
];

// 儲存到統一工作目錄
file_put_contents($work_dir . '/config/processed_data.json', json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// 生成資料庫配置檔案（step-06 需要）
$deployer->log("生成資料庫配置檔案...");
$db_name = str_replace('.tw', '', $domain);  // 移除 .tw 後綴
$db_user = $db_name;  // 用戶名稱同資料庫名稱
$db_password = '82b15dc192ae';  // 統一密碼

$database_config = [
    'host' => 'localhost',
    'database' => $db_name,
    'username' => $db_user,
    'password' => $db_password,
    'table_prefix' => 'wp_',
    'charset' => 'utf8mb4',
    'collate' => 'utf8mb4_unicode_ci'
];

file_put_contents($work_dir . '/database_config.json', json_encode($database_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$deployer->log("  資料庫名稱: {$db_name}");
$deployer->log("  資料庫用戶: {$db_user}");

// 生成網站配置檔案（step-06 需要）
$deployer->log("生成網站配置檔案...");
$document_root = "/www/wwwroot/www.{$domain}";

$bt_website_config = [
    'domain' => $domain,
    'document_root' => $document_root,
    'subdomain' => "www.{$domain}",
    'site_name' => $website_name,
    'created_at' => date('Y-m-d H:i:s'),
    'php_version' => '8.1',
    'ssl_enabled' => true,
    'database' => [
        'name' => $db_name,
        'user' => $db_user,
        'password' => $db_password
    ]
];

file_put_contents($work_dir . '/bt_website.json', json_encode($bt_website_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$deployer->log("  網站目錄: {$document_root}");
$deployer->log("  網域名稱: {$domain}");

// 建立任務資訊檔案
$job_info = [
    'job_id' => $job_id,
    'website_name' => $website_name,
    'domain' => $domain,
    'user_email' => $user_email,
    'start_time' => date('Y-m-d H:i:s'),
    'work_directory' => $work_dir,
    'subdirectories' => [
        'config' => $work_dir . '/config',
        'scripts' => $work_dir . '/scripts',
        'json' => $work_dir . '/json',
        'images' => $work_dir . '/images',
        'logs' => $work_dir . '/logs'
    ]
];

file_put_contents($work_dir . '/config/job_info.json', json_encode($job_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("工作目錄結構:");
$deployer->log("  主目錄: {$work_dir}");
$deployer->log("  配置目錄: {$work_dir}/config");
$deployer->log("  腳本目錄: {$work_dir}/scripts");
$deployer->log("  JSON目錄: {$work_dir}/json");
$deployer->log("  圖片目錄: {$work_dir}/images");
$deployer->log("  日誌目錄: {$work_dir}/logs");

// === 階段 3: 處理 Job 目錄中的檔案 ===
$deployer->log("🗂️  階段 3: 處理 Job 目錄中的檔案");

// 檢查是否有 job 資料目錄及其檔案
if (isset($job_data['job_dir']) && is_dir($job_data['job_dir'])) {
    $job_dir = $job_data['job_dir'];
    $deployer->log("處理 Job 檔案目錄: {$job_dir}");
    
    // 掃描目錄中的檔案
    $job_files = glob($job_dir . '/*');
    $processed_files = [];
    
    foreach ($job_files as $file_path) {
        if (is_file($file_path)) {
            $filename = basename($file_path);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            // 跳過 JSON 檔案（已經處理過）
            if ($extension === 'json') {
                continue;
            }
            
            $deployer->log("  發現檔案: {$filename}");
            
            // 根據檔案類型進行處理
            switch ($extension) {
                case 'docx':
                case 'doc':
                    $deployer->log("    Word 文檔: 複製到工作目錄");
                    $target_path = $work_dir . '/documents/' . $filename;
                    if (!is_dir(dirname($target_path))) {
                        mkdir(dirname($target_path), 0755, true);
                    }
                    copy($file_path, $target_path);
                    $processed_files[] = [
                        'type' => 'document',
                        'original' => $file_path,
                        'copied_to' => $target_path,
                        'filename' => $filename
                    ];
                    break;
                    
                case 'pdf':
                    $deployer->log("    PDF 文檔: 複製到工作目錄");
                    $target_path = $work_dir . '/documents/' . $filename;
                    if (!is_dir(dirname($target_path))) {
                        mkdir(dirname($target_path), 0755, true);
                    }
                    copy($file_path, $target_path);
                    $processed_files[] = [
                        'type' => 'pdf',
                        'original' => $file_path,
                        'copied_to' => $target_path,
                        'filename' => $filename
                    ];
                    break;
                    
                case 'jpg':
                case 'jpeg':
                case 'png':
                case 'gif':
                case 'webp':
                    $deployer->log("    圖片檔案: 複製到圖片目錄");
                    $target_path = $work_dir . '/images/' . $filename;
                    copy($file_path, $target_path);
                    $processed_files[] = [
                        'type' => 'image',
                        'original' => $file_path,
                        'copied_to' => $target_path,
                        'filename' => $filename
                    ];
                    break;
                    
                default:
                    $deployer->log("    其他檔案: 複製到 misc 目錄");
                    $target_path = $work_dir . '/misc/' . $filename;
                    if (!is_dir(dirname($target_path))) {
                        mkdir(dirname($target_path), 0755, true);
                    }
                    copy($file_path, $target_path);
                    $processed_files[] = [
                        'type' => 'misc',
                        'original' => $file_path,
                        'copied_to' => $target_path,
                        'filename' => $filename
                    ];
                    break;
            }
        }
    }
    
    // 儲存檔案處理結果
    if (!empty($processed_files)) {
        $files_info = [
            'processed_at' => date('Y-m-d H:i:s'),
            'source_directory' => $job_dir,
            'files' => $processed_files
        ];
        
        file_put_contents($work_dir . '/config/processed_files.json', json_encode($files_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("檔案處理完成，共處理 " . count($processed_files) . " 個檔案");
    } else {
        $deployer->log("Job 目錄中沒有需要處理的檔案");
    }
} else {
    $deployer->log("沒有找到 Job 資料目錄，跳過檔案處理");
}

$deployer->log("參數設定完成");

return $processed_data;