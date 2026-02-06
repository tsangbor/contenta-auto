# 貢獻指南與文檔維護流程

## 📋 文檔管理規範

### 文檔類型與用途

| 文檔 | 用途 | 更新時機 |
|------|------|---------|
| **README.md** | 專案總覽、快速開始、基本使用 | 重大功能新增、架構變更 |
| **CHANGELOG.md** | 詳細的版本變更記錄 | 每次功能調整、Bug 修復 |
| **docs/*.md** | 功能詳細說明、技術文檔 | 新功能完成、API 變更 |
| **{FEATURE}.md** | 特定功能的專門文檔 | 該功能的任何變更 |

---

## 📝 CHANGELOG 維護規範

### 版本號規則（語義化版本）

```
[主版本.次版本.修訂號] - YYYY-MM-DD

主版本: 不兼容的 API 修改
次版本: 向下兼容的功能新增
修訂號: 向下兼容的問題修正
```

### 變更類型分類

使用以下標籤分類變更：

| 標籤 | 說明 | 範例 |
|------|------|------|
| ✨ **Added** | 新增功能 | 新增自動部署模式選擇 |
| 🔧 **Changed** | 功能變更 | 修改 API 請求參數格式 |
| 🐛 **Fixed** | Bug 修復 | 修復 SSHHelper 類別載入錯誤 |
| 🗑️ **Deprecated** | 即將棄用 | 舊版 API 參數將在下版本移除 |
| 🚫 **Removed** | 移除功能 | 移除不再使用的配置選項 |
| 🔒 **Security** | 安全性修復 | 修復 SQL 注入漏洞 |
| 📚 **Documentation** | 文檔更新 | 更新 API 使用說明 |
| ⚡ **Performance** | 效能改進 | 優化圖片生成速度 |

### CHANGELOG 範本

```markdown
## [版本號] - YYYY-MM-DD

### ✨ Added（新增功能）
- 功能描述
  - 詳細說明
  - 影響範圍
  - 相關檔案

### 🔧 Changed（功能變更）
- 變更描述
  - 修改原因
  - 影響範圍

### 🐛 Fixed（Bug 修復）
- 問題描述
  - 錯誤原因
  - 修復方式
  - 影響範圍

### 📚 Documentation（文檔更新）
- 文檔更新說明
```

---

## 🔄 修改工作流程

### 每次修改程式碼時

#### 1. 確認變更類型
- [ ] 是否為新功能？ → Added
- [ ] 是否修改現有功能？ → Changed
- [ ] 是否修復 Bug？ → Fixed
- [ ] 是否為安全性修復？ → Security
- [ ] 是否為效能優化？ → Performance

#### 2. 記錄到 CHANGELOG

```bash
# 編輯 CHANGELOG.md
vi CHANGELOG.md

# 在檔案開頭加入新記錄（保持時間倒序）
```

**範例**：
```markdown
## [1.15.1] - 2026-01-31

### 🐛 Fixed
- **修復 step-17-luke.php SSHHelper 類別載入錯誤**
  - **問題**：`require_once` 語句被放在 PHPDoc 註釋區塊內，導致類別未被載入
  - **原因**：多行註釋沒有正確結束，可執行代碼被當作註釋處理
  - **修復**：將 `require_once` 移出註釋區塊（第 52-53 行）
  - **影響範圍**：step-17-luke.php（Luke API 模式）
  - **錯誤訊息**：`Fatal error: Class "SSHHelper" not found`

### 🔧 Changed
- **OpenAI API 參數兼容性改進**
  - **新增**：`OpenAIHelper` 輔助類別（`includes/utilities/class-openai-helper.php`）
  - **功能**：自動判斷新舊 OpenAI 模型，使用正確的 API 參數
  - **解決問題 1**：GPT-5/GPT-4.1 系列使用 `max_completion_tokens` 而非 `max_tokens`
  - **解決問題 2**：GPT-5/GPT-4.1/o 系列不支援自訂 `temperature` 參數
  - **影響檔案**（7 個）：
    - step-08.php（AI 生成網站配置）
    - step-09.php（AI 文案填充）
    - step-09-5.php（圖片提示詞生成）
    - step-10.php（圖片生成）
    - step-15.php（文章生成）
    - includes/class-content-resolver.php（內容解析）
    - includes/workflows/process-articles.php（文章工作流程）
```

#### 3. 更新 README（如需要）

**更新時機**：
- ✅ 新增主要功能模組
- ✅ API 使用方式變更
- ✅ 配置檔案格式變更
- ✅ 部署步驟變更
- ❌ 內部實作細節調整（只記錄到 CHANGELOG）
- ❌ Bug 修復（除非影響使用方式）

**更新位置**：
```markdown
# README.md 結構

## 已知問題與解決方案          ← 新增此區塊記錄重要修復
## 故障排除                    ← 記錄常見問題
## 更新日誌                    ← 連結到 CHANGELOG.md
```

#### 4. 建立功能文檔（重大功能）

當新增重大功能時，建立獨立文檔：

```bash
# 在專案根目錄建立
{FEATURE-NAME}.md

# 例如：
DNS-CHECK-FEATURE.md
OPENAI-API-FIX.md
LUKE-DEPLOYMENT-GUIDE.md
```

---

## 📊 文檔品質檢查清單

### 提交前檢查

- [ ] CHANGELOG 已更新
- [ ] 版本號正確
- [ ] 日期正確
- [ ] 變更描述清楚
- [ ] 影響範圍已註明
- [ ] 相關檔案已列出
- [ ] README 已更新（如需要）
- [ ] 功能文檔已建立（如需要）

---

## 🎯 文檔撰寫最佳實踐

### DO（應該做）

✅ **使用清晰的標題和分類**
```markdown
### 🐛 Fixed
- **修復 XXX 問題**（簡潔的標題）
  - 問題描述
  - 修復方式
```

✅ **提供具體的檔案位置**
```markdown
- 影響檔案：step-08.php（第 823 行）
```

✅ **說明修改原因**
```markdown
- 原因：OpenAI GPT-5 系列不再支援 max_tokens 參數
```

✅ **記錄錯誤訊息**
```markdown
- 錯誤：`Unsupported parameter: 'max_tokens'`
```

### DON'T（不應該做）

❌ **過於簡略**
```markdown
- 修復 bug（太模糊）
```

❌ **只記錄修改內容**
```markdown
- 修改了 step-08.php（沒說明為什麼）
```

❌ **使用技術黑話**
```markdown
- Refactored the API abstraction layer（改用簡單語言）
```

---

## 📅 定期維護

### 每週
- [ ] 檢查 CHANGELOG 是否有遺漏
- [ ] 整理未分類的修改記錄

### 每月
- [ ] 審查 README 是否需要更新
- [ ] 歸檔舊版本 CHANGELOG（超過 6 個月）
- [ ] 更新文檔索引

### 每季
- [ ] 整體文檔結構審查
- [ ] 移除過時內容
- [ ] 更新範例和截圖

---

## 🔗 相關文檔

- [README.md](./README.md) - 專案總覽
- [CHANGELOG.md](./CHANGELOG.md) - 變更記錄
- [docs/](./docs/) - 詳細技術文檔

---

## 💡 範例參考

### 好的 CHANGELOG 範例

```markdown
## [1.15.0] - 2026-01-31

### ✨ Added
- **新增 Luke API 部署模式支援**
  - 支援透過 Luke Cloud API 建立 WordPress 網站
  - 自動偵測並使用正確的 SSH 金鑰
  - 完整的錯誤處理和日誌記錄
  - 新增檔案：
    - step-03-luke.php（Luke API 網站建立）
    - step-17-luke.php（Luke 最終配置）
    - includes/utilities/class-ssh-helper.php

### 🐛 Fixed
- **修復 DNS 檢查超時問題**
  - 問題：DNS 檢查在 30 分鐘後仍未通過
  - 原因：預設重試次數不足
  - 修復：增加重試次數到 20 次
  - 影響：step-03.php（第 85-120 行）
```

---

**維護者**：開發團隊
**最後更新**：2026-01-31
**版本**：1.0
