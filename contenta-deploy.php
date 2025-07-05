<?php
/**
 * Contenta 自動化部署系統 - 主控制腳本
 * 獨立的本機端 PHP 指令，用於自動化部署網站到 BT.cn 主機
 * 
 * 使用方式: php contenta-deploy.php [job_id] [--step=XX]
 * 例如: php contenta-deploy.php 2506290730-3450
 * 例如: php contenta-deploy.php 2506290730-3450 --step=03
 */

// 檢查是否在命令列模式執行
if (php_sapi_name() !== 'cli') {
    die("此腳本只能在命令列模式下執行\n");
}

// 設定錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 設定時區為台北時間
date_default_timezone_set('Asia/Taipei');

// 定義基本路徑
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
    define('DEPLOY_LOGS_PATH', DEPLOY_BASE_PATH . '/logs');
    define('DEPLOY_DATA_PATH', DEPLOY_BASE_PATH . '/data');
    define('DEPLOY_JSON_PATH', DEPLOY_BASE_PATH . '/json');
}

// 建立必要目錄
$required_dirs = [DEPLOY_CONFIG_PATH, DEPLOY_LOGS_PATH];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 載入配置管理器
require_once DEPLOY_BASE_PATH . '/config-manager.php';

/**
 * 主要部署類別
 */
class ContentaDeployer
{
    private $job_id;
    private $job_data;
    private $config;
    private $steps = [];
    private $start_step = '00';
    private $single_step = true; // 預設為單一步驟執行
    private $job_log_file;
    
    public function __construct($job_id, $start_step = '00', $single_step = true)
    {
        $this->job_id = $job_id;
        $this->start_step = $start_step;
        $this->single_step = $single_step;
        $this->initializeJobLog();
        $this->loadJobData();
        $this->initializeSteps();
        $this->log("=== Contenta 部署系統啟動 ===");
        $this->log("Job ID: " . $this->job_id);
        $this->log("起始步驟: " . $this->start_step);
    }
    
    /**
     * 初始化 Job 專用日誌
     */
    private function initializeJobLog()
    {
        $this->job_log_file = DEPLOY_LOGS_PATH . '/job-' . $this->job_id . '.log';
        
        // 如果日誌檔案已存在，先刪除
        if (file_exists($this->job_log_file)) {
            unlink($this->job_log_file);
        }
        
        // 建立新的日誌檔案
        $init_message = "[" . date('Y-m-d H:i:s') . "] [INIT] Job 日誌初始化: {$this->job_id}\n";
        file_put_contents($this->job_log_file, $init_message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 載入工作資料
     */
    private function loadJobData()
    {
        // 先檢查是否有 job_id 對應的 JSON 檔案
        $job_file = DEPLOY_DATA_PATH . '/' . $this->job_id . '.json';
        if (file_exists($job_file)) {
            $this->job_data = json_decode(file_get_contents($job_file), true);
        } else {
            // 如果沒有專用檔案，使用範例資料進行測試
            $this->log("警告: 找不到 Job 資料檔案 {$job_file}，使用預設測試資料");
            $this->job_data = $this->getDefaultJobData();
        }
        
        if (!$this->job_data) {
            throw new Exception("無法載入 Job 資料");
        }
        
        $this->log("Job 資料載入成功");
    }
    
    /**
     * 取得預設測試資料
     */
    private function getDefaultJobData()
    {
        return [
            "job_id" => $this->job_id,
            "confirmed_data" => [
                "website_name" => "測試網站",
                "website_description" => "這是一個測試網站",
                "domain_suggestions" => "test.tw",
                "user_email" => "test@example.com",
                "domain" => "test.tw"
            ]
        ];
    }
    
    /**
     * 初始化部署步驟
     */
    private function initializeSteps()
    {
        $this->steps = [
            '00' => '專案初始化',
            '01' => 'Cloudflare區域與DNS設定',
            '02' => '網域註冊 (Lihi API)',
            '03' => 'BT Panel 網站建立',
            '04' => 'SSL憑證與Nginx重寫規則設定',
            '05' => '資料庫建立',
            '06' => 'WordPress 核心安裝',
            '07' => '外掛與主題部署及啟用',
            '08' => 'AI 生成網站配置',
            '09' => '頁面組裝與 AI 文案填充',
            '09-5' => '動態圖片提示詞生成',
            '10' => 'AI 圖片生成',
            '11' => 'WordPress 媒體上傳',
            '12' => '圖片路徑替換',
            '13' => 'Elementor 全域模板匯入',
            '14' => 'Elementor 頁面建立與發布',
            '15' => 'AI 文章與精選圖片批量生成',
            '16' => '網站最終檢查與優化',
            '17' => '部署完成通知',
            '18' => '備用步驟 (預留)',
            '19' => '備用步驟 (預留)'
        ];
    }
    
    /**
     * 執行完整部署流程
     */
    public function deploy()
    {
        $start_time = microtime(true);
        
        try {
            $this->log("開始部署流程，起始步驟: {$this->start_step}");
            
            // 根據起始步驟和執行模式篩選要執行的步驟
            $steps_to_execute = [];
            
            if ($this->single_step) {
                // 單一步驟模式：只執行指定步驟
                if (isset($this->steps[$this->start_step])) {
                    $steps_to_execute[$this->start_step] = $this->steps[$this->start_step];
                }
            } else {
                // 連續執行模式：從指定步驟執行到最後
                $start_found = false;
                
                foreach ($this->steps as $step_num => $step_name) {
                    if ($step_num === $this->start_step) {
                        $start_found = true;
                    }
                    
                    if ($start_found) {
                        $steps_to_execute[$step_num] = $step_name;
                    }
                }
            }
            
            if (empty($steps_to_execute)) {
                throw new Exception("無效的起始步驟: {$this->start_step}");
            }
            
            $execution_mode = $this->single_step ? "單一步驟" : "連續執行";
            $this->log("執行模式: {$execution_mode}");
            $this->log("將執行 " . count($steps_to_execute) . " 個步驟");
            
            foreach ($steps_to_execute as $step_num => $step_name) {
                $this->executeStep($step_num, $step_name);
            }
            
            $total_time = round(microtime(true) - $start_time, 2);
            $this->log("=== 部署完成 ===");
            $this->log("總耗時: {$total_time} 秒");
            $this->log("網站 URL: https://" . $this->job_data['confirmed_data']['domain']);
            
        } catch (Exception $e) {
            $this->log("部署失敗: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * 執行單一步驟
     */
    private function executeStep($step_num, $step_name)
    {
        $this->log("步驟 {$step_num}: {$step_name} - 開始");
        $step_start = microtime(true);
        
        try {
            $script_file = DEPLOY_BASE_PATH . "/step-{$step_num}.php";
            
            if (!file_exists($script_file)) {
                throw new Exception("找不到步驟腳本: {$script_file}");
            }
            
            // 執行步驟腳本
            $result = $this->runStepScript($script_file);
            
            $step_time = round(microtime(true) - $step_start, 2);
            $this->log("步驟 {$step_num}: {$step_name} - 完成 ({$step_time}s)");
            
            return $result;
            
        } catch (Exception $e) {
            $this->log("步驟 {$step_num} 失敗: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * 執行步驟腳本
     */
    private function runStepScript($script_file)
    {
        // 設定腳本可用的變數
        $job_id = $this->job_id;
        $job_data = $this->job_data;
        $config = ConfigManager::getInstance();
        $deployer = $this;
        
        // 執行腳本
        return require $script_file;
    }
    
    /**
     * 記錄日誌
     */
    public function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] [{$level}] {$message}\n";
        
        // 輸出到控制台
        echo $log_message;
        
        // 寫入通用日誌檔案
        $general_log_file = DEPLOY_LOGS_PATH . '/deploy-' . date('Y-m-d') . '.log';
        file_put_contents($general_log_file, $log_message, FILE_APPEND | LOCK_EX);
        
        // 寫入 Job 專用日誌檔案
        if (isset($this->job_log_file)) {
            file_put_contents($this->job_log_file, $log_message, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * 取得 Job 資料
     */
    public function getJobData()
    {
        return $this->job_data;
    }
}

// 主程式執行
try {
    // 檢查參數
    if ($argc < 2) {
        echo "使用方式: php contenta-deploy.php [job_id] [--step=XX] [--all]\n";
        echo "例如: php contenta-deploy.php 2506290730-3450 --step=00  (只執行步驟 00)\n";
        echo "例如: php contenta-deploy.php 2506290730-3450 --step=08  (只執行步驟 08)\n";
        echo "例如: php contenta-deploy.php 2506290730-3450 --all      (執行所有步驟)\n";
        echo "例如: php contenta-deploy.php 2506290730-3450 --step=08 --all  (從步驟 08 執行到最後)\n";
        exit(1);
    }
    
    $job_id = $argv[1];
    $start_step = '00'; // 預設從步驟 00 開始
    $single_step = true; // 預設只執行單一步驟
    
    // 處理命令列參數
    if ($argc >= 3) {
        foreach (array_slice($argv, 2) as $arg) {
            if (strpos($arg, '--step=') === 0) {
                $start_step = substr($arg, 7);
            } elseif ($arg === '--all') {
                $single_step = false; // 執行所有步驟
            }
        }
    }
    
    // 驗證 job_id 格式
    if (!preg_match('/^\d{10}-\d{4}$/', $job_id)) {
        echo "錯誤: job_id 格式不正確，應為 YYMMDDHHMM-XXXX 格式\n";
        exit(1);
    }
    
    // 驗證起始步驟格式 (支援 00-19 和 XX-Y 格式，如 09-5)
    if (!preg_match('/^\d{2}(-\d+)?$/', $start_step)) {
        echo "錯誤: 起始步驟格式不正確，應為 00-19 或 XX-Y 格式 (如 09-5)\n";
        exit(1);
    }
    
    // 檢查主步驟數字範圍
    $main_step = explode('-', $start_step)[0];
    if (intval($main_step) > 19) {
        echo "錯誤: 主步驟數字不能超過 19\n";
        exit(1);
    }
    
    // 建立部署器並執行
    $deployer = new ContentaDeployer($job_id, $start_step, $single_step);
    $deployer->deploy();
    
} catch (Exception $e) {
    echo "部署失敗: " . $e->getMessage() . "\n";
    exit(1);
}