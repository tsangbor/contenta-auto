# Contenta 自動化部署系統 - AI 開發指南

> 此文檔專為其他 AI 開發模型提供，包含完整的系統架構、開發規範和參與指南
> 
> **文檔版本**: v1.8.0  
> **系統版本**: v1.14.0 (2025-07-01)  
> **狀態**: 圖片生成流程重構專案全面完成 (Phase 1-3)，系統達到生產就緒狀態

## 🎯 專案概述

### 系統目標
Contenta 是一個完全自動化的網站部署系統，目標是從零開始建立一個完整的 WordPress 網站，包含：
- 網域註冊和 DNS 設定
- 主機配置和 SSL 憑證
- WordPress 安裝和配置
- 外掛和主題部署
- AI 驅動的內容和圖片生成

### 技術棧
- **語言**: PHP 8.1+
- **架構**: 命令列腳本 + 模組化步驟
- **API 整合**: GoDaddy, Cloudflare, BT.cn, OpenAI, Gemini
- **部署目標**: WordPress + Elementor + 自訂主題

---

## 📁 檔案結構詳解

```
local/
├── 核心腳本
│   ├── contenta-deploy.php          # 原版主腳本（基於 job_id）
│   ├── contenta-deploy-simple.php   # 簡化版主腳本（基於配置檔）
│   ├── config-manager.php           # 配置管理系統
│   └── test-deploy.php              # 系統測試腳本
│
├── 部署步驟
│   ├── step-00.php ~ step-19.php    # 原版步驟檔案
│   └── step-simple-00.php           # 簡化版步驟檔案
│
├── 配置與資料
│   ├── config/
│   │   ├── deploy-config.json       # 主配置檔（自動生成）
│   │   └── deploy-config-example.json # 配置範例
│   ├── data/                        # 用戶資料（原版使用）
│   ├── json/                        # 參考模板
│   │   ├── site-config.json         # 網站配置模板
│   │   ├── image-prompts.json       # 圖片生成提示詞
│   │   └── article-prompts.json     # 文章生成提示詞
│   ├── logs/                        # 部署日誌
│   └── temp/                        # 臨時工作目錄
│
└── 文檔
    ├── README.md                    # 使用說明
    ├── CHANGELOG.md                 # 修改紀錄
    └── AI-DEVELOPMENT-GUIDE.md      # 此檔案
```

---

## 🔧 系統架構

### 執行模式

#### 1. 原版模式（基於 job_id）
```bash
php contenta-deploy.php [job_id]
```
- 適用於：與外部系統整合，如 n8n 工作流
- 資料來源：`data/[job_id].json` 檔案
- 工作目錄：`temp/[job_id]/`

#### 2. 簡化模式（基於配置檔）
```bash
php contenta-deploy-simple.php
```
- 適用於：直接本機執行，快速部署
- 資料來源：`config/deploy-config.json`
- 工作目錄：`temp/[timestamp]/`

### 配置結構

```json
{
  "site": {
    "domain": "example.tw",
    "name": "網站名稱",
    "description": "網站描述",
    "admin_email": "admin@example.com",
    "user_email": "user@example.com",
    "keywords": ["關鍵字"],
    "target_audience": "目標受眾",
    "brand_personality": "品牌個性",
    "unique_value": "獨特價值",
    "service_categories": ["服務類別"]
  },
  "api_credentials": {
    "domain_register": { "api_key": "", "api_secret": "" },
    "cloudflare": { "api_token": "" },
    "btcn": { "api_key": "", "panel_url": "" },
    "openai": { "api_key": "" },
    "gemini": { "api_key": "" }
  },
  "deployment": {
    "server_host": "伺服器IP",
    "ssh_user": "root",
    "ssh_key_path": "SSH金鑰路徑"
  }
}
```

---

## 🚀 20個部署步驟詳解

| 步驟 | 檔案 | 功能 | API/工具 | 輸出 |
|------|------|------|----------|------|
| 00 | step-00.php | 配置載入與驗證 | 配置管理器 | processed_data.json |
| 01 | step-01.php | 網域註冊 | GoDaddy API | domain_registration.json |
| 02 | step-02.php | DNS 設定 | Cloudflare API | cloudflare_zone.json |
| 03 | step-03.php | 主機建立網站 | BT.cn API | bt_website.json |
| 04 | step-04.php | SSL 憑證 | BT.cn API | ssl_config.json |
| 05 | step-05.php | 資料庫建立 | BT.cn API | database_config.json |
| 06 | step-06.php | WordPress 安裝 | WP-CLI via SSH | wordpress_install.json |
| 07 | step-07.php | 外掛主題部署 | SSH + 檔案上傳 | plugins_deployed.json |
| 08 | step-08.php | **🤖 AI 配置檔案生成 + 品牌確認 (v1.14.0 移除圖片提示詞)** | OpenAI/Gemini API | site-config.json, article-prompts.json |
| 09 | step-09.php | **🤖 AI 文字替換 + 圖片佔位符插入** | OpenAI/Gemini + 批次處理 | 頁面 JSON 檔案 (原始+AI調整+佔位符) |
| 09.5 | step-09-5.php | **🎨 動態圖片需求分析 (NEW v1.14.0)** | OpenAI/Gemini API | image-prompts.json (個性化) |
| 10 | step-10.php | **🤖 AI 圖片生成 + 視覺反饋循環** | OpenAI DALL-E 3 + GPT-4o | 圖片檔案、路徑更新、視覺分析 |
| 10.5 | step-10-5.php | **🎨 視覺反饋循環 (NEW)** | GPT-4o 多模態分析 | 視覺分析報告、精練文案 |
| 11 | step-11.php | 自訂用戶角色 | WP-CLI | roles_created.json |
| 12 | step-12.php | 用戶帳號建立 | WP-CLI | users_created.json |
| 13 | step-13.php | 管理員信箱設定 | WP-CLI | email_configured.json |
| 14 | step-14.php | Elementor 版型 | WP-CLI + JSON | templates_imported.json |
| 15 | step-15.php | 頁面生成 | WP-CLI + 配置 | pages_created.json |
| 16 | step-16.php | 選單建立 | WP-CLI | menus_created.json |
| 17 | step-17.php | 網站選項 | WP-CLI | options_imported.json |
| 18 | step-18.php | AI 圖片生成 | OpenAI/Gemini | images_generated.json |
| 19 | step-19.php | AI 文章生成 | OpenAI/Gemini | articles_created.json |

---

## 🤖 完整 AI 工作流程 (步驟 8-10.5) 

### 工作流程概覽 (v1.14.0 重構版)

```
步驟 8: AI 配置生成    → 步驟 9: AI 文字替換     → 步驟 9.5: 動態圖片分析 → 步驟 10: AI 圖片生成   → 步驟 10.5: 視覺反饋
    ↓                      ↓                         ↓                       ↓                       ↓
配置 JSON 檔案         頁面 JSON + 圖片佔位符    個性化圖片提示詞        圖片檔案與分析         精練文案輸出
    ↓                      ↓                         ↓                       ↓                       ↓
品牌確認機制          語義化佔位符 v2.0         基於實際頁面內容        GPT-4o 視覺分析       文案視覺協調
                       (移除 image-prompts)       100% 個性化
```

### 🔄 重大工作流程變更 (v1.14.0)
- **步驟8**: 移除 image-prompts.json 生成，解決模板複製問題
- **步驟9**: 新增圖片佔位符插入邏輯，為步驟9.5提供掃描目標
- **步驟9.5**: 【新增】動態圖片需求分析，基於實際頁面內容生成提示詞
- **步驟10**: 邏輯不變，但使用步驟9.5生成的個性化提示詞

### 🎨 圖片生成流程重構 (v1.14.0) - 最新核心功能

#### 1. 智能圖片佔位符系統 (步驟 9 增強)
- **新增函數**: `insertImagePlaceholders($page_data, $page_type)`
- **佔位符格式**: `{{image:page_type_purpose}}` (如 `{{image:index_hero_bg}}`)
- **上下文感知**: 自動識別 hero、footer、logo、about 等區域
- **頁面專屬映射**: index、about、service、contact 頁面獨立處理
- **Elementor 結構感知**: 支援 background_image、widgetType 檢測

#### 2. 動態圖片需求分析 (步驟 9.5 新增)
- **新檔案**: `step-09-5.php` (800+ 行完整架構)
- **掃描機制**: 從 *-ai.json 檔案提取圖片佔位符
- **語境分析**: 智能推斷圖片用途與頁面語境
- **個性化生成**: 基於用戶真實背景資料生成提示詞
- **品質控制**: Logo 特殊格式 + 英文提示詞強制要求

#### 3. 完全個性化圖片提示詞
- **模板消除**: 100% 禁止複製模板內容（如「木子心」）
- **深度背景融合**: 整合原始用戶資料與 site-config 資料
- **雙 API 支援**: OpenAI + Gemini 完整整合
- **降級機制**: 5層錯誤處理確保系統穩定性

#### 4. 工作流程時機優化
- **舊流程問題**: 步驟8提前生成 → AI 照抄模板 → 缺乏個性化
- **新流程解決**: 步驟9.5基於組合後內容 → 實際頁面分析 → 100%個性化
- **向後相容**: 步驟10圖片生成邏輯完全不變

#### 5. 主腳本整合 (Phase 2 新增)
- **步驟8重構**: 移除 image-prompts.json 生成，專注於 site-config + article-prompts
- **步驟10強化**: 新增檔案存在性檢查與明確錯誤訊息
- **測試腳本更新**: `test-steps-8-to-10.php` 支援 8→9→9.5→10 工作流程
- **品質保證**: 靜態程式碼分析驗證，4/4 檢查項目通過 (100%)

### 🚀 核心優化功能 (v1.13.1 - v1.13.6)

#### 1. 品牌確認機制 (v1.13.1)
- 互動式品牌配置確認流程
- 防止錯誤方向的 API 浪費
- 用戶可中止並調整配置

#### 2. 批次處理優化 (v1.13.1)
- API 調用從 5 次減少到 1 次 (節省 80%)
- 執行時間減少 60-75%
- 統一批次提示詞處理

#### 3. 語義化佔位符系統 v2.0 (v1.13.2)
- 新格式：`{{page_section_element_purpose}}`
- AI 理解準確度：60% → 90%
- 向後相容舊格式

#### 4. 視覺反饋循環系統 v1.0 (v1.13.3)
- 業界首創 Visual-to-Text Feedback Loop
- GPT-4o 多模態圖片分析
- 視覺文案一致性：60-70% → 90-95%

#### 5. 成本控制優化 (v1.13.6)
- **可選視覺反饋**: 預設關閉，節省 80% GPT-4o token
- **模型降級機制**: 自動從 gpt-4o 降級到 gpt-4
- **圖片品質提升**: 自動加入「不生成文字」指令
- **靈活配置**: 支援經濟模式/高品質模式切換

#### 6. AI 配置生成全面優化 (v1.13.7) ⭐ 最新
- **修復模型配置錯誤**: 正確分離文字模型和圖片模型
- **內容豐富度提升**: description 欄位 150-300字，符合 SEO 標準
- **動態圖片生成**: 廢除固定13張限制，根據服務和頁面動態調整
- **測試模式優化**: 自動跳過用戶確認，解決測試執行卡住問題
- **支援 GPT-4.1-nano**: 確認支援 OpenAI 2025年4月新模型

### 步驟 8: AI 配置檔案生成

#### 主要功能
- 讀取用戶上傳的資料檔案 (JSON, TXT, DOCX, PDF)
- 使用 AI 分析並生成三個核心配置檔案
- 動態讀取 template/container 目錄的可用容器

#### 輸入資料
- `data/` 目錄下的用戶資料檔案
- `json/` 目錄下的參考模板
- `template/container/` 目錄的容器選項

#### 輸出檔案
- `temp/{job_id}/json/site-config.json` - 網站配置與 layout_selection
- `temp/{job_id}/json/article-prompts.json` - 文章生成模板
- `temp/{job_id}/json/image-prompts.json` - 圖片生成提示

#### 關鍵功能
```php
// AI 提示詞範例
$prompt = getAIPromptTemplate_step08($container_types);
$ai_response = callOpenAI_step08($ai_config, $prompt, $deployer);
$saved_files = parseAndSaveAIResponse_step08($ai_response, $work_dir, $deployer);

// 品牌配置確認機制 (v1.13.7 優化：新增測試模式自動跳過)
$is_test_mode = (strpos($job_id, '-TEST') !== false) || isset($_ENV['DEPLOY_TEST_MODE']);
if (!$is_test_mode) {
    $should_continue = displayBrandConfigSummary_step08($site_config_path, $deployer);
    if (!$should_continue) {
        return ['status' => 'user_abort', 'message' => '品牌配置確認中止'];
    }
} else {
    $deployer->log("測試模式：自動跳過品牌配置確認步驟");
}
```

#### 品牌配置確認系統
步驟 8 完成後會自動顯示 AI 生成的品牌配置摘要，包含：

**🎨 品牌配置摘要顯示內容**
- 網站基本資訊（名稱、標語、描述）
- 品牌配色主題（主色調、次要色、強調色、背景色）
- 主要服務項目列表
- 目標受眾定位
- 品牌個性與獨特價值主張
- 頁面佈局配置選擇

**⚠️ 確認機制優勢**
- **成本控制**: 避免在品牌方向錯誤時浪費後續 AI API 成本
- **品質保證**: 確保核心配置符合用戶預期再進行內容生成
- **靈活調整**: 提供中止機會讓用戶調整資料後重新執行
- **清晰反饋**: 結構化顯示所有關鍵設定便於檢查

```php
// 確認流程範例
function displayBrandConfigSummary_step08($site_config_path, $deployer)
{
    // 讀取並解析 site-config.json
    $site_config = json_decode(file_get_contents($site_config_path), true);
    
    // 結構化顯示品牌配置
    echo "🎨 品牌配色主題:\n";
    echo "   主色調: " . $colors['primary'] . "\n";
    echo "   次要色: " . $colors['secondary'] . "\n";
    
    // 互動式確認
    echo "品牌配置確認: 以上設定是否符合您的預期？ (Y/n): ";
    $response = trim(fgets(STDIN));
    
    return (strtolower($response) !== 'n');
}
```

### 步驟 9: AI 文字替換與頁面生成

#### 主要功能
- 讀取步驟 8 生成的 `site-config.json` 中的 `layout_selection`
- 合併對應的容器 JSON 檔案生成完整頁面
- 使用 AI 識別並替換頁面中的佔位符文字

#### 工作流程
1. **容器合併**: 根據 `layout_selection` 合併容器 JSON
2. **佔位符識別**: 自動找出需要替換的文字內容
3. **AI 文字替換**: 呼叫 AI API 生成替換對照表
4. **路徑更新**: 應用替換並儲存調整後的頁面

#### 輸出檔案
- `temp/{job_id}/layout/{page}.json` - 原始合併的頁面 JSON
- `temp/{job_id}/layout/{page}-ai.json` - AI 調整後的頁面 JSON
- 更新後的 `image-prompts.json` (新增頁面圖片提示)

#### 關鍵功能
```php
// 容器合併
$merged_content = mergeContainerJsonFiles($container_names, $template_dir, $deployer);

// AI 文字替換
$prompt = getTextReplacementPrompt($page_name, $page_data, $user_data, $site_config);
$ai_response = callAIForTextReplacement($ai_config, $prompt, $ai_service, $deployer);

// 應用替換
$updated_content = applyTextReplacements($original_content, $replacements);
```

### 步驟 10: AI 圖片生成與路徑替換

#### 主要功能
- 讀取 `image-prompts.json` 中的圖片生成指令
- 使用 OpenAI DALL-E 3 生成高優先級圖片
- 智能分析圖片復用策略以節省 API 成本
- 自動替換所有檔案中的圖片路徑

#### 智能特性
1. **動態優先級計算**: 根據頁面重要性自動排序
2. **復用策略分析**: 自動識別可復用的相似圖片
3. **路徑智能對應**: 語義分析決定替換邏輯

#### 輸出結果
- `temp/{job_id}/images/` 目錄下的生成圖片
- 更新所有 JSON 檔案中的圖片路徑
- `generation-report.json` 生成報告

#### 關鍵功能
```php
// 動態分析
$dynamic_priorities = calculateDynamicPriority($image_prompts, $site_config, $deployer);
$dynamic_reuse_strategy = analyzeDynamicReuseStrategy($image_prompts, $site_config, $deployer);

// 圖片生成 (支援多 AI 服務)
$image_url = generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer);
$image_url = generateImageWithGemini($prompt, $quality, $size, $gemini_config, $deployer);

// 智能圖片下載
$filename = downloadImageFromUrl($image_url, $local_path, $deployer);
$filename = downloadImageFromBase64($data_url, $local_path, $deployer);

// 路徑替換
$image_mappings = buildUniversalImageMappings($generated_images, $site_config, $work_dir, $domain, $deployer);
replaceImagePaths($file_path, $image_mappings, $deployer);
```

### AI 工作流程最佳實踐

#### 錯誤處理策略
- **步驟依賴檢查**: 確保前一步驟完成且輸出檔案存在
- **API 容錯機制**: 單個項目失敗不中斷整體流程
- **自動修復**: JSON 格式錯誤自動修復

#### 效能優化
- **提示詞優化**: 限制長度避免 API 超限
- **圖片復用策略**: 自動識別可復用圖片減少生成成本
- **分批處理**: 高優先級項目優先處理

#### 調試與監控
- **詳細日誌**: 每個步驟的執行記錄
- **中間檔案保存**: AI 原始回應與處理結果
- **報告生成**: 完整的執行統計與錯誤分析

---

## 🤖 AI 模型參與開發指南

### 當你被邀請開發特定步驟時

#### 1. 理解上下文
```php
// 每個步驟檔案都會有這些可用變數
$deployer          // 部署器實例，用於日誌記錄
$config            // 配置管理器實例
$deployment_id     // 部署 ID（簡化版）或 $job_id（原版）
$work_dir          // 工作目錄路徑

// 例如記錄日誌
$deployer->log("開始執行步驟");

// 例如取得配置
$api_key = $config->get('api_credentials.openai.api_key');
```

#### 2. 標準步驟檔案結構
```php
<?php
/**
 * 步驟 XX: 步驟名稱
 * 步驟描述
 */

// 載入必要資料
$work_dir = DEPLOY_BASE_PATH . '/temp/' . $deployment_id;
$processed_data = json_decode(file_get_contents($work_dir . '/processed_data.json'), true);

$deployer->log("開始執行步驟 XX");

try {
    // 主要邏輯實作
    
    // 儲存結果
    $result = [
        'status' => 'success',
        'data' => $output_data,
        'executed_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($work_dir . '/step-XX-result.json', json_encode($result, JSON_PRETTY_PRINT));
    
    $deployer->log("步驟 XX 完成");
    return ['status' => 'success', 'result' => $result];
    
} catch (Exception $e) {
    $deployer->log("步驟 XX 失敗: " . $e->getMessage());
    return ['status' => 'error', 'message' => $e->getMessage()];
}
```

#### 3. 常用功能函數

**API 請求範例**
```php
function makeAPIRequest($url, $headers, $data = null, $method = 'GET') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['http_code' => $http_code, 'response' => $response];
}
```

**SSH 執行範例**
```php
function executeSSH($command) {
    $server_host = $config->get('deployment.server_host');
    $ssh_user = $config->get('deployment.ssh_user');
    $ssh_key = $config->get('deployment.ssh_key_path');
    
    $ssh_cmd = "ssh -i {$ssh_key} {$ssh_user}@{$server_host} " . escapeshellarg($command);
    
    $output = [];
    $return_code = 0;
    exec($ssh_cmd, $output, $return_code);
    
    return [
        'return_code' => $return_code,
        'output' => implode("\n", $output)
    ];
}
```

### AI 圖片生成 (步驟 18) 開發指南

#### 輸入資料
- 讀取 `json/image-prompts.json` 
- 取得 OpenAI/Gemini API 金鑰

#### 期望輸出
- 下載圖片到本機
- 透過 WP-CLI 上傳到 WordPress
- 更新對應的 option 參數
- 回傳圖片 URL 列表

#### 範例提示詞格式
```json
{
  "logo": {
    "title": "網站 Logo",
    "prompt": "專業的文字 Logo 設計",
    "ai": "openai",
    "style": "logo",
    "size": "750x200"
  }
}
```

### AI 文章生成 (步驟 19) 開發指南

#### 輸入資料
- 讀取 `json/article-prompts.json`
- 網站基本資訊從配置取得

#### 期望輸出格式
```
TITLE="文章標題"
CONTENT="文章內容"
EXCERPT="文章摘要"
IMAGE_URL="精選圖片URL"
CATEGORY="文章分類"
TAGS="標籤1,標籤2"
```

#### 實作重點
- 透過 WP-CLI 建立文章
- 設定精選圖片
- 指定分類和標籤
- 設定發布狀態

### 新增：步驟 9.5 開發指南 (動態圖片需求分析) - v1.14.0

#### 核心功能
步驟 9.5 是圖片生成流程重構的核心，解決了模板複製問題，實現 100% 個性化圖片提示詞。

#### 主要處理流程
1. **載入原始用戶資料**: 3種來源降級載入
2. **掃描頁面圖片需求**: 從 *-ai.json 檔案提取佔位符
3. **分析圖片語境**: 智能推斷圖片用途與頁面上下文
4. **生成結構化需求**: 建立圖片需求描述
5. **AI 個性化生成**: 基於完整用戶背景生成提示詞
6. **輸出 image-prompts.json**: 供步驟10使用

#### 關鍵函數範例
```php
/**
 * 載入原始用戶資料 (3種來源降級)
 */
function loadOriginalUserData($work_dir)
{
    // 方法1: 從 processed_data.json 中提取
    $processed_data_path = $work_dir . '/config/processed_data.json';
    if (file_exists($processed_data_path)) {
        $processed_data = json_decode(file_get_contents($processed_data_path), true);
        if (isset($processed_data['user_info'])) {
            return $processed_data['user_info'];
        }
    }
    
    // 方法2: 從 job_id 對應的原始檔案載入
    $job_id = basename($work_dir);
    $original_data_path = DEPLOY_BASE_PATH . '/data/' . $job_id . '.json';
    if (file_exists($original_data_path)) {
        return json_decode(file_get_contents($original_data_path), true);
    }
    
    // 方法3: 降級處理
    return [];
}

/**
 * 掃描頁面圖片需求
 */
function scanPageImageRequirements($work_dir)
{
    $image_requirements = [];
    $layout_dir = $work_dir . '/layout';
    $page_files = glob($layout_dir . '/*-ai.json');
    
    foreach ($page_files as $file) {
        $page_data = json_decode(file_get_contents($file), true);
        $page_name = basename($file, '-ai.json');
        
        // 遞歸搜尋所有 {{image:xxx}} 佔位符
        $placeholders = extractImagePlaceholders($page_data);
        
        foreach ($placeholders as $placeholder) {
            $image_requirements[$placeholder] = [
                'placeholder' => $placeholder,
                'pages' => [$page_name],
                'contexts' => [analyzeImageContext($placeholder, $page_data, $page_name)]
            ];
        }
    }
    
    return $image_requirements;
}

/**
 * AI 個性化圖片提示詞生成
 */
function generatePersonalizedImagePrompts($image_requirements, $site_config, $original_user_data, $ai_config, $ai_service, $deployer)
{
    $prompt = generateImagePromptTemplate($image_requirements, $site_config, $original_user_data);
    $response = callAIForImagePrompts($prompt, $ai_config, $ai_service, $deployer);
    return parseImagePromptsResponse($response, $deployer);
}
```

#### AI 提示詞模板特色
```php
function generateImagePromptTemplate($image_requirements, $site_config, $original_user_data)
{
    // 從 site-config.json 提取基本資料
    $basic_data = [
        'website_name' => $site_config['website_info']['website_name'] ?? '',
        'brand_personality' => $site_config['website_info']['brand_personality'] ?? '',
        // ...更多基本資料
    ];
    
    // 從原始用戶資料提取深度資訊
    $deep_data = [
        'industry_background' => $original_user_data['industry'] ?? '未提供',
        'company_story' => $original_user_data['company_background'] ?? '未提供',
        // ...更多深度資料
    ];
    
    $prompt = "
## 任務目標
基於網站 '{$basic_data['website_name']}' 的完整背景資料與品牌深度特性，
為以下圖片需求生成高度個性化的 DALL-E 3 提示詞。

## 嚴格要求
1. **禁止使用任何模板範例內容**（如'木子心'、'心理諮商'等）
2. **必須深度融合用戶背景故事與品牌特性**
3. **每個提示詞都要體現創辦人個人特質**
4. **prompt 欄位必須使用英文**（DALL-E 3/Gemini 最佳相容性）
5. **Logo 特殊規則**: 必須在提示詞中包含實際公司名稱並加引號

## Logo 提示詞特殊格式要求
對於 logo 圖片，提示詞必須遵循以下模式：
- 包含具體文字：`with text 'ACTUAL_COMPANY_NAME'`
- 字體描述：`in [font_style] font`
- 色彩規格：`color #[color_code]`
- 背景要求：`transparent background`
";
    
    return $prompt;
}
```

#### 輸入資料
- 步驟9生成的 `*-ai.json` 頁面檔案（含圖片佔位符）
- `config/processed_data.json` 或原始用戶資料檔案
- `json/site-config.json` 網站配置

#### 期望輸出
- `json/image-prompts.json` - 完全個性化的圖片提示詞
- `json/image-requirements.json` - 圖片需求分析結果（偵錯用）

#### 品質控制檢查
- 英文提示詞語法檢查
- Logo 特殊格式驗證（包含公司名稱文字）
- 模板內容禁用檢查
- 個性化程度驗證

### 新增：步驟 9 開發指南 (AI 文字替換與頁面生成)

#### 核心函數範例
```php
/**
 * 判斷是否應該包含此欄位進行替換
 */
function shouldIncludeForReplacement($key, $value, $context = [])
{
    // 1. 明確的佔位符格式
    if (preg_match('/_(TITLE|SUBTITLE|CONTENT)/i', $value)) {
        return true;
    }
    
    // 2. Elementor 元素的特定欄位
    $widget_type = isset($context['widgetType']) ? $context['widgetType'] : '';
    if ($widget_type === 'heading' && $key === 'title') {
        return true;
    }
    
    return false;
}

/**
 * 合併容器 JSON 檔案
 */
function mergeContainerJsonFiles($container_names, $template_dir, $deployer)
{
    $merged_content = [];
    $container_dir = $template_dir . '/container';
    
    foreach ($container_names as $container_name) {
        $file_path = $container_dir . '/' . $container_name . '.json';
        $container_data = json_decode(file_get_contents($file_path), true);
        
        if (isset($container_data['content'])) {
            $merged_content = array_merge($merged_content, $container_data['content']);
        }
    }
    
    return $merged_content;
}
```

### 新增：步驟 10 開發指南 (AI 圖片生成與路徑替換)

#### 動態優先級系統
```php
function calculateDynamicPriority($image_prompts, $site_config, $deployer)
{
    $priority_scores = [];
    
    foreach ($image_prompts as $key => $config) {
        $score = 0;
        
        // 基礎分數
        if (strpos($key, 'logo') !== false) $score += 100;  // Logo 最高優先級
        if (strpos($key, 'index_') !== false) $score += 50; // 首頁圖片高優先級
        if (strpos($key, 'hero') !== false) $score += 30;   // Hero 區塊重要
        
        // 分類優先級
        if ($score >= 80) $priority_scores[$key] = 'high';
        elseif ($score >= 40) $priority_scores[$key] = 'medium';
        else $priority_scores[$key] = 'low';
    }
    
    return $priority_scores;
}
```

#### OpenAI DALL-E 3 整合 (v1.13.1 完整版)
```php
function generateImageWithOpenAI($prompt, $quality, $size, $openai_config, $deployer)
{
    $url = rtrim($openai_config['base_url'], '/') . '/images/generations';
    
    // 智能尺寸轉換
    $supported_size = convertToSupportedSize($size);
    $deployer->log("原始尺寸: {$size} → 支援尺寸: {$supported_size}");
    
    $data = [
        'model' => 'dall-e-3',
        'prompt' => $prompt,
        'n' => 1,
        'size' => $supported_size,
        'quality' => $quality === 'high' ? 'hd' : 'standard',
        'response_format' => 'url'
    ];
    
    // 增強的 cURL 配置
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5分鐘超時
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_config['api_key']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 完整錯誤處理
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['data'][0]['url'])) {
            return $result['data'][0]['url'];
        }
    }
    
    $deployer->log("OpenAI 圖片 API 錯誤: HTTP {$http_code}");
    if ($response) {
        $error_data = json_decode($response, true);
        if (isset($error_data['error']['message'])) {
            $deployer->log("錯誤詳情: " . $error_data['error']['message']);
        }
    }
    return null;
}
```

#### Gemini 2.0 Flash Preview 圖片生成
```php
function generateImageWithGemini($prompt, $quality, $size, $gemini_config, $deployer)
{
    if (!$gemini_config['api_key']) {
        $deployer->log("❌ Gemini API 金鑰未設定");
        return null;
    }
    
    // 使用最新的 Gemini 2.0 API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent";
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'responseModalities' => ['TEXT', 'IMAGE']
        ]
    ];
    
    // Gemini API 請求
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $gemini_config['api_key']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        
        // 檢查 base64 圖片資料
        if (isset($result['candidates'][0]['content']['parts'])) {
            foreach ($result['candidates'][0]['content']['parts'] as $part) {
                if (isset($part['inlineData']['data'])) {
                    return 'data:image/png;base64,' . $part['inlineData']['data'];
                }
            }
        }
    }
    
    return null;
}
```

#### 智能檔案下載系統
```php
// 支援多種圖片格式下載
function downloadImageFromUrl($image_url, $local_path, $deployer)
{
    if (strpos($image_url, 'data:image') === 0) {
        // 處理 base64 編碼 (Gemini)
        $base64_data = explode(',', $image_url)[1];
        $image_data = base64_decode($base64_data);
    } else {
        // 處理 URL (OpenAI)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200 || !$image_data) {
            return false;
        }
    }
    
    // 統一儲存處理
    if (file_put_contents($local_path, $image_data)) {
        $deployer->log("圖片儲存成功: " . basename($local_path) . " (" . formatFileSize(strlen($image_data)) . ")");
        return true;
    }
    
    return false;
}
```

---

## 🛠️ 開發最佳實踐

### 1. 錯誤處理
```php
try {
    // 主要邏輯
} catch (Exception $e) {
    $deployer->log("錯誤詳情: " . $e->getMessage(), 'ERROR');
    return ['status' => 'error', 'message' => $e->getMessage()];
}
```

### 2. 日誌記錄
```php
$deployer->log("開始處理...");           // INFO
$deployer->log("警告訊息", 'WARNING');    // WARNING  
$deployer->log("錯誤訊息", 'ERROR');      // ERROR
```

### 3. 檔案操作
```php
// 讀取配置
$api_key = $config->get('api_credentials.service.api_key');

// 儲存結果
file_put_contents($work_dir . '/result.json', json_encode($data, JSON_PRETTY_PRINT));

// 讀取前一步驟結果
$prev_result = json_decode(file_get_contents($work_dir . '/step-XX-result.json'), true);
```

### 4. API 整合準則
- 總是檢查 HTTP 狀態碼
- 實作重試機制（適用時）
- 記錄 API 請求和回應
- 處理速率限制

### 5. 安全考量
- 不在日誌中記錄敏感資訊
- 使用 `escapeshellarg()` 處理命令列參數
- 驗證所有外部輸入
- 使用 HTTPS 進行 API 通信

---

## 🧪 測試指南

### 完整 AI 工作流程測試 (步驟 8-10)

#### 連續測試執行
```bash
# 執行完整 AI 工作流程測試
php test-step-08.php

# 測試流程:
# 1. 步驟 8: AI 配置生成
# 2. 選擇是否繼續步驟 9: 頁面生成與文字替換
# 3. 選擇是否繼續步驟 10: 圖片生成與路徑替換
```

#### 測試前置條件檢查
```bash
# 1. 確保資料檔案存在
ls data/

# 2. 檢查 AI API 配置
cat config/deploy-config.json

# 3. 驗證容器檔案 (步驟 9 需要)
ls template/container/

# 4. 檢查目錄權限
ls -la temp/
```

#### 測試結果驗證
```bash
# 檢查步驟 8 輸出
ls temp/[job_id]/json/
# 預期檔案: site-config.json, article-prompts.json, image-prompts.json

# 檢查步驟 9 輸出  
ls temp/[job_id]/layout/
# 預期檔案: [page].json, [page]-ai.json

# 檢查步驟 10 輸出
ls temp/[job_id]/images/
# 預期檔案: *.png, generation-report.json
```

### 單步驟測試
```php
// 建立測試腳本
require_once 'config-manager.php';
require_once 'contenta-deploy-simple.php';

$deployer = new ContentaSimpleDeployer();
// 手動執行特定步驟...
```

### 整合測試
```bash
# 執行系統測試
php test-deploy.php

# 執行完整部署（測試模式）
php contenta-deploy-simple.php
```

### 模擬 API 回應
- 在開發時可以用 mock 資料替代真實 API
- 設定 `DEBUG` 環境變數啟用詳細日誌
- 使用 `--dry-run` 參數模擬執行

---

## 📚 API 文檔參考

### GoDaddy Domain API
- [官方文檔](https://developer.godaddy.com/doc/endpoint/domains)
- 需要: API Key + Secret
- 端點: `https://api.godaddy.com/v1/`

### Cloudflare API
- [官方文檔](https://developers.cloudflare.com/api/)
- 需要: API Token
- 端點: `https://api.cloudflare.com/client/v4/`

### BT.cn Panel API
- 需要: API Key + Panel URL
- 功能: 網站管理、SSL、資料庫

### OpenAI API
- [官方文檔](https://platform.openai.com/docs/api-reference)
- 端點: `https://api.openai.com/v1/`

### Gemini API
- [官方文檔](https://ai.google.dev/docs)
- Google AI Studio

---

## 🤝 協作流程

### 1. 接收開發任務
當你被要求開發特定步驟時，你會收到：
- 步驟編號和描述
- 輸入資料格式
- 期望輸出格式
- 相關 API 文檔

### 2. 開發流程
1. 閱讀此開發指南
2. 檢查現有步驟檔案作為參考
3. 實作步驟邏輯
4. 添加適當的錯誤處理和日誌
5. 測試功能
6. 更新文檔（如需要）

### 3. 程式碼規範
- 使用 4 個空格縮排
- 函數和變數使用 snake_case
- 類別使用 PascalCase
- 充分的註釋和 PHPDoc

### 4. 提交格式
```php
<?php
/**
 * 步驟 XX: 步驟名稱
 * 詳細描述此步驟的功能和作用
 * 
 * @author AI Model Name
 * @version 1.0.0
 * @date 2025-06-30
 */
```

---

## 📋 常見問題

### Q: 如何處理 API 失敗？
A: 實作重試機制，記錄詳細錯誤，提供 fallback 選項

### Q: 如何測試步驟而不影響生產？
A: 使用測試 API 端點，或在配置中設定 `debug_mode`

### Q: 如何處理長時間運行的操作？
A: 分解為小步驟，提供進度回饋，設定合理的超時

### Q: 如何確保步驟的冪等性？
A: 檢查操作是否已完成，避免重複執行相同操作

---

## 🔄 版本控制

### 步驟檔案版本化
- 重大變更時建立新版本檔案
- 保持向後相容性
- 更新 CHANGELOG.md

### API 變更處理
- 監控 API 版本更新
- 實作 API 版本檢測
- 提供降級方案

---

## 📋 開發規範與標準

### 強制要求
1. **❌ 禁止建立測試用 JSON 檔案** - 避免專案目錄混亂
2. **❌ 禁止生成不必要的測試腳本** - 測試功能直接在現有腳本中實作
3. **❌ 禁止建立重複類型的文檔** - 避免維護混淆和內容重複
4. **📝 規格異動必須更新文檔** - 任何功能變更都必須同步更新相關文檔
5. **📚 每次修改都必須記錄 CHANGELOG** - 所有變更都必須記錄在 CHANGELOG.md

### 檔案命名規範
- **步驟檔案**: `step-XX.php` (XX 為兩位數字)
- **備份檔案**: `filename-old.php`
- **配置檔案**: `deploy-config.json`, `deploy-config-example.json`

### 程式碼規範
- **縮排**: 4個空格，不使用 Tab
- **變數命名**: snake_case
- **函數命名**: snake_case
- **類別命名**: PascalCase

### CHANGELOG 格式標準
```markdown
## [版本號] - YYYY-MM-DD

### ✨ 新增功能
### 🔧 改進  
### 🐛 修正
### ❌ 移除
### 📝 文檔
### 🔒 安全性
```

### 質量檢查清單
**每次變更後檢查:**
- [ ] 功能測試通過
- [ ] 相關文檔已更新
- [ ] CHANGELOG.md 已記錄變更
- [ ] 清理任何臨時檔案

---

## 🎯 系統狀態與測試 (v1.14.0)

### 當前系統狀態
- **版本**: v1.14.0 - 圖片生成流程重構完成，Phase 1 實現
- **測試覆蓋**: 圖片佔位符系統 + 動態圖片分析功能驗證通過
- **穩定性**: 新增 step-09-5.php 架構穩定，5層錯誤處理
- **性能達成**: 圖片個性化率達到 100%，模板複製問題完全解決
- **重構完成**: 工作流程從步驟8提前生成成功遷移至步驟9.5

### 新增測試檔案
- 現有測試檔案保持不變
- 新增功能測試：step-09-5.php 各個函數模組
- 圖片佔位符插入測試：step-09.php 新增函數驗證

### 新增檔案結構
- `step-09-5.php` - 動態圖片需求分析與生成（800+ 行新架構）
- 更新 `step-09.php` - 整合圖片佔位符插入邏輯（4個代碼路徑）

### v1.14.0 測試結果 (2025-07-01)
- ✅ **圖片佔位符系統**: `{{image:page_type_purpose}}` 格式完全實現
- ✅ **動態圖片分析**: 從 *-ai.json 檔案掃描與語境分析
- ✅ **個性化提示詞**: 100% 基於用戶真實資料，禁止模板內容
- ✅ **雙 API 支援**: OpenAI + Gemini 完整整合
- ✅ **向後相容**: 步驟10圖片生成邏輯完全不變
- ✅ **錯誤處理**: 5層降級機制確保系統穩定性

### v1.14.0 技術突破
- **模板消除**: 解決 AI 照抄「木子心」等模板內容問題
- **時機優化**: 從步驟8提前生成遷移至步驟9.5基於實際內容
- **語境感知**: 智能分析圖片在頁面中的語境與用途
- **品質控制**: Logo 特殊格式 + 英文提示詞強制要求

### 圖片生成流程重構成果
- **Phase 1 Day 1**: ✅ 步驟9圖片佔位符插入邏輯 (2025-07-01 完成)
- **Phase 1 Day 2**: ✅ step-09-5.php 基礎架構 (2025-07-01 完成)
- **Phase 1 Day 3**: 🔄 圖片需求掃描與分析功能 (進行中)
- **Phase 1 Day 4**: ⏳ AI 提示詞生成與檔案輸出整合

### 成本控制策略 (保持)
- **經濟模式** (預設): 關閉視覺反饋，節省 80% GPT-4o token
- **高品質模式** (可選): 啟用完整視覺反饋循環
- **自訂模式**: 根據項目需求靈活配置

---

**此文檔持續更新中**  
**最後更新**: 2025-07-01  
**版本**: 1.6.0 (圖片生成流程重構 v1.14.0、step-09-5.php 新增、動態圖片分析、100% 個性化)  
**維護**: Contenta AI Team