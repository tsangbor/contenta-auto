# Contenta 自動化部署系統

獨立的本機端 PHP 指令系統，用於自動化部署網站到 BT.cn 主機。

## 系統概述

這是一個完整的自動化網站部署解決方案，支援從網域註冊到內容生成的全流程自動化。

### 主要功能

- 🌐 **網域註冊**: 透過 GoDaddy API 自動註冊網域
- ☁️ **DNS 設定**: Cloudflare API 整合
- 🖥️ **主機管理**: BT.cn Panel API 整合
- 🔒 **SSL 憑證**: Let's Encrypt 自動申請
- 🗄️ **資料庫**: MySQL 自動建立和配置
- 📝 **WordPress**: 全自動安裝和設定
- 🔌 **外掛部署**: 批量安裝和授權啟用
- 🎨 **主題設定**: Elementor 版型自動匯入
- 🤖 **AI 整合**: 自動圖片和文章生成

## 檔案結構

```
local/
├── contenta-deploy.php          # 主部署腳本
├── config-manager.php           # 配置管理系統
├── step-00.php ~ step-19.php    # 20個部署步驟
├── test-deploy.php              # 測試腳本
├── create-remaining-steps.php   # 批量建立步驟檔案
├── config/                      # 配置目錄
│   ├── deploy-config.json       # 主配置檔案（自動生成）
│   └── deploy-config-example.json # 配置範例
├── data/                        # 用戶資料目錄
├── json/                        # 參考模板
│   ├── site-config.json         # 網站配置模板
│   ├── image-prompts.json       # 圖片生成提示詞
│   └── article-prompts.json     # 文章生成提示詞
├── logs/                        # 日誌目錄（自動生成）
└── temp/                        # 臨時工作目錄（自動生成）
```

## 快速開始

### 1. 系統測試

```bash
php test-deploy.php
```

### 2. 配置設定

複製並編輯配置檔案：

```bash
cp config/deploy-config-example.json config/deploy-config.json
```

填入你的 API 憑證：
- GoDaddy API 金鑰
- Cloudflare API Token
- BT.cn Panel API
- OpenAI/Gemini API 金鑰
- 伺服器 SSH 資訊

### 3. 準備 Job 資料

在 `data/` 目錄下建立 JSON 檔案，格式如下：

```json
{
  "job_id": "2506290730-3450",
  "confirmed_data": {
    "website_name": "我的網站",
    "website_description": "網站描述",
    "domain": "example.tw",
    "user_email": "user@example.com"
  }
}
```

### 4. 執行部署

```bash
php contenta-deploy.php [job_id] [--step=XX]
```

例如：
```bash
# 完整執行（從步驟00開始）
php contenta-deploy.php 2506290730-3450

# 從步驟03開始執行（適用於失敗恢復）
php contenta-deploy.php 2506290730-3450 --step=03

# 從步驟10開始執行（跳過前面的步驟）
php contenta-deploy.php 2506290730-3450 --step=10
```

### 5. 檢查日誌

每個 job 都會建立專用的日誌檔案：

```bash
# 檢查特定 job 的日誌
tail -f logs/job-2506290730-3450.log

# 檢查通用日誌
tail -f logs/deploy-2025-06-30.log
```

## 部署步驟說明

| 步驟 | 名稱 | 說明 |
|------|------|------|
| 00 | 設定參數與載入配置 | 處理 JSON 資料，驗證配置 |
| 01 | 網域註冊 | 透過 GoDaddy API 註冊網域 |
| 02 | Cloudflare DNS 設定 | 建立 DNS 記錄 |
| 03 | BT.cn 主機建立網站 | 在主機上建立網站 |
| 04 | SSL 憑證設置 | Let's Encrypt 憑證申請 |
| 05 | 資料庫建立 | MySQL 資料庫和用戶建立 |
| 06 | WordPress 下載安裝 | WP-CLI 自動安裝 |
| 07 | 外掛主題部署與啟用 | 使用 rsync 同步 wp-content/plugins 和 themes、清理預設主題、先啟用 16 個指定外掛、啟用主題、建立專用管理員帳號、啟用 Elementor Pro 和 FlyingPress 授權 |
| 08 | AI 配置檔案生成 | 使用 AI 生成網站配置和內容提示詞 |
| 09 | AI 文字替換與頁面生成 | 處理頁面模板和全域模板的佔位符替換 |
| 10 | AI 圖片生成與路徑替換 | 使用 OpenAI DALL-E 3 或 Gemini 生成圖片 |
| 11 | 用戶角色設定 | 自訂用戶角色 |
| 12 | 用戶帳號建立 | 建立客戶帳號 |
| 13 | 網站管理員信箱設定 | 更新管理員信箱 |
| 14 | Elementor 版型匯入 | 匯入頁面版型 |
| 15 | 頁面生成 | 根據配置生成頁面 |
| 16 | 選單導航建立 | 建立網站選單 |
| 17 | 網站選項匯入 | 匯入網站設定 |
| 18 | AI 圖片生成 | 生成並上傳圖片 |
| 19 | AI 文章生成 | 生成 DEMO 文章 |

## 必要外掛清單

系統會自動安裝以下外掛：

- Advanced Custom Fields
- Auto Upload Images
- Better Search Replace
- Contact Form 7
- Elementor & Elementor Pro
- Flying Press
- One User Avatar
- Performance Lab
- Astra Pro Sites
- Rank Math SEO & Pro
- Google Site Kit
- Header Footer Elementor
- Ultimate Elementor
- Insert Headers and Footers

## API 整合

### 網域註冊 (GoDaddy)
- 網域可用性檢查
- 自動註冊流程
- DNS 設定為 Cloudflare

### DNS 管理 (Cloudflare)
- Zone 建立
- A 記錄設定
- SSL 自動配置

### 主機管理 (BT.cn)
- 網站建立
- SSL 憑證申請
- 資料庫管理
- PHP 版本設定

### AI 服務
- OpenAI: 文章生成、圖片生成
- Gemini: 圖片生成、內容分析

## 日誌系統

所有操作都會記錄到 `logs/deploy-YYYY-MM-DD.log` 檔案中，包含：

- 執行時間戳
- 步驟進度
- 錯誤訊息
- API 回應

## 安全注意事項

1. **API 金鑰保護**: 配置檔案包含敏感資訊，請勿提交至版本控制
2. **SSH 金鑰**: 使用 SSH 金鑰驗證，避免密碼驗證
3. **權限控制**: 確保檔案權限設定正確
4. **網路安全**: 建議在 VPN 環境下執行

## 故障排除

### 常見問題

1. **SSH 連線失敗**
   - 檢查 SSH 金鑰路徑
   - 驗證伺服器 IP 和埠號
   - 確認防火牆設定

2. **API 呼叫失敗**
   - 檢查 API 金鑰正確性
   - 確認 API 額度未超限
   - 檢查網路連線

3. **外掛啟用失敗**
   - 確認外掛檔案完整
   - 檢查授權金鑰
   - 驗證 WordPress 權限

### 除錯模式

設定環境變數啟用詳細日誌：

```bash
export DEBUG=1
php contenta-deploy.php [job_id]
```

## 開發與擴展

### 新增步驟

1. 建立新的 `step-XX.php` 檔案
2. 在 `contenta-deploy.php` 中新增步驟定義
3. 實作步驟邏輯
4. 更新文檔

### 自訂配置

修改 `config-manager.php` 中的預設配置來自訂系統行為。

## 技術支援

如有問題或建議，請聯絡開發團隊或查閱系統日誌進行故障排除。

---

## 📋 更新日誌

詳見 [CHANGELOG.md](CHANGELOG.md)

## 📚 文檔結構

專案文檔已按照類型組織在 `docs/` 目錄下：

### 📖 核心指南 (docs/guides/)
- [AI 開發指南](docs/guides/AI-DEVELOPMENT-GUIDE.md)
- [AI 提示詞優化指南](docs/guides/AI-PROMPT-OPTIMIZATION.md)
- [品牌確認指南](docs/guides/BRAND-CONFIRMATION-GUIDE.md)
- [BT API 更新指南](docs/guides/BT-API-UPDATE-GUIDE.md)
- [成本優化指南](docs/guides/COST-OPTIMIZATION-GUIDE.md)
- [Gemini 優化指南](docs/guides/GEMINI-OPTIMIZATION-GUIDE.md)
- [提示詞品質測試指南](docs/guides/HOW-TO-TEST-PROMPT-QUALITY.md)

### 🔧 系統規格 (docs/specs/)
- [圖片生成流程重構規格書](docs/specs/圖片生成流程重構-開發規格書.md)
- [圖片佔位符更新](docs/specs/IMAGE-PLACEHOLDER-UPDATE.md)
- [佔位符系統更新](docs/specs/PLACEHOLDER-SYSTEM-UPDATE.md)
- [語義化佔位符映射](docs/specs/SEMANTIC-PLACEHOLDER-MAPPING.md)
- [視覺反饋配置](docs/specs/VISUAL-FEEDBACK-CONFIG.md)
- [批次 API 分析](docs/specs/BATCH-API-ANALYSIS.md)

### 📊 歷史報告 (docs/reports/)
- [檔案清理報告](docs/reports/FILE-CLEANUP-REPORT.md)
- [最終測試成功報告](docs/reports/FINAL-TEST-SUCCESS-REPORT.md)
- [優化驗證報告](docs/reports/OPTIMIZATION-VERIFICATION-REPORT.md)
- [2025-07-01 更新記錄](docs/reports/UPDATE-RECORD-2025-07-01.md)
- [WordPress 安全性強化總結](docs/reports/WordPress-Security-Enhancement-Summary.md)
- [圖片生成總結](docs/reports/image-generation-summary.md)

### 📝 筆記與草稿 (docs/notes/)
- [Claude 指導方針](docs/notes/CLAUDE.md)
- [專案經理筆記](docs/notes/專案經理筆記.md)

---

---

## 📝 最新更新 - Global 模板處理功能 (v1.14)

### 🆕 新增功能 (2025-07-04)

#### 步驟 9 增強：Global 模板處理
- **新增處理對象**：`template/global/` 目錄下的全域模板
- **支援模板**：
  - `404error001.json` - 404 錯誤頁面
  - `archive001.json` - 歸檔頁面  
  - `footer001.json` - 頁尾模板
  - `header001.json` - 頁首模板
  - `singlepost001.json` - 單篇文章模板

#### 技術特色
- **統一處理流程**：使用相同的 AI 佔位符替換機制
- **獨立儲存**：處理後檔案存放在 `temp/{job_id}/layout/global/`
- **向後相容**：不影響現有頁面處理流程
- **完整日誌**：詳細記錄處理過程與結果

#### 執行效能
- **處理速度**：5 個全域模板約 10 秒完成
- **AI 呼叫**：每個模板獨立 AI 處理
- **成功率**：100% 測試通過

---

**版本**: v1.14 (Global 模板處理功能)  
**最後更新**: 2025-07-04  
**開發團隊**: Contenta AI Team