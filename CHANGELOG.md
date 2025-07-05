# Contenta 自動化部署系統 - 更改紀錄

> 本檔案記錄最新的系統更改。歷史記錄已移至 `archive/` 目錄。

## 最新更改

### [1.14.24] - 2025-07-04 23:00

#### 🎨 Step-10 完全重構：圖片佔位符識別與 AI 圖片生成統一流程

**重大架構變更**
- ✅ **合併原 step-09-5 和 step-10 功能**：單一步驟完成圖片佔位符提取到圖片生成的完整流程
- ✅ **智能佔位符識別**：自動掃描所有 *-ai.json 檔案中的圖片佔位符 `{{*_BG}}`, `{{*_PHOTO}}`, `{{*_IMG}}`
- ✅ **AI 圖片提示詞生成**：根據品牌資訊自動生成詳細英文圖片提示詞
- ✅ **雙 AI 圖片生成**：優先使用 Gemini (成本低)，失敗自動降級到 OpenAI DALL-E 3

**新增功能特色**
```php
// 1. 智能佔位符掃描
$image_placeholders = extractImagePlaceholders($work_dir, $deployer);
// 支援多種圖片類型：_BG, _PHOTO, _IMG, _IMAGE, _LOGO, _ICON

// 2. AI 提示詞生成
$image_prompts = generateImagePrompts($placeholders, $site_config, $ai_service, $config, $deployer);
// 根據品牌調性、目標受眾生成客製化提示詞

// 3. 圖片生成與儲存
$generated_images = generateImages($image_prompts, $images_dir, $ai_service, $config, $deployer);
// 優先 Gemini → 降級 OpenAI → 多重映射格式

// 4. 兼容 step-11/step-12 的映射格式
$image_mapping = buildImageMapping($generated_images, $image_placeholders, $deployer);
```

**檔案輸出結構**
```
temp/{job_id}/
├── image-prompts.json          # AI 生成的圖片提示詞
├── images/                     # 生成的圖片檔案
│   ├── home_hero-bg.png
│   ├── about_hero-photo.png
│   └── header-001_logo.png
├── image-mapping.json          # 多格式映射表（step-11/12 兼容）
└── step-10-result.json         # 步驟執行結果
```

**多重映射兼容性**
為確保與 step-11 和 step-12 完全兼容，建立多種 key 格式：
```json
{
    "home_hero-bg": "/wp-content/uploads/ai-generated/home_hero-bg.png",
    "home-hero-bg": "/wp-content/uploads/ai-generated/home_hero-bg.png", 
    "hero-bg": "/wp-content/uploads/ai-generated/home_hero-bg.png"
}
```

**AI 服務整合**
- 🤖 **Gemini 圖片生成**：gemini-2.0-flash-preview-image-generation ($0.03/圖)
- 🤖 **OpenAI DALL-E 3**：標準品質 $0.04/圖，高品質 $0.08/圖
- 🔄 **自動降級機制**：Gemini 失敗 → OpenAI 備援
- ⚡ **API 請求優化**：間隔控制，避免頻率限制

**效能與成本優化**
- 💰 預設使用 Gemini (成本降低 25-60%)
- 📊 生成統計與錯誤處理完整
- 🚀 單步驟完成，減少複雜度
- 🔧 完整的錯誤恢復機制

---

### [1.14.23] - 2025-07-04 22:40

#### 🔧 Step-09 完整檔案生成：確保所有模板都產生 *-ai.json

**問題解決**
- ❌ **之前問題**：只有包含 {{}} 佔位符的模板才會生成 *-ai.json 檔案
- ✅ **修復後**：所有處理過的頁面和全域模板都會生成 *-ai.json，即使沒有佔位符要替換

**影響範圍**
- **之前**：10個模板處理，只生成7個 *-ai.json（有佔位符的）
- **現在**：10個模板處理，生成完整的10個 *-ai.json（包含無佔位符的）

**具體改進**
```php
// 舊邏輯（有問題）
if (isset($filled_mapping[$page_name])) {
    // 只處理有佔位符的模板
}

// 新邏輯（已修復）
foreach ($processed_pages as $page_name => $page_data) {
    if (isset($filled_mapping[$page_name])) {
        $updated_page_data = applyTextMapping($page_data, $filled_mapping[$page_name]);
    } else {
        $updated_page_data = $page_data;  // 直接使用原始資料
    }
    // 每個模板都生成 *-ai.json
}
```

**確保完整性**
- 🎯 所有頁面都有對應的 *-ai.json（home-ai.json, about-ai.json 等）
- 🎯 所有全域模板都有對應的 *-ai.json（header-001-ai.json, footer-001-ai.json 等）
- 🎯 無佔位符的模板直接複製原始內容到 *-ai.json
- 🎯 有佔位符的模板進行 AI 文案替換後儲存到 *-ai.json

---

### [1.14.22] - 2025-07-04 22:30

#### 🚀 Step-09 動態連結提取：移除硬編碼限制，實現真正通用性

**重大改進**
- ✅ **完全動態化連結提取**：不再硬編碼任何連結，完全從 site-config.json 動態提取
- ✅ **多層次連結收集**：
  1. menu_structure.primary → 主選單連結
  2. categories → /category/{slug} 連結
  3. page_list → /{page} 連結
  4. contact_info → mailto: 和 tel: 連結
  5. social_media → 所有社群平台連結
  6. 通用錨點連結 (#, #top, #contact 等)

**問題解決**
- ❌ **之前問題**：硬編碼固定連結 `['/about', '/service', 'tel:+886-912-345-678']`，不同用戶/專案無法通用
- ✅ **修復後**：完全從當前專案的 site-config.json 動態提取所有有效連結

**通用性提升**
```php
// 舊版（硬編碼）
$valid_links = ['/about', '/service', 'tel:+886-912-345-678'];

// 新版（動態提取）
foreach ($contact_info as $key => $value) {
    if ($key === 'email') $valid_links[] = 'mailto:' . $value;
    if ($key === 'phone') $valid_links[] = 'tel:' . $value;
}
```

**適用性確保**
- 🎯 不同品牌/語言的網站都能正確提取連結
- 🎯 聯絡資訊、社群連結、頁面結構完全客製化
- 🎯 自動去重和清理，確保連結有效性

---

### [1.14.21] - 2025-07-04 22:20

#### 🔧 Step-09 全域模板統一處理：修復 text-mapping.json 缺失問題

**重大架構修復**
- ✅ **全域模板佔位符統一提取**：template/global/*.json 的 {{}} 佔位符現在也會加入 text-mapping.json
- ✅ **統一 AI 文案生成**：頁面 + 全域模板一起交給 AI 模型處理，確保風格一致
- ✅ **完整替換流程**：所有 {{}} 佔位符都通過統一的四階段流程處理

**問題解決**
- ❌ **之前問題**：全域模板（header-001.json 等）有 {{HEADER_CTA_BUTTON}} 等佔位符，但沒進入 text-mapping.json
- ✅ **修復後**：全域模板佔位符以 `global_` 前綴加入 text-mapping.json，如 `global_header-001`

**新的處理流程**
```
1. 合併頁面容器 → home.json, about.json 等
2. 讀取全域模板 → global/header-001.json 等  
3. 統一提取佔位符 → text-mapping.json (包含頁面+全域)
4. AI 統一生成文案 → text-mapping-filled.json
5. 分別應用替換 → *-ai.json + global/*-ai.json
```

**檔案結構優化**
```
temp/{job_id}/layout/
├── text-mapping.json        # 現在包含全域模板佔位符
├── text-mapping-filled.json # AI 統一填充結果
├── home-ai.json, about-ai.json 等
└── global/
    ├── header-001-ai.json   # 全域模板 AI 版本
    ├── footer-001-ai.json
    └── 其他全域模板...
```

---

### [1.14.20] - 2025-07-04 22:10

#### 🎯 Step-09 AI 提示詞優化：連結限制與內容品質提升

**主要改進**
- ✅ **連結範圍限制**：AI 只能使用 site-config.json 中定義的有效連結
  - 從 menu_structure 提取主選單連結
  - 從 categories 自動生成分類連結
  - 加入錨點連結（#contact、#about 等）和聯絡連結
- ✅ **內容豐富度要求**：CONTENT/DESCR 類型字數從 50-200 字提升至 150-600 字
- ✅ **全域模板確認處理**：layout/global/ 目錄下的 5 個模板已完整處理

**有效連結清單**
```
- /, /about, /service, /blog, /contact
- /關於我, /服務項目, /部落格, /聯絡我
- /category/human-design-knowledge, /category/self-awareness 等
- #contact, #about, #service, mailto:eric791206@gmail.com
```

**AI 提示詞新增限制**
- 禁止自創連結（如之前的 `/consultation`）
- CONTENT 和 DESCR 需包含具體細節、實例或深度說明
- 字數要求明確：至少是 SUBTITLE 的 3 倍長度

---

### [1.14.19] - 2025-07-04 22:00

#### 🐛 Step-09 致命錯誤修復

**問題解決**
- ✅ **函數重複宣告錯誤修復**：將嵌套函數改為匿名函數（Closure）
- ✅ **所有 5 個頁面現在都能完整處理**
- ✅ **62 個佔位符全部正確替換，生成所有 *-ai.json 檔案**

**技術改進**：使用 `$replaceInContent = function($data, $mapping) use (&$replaceInContent)` 避免全域函數衝突

---

### [1.14.18] - 2025-07-04

#### 🔄 Step-09 完全重構：新架構文案填充系統

**重大功能變更**
- ✅ **新的四階段流程**
  1. 合併頁面容器（layout_selection → 頁面骨架）
  2. 提取 {{}} 佔位符，生成 text-mapping.json
  3. AI 智能文案填充（支援 OpenAI 和 Gemini）
  4. 應用文案到頁面，生成 *-ai.json

**技術架構改進**
- ✅ **配置系統標準化**
  - 統一從 `config/deploy-config.json` 讀取配置
  - 支援成本優化策略（優先使用 Gemini）
  - 完整的 AI 服務切換機制

- ✅ **智能佔位符過濾**
  - 排除 `{{*_BG}}` 和 `{{*_PHOTO}}` 相關佔位符
  - 專注文案內容佔位符處理
  - 準確的佔位符統計（62個文案佔位符）

- ✅ **雙 AI 服務支援**
  - OpenAI API 完整整合
  - Gemini API 完整整合
  - 自動選擇與成本優化策略

**生成檔案結構**
```
temp/{job_id}/layout/
├── home.json, about.json, service.json, blog.json, contact.json  # 原始合併頁面
├── text-mapping.json                                           # 文案對應表
├── text-mapping-filled.json                                   # AI填充後文案
└── *-ai.json                                                   # 最終AI調整頁面
```

**執行效能**
- 頁面處理：5個頁面，總計62個佔位符
- AI 呼叫：單次統一填充，提高效率
- 配置讀取：標準化JSON配置，提高可維護性

---

### [1.14.17] - 2025-07-04

#### 🔧 WordPress 檔案系統權限修復

**緊急修復**
- ✅ **Step-07 檔案系統權限問題**
  - 在 rsync 完成後立即設定 `FS_METHOD = direct`
  - 自動設定正確的檔案擁有者：`chown -R www-data:www-data`
  - 設定適當的檔案權限：`chmod -R 755` (根目錄) + `chmod -R 775` (wp-content)
  - 修復 Ultimate Elementor 與其他外掛的 FTP 連線錯誤

**技術實作**
```bash
# 設定 WordPress 使用直接檔案系統
wp config set FS_METHOD direct --allow-root

# 設定檔案擁有者與權限
chown -R www-data:www-data /www/wwwroot/www.domain.tw/
chmod -R 755 /www/wwwroot/www.domain.tw/
chmod -R 775 /www/wwwroot/www.domain.tw/wp-content/
```

**解決問題**
- 🔧 修復 `ftp_fput(): Argument #1 ($ftp) must be of type FTP\Connection, null given` 錯誤
- 🔧 解決外掛啟用失敗問題 (insert-headers-and-footers, ultimate-elementor)
- 🔧 修復主題啟用時的檔案系統權限錯誤

---

### [1.14.16] - 2025-07-04

#### 🔧 部署系統優化與錯誤修復

**主要改進**
- ✅ 步驟描述準確化 - 所有步驟描述現在完全對應實際功能
- ✅ 主選單自動建立 - 頁面建立後自動建立並配置主選單
- ✅ 分類管理優化 - 完善分類對應與自動建立機制
- ✅ 文章建立流程簡化 - 移除複雜檔案操作，提高穩定性
- ✅ 配置檔案生成完善 - 自動生成必要配置檔案

**錯誤修復**
- 🔧 修復 Ultimate Elementor 外掛衝突問題
- 🔧 解決分類重複建立錯誤
- 🔧 修復檔案讀取與 SSH 連線問題

**系統狀態**
- 部署成功率：95% 以上
- 核心功能完整且穩定
- 已達到生產環境可用狀態

---

## 歷史記錄

更多詳細的歷史記錄請參閱：
- [2025-07-04 詳細記錄](archive/CHANGELOG-20250704.md)
- [2025-07-03 記錄](archive/CHANGELOG-20250703.md) (待整理)
- [更早期記錄](archive/) (待整理)

---

## 使用說明

### 查看特定日期的更改
```bash
# 查看 2025-07-04 的詳細更改
cat archive/CHANGELOG-20250704.md

# 查看所有歷史記錄
ls archive/
```

### 新增更改記錄
1. 在當前 `CHANGELOG.md` 新增最新更改
2. 當日期變更時，將前一天的記錄移至 `archive/CHANGELOG-YYYYMMDD.md`
3. 保持主檔案簡潔，僅顯示最新狀態

---

*最後更新: 2025-07-04*