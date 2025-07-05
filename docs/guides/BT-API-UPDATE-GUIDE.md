# BT.cn Panel API 更新指南

## 📋 更新概覽

本次更新將 BT.cn 主機管理的 API 呼叫方式進行了全面重構，支援更穩定的瀏覽器 Cookie 認證方式，並提供 API Key 認證作為備用方案。

## 🔄 主要變更

### 1. 新增統一 API 管理類別
- **新檔案**: `includes/class-bt-panel-api.php`
- **功能**: 統一管理所有 BT.cn Panel API 呼叫
- **認證**: 支援雙重認證機制

### 2. 更新步驟檔案
- **step-03.php**: 網站建立（已更新）
- **step-05.php**: 資料庫建立（已更新）
- **step-04-ssl.php**: SSL 憑證設置（新增）

### 3. 配置檔案更新
- **deploy-config-example.json**: 新增 Cookie 認證配置

## 🔐 認證機制

### 方法一：瀏覽器 Cookie 認證（推薦）
```json
{
  "btcn": {
    "panel_url": "http://jp1.contenta.tw:8888",
    "session_cookie": "session=your_session_value; X-CSRFToken=your_csrf_token",
    "http_token": "your_http_token_from_browser"
  }
}
```

### 方法二：API Key 認證（備用）
```json
{
  "btcn": {
    "panel_url": "http://jp1.contenta.tw:8888",
    "api_key": "your_bt_panel_api_key"
  }
}
```

## 🛠️ 如何獲取 Cookie 認證資訊

### 步驟 1: 登入 BT.cn Panel
1. 在瀏覽器中登入 BT Panel
2. 打開開發者工具 (F12)
3. 切換到 Network 標籤

### 步驟 2: 執行 API 操作
1. 在 Panel 中執行任何操作（如建立網站）
2. 在 Network 標籤中找到 API 請求

### 步驟 3: 提取認證資訊
1. **session_cookie**: 從請求頭的 `Cookie` 欄位複製
2. **http_token**: 從請求頭的 `x-http-token` 欄位複製

### 範例提取結果：
```
Cookie: session=abcd1234; X-CSRFToken=xyz789
x-http-token: token12345
```

配置為：
```json
{
  "session_cookie": "session=abcd1234; X-CSRFToken=xyz789",
  "http_token": "token12345"
}
```

## 🚀 新功能介紹

### BTPanelAPI 類別方法

#### createWebsite()
```php
$result = $btAPI->createWebsite($domain, $path, $phpVersion, $siteName);
```
- **功能**: 建立新網站
- **參數**: 網域、路徑、PHP版本、網站名稱
- **回傳**: 成功狀態與結果資訊

#### createDatabase()
```php
$result = $btAPI->createDatabase($dbName, $dbUser, $dbPass);
```
- **功能**: 建立資料庫與用戶
- **參數**: 資料庫名稱、用戶名稱、密碼
- **回傳**: 成功狀態與結果資訊

#### setupSSL()
```php
$result = $btAPI->setupSSL($domain);
```
- **功能**: 申請 Let's Encrypt SSL 憑證
- **參數**: 網域名稱
- **回傳**: SSL 設置結果

#### setupWordPressRewrite()
```php
$result = $btAPI->setupWordPressRewrite($domain);
```
- **功能**: 設置 WordPress 偽靜態規則
- **參數**: 網域名稱
- **回傳**: 設置結果

## 📁 檔案變更清單

### 新增檔案
- `includes/class-bt-panel-api.php` - BT Panel API 統一管理類別
- `step-04-ssl.php` - SSL 憑證申請與設置步驟
- `BT-API-UPDATE-GUIDE.md` - 本更新指南

### 修改檔案
- `step-03.php` - 更新為使用新 API 類別
- `step-05.php` - 更新為使用新 API 類別
- `config/deploy-config-example.json` - 新增 Cookie 認證配置

### 移除功能
- 移除步驟檔案中的重複 API 函數
- 移除舊的 API Key 認證邏輯

## 🔧 升級步驟

### 1. 更新配置檔案
複製新的配置範例並填入您的認證資訊：
```bash
cp config/deploy-config-example.json config/deploy-config.json
```

### 2. 獲取 Cookie 認證資訊
按照上述步驟從瀏覽器獲取認證資訊

### 3. 測試新功能
```bash
# 測試網站建立功能
php contenta-deploy.php [job_id] --step=03

# 測試資料庫建立功能  
php contenta-deploy.php [job_id] --step=05

# 測試 SSL 設置功能
php contenta-deploy.php [job_id] --step=04-ssl
```

## ⚡ 優勢與改進

### 穩定性提升
- **Cookie 認證**: 模擬真實瀏覽器請求，更不易被阻擋
- **雙重備援**: API Key 作為備用方案
- **錯誤處理**: 完整的錯誤檢測與回報機制

### 功能擴展
- **SSL 自動化**: 自動申請與設置 Let's Encrypt 憑證
- **偽靜態規則**: 自動設置 WordPress 友善 URL
- **狀態檢查**: 檢查現有資源避免重複建立

### 程式碼品質
- **模組化設計**: 單一職責的 API 類別
- **統一介面**: 一致的回傳格式與錯誤處理
- **可擴展性**: 易於新增更多 BT Panel 功能

## 🚨 注意事項

### Cookie 過期處理
- Cookie 認證可能會過期
- 系統會自動降級到 API Key 認證
- 建議定期更新 Cookie 資訊

### 權限要求
- 確保 BT Panel 帳號有相應操作權限
- 網站建立需要主機管理權限
- SSL 申請需要網域控制權限

### 錯誤處理
- 所有 API 呼叫都包含完整錯誤處理
- 失敗時會記錄詳細錯誤訊息
- 非致命錯誤會繼續執行後續步驟

## 🔮 未來計劃

### 即將新增功能
- 自動 PHP 擴展安裝
- 備份與還原功能
- 效能監控設置
- 防火牆規則配置

### 效能優化
- API 呼叫快取機制
- 批次操作支援
- 異步處理能力

---

**更新日期**: 2025-07-02  
**版本**: v1.14.0  
**維護者**: Claude AI 助手