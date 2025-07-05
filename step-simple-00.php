<?php
/**
 * 步驟 00: 設定參數與載入配置（簡化版）
 * 直接從配置檔讀取所有資訊
 */

$deployer->log("開始處理配置資料...");

// 從配置取得網站資訊
$website_name = $config->get('site.name');
$website_description = $config->get('site.description');
$domain = $config->get('site.domain');
$user_email = $config->get('site.user_email');
$admin_email = $config->get('site.admin_email');

$deployer->log("網站名稱: {$website_name}");
$deployer->log("網域: {$domain}");
$deployer->log("用戶信箱: {$user_email}");

// 驗證必要資訊
if (empty($website_name) || empty($domain) || empty($user_email)) {
    throw new Exception("網站基本資訊不完整，請檢查配置檔案中的 site 區段");
}

// 建立工作目錄（使用部署ID）
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $deployment_id;
if (!is_dir($work_dir)) {
    mkdir($work_dir, 0755, true);
}

// 建立 config 子目錄
$config_dir = $work_dir . '/config';
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0755, true);
}

$deployer->log("工作目錄建立: {$work_dir}");

// 準備處理後的資料供其他步驟使用
$processed_data = [
    'website_name' => $website_name,
    'website_description' => $website_description,
    'domain' => $domain,
    'user_email' => $user_email,
    'admin_email' => $admin_email,
    'work_dir' => $work_dir,
    'deployment_id' => $deployment_id,
    
    // 從配置複製其他資訊
    'keywords' => $config->get('site.keywords', []),
    'target_audience' => $config->get('site.target_audience', ''),
    'brand_personality' => $config->get('site.brand_personality', ''),
    'unique_value' => $config->get('site.unique_value', ''),
    'service_categories' => $config->get('site.service_categories', [])
];

// 儲存處理後的資料
file_put_contents($work_dir . '/config/processed_data.json', json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("參數設定完成");

return $processed_data;