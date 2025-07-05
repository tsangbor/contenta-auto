<?php
/**
 * 測試改進的佔位符檢測邏輯
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

// 載入改進的函數
require_once 'test-improved-detection-functions.php';

echo "=== 改進的佔位符檢測測試 ===\n\n";

// 測試資料
$test_data = [
    // 1. 標準 heading 元素
    [
        'widgetType' => 'heading',
        'settings' => [
            'title' => '我們的服務'
        ]
    ],
    // 2. icon-box 元素
    [
        'widgetType' => 'icon-box',
        'settings' => [
            'title_text' => '內容策略',
            'description_text' => '我們將根據'
        ]
    ],
    // 3. text-editor 元素
    [
        'widgetType' => 'text-editor',
        'settings' => [
            'editor' => '<p>首頁ABOUT內容</p>'
        ]
    ],
    // 4. 明確的佔位符
    [
        'widgetType' => 'heading',
        'settings' => [
            'title' => 'HERO_TITLE'
        ]
    ],
    // 5. 包含佔位符格式的文字
    [
        'widgetType' => 'heading',
        'settings' => [
            'title' => 'HOME_SUBTITLE_TEXT'
        ]
    ]
];

foreach ($test_data as $index => $element) {
    echo "🧪 測試元素 " . ($index + 1) . ":\n";
    echo "Widget Type: " . $element['widgetType'] . "\n";
    
    $placeholders = [];
    findPlaceholders($element, $placeholders);
    
    echo "發現的佔位符:\n";
    if (empty($placeholders)) {
        echo "  (無)\n";
    } else {
        foreach ($placeholders as $placeholder) {
            echo "  - {$placeholder}\n";
        }
    }
    
    // 測試簡化內容
    $simplified = simplifyPageContent($element);
    echo "簡化內容:\n";
    echo "  " . json_encode($simplified, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "\n";
}

// 測試真實檔案
echo "📄 測試真實檔案:\n";
$job_id = '2506302336-TEST';
$layout_dir = __DIR__ . '/temp/' . $job_id . '/layout';

if (is_dir($layout_dir)) {
    $files = ['home.json', 'about.json'];
    
    foreach ($files as $filename) {
        $file_path = $layout_dir . '/' . $filename;
        if (file_exists($file_path)) {
            echo "\n📁 檔案: {$filename}\n";
            
            $content = json_decode(file_get_contents($file_path), true);
            if ($content) {
                $placeholders = [];
                findPlaceholders($content, $placeholders);
                
                echo "發現的佔位符 (" . count($placeholders) . " 個):\n";
                foreach ($placeholders as $placeholder) {
                    echo "  - {$placeholder}\n";
                }
                
                $simplified = simplifyPageContent($content);
                $simplified_json = json_encode($simplified, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                echo "簡化內容長度: " . strlen($simplified_json) . " 字元\n";
                echo "原始內容長度: " . strlen(json_encode($content)) . " 字元\n";
                echo "壓縮比例: " . round(strlen($simplified_json) / strlen(json_encode($content)) * 100, 1) . "%\n";
            }
        }
    }
} else {
    echo "測試目錄不存在: {$layout_dir}\n";
}

echo "\n=== 測試完成 ===\n";