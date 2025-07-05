# Config 設定說明

## 重要提醒

`deploy-config.json` 包含敏感的 API 密鑰和認證資訊，**絕對不要**提交到版本控制系統中。

## 設定步驟

1. 複製範本檔案：
   ```bash
   cp deploy-config.example.json deploy-config.json
   ```

2. 編輯 `deploy-config.json`，填入你的實際 API 密鑰和設定值

## 設定項目說明

### AI Features
- **enable_visual_feedback**: 視覺反饋功能開關（預設關閉以節省成本）
- **cost_optimization**: 成本優化策略設定

### API Credentials
需要設定以下服務的 API 密鑰：
- **OpenAI**: GPT 和 DALL-E 圖片生成
- **Gemini**: Google AI 服務（較便宜的替代方案）
- **BunnyCDN**: CDN 服務
- **Cloudflare**: DNS 和安全服務
- **BTCN**: 主機控制面板
- **Lihi**: 短網址服務

### Deployment
- 伺服器連線資訊
- WordPress 安裝設定
- 管理員帳號密碼

### WordPress 插件
- 必要插件列表
- 付費插件授權碼

## 安全注意事項

1. **永遠不要**將 `deploy-config.json` 提交到 Git
2. 定期更換 API 密鑰
3. 使用強密碼
4. 限制 API 密鑰的權限範圍
5. 考慮使用環境變數來管理敏感資訊

## 故障排除

如果遇到認證問題：
1. 檢查 API 密鑰是否正確
2. 確認 API 密鑰是否有效且未過期
3. 檢查服務端點 URL 是否正確
4. 確認帳戶是否有足夠的配額或餘額