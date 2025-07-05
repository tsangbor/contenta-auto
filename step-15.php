<?php
/**
 * 步驟 15: AI 文章與精選圖片生成 (v1.1)
 * 
 * 核心職責：以循環方式遍歷 article-prompts.json 中定義的所有文章策略，
 * 並為每一條策略生成一篇完整、高品質的 WordPress 文章，
 * 同時為其生成、上傳和設定一張與文章內容高度相關的精選圖片。
 * 
 * 執行工作流：
 * 1. 載入文章策略陣列
 * 2. 循環處理每個策略：
 *    15.1 生成文章內容
 *    15.2 分析文章並生成精選圖片提示詞
 *    15.3 生成精選圖片實體
 *    15.4 上傳圖片並發布文章
 *    15.5 關聯精選圖片
 * 3. 錯誤容忍：單個失敗不中斷整體流程
 * 
 * @package Contenta
 * @version 1.1
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
$website_info_file = $work_dir . '/wordpress_install.json';
if (!file_exists($website_info_file)) {
    $website_info_file = $work_dir . '/bt_website.json';
}

if (!file_exists($website_info_file)) {
    $deployer->log("錯誤: 找不到網站資訊檔案");
    return ['status' => 'error', 'message' => '找不到網站資訊檔案'];
}

$website_info = json_decode(file_get_contents($website_info_file), true);

$domain = $processed_data['confirmed_data']['domain'];
$document_root = $website_info['document_root']; // /www/wwwroot/www.{domain}

$deployer->log("=== 步驟 15: AI 文章與精選圖片生成 ===");
$deployer->log("目標網域: {$domain}");
$deployer->log("WordPress 目錄: {$document_root}");

// 載入文章策略
$article_prompts_file = $work_dir . '/json/article-prompts.json';
if (!file_exists($article_prompts_file)) {
    $deployer->log("錯誤: article-prompts.json 檔案不存在: {$article_prompts_file}");
    return ['status' => 'error', 'message' => 'article-prompts.json 不存在'];
}

$prompts = json_decode(file_get_contents($article_prompts_file), true);
if (!is_array($prompts) || empty($prompts)) {
    $deployer->log("錯誤: article-prompts.json 格式無效或為空");
    return ['status' => 'error', 'message' => 'article-prompts.json 格式無效'];
}

$deployer->log("找到 " . count($prompts) . " 個文章策略");

// 初始化 WP-CLI 執行器
$wp_cli = new WP_CLI_Executor($config);
$wp_cli->set_document_root($document_root);

// 檢查 WP-CLI 可用性
$deployer->log("檢查 WP-CLI 可用性...");
if (!$wp_cli->is_available()) {
    $deployer->log("錯誤: WP-CLI 不可用");
    return ['status' => 'error', 'message' => 'WP-CLI 不可用'];
}

$wp_info = $wp_cli->get_wp_info();
$deployer->log("WordPress 資訊: " . ($wp_info['available'] ? '正常' : '異常'));

// 載入站點配置以取得分類資訊
$site_config_file = $work_dir . '/json/site-config.json';
if (file_exists($site_config_file)) {
    $site_config = json_decode(file_get_contents($site_config_file), true);
    
    // 建立分類（如果不存在）
    if (isset($site_config['categories']) && is_array($site_config['categories'])) {
        $deployer->log("檢查並建立文章分類...");
        
        foreach ($site_config['categories'] as $category) {
            $name = $category['name'] ?? '';
            $slug = $category['slug'] ?? '';
            $description = $category['description'] ?? '';
            
            if (empty($name) || empty($slug)) {
                continue;
            }
            
            // 檢查分類是否已存在
            if ($wp_cli->category_exists($slug)) {
                $deployer->log("  分類 '{$name}' (slug: {$slug}) 已存在");
            } else {
                // 建立新分類
                $create_result = $wp_cli->create_category($name, $slug, $description);
                
                if ($create_result['return_code'] === 0) {
                    $deployer->log("  建立分類 '{$name}' (slug: {$slug}) 成功，ID: {$create_result['term_id']}");
                } else {
                    $deployer->log("  建立分類 '{$name}' 失敗: " . $create_result['output']);
                }
            }
        }
    }
} else {
    $deployer->log("未找到 site-config.json，跳過分類建立");
}

// 取得 API 配置
$openai_api_key = $config->get('api_credentials.openai.api_key');
$google_api_key = $config->get('api_credentials.gemini.api_key');

$deployer->log("API 金鑰檢查:");
$deployer->log("OpenAI API 金鑰: " . (empty($openai_api_key) ? '未設定' : substr($openai_api_key, 0, 10) . '...'));
$deployer->log("Gemini API 金鑰: " . (empty($google_api_key) ? '未設定' : substr($google_api_key, 0, 10) . '...'));

if (empty($openai_api_key)) {
    $deployer->log("錯誤: 未設定 OpenAI API 金鑰");
    return ['status' => 'error', 'message' => '未設定 OpenAI API 金鑰'];
}

// 成本優化建議
$deployer->log("成本優化分析:");
$content_model = Contenta_Cost_Optimizer::recommend_model('content_generation', 'standard');
$image_model = Contenta_Cost_Optimizer::recommend_model('image_generation', 'standard');
$deployer->log("建議內容生成模型: {$content_model}");
$deployer->log("建議圖片生成模型: {$image_model}");

// 建立圖片臨時目錄
$images_temp_dir = $work_dir . '/images';
if (!is_dir($images_temp_dir)) {
    mkdir($images_temp_dir, 0755, true);
}

// 統計變數
$successful_articles = 0;
$failed_articles = 0;
$total_cost = 0;
$generation_results = [];

/**
 * 清理 AI 生成的內容，移除 markdown 程式碼標記
 */
function clean_ai_content($content) {
    // 移除開頭的 ```html 或 ```
    $content = preg_replace('/^```(html)?\s*\n?/i', '', $content);
    
    // 移除結尾的 ```
    $content = preg_replace('/\n?```\s*$/i', '', $content);
    
    // 移除任何其他的程式碼區塊標記
    $content = preg_replace('/```[a-z]*\s*\n?/i', '', $content);
    
    return trim($content);
}

/**
 * 呼叫 OpenAI API 生成內容
 */
function call_openai_api($prompt, $model = 'gpt-4o-mini', $max_tokens = 2000, $api_key = null) {
    global $deployer;
    
    if ($api_key === null) {
        global $openai_api_key;
        $api_key = $openai_api_key;
    }
    
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ],
        'max_tokens' => $max_tokens,
        'temperature' => 0.7
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return [
                'success' => true,
                'content' => $result['choices'][0]['message']['content'],
                'usage' => $result['usage'] ?? []
            ];
        }
    }
    
    $deployer->log("OpenAI API 錯誤 - HTTP {$http_code}: {$response}");
    return ['success' => false, 'error' => $response];
}

/**
 * 生成 DALL-E 3 圖片
 */
function generate_dalle_image($prompt, $size = '1024x1024', $quality = 'standard', $api_key = null) {
    global $deployer;
    
    if ($api_key === null) {
        global $openai_api_key;
        $api_key = $openai_api_key;
    }
    
    $url = 'https://api.openai.com/v1/images/generations';
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $size,
        'quality' => $quality
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'][0]['url'])) {
            return [
                'success' => true,
                'url' => $result['data'][0]['url'],
                'revised_prompt' => $result['data'][0]['revised_prompt'] ?? $prompt
            ];
        }
    }
    
    $deployer->log("DALL-E 3 API 錯誤 - HTTP {$http_code}: {$response}");
    return ['success' => false, 'error' => $response];
}

/**
 * 下載圖片到本地
 */
function download_image($url, $local_path) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $image_data !== false) {
        return file_put_contents($local_path, $image_data) !== false;
    }
    
    return false;
}

// 主要循環：處理每個文章策略
foreach ($prompts as $index => $article_prompt) {
    $current_article = $index + 1;
    $total_articles = count($prompts);
    
    $deployer->log("處理文章 {$current_article}/{$total_articles}: {$article_prompt['title']}");
    
    try {
        // 15.1 生成文章內容
        $deployer->log("  15.1 生成文章內容...");
        
        $content_prompt = "你是一位專業的內容編寫專家。請根據以下要求撰寫一篇高品質的繁體中文文章：

標題: {$article_prompt['title']}
描述: {$article_prompt['description']}
分類: {$article_prompt['category']}
關鍵詞: " . implode('、', $article_prompt['tags']) . "
語調: {$article_prompt['tone']}
字數要求: {$article_prompt['word_count']} 字

內容大綱:
" . implode("\n", array_map(function($item) { return "- $item"; }, $article_prompt['content_outline'])) . "

請撰寫一篇結構完整的文章，包含：
1. 吸引人的引言
2. 詳細的正文內容（依照大綱展開）
3. 具啟發性的結論
4. 使用 HTML 格式標籤（h2, h3, p, ul, li 等）來組織內容

重要規則：
- 直接輸出 HTML 格式的文章內容
- 不要在開頭加上 ```html 或結尾加上 ```
- 不要使用任何 markdown 程式碼區塊標記
- 不要包含任何額外的說明文字
- 確保所有內容都是有效的 HTML";

        $content_result = call_openai_api($content_prompt, 'gpt-4o-mini', 3000, $openai_api_key);
        
        if (!$content_result['success']) {
            throw new Exception("文章內容生成失敗: " . $content_result['error']);
        }
        
        $article_content = clean_ai_content($content_result['content']);
        $content_tokens = $content_result['usage'];
        
        $deployer->log("  文章內容生成完成 (" . strlen($article_content) . " 字元)");
        
        // 15.2 分析文章並生成精選圖片提示詞
        $deployer->log("  15.2 生成圖片提示詞...");
        
        $image_prompt_instruction = "你是一位專業的藝術總監。請仔細閱讀以下文章內容，然後為其設計一張精選圖片。

文章內容：
{$article_content}

請分析文章的核心主題、情感氛圍和視覺元素，然後生成一個詳細的英文圖片提示詞。

要求：
1. 提示詞必須是英文
2. 描述要具體且富有視覺感
3. 包含藝術風格建議（如 modern, minimalist, warm lighting 等）
4. 適合作為部落格文章的精選圖片
5. 避免包含具體的文字或數字
6. 長度控制在 200 字以內

請直接輸出英文提示詞，不要包含任何中文說明。";

        $image_prompt_result = call_openai_api($image_prompt_instruction, 'gpt-4o-mini', 300, $openai_api_key);
        
        if (!$image_prompt_result['success']) {
            throw new Exception("圖片提示詞生成失敗: " . $image_prompt_result['error']);
        }
        
        $image_prompt = trim($image_prompt_result['content']);
        $deployer->log("  圖片提示詞: " . substr($image_prompt, 0, 100) . "...");
        
        // 15.3 生成精選圖片實體
        $deployer->log("  15.3 生成精選圖片...");
        
        $image_result = generate_dalle_image($image_prompt, '1024x1024', 'standard', $openai_api_key);
        
        if (!$image_result['success']) {
            throw new Exception("圖片生成失敗: " . $image_result['error']);
        }
        
        // 下載圖片到本地
        $post_slug = sanitize_title($article_prompt['title']);
        $image_filename = "featured_{$post_slug}_{$index}.png";
        $local_image_path = $images_temp_dir . '/' . $image_filename;
        
        if (!download_image($image_result['url'], $local_image_path)) {
            throw new Exception("圖片下載失敗");
        }
        
        $deployer->log("  圖片下載完成: {$image_filename}");
        
        // 15.4 上傳圖片並發布文章
        $deployer->log("  15.4 上傳圖片並發布文章...");
        
        // 上傳圖片到媒體庫
        $media_title = $article_prompt['title'] . " - 精選圖片";
        $media_alt = $article_prompt['title'];
        
        $upload_result = $wp_cli->upload_media($local_image_path, $media_title, $media_alt);
        
        if ($upload_result['return_code'] !== 0 || !$upload_result['attachment_id']) {
            $error_msg = $upload_result['error'] ?? $upload_result['output'] ?? '未知錯誤';
            throw new Exception("圖片上傳失敗: " . $error_msg);
        }
        
        $attachment_id = $upload_result['attachment_id'];
        $deployer->log("  圖片上傳成功，附件 ID: {$attachment_id}");
        
        // 建立文章
        $post_data = [
            'title' => $article_prompt['title'],
            'content' => $article_content,
            'status' => 'publish',
            'type' => 'post',
            'tags' => implode(',', $article_prompt['tags'] ?? [])
        ];
        
        // 分類對應關係（從 article-prompts.json 到 site-config.json 的分類）
        $category_mapping = [
            '人類圖諮詢' => 'human-design-knowledge',
            '能量調頻' => 'self-awareness',  // 能量調頻相關文章歸類到自我覺察
            '線上課程' => 'growth-resources', // 線上課程歸類到自我成長資源推薦
            '自我成長' => 'self-awareness', 
            '個人品牌' => 'growth-resources'
        ];
        
        // 設定分類（使用分類 ID）
        $article_category = $article_prompt['category'] ?? '';
        if (!empty($article_category) && isset($category_mapping[$article_category])) {
            $category_slug = $category_mapping[$article_category];
            
            // 取得分類 ID
            $category_id = $wp_cli->get_category_id($category_slug);
            if ($category_id) {
                $post_data['category'] = $category_id;
                $deployer->log("  文章將分類至: {$article_category} (slug: {$category_slug}, ID: {$category_id})");
            } else {
                $deployer->log("  警告: 找不到分類 ID for slug: {$category_slug}");
            }
        }
        
        $post_result = $wp_cli->create_post($post_data);
        
        if ($post_result['return_code'] !== 0 || !$post_result['post_id']) {
            $error_msg = $post_result['error'] ?? $post_result['output'] ?? '未知錯誤';
            throw new Exception("文章建立失敗: " . $error_msg);
        }
        
        $post_id = $post_result['post_id'];
        $deployer->log("  文章建立成功，文章 ID: {$post_id}");
        
        // 15.5 關聯精選圖片
        $deployer->log("  15.5 設定精選圖片...");
        
        $featured_result = $wp_cli->set_featured_image($post_id, $attachment_id);
        
        if ($featured_result['return_code'] !== 0) {
            throw new Exception("精選圖片設定失敗: " . $featured_result['output']);
        }
        
        $deployer->log("  精選圖片設定完成");
        
        // 清理本地圖片檔案
        if (file_exists($local_image_path)) {
            unlink($local_image_path);
        }
        
        // 記錄成功結果
        $successful_articles++;
        
        $article_result = [
            'index' => $index,
            'title' => $article_prompt['title'],
            'post_id' => $post_id,
            'attachment_id' => $attachment_id,
            'image_prompt' => $image_prompt,
            'status' => 'success',
            'content_length' => strlen($article_content),
            'tokens_used' => $content_tokens
        ];
        
        $generation_results[] = $article_result;
        
        $deployer->log("文章 {$current_article} 處理完成: ID {$post_id}");
        
    } catch (Exception $e) {
        // 錯誤處理：記錄錯誤但繼續下一篇文章
        $failed_articles++;
        
        $deployer->log("文章 {$current_article} 處理失敗: " . $e->getMessage());
        
        $article_result = [
            'index' => $index,
            'title' => $article_prompt['title'],
            'status' => 'failed',
            'error' => $e->getMessage()
        ];
        
        $generation_results[] = $article_result;
        
        // 清理可能的臨時檔案
        if (isset($local_image_path) && file_exists($local_image_path)) {
            unlink($local_image_path);
        }
        
        // 繼續下一次循環
        continue;
    }
    
    // 添加短暫延遲避免 API 限制
    sleep(2);
}

// 清理圖片臨時目錄
if (is_dir($images_temp_dir)) {
    $remaining_files = glob($images_temp_dir . '/*');
    foreach ($remaining_files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($images_temp_dir);
}

// 生成最終報告
$deployer->log("=== 步驟 15 完成報告 ===");
$deployer->log("總文章數: " . count($prompts));
$deployer->log("成功生成: {$successful_articles}");
$deployer->log("失敗數量: {$failed_articles}");
$deployer->log("成功率: " . round(($successful_articles / count($prompts)) * 100, 1) . "%");

// 儲存詳細結果
$final_result = [
    'step' => 'step-15',
    'domain' => $domain,
    'total_articles' => count($prompts),
    'successful_articles' => $successful_articles,
    'failed_articles' => $failed_articles,
    'success_rate' => round(($successful_articles / count($prompts)) * 100, 1),
    'generation_results' => $generation_results,
    'processed_at' => date('Y-m-d H:i:s')
];

file_put_contents($work_dir . '/article_generation_results.json', json_encode($final_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$deployer->log("詳細結果已儲存至: article_generation_results.json");

if ($failed_articles > 0) {
    $deployer->log("有 {$failed_articles} 篇文章生成失敗，請檢查日誌");
}

// 輔助函數：清理標題用於 slug
function sanitize_title($title) {
    // 移除 HTML 標籤
    $title = strip_tags($title);
    // 轉換為小寫
    $title = strtolower($title);
    // 移除特殊字符，只保留字母、數字、中文字符和空格
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);
    // 將空格替換為連字符
    $title = preg_replace('/\s+/', '-', trim($title));
    // 移除連續的連字符
    $title = preg_replace('/-+/', '-', $title);
    // 移除開頭和結尾的連字符
    $title = trim($title, '-');
    
    return $title;
}

return [
    'status' => $failed_articles === 0 ? 'success' : 'partial_success',
    'successful_articles' => $successful_articles,
    'failed_articles' => $failed_articles,
    'results' => $final_result
];