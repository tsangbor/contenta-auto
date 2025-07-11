<?php
/**
 * 認證管理器 - 負責在執行 BT 主機操作前更新認證資訊
 */

class AuthManager {
    private $configPath;
    private $authUpdaterPath;
    private $logFile;
    
    public function __construct($configPath = 'config/deploy-config.json') {
        $this->configPath = $configPath;
        $this->authUpdaterPath = 'auth-updater.js';
        $this->logFile = 'logs/auth-manager.log';
        
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
        $logMessage = "[{$timestamp}] [AuthManager] [{$level}] {$message}" . PHP_EOL;
        
        echo $logMessage;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 檢查認證是否需要更新
     */
    public function needsUpdate() {
        if (!file_exists($this->configPath)) {
            $this->log('設定檔不存在，需要更新認證', 'WARNING');
            return true;
        }
        
        try {
            $config = json_decode(file_get_contents($this->configPath), true);
            $lastUpdated = $config['api_credentials']['btcn']['_last_updated']['cookie'] ?? null;
            
            if (!$lastUpdated) {
                $this->log('從未更新過認證，需要更新', 'INFO');
                return true;
            }
            
            $lastUpdateTime = new DateTime($lastUpdated);
            $now = new DateTime();
            $hoursSince = ($now->getTimestamp() - $lastUpdateTime->getTimestamp()) / 3600;
            
            // 如果超過 0.5 小時（30分鐘）就需要更新
            if ($hoursSince > 0.5) {
                $this->log(sprintf('認證已過期 %.1f 小時，需要更新', $hoursSince), 'INFO');
                return true;
            }
            
            $this->log(sprintf('認證仍有效，%.1f 小時後過期', 0.5 - $hoursSince), 'INFO');
            return false;
            
        } catch (Exception $e) {
            $this->log('檢查認證狀態時發生錯誤: ' . $e->getMessage(), 'ERROR');
            return true;
        }
    }
    
    /**
     * 取得登入認證資訊
     */
    protected function getLoginCredentials() {
        // 子類可以覆寫此方法來提供不同的認證來源
        return [
            'username' => getenv('BTPANEL_USERNAME') ?: 'tsangbor',
            'password' => getenv('BTPANEL_PASSWORD') ?: 'XSW2cde',
            'login_url' => 'https://jp3.contenta.tw:8888/btpanel'
        ];
    }

    /**
     * 更新認證資訊
     */
    public function updateCredentials($force = false) {
        $this->log('=== 開始認證更新流程 ===');
        
        if (!$force && !$this->needsUpdate()) {
            $this->log('認證仍然有效，跳過更新');
            return true;
        }
        
        // 檢查 Node.js 和認證更新器
        if (!$this->checkDependencies()) {
            return false;
        }
        
        $this->log('執行 Playwright 認證更新...');
        
        // 取得登入認證資訊
        $credentials = $this->getLoginCredentials();
        
        $command = sprintf(
            'node %s --username %s --password %s --config %s 2>&1',
            escapeshellarg($this->authUpdaterPath),
            escapeshellarg($credentials['username']),
            escapeshellarg($credentials['password']),
            escapeshellarg($this->configPath)
        );
        
        $this->log("執行命令: node auth-updater.js ...");
        $this->log("登入 URL: " . $credentials['login_url']);
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        $outputText = implode("\n", $output);
        
        if ($returnCode === 0) {
            $this->log('認證更新成功！', 'SUCCESS');
            $this->log('更新輸出: ' . $outputText);
            
            // 驗證更新結果
            return $this->verifyCredentials();
        } else {
            $this->log('認證更新失敗！', 'ERROR');
            $this->log('錯誤輸出: ' . $outputText);
            return false;
        }
    }
    
    /**
     * 檢查依賴項
     */
    private function checkDependencies() {
        // 檢查 Node.js
        $nodeVersion = shell_exec('node --version 2>/dev/null');
        if (!$nodeVersion) {
            $this->log('錯誤: Node.js 未安裝或不在 PATH 中', 'ERROR');
            return false;
        }
        $this->log('Node.js 版本: ' . trim($nodeVersion));
        
        // 檢查認證更新器腳本
        if (!file_exists($this->authUpdaterPath)) {
            $this->log('錯誤: 認證更新器腳本不存在: ' . $this->authUpdaterPath, 'ERROR');
            return false;
        }
        
        // 檢查 Playwright
        if (!file_exists('node_modules/playwright')) {
            $this->log('警告: Playwright 可能未安裝，嘗試安裝...', 'WARNING');
            exec('npm install playwright 2>&1', $output, $returnCode);
            if ($returnCode !== 0) {
                $this->log('錯誤: 無法安裝 Playwright', 'ERROR');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 驗證認證資訊
     */
    private function verifyCredentials() {
        if (!file_exists($this->configPath)) {
            $this->log('設定檔不存在，驗證失敗', 'ERROR');
            return false;
        }
        
        $config = json_decode(file_get_contents($this->configPath), true);
        $cookie = $config['api_credentials']['btcn']['session_cookie'] ?? null;
        $token = $config['api_credentials']['btcn']['http_token'] ?? null;
        
        if (empty($cookie) || empty($token)) {
            $this->log('認證資訊不完整，驗證失敗', 'ERROR');
            return false;
        }
        
        $this->log('認證資訊驗證成功');
        $this->log('Cookie: ' . substr($cookie, 0, 50) . '...');
        $this->log('Token: ' . substr($token, 0, 20) . '...');
        
        return true;
    }
    
    /**
     * 取得當前認證資訊
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
     * 確保認證可用（主要公開方法）
     */
    public function ensureValidCredentials($force = false) {
        $this->log('=== 確保認證可用 ===');
        
        try {
            if ($this->updateCredentials($force)) {
                $credentials = $this->getCredentials();
                if ($credentials && $credentials['cookie'] && $credentials['token']) {
                    $this->log('認證準備完成，可以執行 BT 主機操作', 'SUCCESS');
                    return true;
                } else {
                    $this->log('認證資訊不完整', 'ERROR');
                    return false;
                }
            } else {
                $this->log('認證更新失敗', 'ERROR');
                return false;
            }
        } catch (Exception $e) {
            $this->log('認證處理過程中發生錯誤: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
}
?>