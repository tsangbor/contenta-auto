<?php
/**
 * 步驟 08 簡化版: AI 生成網站配置檔案
 * 避免函數重複定義問題的簡化版本
 */

// 確保工作目錄存在
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
if (!is_dir($work_dir)) {
    // 建立完整結構
    $subdirs = ['config', 'scripts', 'json', 'images', 'logs', 'layout'];
    mkdir($work_dir, 0755, true);
    foreach ($subdirs as $subdir) {
        mkdir($work_dir . '/' . $subdir, 0755, true);
    }
    
    $deployer->log("工作目錄不存在，正在建立: {$work_dir}");
    
    // 使用現有資料或建立預設資料
    $data_dir = DEPLOY_BASE_PATH . '/data';
    $json_file = null;
    if (is_dir($data_dir)) {
        $data_files = scandir($data_dir);
        foreach ($data_files as $file) {
            if (preg_match('/\.json$/', $file)) {
                $json_file = $data_dir . '/' . $file;
                break;
            }
        }
    }
    
    if ($json_file && file_exists($json_file)) {
        $existing_data = json_decode(file_get_contents($json_file), true);
        if ($existing_data && isset($existing_data['confirmed_data'])) {
            $processed_data = $existing_data;
        } else {
            $processed_data = [
                'confirmed_data' => [
                    'domain' => 'test-ai-generation.tw',
                    'website_name' => 'AI 測試網站',
                    'website_description' => '這是用於測試 AI 配置生成的網站',
                    'user_email' => 'test@example.com'
                ]
            ];
        }
    } else {
        $processed_data = [
            'confirmed_data' => [
                'domain' => 'test-ai-generation.tw',
                'website_name' => 'AI 測試網站',
                'website_description' => '這是用於測試 AI 配置生成的網站',
                'user_email' => 'test@example.com'
            ]
        ];
    }
    
    file_put_contents($work_dir . '/config/processed_data.json', 
        json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("已建立工作環境並載入資料");
} else {
    // 載入現有資料
    $processed_data_file = $work_dir . '/config/processed_data.json';
    if (file_exists($processed_data_file)) {
        $processed_data = json_decode(file_get_contents($processed_data_file), true);
    } else {
        $deployer->log("警告: processed_data.json 不存在，建立基本資料");
        $processed_data = [
            'confirmed_data' => [
                'domain' => 'test-ai-generation.tw',
                'website_name' => 'AI 測試網站',
                'website_description' => '這是用於測試 AI 配置生成的網站',
                'user_email' => 'test@example.com'
            ]
        ];
        if (!is_dir($work_dir . '/config')) {
            mkdir($work_dir . '/config', 0755, true);
        }
        file_put_contents($work_dir . '/config/processed_data.json', 
            json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

$domain = $processed_data['confirmed_data']['domain'];
$website_name = $processed_data['confirmed_data']['website_name'];

$deployer->log("開始 AI 生成網站配置檔案: {$domain}");

// 建立模擬配置檔案（簡化版本）
$site_config = [
    'site_info' => [
        'name' => $website_name,
        'domain' => $domain,
        'description' => $processed_data['confirmed_data']['website_description'] ?? '測試網站'
    ],
    'color_scheme' => [
        'primary' => '#2D4C4A',
        'secondary' => '#7A8370', 
        'text' => '#1A1A1A',
        'accent' => '#BFAA96'
    ]
];

$article_prompts = [
    'articles' => [
        [
            'title' => '歡迎來到我的網站',
            'content' => '這是一篇示例文章內容。',
            'category' => '公告'
        ]
    ]
];

$image_prompts = [
    'logo' => [
        'title' => '網站 Logo',
        'prompt' => "網站 Logo 設計，文字為 '{$website_name}'，專業風格",
        'ai' => 'openai',
        'style' => 'logo',
        'quality' => 'high',
        'size' => '800x200'
    ],
    'index_hero_bg' => [
        'title' => '首頁背景圖',
        'prompt' => '專業網站首頁背景圖，現代簡約風格',
        'ai' => 'gemini',
        'style' => 'background',
        'quality' => 'high',
        'size' => '1920x1080'
    ]
];

// 儲存生成的檔案
$saved_files = [];
$files_to_save = [
    'site-config.json' => $site_config,
    'article-prompts.json' => $article_prompts,
    'image-prompts.json' => $image_prompts
];

foreach ($files_to_save as $filename => $content) {
    $file_path = $work_dir . '/json/' . $filename;
    $json_content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($file_path, $json_content)) {
        $saved_files[] = $filename;
        $deployer->log("儲存檔案: {$filename}");
    } else {
        $deployer->log("儲存失敗: {$filename}");
    }
}

// 儲存生成資訊
$generation_info = [
    'ai_service' => 'Mock',
    'ai_model' => 'test-model',
    'generated_files' => $saved_files,
    'timestamp' => date('Y-m-d H:i:s')
];

file_put_contents($work_dir . '/json/generation_info.json', 
    json_encode($generation_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("步驟 08 完成，生成了 " . count($saved_files) . " 個配置檔案");

return ['status' => 'success', 'generated_files' => $saved_files];

?>