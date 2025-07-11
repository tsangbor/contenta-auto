<?php
/**
 * 整合認證更新的部署腳本
 * 在執行部署前自動更新 BT Panel 認證資訊
 */

class DeployWithAuth {
    private $configPath = 'config/deploy-config.json';
    private $authUpdaterPath = 'auth-updater.js';
    private $logFile = 'logs/deploy-with-auth.log';
    
    public function __construct($configPath = null) {
        if ($configPath) {
            $this->configPath = $configPath;
        }
        
        // 確保日誌目錄存在
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * 記錄日誌
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 檢查認證是否需要更新
     */
    public function checkCredentialsAge() {
        $this->log('檢查認證狀態...');
        
        $command = "node {$this->authUpdaterPath} --check --config {$this->configPath}";
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        $message = implode("\n", $output);
        $this->log($message);
        
        return $returnCode === 0; // 0 表示不需要更新，1 表示需要更新
    }
    
    /**
     * 更新認證資訊
     */
    public function updateCredentials($force = false) {
        if (!$force && $this->checkCredentialsAge()) {
            $this->log('認證仍然有效，跳過更新');
            return true;
        }
        
        $this->log('開始更新認證資訊...');
        
        // 檢查 auth-updater.js 是否存在
        if (!file_exists($this->authUpdaterPath)) {
            $this->log('錯誤: auth-updater.js 不存在', 'ERROR');
            return false;
        }
        
        // 從環境變數或設定檔取得登入資訊
        $username = getenv('BTPANEL_USERNAME') ?: 'tsangbor';
        $password = getenv('BTPANEL_PASSWORD') ?: 'XSW2cde3';
        
        $command = sprintf(
            'node %s --username "%s" --password "%s" --config %s',
            $this->authUpdaterPath,
            escapeshellarg($username),
            escapeshellarg($password),
            $this->configPath
        );
        
        $output = [];
        $returnCode = 0;
        
        $this->log('執行認證更新命令...');
        exec($command, $output, $returnCode);
        
        $message = implode("\n", $output);
        $this->log($message);
        
        if ($returnCode === 0) {
            $this->log('認證更新成功！', 'SUCCESS');
            return true;
        } else {
            $this->log('認證更新失敗！', 'ERROR');
            return false;
        }
    }
    
    /**
     * 執行主要的部署流程
     */
    public function deploy($stepNumber = null) {
        $this->log('=== 開始部署流程 ===');
        
        // 1. 更新認證
        if (!$this->updateCredentials()) {
            $this->log('認證更新失敗，中止部署', 'ERROR');
            return false;
        }
        
        // 2. 執行對應的部署步驟
        if ($stepNumber) {
            return $this->executeStep($stepNumber);
        } else {
            return $this->executeFullDeploy();
        }
    }
    
    /**
     * 執行特定步驟
     */
    private function executeStep($stepNumber) {
        $stepFile = "step-{$stepNumber}.php";
        
        if (!file_exists($stepFile)) {
            $this->log("錯誤: 步驟檔案 {$stepFile} 不存在", 'ERROR');
            return false;
        }
        
        $this->log("執行步驟 {$stepNumber}...");
        
        // 包含並執行步驟檔案
        try {
            require_once $stepFile;
            $this->log("步驟 {$stepNumber} 執行完成", 'SUCCESS');
            return true;
        } catch (Exception $e) {
            $this->log("步驟 {$stepNumber} 執行失敗: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * 執行完整部署流程
     */
    private function executeFullDeploy() {
        $this->log('執行完整部署流程...');
        
        // 檢查主要部署腳本
        if (file_exists('contenta-deploy.php')) {
            $this->log('執行 contenta-deploy.php...');
            require_once 'contenta-deploy.php';
            return true;
        } else {
            $this->log('錯誤: contenta-deploy.php 不存在', 'ERROR');
            return false;
        }
    }
    
    /**
     * 取得認證資訊（用於測試）
     */
    public function getCredentials() {
        if (!file_exists($this->configPath)) {
            return null;
        }
        
        $config = json_decode(file_get_contents($this->configPath), true);
        
        return [
            'cookie' => $config['api_credentials']['btcn']['session_cookie'] ?? null,
            'token' => $config['api_credentials']['btcn']['http_token'] ?? null,
            'last_updated' => $config['api_credentials']['btcn']['_last_updated'] ?? null
        ];
    }
    
    /**
     * 設定排程任務來定期更新認證
     */
    public function setupCronJob() {
        $this->log('設定認證更新排程任務...');
        
        $cronCommand = sprintf(
            '0 */6 * * * cd %s && node %s --config %s >> %s 2>&1',
            getcwd(),
            $this->authUpdaterPath,
            $this->configPath,
            'logs/auth-cron.log'
        );
        
        echo "建議的 crontab 設定（每 6 小時更新一次認證）：\n";
        echo $cronCommand . "\n\n";
        echo "執行以下命令來設定：\n";
        echo "crontab -e\n";
        echo "然後添加上述行到 crontab 中\n";
    }
}

// CLI 使用
if (php_sapi_name() === 'cli') {
    $options = getopt('s:hfcv', ['step:', 'help', 'force', 'check', 'verbose', 'setup-cron']);
    
    if (isset($options['h']) || isset($options['help'])) {
        echo "使用方法:\n";
        echo "  php deploy-with-auth.php [選項]\n\n";
        echo "選項:\n";
        echo "  -s, --step NUMBER    執行特定步驟 (如: -s 08)\n";
        echo "  -f, --force          強制更新認證\n";
        echo "  -c, --check          僅檢查認證狀態\n";
        echo "  --setup-cron         顯示 cron 設定建議\n";
        echo "  -v, --verbose        詳細輸出\n";
        echo "  -h, --help           顯示此說明\n\n";
        echo "範例:\n";
        echo "  php deploy-with-auth.php                # 完整部署\n";
        echo "  php deploy-with-auth.php -s 08          # 僅執行步驟 08\n";
        echo "  php deploy-with-auth.php -c             # 檢查認證狀態\n";
        echo "  php deploy-with-auth.php -f             # 強制更新認證後部署\n";
        exit(0);
    }
    
    $deployer = new DeployWithAuth();
    
    if (isset($options['setup-cron'])) {
        $deployer->setupCronJob();
        exit(0);
    }
    
    if (isset($options['c']) || isset($options['check'])) {
        $credentials = $deployer->getCredentials();
        if ($credentials) {
            echo "當前認證資訊:\n";
            echo "Cookie: " . ($credentials['cookie'] ? '已設定' : '未設定') . "\n";
            echo "Token: " . ($credentials['token'] ? '已設定' : '未設定') . "\n";
            echo "最後更新: " . ($credentials['last_updated']['cookie'] ?? '未知') . "\n";
        } else {
            echo "無法讀取認證資訊\n";
        }
        exit(0);
    }
    
    $stepNumber = $options['s'] ?? $options['step'] ?? null;
    $force = isset($options['f']) || isset($options['force']);
    
    if ($force) {
        $deployer->updateCredentials(true);
    }
    
    $success = $deployer->deploy($stepNumber);
    exit($success ? 0 : 1);
}
?>