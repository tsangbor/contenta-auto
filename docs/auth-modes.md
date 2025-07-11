# BT Panel 認證模式說明

步驟 00 現在支援兩種認證模式：自動模式和手動模式。認證模式可以在 `config/deploy-config.json` 中設定。

## 自動模式（預設）

使用 Playwright 自動登入 BT Panel 並取得認證資訊。

```bash
php contenta-deploy.php 2506290730-3450 --step=00
```

## 手動模式

當 Playwright 有問題時，可以使用手動模式。

### 配置方式 1：在 config/deploy-config.json 中設定（推薦）

在 `config/deploy-config.json` 的 `btcn` 區塊中加入：

```json
"btcn": {
  "panel_url": "https://jp3.contenta.tw:8888",
  "panel_auth": "https://jp3.contenta.tw:8888/btnpanel",
  "panel_login": "tsangbor",
  "panel_password": "XSW2cde",
  "auth_mode": "manual",
  "manual_cookie": "request_token=你的cookie值",
  "manual_token": "你的token值",
  ...
}
```

### 配置方式 2：使用環境變數

如果不想修改配置檔案，也可以使用環境變數。

### 步驟 1：取得認證資訊

1. 使用瀏覽器登入 BT Panel
2. 開啟開發者工具 (F12)
3. 進入 Network 標籤
4. 重新整理頁面或點擊任何功能
5. 找到任何 API 請求（例如 /system?action=GetSystemTotal）
6. 在 Request Headers 中找到：
   - `Cookie: request_token=xxxxx`（複製完整的 request_token=xxxxx）
   - `X-CSRF-Token: xxxxx`（只複製值，不包含 X-CSRF-Token:）

### 步驟 2：使用手動模式執行

```bash
# 設定環境變數並執行
CONTENTA_AUTH_MODE=manual \
BTPANEL_COOKIE='request_token=你的cookie值' \
BTPANEL_TOKEN='你的token值' \
php contenta-deploy.php 2506290730-3450 --step=00
```

### 範例

```bash
CONTENTA_AUTH_MODE=manual \
BTPANEL_COOKIE='request_token=abcdef123456' \
BTPANEL_TOKEN='xyz789' \
php contenta-deploy.php 2506290730-3450 --step=00
```

## 切換模式

### 在配置檔案中切換

在 `config/deploy-config.json` 的 `btcn` 區塊中設定：
- **自動模式**：`"auth_mode": "auto"` 或不設定此欄位
- **手動模式**：`"auth_mode": "manual"`

### 使用環境變數覆蓋

環境變數的優先權高於配置檔案：
- **自動模式**：不設定 `CONTENTA_AUTH_MODE` 或設定為 `auto`
- **手動模式**：設定 `CONTENTA_AUTH_MODE=manual`

## 注意事項

1. 手動模式的認證資訊有效期為 24 小時
2. 認證過期後需要重新取得
3. Cookie 和 Token 必須是從同一個登入會話取得
4. 確保複製時包含完整的格式（Cookie 要包含 request_token= 前綴）