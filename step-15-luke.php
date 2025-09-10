<?php
/**
 * 步驟 15: AI 文章與精選圖片生成 (Luke API 模式)
 * 
 * 使用 Luke SSH 配置的 WP_CLI_Executor 來執行文章生成工作流程
 * 具體的業務邏輯在 includes/workflows/process-articles.php 中實作
 * 
 * Luke 模式特點：
 * - 使用 Luke SSH 配置進行 WP-CLI 連線
 * - 使用 Luke 路徑格式：/var/www/{domain}/html/
 * - 使用改進的 WP_CLI_Executor 類別支援 Luke 模式
 * 
 * @package Contenta
 * @version 3.0 (使用共用工作流程和統一執行器)
 */

// 載入必要的類別
$base_path = defined('DEPLOY_BASE_PATH') ? DEPLOY_BASE_PATH : __DIR__;
$wp_cli_executor_file = $base_path . '/includes/utilities/class-wp-cli-executor.php';
$cost_optimizer_file = $base_path . '/includes/utilities/class-contenta-cost-optimizer.php';

$deployer->log("檢查 WP_CLI_Executor 檔案: {$wp_cli_executor_file}");
if (file_exists($wp_cli_executor_file)) {
    require_once $wp_cli_executor_file;
    $deployer->log("WP_CLI_Executor 載入成功");
} else {
    $deployer->log("錯誤: 找不到 WP_CLI_Executor 類別檔案: {$wp_cli_executor_file}");
    return ['status' => 'error', 'message' => '找不到 WP_CLI_Executor 類別檔案'];
}

$deployer->log("檢查 Contenta_Cost_Optimizer 檔案: {$cost_optimizer_file}");
if (file_exists($cost_optimizer_file)) {
    require_once $cost_optimizer_file;
    $deployer->log("Contenta_Cost_Optimizer 載入成功");
} else {
    $deployer->log("錯誤: 找不到 Contenta_Cost_Optimizer 類別檔案: {$cost_optimizer_file}");
    return ['status' => 'error', 'message' => '找不到 Contenta_Cost_Optimizer 類別檔案'];
}

// 載入處理後的資料
$work_dir = $base_path . '/temp/' . $job_id;
$processed_data = json_decode(file_get_contents($work_dir . '/config/processed_data.json'), true);

// 嘗試載入網站資訊
$site_config_file = $work_dir . '/config/site-config.json';
if (file_exists($site_config_file)) {
    $site_config = json_decode(file_get_contents($site_config_file), true);
} else {
    $site_config = [];
}

$domain = $processed_data['confirmed_data']['domain'] ?? '';
$document_root = "/var/www/{$domain}/html"; // Luke 模式路徑格式

$deployer->log("=== 步驟 15: AI 文章與精選圖片生成 (Luke API 模式) ===");
$deployer->log("目標網域: {$domain}");
$deployer->log("WordPress 目錄: {$document_root}");

// 初始化 Luke 模式的 WP-CLI 執行器
$deployer->log("初始化 Luke 模式 WP-CLI 執行器...");
$wp_cli = new WP_CLI_Executor($config, true); // true = Luke 模式
$wp_cli->set_document_root($document_root);

// 載入並執行共用的文章處理工作流程
$workflow_file = $base_path . '/includes/workflows/process-articles.php';
if (!file_exists($workflow_file)) {
    $deployer->log("錯誤: 找不到共用工作流程檔案: {$workflow_file}");
    return ['status' => 'error', 'message' => '找不到共用工作流程檔案'];
}

$deployer->log("載入共用文章處理工作流程...");

// 設定工作流程需要的全域變數已經都存在：
// - $wp_cli: WP_CLI_Executor 實例 (已初始化為 Luke 模式)
// - $deployer: 日誌記錄器 (已存在)
// - $work_dir: 工作目錄 (已設定)
// - $config: 配置管理器 (已存在)
// - $job_id: Job ID (已存在)

// 執行共用工作流程
$workflow_result = require $workflow_file;

// 返回工作流程結果
return $workflow_result;