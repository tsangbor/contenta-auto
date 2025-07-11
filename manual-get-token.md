# 手動取得 BT Panel Token 步驟

## 步驟 1：使用瀏覽器登入

1. 開啟瀏覽器（建議使用 Chrome 或 Firefox）
2. 前往：https://jp3.contenta.tw:8888/btpanel
3. 輸入帳號：tsangbor
4. 輸入密碼：XSW2cde3
5. 點擊登入

## 步驟 2：開啟開發者工具

1. 按 F12 或右鍵選擇「檢查」
2. 切換到 **Network** (網路) 標籤
3. 重新整理頁面 (F5)

## 步驟 3：找出 Cookie

1. 在 Network 標籤中，點擊任何一個請求
2. 查看 **Request Headers** (請求標頭)
3. 找到 **Cookie** 欄位，複製整個值
4. 通常格式類似：`session=xxxxx; token=xxxxx`

## 步驟 4：找出 X-HTTP-Token

1. 在相同的請求中，尋找這些可能的 header：
   - `X-HTTP-Token`
   - `X-CSRF-Token`
   - `X-Requested-With`
2. 複製對應的值

## 步驟 5：更新設定檔

將找到的值更新到 `config/deploy-config.json`：

```json
"btcn": {
    "session_cookie": "貼上你的完整 Cookie 值",
    "http_token": "貼上你的 X-HTTP-Token 值"
}
```

## 替代方法：使用瀏覽器 Console

1. 登入後，開啟開發者工具的 **Console** (主控台)
2. 執行以下命令：

```javascript
// 取得所有 cookies
console.log('Cookies:', document.cookie);

// 嘗試找出 token
console.log('Token from meta:', document.querySelector('meta[name="csrf-token"]')?.content);
console.log('Token from window:', window.http_token || window.csrf_token);
console.log('Token from localStorage:', localStorage.getItem('http_token') || localStorage.getItem('csrf_token'));
```

## 注意事項

- Cookie 和 Token 會過期，需要定期更新
- 保護好這些認證資訊，不要公開分享
- 建議使用環境變數來存儲敏感資訊