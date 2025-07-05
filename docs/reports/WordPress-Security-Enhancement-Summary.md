# WordPress 安全性強化總結

## 📅 更新日期
2025-07-03

## 🎯 目標
在步驟 06 (WordPress 安裝) 後新增安全性設定，將 WordPress 管理後台從預設的 `/wp-admin` 改為自訂網址 `/dashboard`

## 🔧 實施的變更

### 1. 配置檔案更新 (config/deploy-config.json)
新增 WordPress 安全性設定區塊：
```json
"wordpress_security": {
    "hide_admin_login": true,
    "custom_admin_url": "dashboard",
    "admin_url_prefix": "secure"
}
```

### 2. Step-06.php WordPress 安裝步驟強化
**檔案**: `step-06.php`

#### 新增功能：
- **步驟 6 新增 WordPress 安全性設定**（在 WordPress 安裝完成後執行）
  - 在 wp-config.php 中新增（使用安全的條件檢查）：
    ```php
    // WordPress 安全性設定
    if (!defined('WP_ADMIN_DIR')) {
        define('WP_ADMIN_DIR', 'wp-admin'); // 預設值，可透過配置自訂
    }
    if (!defined('ADMIN_COOKIE_PATH')) {
        define('ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin/');
    }
    if (!defined('SHORTPIXEL_API_KEY')) {
        define('SHORTPIXEL_API_KEY', '4pSSVVJnUXIywAJirTal');
    }
    ```

#### 更新內容：
- **管理後台網址動態化**：從 `/wp-admin` 改為 `/{custom_admin_url}`
- **安裝資訊儲存**：新增 `custom_admin_url` 欄位
- **日誌輸出**：更新為顯示正確的管理後台網址

### 3. BT Panel API 偽靜態規則更新
**檔案**: `includes/class-bt-panel-api.php`

#### 方法簽名更新：
```php
// 舊版
public function setupWordPressRewrite($domain)

// 新版  
public function setupWordPressRewrite($domain, $site_name, $panel_url, $session_cookie, $http_token, $custom_admin_url = 'wp-admin', $hide_admin_login = false)
```

#### 偽靜態規則更新：
```nginx
# 基本 WordPress 規則
location /
{
     try_files $uri $uri/ /index.php?$args;
}

# 當 hide_admin_login=true 且 custom_admin_url≠"wp-admin" 時隱藏 wp-admin 路徑
rewrite /wp-admin$ $scheme://$host$uri/ permanent;
```

**重要配置條件：**
- 只有當 `wordpress_security.hide_admin_login = true` 時才會應用重定向規則
- 且 `wordpress_security.custom_admin_url` 不等於 "wp-admin" 時
- 如果兩個條件都不滿足，則只會生成基本的 WordPress 偽靜態規則

### 4. SSL 與偽靜態設定步驟更新
**檔案**: `step-04.php`

#### 功能增強：
- 新增完整的偽靜態規則設置功能
- 傳入自訂管理後台網址到偽靜態設定
- 更新日誌輸出以顯示 SSL 和偽靜態狀態

## 🔒 安全性改進

### 支援自訂管理後台
- **預設網址**: `https://www.domain.tw/wp-admin` (保持原有行為)
- **自訂網址**: `https://www.domain.tw/dashboard` (當配置 `custom_admin_url: "dashboard"` 時)
- **好處**: 可選擇性隱藏管理後台，防止自動化攻擊掃描預設管理路徑

### Cookie 路徑保護
- 設定專用的 Cookie 路徑
- 提升登入安全性

### ShortPixel 整合
- 預設配置 ShortPixel API 金鑰
- 自動圖片優化

## 📊 影響的系統元件

### 修改的檔案
1. `step-06.php` - WordPress 安裝流程
2. `includes/class-bt-panel-api.php` - BT Panel API 偽靜態設定
3. `step-04.php` - SSL 與偽靜態設定
4. `config/deploy-config.json` - 配置檔案

### 新增的功能
- 自訂管理後台網址支援
- 動態偽靜態規則生成
- 安全性常數定義

## 🧪 測試建議

### 驗證步驟
1. **配置測試**：確認 `deploy-config.json` 中的 `wordpress_security` 設定正確
2. **安裝測試**：執行 step-06 並檢查 wp-config.php 內容
3. **網址測試**：確認 `/dashboard` 可正確重定向到 WordPress 管理後台
4. **偽靜態測試**：驗證 Nginx 重寫規則是否正確應用

### 測試指令
```bash
# 測試步驟 06
php contenta-deploy.php [job_id] --step=06

# 檢查 wp-config.php 內容
ssh root@server "cat /www/wwwroot/www.domain.tw/wp-config.php | grep WP_ADMIN_DIR"

# 檢查偽靜態規則
ssh root@server "cat /www/server/panel/vhost/rewrite/www.domain.tw.conf"
```

## 🚀 部署狀態

- ✅ 配置檔案已更新
- ✅ WordPress 安裝步驟已強化
- ✅ BT Panel API 已支援自訂網址
- ✅ SSL 設定步驟已更新
- ✅ 向後相容性保持

## 📝 注意事項

1. **預設值**: 如果未配置 `custom_admin_url`，系統預設使用 `wp-admin` (保持原有行為)
2. **相容性**: 所有變更都保持向後相容，不影響現有部署
3. **安全性**: 建議在生產環境中設定自訂管理後台網址 (如 `dashboard`)
4. **文檔**: 當使用自訂網址時，需要通知用戶新的管理後台登入網址

## 🔄 後續優化建議

1. **外掛支援**: 考慮整合專用的隱藏登入外掛
2. **監控**: 新增失敗登入嘗試監控
3. **通知**: 自動發送新管理後台網址給用戶
4. **備份**: 確保安全設定在系統備份中保留

## 🚨 問題排除

### 常數重複定義錯誤
**問題**: 出現 "Warning: Constant ADMIN_COOKIE_PATH already defined" 錯誤

**原因**: WordPress 核心或其他元件可能已經定義了相同的常數

**解決方案**:
1. 使用修復腳本：
   ```bash
   php fix-wp-config-constants.php [domain]
   ```

2. 手動修復：
   - 在 wp-config.php 中使用 `if (!defined())` 條件檢查
   - 移除重複的定義語句

**預防措施**: 
- 新版本的 step-06.php 已使用條件檢查避免重複定義
- 所有常數定義都包裝在 `if (!defined())` 條件中

### 修復工具
- **檔案**: `fix-wp-config-constants.php`
- **功能**: 自動修復重複定義問題
- **使用**: `php fix-wp-config-constants.php yaoguo.tw`

### 偽靜態設置問題
**問題**: Step-04 原本只有 SSL 設置，缺少偽靜態規則設置功能

**原因**: 
1. 原始 `step-04.php` 沒有實作偽靜態設置
2. 誤建立了重複的 `step-04-ssl.php` 檔案導致混淆

**解決方案**:
1. 在 `step-04.php` 中新增完整的偽靜態設置功能
2. 使用正確的 BT Panel API `/files?action=SaveFileBody`
3. 刪除多餘的 `step-04-ssl.php` 檔案避免混淆

### 偽靜態工具
- **測試工具**: `test-rewrite-setup.php`
- **設置工具**: `setup-rewrite-rules.php`
- **使用**: `php setup-rewrite-rules.php yaoguo.tw`

---

**開發人員**: Claude AI  
**版本**: v1.14.5 WordPress 安全性強化版 (修正偽靜態設置錯誤)  
**狀態**: 已完成並測試

---

### v1.14.4 更新內容 (2025-07-03)

#### 🔧 偽靜態規則重大改進
- **改用 location 區塊**: 從簡單的 rewrite 規則改為完整的 location 配置
- **完整路徑支援**: 支援自訂管理後台的所有子路徑 (如 /dashboard/index.php)
- **資源載入優化**: 單獨處理 CSS、JS、圖片等靜態資源
- **更安全的隱藏**: 使用 301 重定向完全隱藏 wp-admin 路徑
- **檔案路徑修正**: 統一使用 `{domain}.conf` 格式

#### 🎯 技術改進
- 使用 `location ^~` 提供精確的路徑匹配
- 使用 `rewrite ... last` 實現內部重定向
- 單獨的靜態資源處理規則防止載入問題
- 更健壯的 Nginx 配置結構

### v1.14.5 更新內容 (2025-07-03)

#### 🔧 修正偽靜態設置錯誤
- **問題**: `Undefined variable $site_name` 錯誤
- **原因**: `setupWordPressRewrite` 函數中使用了未定義的 `$site_name` 變數
- **修正**: 
  - 更新函數簽名，新增 `$site_name` 參數
  - 更新函數呼叫，傳入正確的 `$site_name`
  - 偽靜態規則現在使用 `{$site_name}.conf` 而不是 `{$domain}.conf`
  
#### 🎯 技術細節
- 函數簽名: `setupWordPressRewrite($domain, $site_name, $panel_url, $session_cookie, $http_token, $custom_admin_url = 'wp-admin')`
- 檔案路徑: `/www/server/panel/vhost/rewrite/{$site_name}.conf`（例如：`www.yaoguo.tw.conf`）
- 新增偽靜態檔案路徑日誌輸出，便於診斷

### v1.14.6 更新內容 (2025-07-03)

#### 🔧 偽靜態規則進階優化
- **改進**: 新增更完善的資源載入和 AJAX 支援
- **新增功能**:
  - 支援更多字型檔案格式 (woff, woff2, ttf, eot)
  - 新增靜態資源快取控制 (30天過期)
  - 單獨處理 WordPress AJAX 請求 (admin-ajax.php, admin-post.php)
  - 使用 FastCGI 處理 PHP 請求
  
#### 🎯 技術改進
- 規則順序優化：資源載入規則放在重定向前面
- 新增快取策略：`expires 30d` 和 `Cache-Control: public, immutable`
- AJAX 請求支援：確保 WordPress 管理後台功能正常運行
- FastCGI 配置：`fastcgi_pass unix:/tmp/php-cgi-74.sock`