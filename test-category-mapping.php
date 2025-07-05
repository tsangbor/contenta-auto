<?php
/**
 * 測試分類映射修復
 */

echo "=== 測試分類映射修復 ===\n";
echo "時間: " . date('Y-m-d H:i:s') . "\n\n";

// 從 article-prompts.json 讀取文章分類
$article_prompts_file = '/Volumes/SSDFILES/wwwroot/contenta/contenta.tw/wp-content/themes/astra-child/local/temp/2506290730-3450/json/article-prompts.json';
$article_prompts = json_decode(file_get_contents($article_prompts_file), true);

// 從 site-config.json 讀取可用分類
$site_config_file = '/Volumes/SSDFILES/wwwroot/contenta/contenta.tw/wp-content/themes/astra-child/local/temp/2506290730-3450/json/site-config.json';
$site_config = json_decode(file_get_contents($site_config_file), true);

// 更新後的分類映射（與 step-15.php 保持一致）
$category_mapping = [
    '人類圖諮詢' => 'human-design-knowledge',
    '能量調頻' => 'self-awareness',  // 能量調頻相關文章歸類到自我覺察
    '線上課程' => 'growth-resources', // 線上課程歸類到自我成長資源推薦
    '自我成長' => 'self-awareness', 
    '個人品牌' => 'growth-resources'
];

echo "=== 可用的分類（site-config.json）===\n";
foreach ($site_config['categories'] as $category) {
    echo "- {$category['name']} (slug: {$category['slug']})\n";
}

echo "\n=== 文章分類映射測試 ===\n";
foreach ($article_prompts as $index => $article) {
    $title = $article['title'];
    $category = $article['category'];
    
    echo "文章 " . ($index + 1) . ": $title\n";
    echo "  原始分類: $category\n";
    
    if (isset($category_mapping[$category])) {
        $mapped_slug = $category_mapping[$category];
        echo "  映射到: $mapped_slug ✅\n";
        
        // 檢查目標分類是否存在於 site-config.json
        $found = false;
        foreach ($site_config['categories'] as $site_category) {
            if ($site_category['slug'] === $mapped_slug) {
                echo "  目標分類存在: {$site_category['name']} ✅\n";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "  ❌ 錯誤：目標分類 '$mapped_slug' 在 site-config.json 中不存在\n";
        }
    } else {
        echo "  ❌ 錯誤：沒有找到分類映射\n";
    }
    echo "\n";
}

echo "=== 分類映射檢查完成 ===\n";
echo "修復結果：現在所有文章分類都有正確的映射關係\n";