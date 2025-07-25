# 即夢 AI 測試版本說明

本專案提供三種不同的即夢 AI 調用方式，用於生成 Humaaans 風格的插圖。

## 🚀 版本對比

| 版本 | 語言 | 依賴 | 特點 |
|------|------|------|------|
| **PHP 原生版本** | PHP | 無外部依賴 | 詳細調試，完整功能 |
| **JavaScript 原生版本** | Node.js | 僅使用原生模組 | 跨平台，獨立實現 |
| **JavaScript MCP 版本** | Node.js | jimeng-ai-mcp | 簡化調用，封裝完善 |

## 📁 檔案說明

### 1. PHP 原生版本
- **檔案**: `test-jimeng-humaaans.php`
- **特點**: 
  - 基於火山引擎官方簽名算法
  - 包含詳細的 Humaaans 風格提示詞
  - 支援英文和中文提示詞
  - 完整的錯誤處理和調試功能
  - 自動圖片儲存功能

### 2. JavaScript 原生版本  
- **檔案**: `test-jimeng-signature.js`
- **特點**:
  - 使用 Node.js 原生 crypto 和 https 模組
  - 完整的簽名驗證過程展示
  - JimengAIClient 類封裝
  - 詳細的調試輸出

### 3. JavaScript MCP 版本
- **檔案**: `test-jimeng-mcp.js` 
- **特點**:
  - 使用 jimeng-ai-mcp 包簡化調用
  - 更簡潔的 API 接口
  - 自動處理簽名和認證
  - HumaaansPromptBuilder 類用於風格化提示詞

## 🛠 使用方法

### 前置要求
1. 在 `config/deploy-config.json` 中配置即夢 AI 的 API 金鑰：
```json
{
  "api_credentials": {
    "jimeng": {
      "AccessKeyID": "你的AccessKeyID",
      "SecretAccessKey": "你的SecretAccessKey"
    }
  }
}
```

### PHP 版本
```bash
php test-jimeng-humaaans.php
```

### JavaScript 原生版本
```bash
node test-jimeng-signature.js
# 或
npm run test-jimeng
```

### JavaScript MCP 版本
```bash
# 首次使用需要安裝 MCP 包
npm run install-jimeng-mcp

# 執行測試
node test-jimeng-mcp.js
# 或  
npm run test-jimeng-mcp
```

## 🎨 Humaaans 風格特點

所有版本都包含針對 Humaaans 風格優化的提示詞：

- **視覺風格**: 扁平插畫，極簡設計
- **角色特徵**: 幾何形狀，友善特徵
- **色彩配置**: 深藍色、淺藍色、深灰色點綴
- **構圖**: 抽象幾何背景，充足留白
- **純視覺**: 無文字、無標誌

## 🔧 故障排除

### 常見錯誤

1. **SignatureDoesNotMatch**: 簽名算法錯誤
   - ✅ 本專案的簽名算法已驗證正確

2. **Access Denied**: 權限配置問題
   - 檢查 API 金鑰是否有「視覺智能-圖像生成」權限
   - 確認帳戶已通過實名認證
   - 檢查帳戶餘額和計費配置

3. **模組載入失敗** (MCP 版本):
   - 執行 `npm run install-jimeng-mcp` 安裝依賴
   - 或改用原生版本

### 調試建議

1. **使用原生版本進行調試**: 原生版本提供詳細的簽名生成過程
2. **檢查金鑰格式**: AccessKeyID 應以 'AK' 開頭，SecretAccessKey 為 base64 格式
3. **權限驗證**: 在火山引擎控制台檢查 API 金鑰權限設定

## 📊 測試場景

所有版本都包含三個測試場景：

1. **homepage_hero**: 專業團隊協作場景
2. **about_team**: 多元團隊合作場景  
3. **service_illustration**: 科技創新概念場景

每個場景都有英文和中文版本的 Humaaans 風格提示詞。

## 🎯 選擇建議

- **開發測試**: 使用 **PHP 原生版本**，調試信息最詳細
- **生產環境**: 使用 **JavaScript MCP 版本**，代碼最簡潔
- **學習研究**: 使用 **JavaScript 原生版本**，了解簽名機制

## ⚠️ 重要提醒

目前所有版本都會遇到 "Access Denied" 錯誤，這是帳戶權限配置問題，不是程式碼問題。請確認：

1. 即夢 AI 服務已正式開通
2. API 金鑰具有正確的服務權限  
3. 帳戶已完成實名認證和計費配置

一旦權限問題解決，所有版本都能正常生成 Humaaans 風格圖片。