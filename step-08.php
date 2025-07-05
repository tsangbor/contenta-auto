<?php
/**
 * 步驟 08: AI 生成網站配置檔案
 * 使用 AI 基於用戶資料生成兩個標準化的 JSON 配置檔案
 * - site-config.json: 網站基本配置與內容
 * - article-prompts.json: 文章內容模板
 * 
 * 注意: image-prompts.json 已移至步驟 9.5 動態生成
 */

// 確保工作目錄存在
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
if (!is_dir($work_dir)) {
    // 如果工作目錄不存在，建立完整結構
    $subdirs = ['config', 'scripts', 'json', 'images', 'logs', 'layout'];
    if (!is_dir($work_dir)) {
        mkdir($work_dir, 0755, true);
    }
    foreach ($subdirs as $subdir) {
        $subdir_path = $work_dir . '/' . $subdir;
        if (!is_dir($subdir_path)) {
            mkdir($subdir_path, 0755, true);
        }
    }
    
    // 建立基本的 processed_data.json
    $deployer->log("工作目錄不存在，正在建立: {$work_dir}");
    
    // 檢查是否有現有的資料檔案
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
        // 使用現有的 JSON 資料
        $existing_data = json_decode(file_get_contents($json_file), true);
        if ($existing_data && isset($existing_data['confirmed_data'])) {
            $processed_data = $existing_data;
        } else {
            // 建立基本結構
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
        // 建立預設測試資料
        $processed_data = [
            'confirmed_data' => [
                'domain' => 'test-ai-generation.tw',
                'website_name' => 'AI 測試網站',
                'website_description' => '這是用於測試 AI 配置生成的網站',
                'user_email' => 'test@example.com'
            ]
        ];
    }
    
    // 儲存 processed_data.json
    file_put_contents($work_dir . '/config/processed_data.json', 
        json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("已建立工作環境並載入資料");
} else {
    // 載入現有的處理後資料
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

// 取得 AI API 設定（確保文字生成使用正確的 text model）
$openai_config = [
    'api_key' => $config->get('api_credentials.openai.api_key'),
    'model' => $config->get('api_credentials.openai.model') ?: 'gpt-4',
    'image_model' => $config->get('api_credentials.openai.image_model') ?: 'dall-e-3',
    'base_url' => $config->get('api_credentials.openai.base_url') ?: 'https://api.openai.com/v1/'
];

$gemini_config = [
    'api_key' => $config->get('api_credentials.gemini.api_key'),
    'model' => $config->get('api_credentials.gemini.model') ?: 'gemini-pro',
    'image_model' => $config->get('api_credentials.gemini.image_model') ?: 'gemini-2.0-flash-preview-image-generation',
    'base_url' => $config->get('api_credentials.gemini.base_url') ?: 'https://generativelanguage.googleapis.com/v1beta/models/'
];

// 優先使用 OpenAI，除非未設定 API Key
$use_openai = !empty($openai_config['api_key']);
$use_gemini = !empty($gemini_config['api_key']);

if (!$use_openai && !$use_gemini) {
    $deployer->log("跳過 AI 配置生成 - 未設定任何 AI API 憑證");
    return ['status' => 'skipped', 'reason' => 'no_ai_credentials'];
}

// 預設使用 OpenAI，如果沒有才使用 Gemini
if ($use_openai) {
    $ai_service = 'OpenAI';
    $ai_config = $openai_config;
} else {
    $ai_service = 'Gemini';
    $ai_config = $gemini_config;
}

$deployer->log("使用 AI 服務: {$ai_service}");
$deployer->log("使用模型: " . $ai_config['model']);

/**
 * 載入結構化提示詞模板
 */
if (!function_exists('getAIPromptTemplate_step08')) {
function getAIPromptTemplate_step08($container_list = [])
{
    // 根據 container 列表動態生成可用選項
    $container_options = '';
    if (!empty($container_list)) {
        $container_options = "\n**可用的容器選項**（從 template/container 目錄動態載入）：\n";
        foreach ($container_list as $category => $containers) {
            $container_options .= "  * {$category}: " . implode(', ', $containers) . "\n";
        }
    }
    
    return '
## 角色定義 (Role)

你是一位專業的網站建置專家和品牌策略顧問，具備以下專長：
- 10年以上WordPress網站開發經驗
- 深度理解數位作者的品牌建立需求
- 精通色彩心理學與視覺設計原理
- 擅長將複雜的品牌概念轉化為具體的網站配置
- 具備優秀的文案撰寫和內容策劃能力
- 一位頂尖的網站文案撰寫師，同時也是一位深諳 SEO 策略的數位行銷專家。

**所有輸出必須使用標準繁體中文，符合台灣用戶習慣。

## 任務目標 (Task)
- 你的任務是為 **{website_name}** 網站撰寫所有核心文案，確保其兼具吸引力、說服力與搜尋引擎優化。
- 請以讀者為中心（針對 **{target_audience}**），用引人入勝的敘事方式，精準傳達品牌價值（**{unique_value}**）。
- 文案要具有吸引力，並且能夠引起讀者的思考，展現品牌個性：**{brand_personality}**。
- 文案要具有說服力，讓讀者願意採取行動。
- 文案要符合SEO最佳化原則，確保在搜尋引擎中排名良好，重點關鍵字：**{brand_keywords}**。
- 所有生成的文案都應針對 **{target_audience}** 撰寫。
- 文案的最終目的是建立信任、引導讀者探索服務：**{service_categories}**。

基於提供的JSON資料模板，生成兩個標準化的JSON配置文件：
1. **site-config.json** - 網站架構與配置設定
2. **article-prompts.json** - 部落格內容策略草稿（深度整合品牌調性）

## 嚴格限制條件

### 1. 頁面結構限制
**page_list 基礎頁面**（必須包含）：
- home: "首頁"
- about: "關於我" 
- blog: "部落格"
- 其他頁面：由 AI 根據用戶資料決定（如 service, contact 等）

**layout_selection 容器規則**：
⚠️ **重要限制**：layout_selection 中**嚴禁使用** header 和 footer 容器！
- home 頁面的 hero 容器：必須使用 "homehero" 開頭的容器（如 homehero001, homehero002 等）
- 其他頁面的 hero 容器：使用 "hero" 開頭的容器（如 hero001, hero002 等）
- 其他區塊容器：**僅可選擇**以下實際存在的容器類型：' . $container_options . '

**❌ 嚴禁在 layout_selection 中使用的容器**：
header 類型容器 (如 header001, header002 等) - 不存在
footer 類型容器 (如 footer001, footer002 等) - 不存在
- **絕對禁止**使用任何未在【嚴格限定的容器列表】中出現的容器名稱。
- **絕對禁止**自行創造、編號或推測容器名稱 (例如 `contact002` 是【錯誤的】，因為列表中只有 `contact001`)。
- **如果你的輸出包含任何一個不存在的容器，整個系統將會崩潰並返回錯誤。你的任務將被判定為失敗。請務必僅使用提供的有效容器。**
- **生成後驗證**：輸出 `layout_selection` 時，附帶確認訊息：「已驗證，所有容器均符合規則。」

**⚠️ 重要驗證規則**：
- **只能選擇上方列出的實際存在容器**
- **絕對禁止創造或臆測容器名稱**
- 如果認為需要其他容器類型，**必須回報錯誤**而非自行編造
- 任何未列在上述容器清單中的容器名稱

### 2. 服務項目限制
- 數量：固定3個
- 來源：JSON的service_categories
- 處理邏輯：如果>3個選擇最核心的3個，如果<3個根據品牌定位合理補充

### 3. 內容分類限制
- 數量：最多3個
- 選擇策略：專業知識 → 個人成長 → 實際應用

### 4. Logo設計規格
- 字體：必須指定"使用Potta One字體風格"
- 格式：PNG透明背景
- 尺寸：800x200

### 5. 圖片生成調整（重構）
**注意**: 圖片需求現由步驟 9.5 基於實際頁面內容動態分析，無需在此步驟預設圖片清單。
步驟 9.5 會自動識別所有 `{{image:xxx}}` 佔位符並生成對應的個性化提示詞。

### 6. 文章模板控制（品牌調性深度整合）
- 數量：6-9篇（每分類2-3篇）
- 字數：1200-1800字
- **品牌調性要求**：
  - 每篇文章標題必須反映 **{brand_keywords}** 和 **{service_categories}**
  - 內容描述必須針對 **{target_audience}** 撰寫
  - 文章語調必須符合 **{brand_personality}** 
  - 文章分類必須與 **{service_categories}** 相關
  - 內容大綱必須展現 **{unique_value}** 的特色
- **個性化程度**：避免通用化內容，每篇文章都應該具有明確的品牌識別度

## 配色方案定義

專家導向主題（適用於：顧問、教練、專業服務、B2B）：
- expert-theme-1: 鈦金藍×銀灰（科技感）
- expert-theme-2: 黑金銅×暖感奢華（精品顧問）
- expert-theme-3: 濃墨綠×銀湖藍（理性專業）
- expert-theme-4: 橘磚紅×霧灰（品牌經營）
- expert-theme-5: 靛紫黑×鉻銀（策略金融）

生活導向主題（適用於：生活類、教學、創作、B2C）：
- lifestyle-theme-1: 春日橄欖×深綠對比
- lifestyle-theme-2: 柔粉米×木紅對比
- lifestyle-theme-3: 海岸藍綠×深藍對比
- lifestyle-theme-4: 黃昏杏橘×焦糖棕對比
- lifestyle-theme-5: 湖水粉藍×暗靛跳色

## 重要指示：資料來源優先級

**⚠️ 絕對不可忽略用戶真實資料**
1. **最高優先級**：用戶上傳的 JSON 資料（檔案名稱含有 job_id 的檔案）
2. **最低優先級**：範例模板檔案（僅供結構參考，嚴禁複製內容）

**✅ 正確做法**：
- 網站名稱必須完全使用：**{website_name}**
- 目標受眾必須完全使用：**{target_audience}**
- 品牌個性必須反映：**{brand_personality}**
- 服務項目必須基於：**{service_categories}**
- 關鍵字必須包含：**{brand_keywords}**
- 使用參考模板的**結構**，但**100%填入用戶真實內容**

**❌ 嚴禁行為**：
- ❌ 絕對不可複製參考模板中的任何品牌名稱（如「木子心」）
- ❌ 絕對不可複製參考模板中的任何服務內容
- ❌ 絕對不可忽略上述用戶真實資料參數
- ❌ 絕對不可在 layout_selection 中使用 header 或 footer 容器
- ❌ 絕對不可使用不存在的容器名稱

## 輸出要求

**⚠️ 必須生成完整結構**
site-config.json 必須包含以下所有區塊，不可遺漏：
1. `website_info` - 網站基本資訊
2. `page_list` - 頁面清單
3. `layout_selection` - 版面配置（⚠️ 嚴禁使用 header/footer 容器）
4. `content_options` - 內容選項
5. `menu_structure` - 導航選單結構（必須）
6. `categories` - 部落格分類（必須）
7. `social_media` - 社群媒體連結（必須）
8. `contact_info` - 聯絡資訊（必須）

**🔍 內容驗證要求**：
- website_blogname 必須包含：**{website_name}**
- 所有文案內容必須針對：**{target_audience}**
- 服務項目必須基於：**{service_categories}**
- layout_selection 中每個容器名稱都必須存在於上述容器清單中

請直接輸出三個完整的JSON檔案內容，格式如下：

**⚠️ layout_selection 必須使用以下扁平結構格式**：
```json
"layout_selection": [
  {
    "page": "home",
    "container": {
      "hero": "homehero001",
      "about": "about001", 
      "service": "service001",
      "archive": "archive001"
    }
  },
  {
    "page": "about", 
    "container": {
      "hero": "hero001",
      "about": "about001",
      "contact": "contact001"
    }
  }
]
```

**❌ 絕對禁止使用錯誤的巢狀格式**：
```json
"container": {
  "homehero001": {
    "hero": "homehero001"
  }
}
```

完整格式如下：

```json
{
  "site-config.json": { 
    "website_info": { ... },
    "page_list": { ... },
    "layout_selection": [ ... ],
    "content_options": { ... },
    "menu_structure": { "primary": [ ... ] },
    "categories": [ ... ],
    "social_media": { ... },
    "contact_info": { ... }
  },
  "article-prompts.json": [ 
    /* 品牌調性整合的文章模板陣列 - 每篇文章都必須：
     * 1. 標題包含 {brand_keywords} 相關詞彙
     * 2. 描述針對 {target_audience} 撰寫
     * 3. 語調符合 {brand_personality}
     * 4. 分類與 {service_categories} 相關
     * 5. 內容大綱展現 {unique_value}
     * 6. 避免通用化，具有明確品牌識別度
     */
  ]
}
```

請基於以下資料進行生成，**優先使用用戶真實資料**：
';
}
}

/**
 * 將用戶真實資料參數化到提示詞模板中
 */
if (!function_exists('personalizePromptTemplate_step08')) {
function personalizePromptTemplate_step08($prompt_template, $user_data, $deployer)
{
    $deployer->log("開始參數化提示詞模板...");
    
    // 提取用戶資料中的關鍵參數
    $confirmed_data = $user_data['confirmed_data'] ?? [];
    
    // 優先從 confirmed_data 取值，再檢查是否有其他來源
    $website_name = $confirmed_data['website_name'] ?? $confirmed_data['website_description'] ?? 'AI 測試網站';
    $target_audience = $confirmed_data['target_audience'] ?? '尋求個人成長與專業服務的使用者';
    $brand_personality = $confirmed_data['brand_personality'] ?? '專業、可信、溫暖、創新';
    $unique_value = $confirmed_data['unique_value'] ?? '提供個性化的專業服務解決方案';
    
    // 處理陣列型參數
    $brand_keywords = '個人品牌, 專業服務, 數位轉型';
    if (isset($confirmed_data['brand_keywords'])) {
        if (is_array($confirmed_data['brand_keywords'])) {
            $brand_keywords = implode('、', $confirmed_data['brand_keywords']);
        } elseif (is_string($confirmed_data['brand_keywords'])) {
            $brand_keywords = $confirmed_data['brand_keywords'];
        }
    }
    
    $service_categories = '專業諮詢, 解決方案設計, 客製化服務';
    if (isset($confirmed_data['service_categories'])) {
        if (is_array($confirmed_data['service_categories'])) {
            $service_categories = implode('、', $confirmed_data['service_categories']);
        } elseif (is_string($confirmed_data['service_categories'])) {
            $service_categories = $confirmed_data['service_categories'];
        }
    }
    
    // 進行參數替換
    $personalized_prompt = str_replace([
        '{website_name}',
        '{target_audience}',
        '{brand_personality}',
        '{unique_value}',
        '{brand_keywords}',
        '{service_categories}'
    ], [
        $website_name,
        $target_audience,
        $brand_personality,
        $unique_value,
        $brand_keywords,
        $service_categories
    ], $prompt_template);
    
    $deployer->log("提示詞參數化完成:");
    $deployer->log("- 網站名稱: {$website_name}");
    $deployer->log("- 目標受眾: {$target_audience}");
    $deployer->log("- 品牌個性: {$brand_personality}");
    $deployer->log("- 獨特價值: {$unique_value}");
    $deployer->log("- 關鍵字: {$brand_keywords}");
    $deployer->log("- 服務項目: {$service_categories}");
    
    return $personalized_prompt;
}
}


/**
 * 動態讀取 template/container 目錄中的容器類型
 */
if (!function_exists('collectContainerTypes_step08')) {
function collectContainerTypes_step08($template_dir, $deployer)
{
    $container_types = [];
    $container_dir = $template_dir . '/container';
    
    if (!is_dir($container_dir)) {
        $deployer->log("警告: template/container 目錄不存在: {$container_dir}");
        return $container_types;
    }
    
    $files = scandir($container_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !preg_match('/\.json$/', $file)) continue;
        
        // 從檔案名稱提取容器類型
        $container_name = str_replace('.json', '', $file);
        
        // 分類容器
        if (strpos($container_name, 'homehero') === 0) {
            $container_types['Home Hero容器'][] = $container_name;
        } elseif (strpos($container_name, 'hero') === 0) {
            $container_types['一般 Hero容器'][] = $container_name;
        } elseif (strpos($container_name, 'about') === 0) {
            $container_types['About容器'][] = $container_name;
        } elseif (strpos($container_name, 'service') === 0) {
            $container_types['Service容器'][] = $container_name;
        } elseif (strpos($container_name, 'archive') === 0) {
            $container_types['Archive容器'][] = $container_name;
        } elseif (strpos($container_name, 'contact') === 0) {
            $container_types['Contact容器'][] = $container_name;
        } elseif (strpos($container_name, 'cta') === 0) {
            $container_types['CTA容器'][] = $container_name;
        } elseif (strpos($container_name, 'faq') === 0) {
            $container_types['FAQ容器'][] = $container_name;
        } else {
            $container_types['其他容器'][] = $container_name;
        }
    }
    
    $deployer->log("找到 " . count($files) - 2 . " 個容器模板");
    
    return $container_types;
}
}

/**
 * 讀取 data 目錄下的所有檔案內容（排除 json 目錄）
 */
if (!function_exists('collectDataFiles_step08')) {
function collectDataFiles_step08($data_dir, $deployer)
{
    $data_content = [];
    
    if (!is_dir($data_dir)) {
        $deployer->log("警告: data 目錄不存在: {$data_dir}");
        return $data_content;
    }
    
    $files = scandir($data_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'json') continue; // 排除 json 目錄
        
        $file_path = $data_dir . '/' . $file;
        if (!is_file($file_path)) continue;
        
        $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        try {
            if ($file_ext === 'json') {
                $content = file_get_contents($file_path);
                if ($content) {
                    $data_content[$file] = $content;
                    $deployer->log("讀取 JSON 檔案: {$file}");
                }
            } elseif (in_array($file_ext, ['txt', 'md'])) {
                $content = file_get_contents($file_path);
                if ($content) {
                    $data_content[$file] = $content;
                    $deployer->log("讀取文字檔案: {$file}");
                }
            } elseif (in_array($file_ext, ['docx', 'pdf'])) {
                // 這些檔案需要特殊處理，暫時跳過
                $deployer->log("發現文件檔案（需特殊處理）: {$file}");
                $data_content[$file] = "[文件檔案: {$file}]";
            }
        } catch (Exception $e) {
            $deployer->log("無法讀取檔案 {$file}: " . $e->getMessage());
        }
    }
    
    return $data_content;
}
}

/**
 * 呼叫 OpenAI API
 */
if (!function_exists('callOpenAI_step08')) {
function callOpenAI_step08($ai_config, $prompt, $deployer)
{
    $url = rtrim($ai_config['base_url'], '/') . '/chat/completions';
    
    $data = [
        'model' => $ai_config['model'],
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 16000, // 增加 token 限制以確保完整結構生成
        'temperature' => 0.7
    ];
    
    $deployer->log("呼叫 OpenAI API: " . $ai_config['model']);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 增加到5分鐘
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 連線超時30秒
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $ai_config['api_key']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("OpenAI API 請求失敗: HTTP {$http_code}, 回應: {$response}");
    }
    
    $result = json_decode($response, true);
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception("OpenAI API 回應格式錯誤: " . json_encode($result));
    }
    
    return $result['choices'][0]['message']['content'];
}
}

/**
 * 呼叫 Gemini API
 */
if (!function_exists('callGemini_step08')) {
function callGemini_step08($ai_config, $prompt, $deployer)
{
    $url = rtrim($ai_config['base_url'], '/') . '/' . $ai_config['model'] . ':generateContent?key=' . $ai_config['api_key'];
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 8000 // 增加 token 限制以避免截斷
        ]
    ];
    
    $deployer->log("呼叫 Gemini API: " . $ai_config['model']);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 增加到5分鐘
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 連線超時30秒
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Gemini API 請求失敗: HTTP {$http_code}, 回應: {$response}");
    }
    
    $result = json_decode($response, true);
    if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Gemini API 回應格式錯誤: " . json_encode($result));
    }
    
    return $result['candidates'][0]['content']['parts'][0]['text'];
}
}

/**
 * 解析並儲存 AI 回應的 JSON 檔案
 */
if (!function_exists('parseAndSaveAIResponse_step08')) {
function parseAndSaveAIResponse_step08($ai_response, $work_dir, $deployer)
{
    $deployer->log("解析 AI 回應...");
    
    // 嘗試從回應中提取 JSON
    $json_start = strpos($ai_response, '```json');
    $json_end = strrpos($ai_response, '```');
    
    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($ai_response, $json_start + 7, $json_end - $json_start - 7);
    } else {
        // 如果沒有 markdown 格式，嘗試直接解析
        $json_content = $ai_response;
    }
    
    $parsed_data = json_decode(trim($json_content), true);
    
    if (!$parsed_data) {
        throw new Exception("無法解析 AI 回應的 JSON 格式");
    }
    
    $saved_files = [];
    $required_files = ['site-config.json', 'article-prompts.json'];
    
    foreach ($required_files as $filename) {
        if (isset($parsed_data[$filename])) {
            // 特別檢查 site-config.json 的完整性
            if ($filename === 'site-config.json') {
                $required_sections = ['website_info', 'page_list', 'layout_selection', 'content_options', 'menu_structure', 'categories', 'social_media', 'contact_info'];
                $missing_sections = [];
                
                foreach ($required_sections as $section) {
                    if (!isset($parsed_data[$filename][$section])) {
                        $missing_sections[] = $section;
                    }
                }
                
                if (!empty($missing_sections)) {
                    $deployer->log("警告: site-config.json 缺少區塊: " . implode(', ', $missing_sections));
                    // 不拋出異常，但記錄警告
                }
            }
            
            $file_path = $work_dir . '/json/' . $filename;
            $content = json_encode($parsed_data[$filename], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            if (file_put_contents($file_path, $content)) {
                $saved_files[] = $filename;
                $deployer->log("儲存檔案: {$filename}");
            } else {
                throw new Exception("無法儲存檔案: {$filename}");
            }
        } else {
            $deployer->log("警告: AI 回應中缺少檔案: {$filename}");
        }
    }
    
    return $saved_files;
}
}

/**
 * 顯示品牌配置摘要並請求用戶確認
 */
if (!function_exists('displayBrandConfigSummary_step08')) {
function displayBrandConfigSummary_step08($site_config_path, $deployer)
{
    try {
        $site_config = json_decode(file_get_contents($site_config_path), true);
        
        if (!$site_config) {
            $deployer->log("警告: 無法讀取 site-config.json，跳過確認步驟");
            return true;
        }
        
        // 顯示品牌配置摘要
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "🎨 品牌配置摘要 - 請確認以下 AI 生成的核心設定\n";
        echo str_repeat("=", 80) . "\n\n";
        
        // 網站基本資訊
        if (isset($site_config['website_info'])) {
            echo "📋 網站基本資訊:\n";
            echo "   網站名稱: " . ($site_config['website_info']['website_blogname'] ?? 'N/A') . "\n";
            echo "   網站描述: " . ($site_config['website_info']['website_blogdescription'] ?? 'N/A') . "\n";
            echo "   網域: " . ($site_config['website_info']['website_domain'] ?? 'N/A') . "\n";
            echo "   作者描述: " . mb_substr($site_config['website_info']['website_author_description'] ?? 'N/A', 0, 80) . "...\n";
            echo "\n";
        }
        
        // SEO 設定
        if (isset($site_config['website_info'])) {
            echo "🔍 SEO 設定:\n";
            echo "   SEO 標題: " . ($site_config['website_info']['seo_title'] ?? 'N/A') . "\n";
            echo "   SEO 描述: " . ($site_config['website_info']['seo_description'] ?? 'N/A') . "\n";
            echo "   SEO 關鍵字: " . ($site_config['website_info']['seo_keywords'] ?? 'N/A') . "\n";
            echo "\n";
        }
        
        // 品牌配色主題
        if (isset($site_config['website_info']['color_scheme'])) {
            echo "🎨 品牌配色主題: " . $site_config['website_info']['color_scheme'] . "\n\n";
        } else {
            echo "🎨 品牌配色主題: (將在後續步驟自動生成)\n\n";
        }
        
        // 頁面列表
        if (isset($site_config['page_list'])) {
            echo "📄 網站頁面:\n";
            foreach ($site_config['page_list'] as $key => $title) {
                echo "   • {$title} (/{$key})\n";
            }
            echo "\n";
        }
        
        // 服務項目（從 content_options 中提取）
        if (isset($site_config['content_options']['index_service_list'])) {
            echo "🛍️ 主要服務項目:\n";
            foreach ($site_config['content_options']['index_service_list'] as $index => $service) {
                echo "   " . ($index + 1) . ". " . $service['title'] . "\n";
                echo "      - " . $service['description'] . "\n";
            }
            echo "\n";
        }
        
        // 內容選項（Hero 部分）
        if (isset($site_config['content_options'])) {
            echo "✍️ 首頁主要文案:\n";
            echo "   Hero 標題: " . ($site_config['content_options']['index_hero_title'] ?? 'N/A') . "\n";
            echo "   Hero 副標題: " . ($site_config['content_options']['index_hero_subtitle'] ?? 'N/A') . "\n";
            echo "   關於標題: " . ($site_config['content_options']['index_about_title'] ?? 'N/A') . "\n";
            echo "   關於內容: " . mb_substr($site_config['content_options']['index_about_content'] ?? 'N/A', 0, 100) . "...\n";
            echo "\n";
        }
        
        // 文章分類
        if (isset($site_config['categories']) && !empty($site_config['categories'])) {
            echo "📂 文章分類:\n";
            foreach ($site_config['categories'] as $category) {
                echo "   • " . $category['name'] . " (" . $category['slug'] . ")\n";
                if (!empty($category['description'])) {
                    echo "     " . mb_substr($category['description'], 0, 60) . "...\n";
                }
            }
            echo "\n";
        }
        
        // 社群媒體連結
        if (isset($site_config['social_media'])) {
            echo "🌐 社群媒體:\n";
            foreach ($site_config['social_media'] as $platform => $url) {
                if (!empty($url) && $url !== 'N/A') {
                    echo "   • " . ucfirst($platform) . ": " . $url . "\n";
                }
            }
            echo "\n";
        }
        
        // 聯絡資訊
        if (isset($site_config['contact_info'])) {
            echo "📞 聯絡資訊:\n";
            echo "   Email: " . ($site_config['contact_info']['email'] ?? 'N/A') . "\n";
            if (!empty($site_config['contact_info']['phone'])) {
                echo "   電話: " . $site_config['contact_info']['phone'] . "\n";
            }
            if (!empty($site_config['contact_info']['address'])) {
                echo "   地址: " . $site_config['contact_info']['address'] . "\n";
            }
            if (!empty($site_config['contact_info']['business_hours'])) {
                echo "   營業時間: " . $site_config['contact_info']['business_hours'] . "\n";
            }
            echo "\n";
        }
        
        // 頁面佈局選擇
        if (isset($site_config['layout_selection'])) {
            echo "🏗️ 頁面佈局配置:\n";
            foreach ($site_config['layout_selection'] as $page) {
                $page_name = $page['page'] ?? 'Unknown';
                echo "   {$page_name} 頁面:\n";
                if (isset($page['container'])) {
                    foreach ($page['container'] as $section => $template) {
                        // 修復 Array to string conversion 警告
                        $template_display = is_array($template) ? json_encode($template, JSON_UNESCAPED_UNICODE) : $template;
                        echo "      - {$section}: {$template_display}\n";
                    }
                }
            }
            echo "\n";
        }
        
        echo str_repeat("-", 80) . "\n";
        echo "⚠️  重要提醒:\n";
        echo "   • 品牌方向一旦確立，後續步驟將基於此配置進行\n";
        echo "   • 如果配置不符合預期，建議現在中止並重新調整\n";
        echo "   • 繼續執行將消耗更多 AI API 成本進行圖片和內容生成\n";
        echo str_repeat("-", 80) . "\n\n";
        
        // 互動式確認
        while (true) {
            echo "📝 品牌配置確認: 以上設定是否符合您的預期？ (Y/n): ";
            $handle = fopen("php://stdin", "r");
            $response = trim(fgets($handle));
            fclose($handle);
            
            $response_lower = strtolower($response);
            
            if ($response_lower === '' || $response_lower === 'y' || $response_lower === 'yes') {
                echo "\n✅ 品牌配置已確認，繼續執行後續步驟...\n\n";
                return true;
            } elseif ($response_lower === 'n' || $response_lower === 'no') {
                echo "\n❌ 品牌配置未確認，已中止執行\n";
                echo "💡 建議修改 data/ 目錄中的用戶資料或 json/ 目錄中的模板後重新執行\n\n";
                return false;
            } else {
                echo "❓ 請輸入 Y (繼續) 或 N (中止): ";
            }
        }
        
    } catch (Exception $e) {
        $deployer->log("品牌配置摘要顯示失敗: " . $e->getMessage());
        echo "\n⚠️ 無法顯示品牌配置摘要，是否繼續？ (Y/n): ";
        $handle = fopen("php://stdin", "r");
        $response = trim(fgets($handle));
        fclose($handle);
        
        return strtolower($response) !== 'n';
    }
}
}

try {
    // 讀取 data 目錄下的所有檔案
    $data_dir = DEPLOY_BASE_PATH . '/data';
    $deployer->log("讀取資料檔案目錄: {$data_dir}");
    
    $data_files = collectDataFiles_step08($data_dir, $deployer);
    if (empty($data_files)) {
        throw new Exception("沒有找到可用的資料檔案");
    }
    
    // 讀取 JSON 模板檔案（使用 collectDataFiles 方式）
    $json_template_dir = DEPLOY_BASE_PATH . '/json';
    $deployer->log("讀取 JSON 模板目錄: {$json_template_dir}");
    
    $json_templates = collectDataFiles_step08($json_template_dir, $deployer);
    
    // 動態讀取容器類型
    $template_dir = DEPLOY_BASE_PATH . '/template';
    $container_types = collectContainerTypes_step08($template_dir, $deployer);
    
    // 建立完整的提示詞（傳入容器類型清單）
    $prompt_template = getAIPromptTemplate_step08($container_types);
    
    // 將用戶真實資料參數化到提示詞中
    $personalized_prompt = personalizePromptTemplate_step08($prompt_template, $processed_data, $deployer);
    
    $full_prompt = $personalized_prompt . "\n\n";
    
    // 優先顯示用戶真實資料
    $full_prompt .= "## 🎯 用戶真實資料（必須使用）\n\n";
    foreach ($data_files as $filename => $content) {
        $full_prompt .= "**檔案: {$filename}**\n";
        if (is_array($content)) {
            $full_prompt .= "```json\n" . json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
        } else {
            $full_prompt .= "```\n" . $content . "\n```\n\n";
        }
    }
    
    // 然後顯示參考模板（僅供結構參考）
    if (!empty($json_templates)) {
        $full_prompt .= "## 📝 參考模板（僅供結構參考，嚴禁複製內容）\n\n";
        foreach ($json_templates as $filename => $content) {
            $full_prompt .= "**參考模板: {$filename}**\n";
            if (is_array($content)) {
                $full_prompt .= "```json\n" . json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```\n\n";
            } else {
                $full_prompt .= "```\n" . $content . "\n```\n\n";
            }
        }
    }
    
    // 儲存提示詞到工作目錄
    file_put_contents($work_dir . '/json/ai_prompt.txt', $full_prompt);
    $deployer->log("AI 提示詞已儲存");
    
    // 呼叫 AI API
    $deployer->log("正在呼叫 {$ai_service} API...");
    
    if ($ai_service === 'OpenAI') {
        $ai_response = callOpenAI_step08($ai_config, $full_prompt, $deployer);
    } else {
        $ai_response = callGemini_step08($ai_config, $full_prompt, $deployer);
    }
    
    // 儲存原始 AI 回應
    file_put_contents($work_dir . '/json/ai_response.txt', $ai_response);
    $deployer->log("AI 原始回應已儲存");
    
    // 解析並儲存 JSON 檔案
    $saved_files = parseAndSaveAIResponse_step08($ai_response, $work_dir, $deployer);
    
    // 建立結果摘要
    $generation_info = [
        'job_id' => $job_id,
        'domain' => $domain,
        'website_name' => $website_name,
        'ai_service' => $ai_service,
        'ai_model' => $ai_config['model'],
        'ai_base_url' => $ai_config['base_url'],
        'data_files_count' => count($data_files),
        'generated_files' => $saved_files,
        'generated_at' => date('Y-m-d H:i:s'),
        'prompt_length' => strlen($full_prompt),
        'response_length' => strlen($ai_response)
    ];
    
    file_put_contents($work_dir . '/json/generation_info.json', 
        json_encode($generation_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $deployer->log("AI 配置檔案生成完成");
    $deployer->log("生成檔案數量: " . count($saved_files));
    $deployer->log("儲存位置: {$work_dir}/json/");
    
    // 顯示品牌配置摘要並請求確認
    $site_config_path = $work_dir . '/json/site-config.json';
    if (file_exists($site_config_path)) {
        $should_continue = displayBrandConfigSummary_step08($site_config_path, $deployer);
        if (!$should_continue) {
            $deployer->log("用戶選擇中止執行，品牌配置需要調整");
            return [
                'status' => 'user_abort', 
                'message' => '品牌配置確認中止，請調整設定後重新執行',
                'generation_info' => $generation_info
            ];
        }
    }
    
    return ['status' => 'success', 'generation_info' => $generation_info];
    
} catch (Exception $e) {
    $deployer->log("AI 配置檔案生成失敗: " . $e->getMessage());
    
    // 儲存錯誤資訊
    $error_info = [
        'job_id' => $job_id,
        'error_message' => $e->getMessage(),
        'error_time' => date('Y-m-d H:i:s'),
        'ai_service' => $ai_service ?? 'unknown'
    ];
    
    file_put_contents($work_dir . '/json/generation_error.json',
        json_encode($error_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return ['status' => 'error', 'message' => $e->getMessage()];
}

?>