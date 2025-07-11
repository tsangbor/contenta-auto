# Ubuntu 主機部署指南

本指南說明如何在 Ubuntu 主機上部署 Contenta Auto 系統，並整合 Playwright 自動認證功能。

## 🚀 快速開始

### 1. 系統需求

- Ubuntu 18.04 LTS 或更新版本
- Node.js 18.x 或更新版本
- PHP 7.4 或更新版本
- 至少 2GB RAM
- 至少 5GB 可用磁碟空間

### 2. 一鍵安裝

```bash
# 下載專案
git clone <your-repo-url> contenta-auto
cd contenta-auto

# 執行安裝腳本
chmod +x ubuntu-setup.sh
./ubuntu-setup.sh
```

### 3. 設定認證資訊

```bash
# 方法 1: 使用環境變數（推薦）
export BTPANEL_USERNAME="your_username"
export BTPANEL_PASSWORD="your_password"

# 方法 2: 直接執行並輸入帳密
node auth-updater.js --username your_username --password your_password
```

### 4. 測試認證更新

```bash
# 檢查認證狀態
node auth-updater.js --check

# 手動更新認證
node auth-updater.js

# 可見模式測試（有桌面環境時）
node auth-updater.js --visible
```

## 📋 詳細使用說明

### 認證更新器 (auth-updater.js)

#### 基本用法

```bash
# 基本更新
node auth-updater.js

# 指定帳號密碼
node auth-updater.js --username myuser --password mypass

# 指定設定檔路徑
node auth-updater.js --config custom-config.json

# 可見模式（調試用）
node auth-updater.js --visible

# 檢查認證狀態
node auth-updater.js --check
```

#### 環境變數設定

```bash
# 永久設定環境變數
echo 'export BTPANEL_USERNAME="your_username"' >> ~/.bashrc
echo 'export BTPANEL_PASSWORD="your_password"' >> ~/.bashrc
source ~/.bashrc
```

### 整合部署腳本 (deploy-with-auth.php)

#### 基本用法

```bash
# 完整部署（自動更新認證）
php deploy-with-auth.php

# 執行特定步驟
php deploy-with-auth.php --step 08

# 強制更新認證後部署
php deploy-with-auth.php --force

# 檢查認證狀態
php deploy-with-auth.php --check

# 顯示說明
php deploy-with-auth.php --help
```

#### 範例使用情境

```bash
# 情境 1: 首次部署
php deploy-with-auth.php

# 情境 2: 僅執行某個步驟
php deploy-with-auth.php -s 10

# 情境 3: 認證可能過期，強制更新
php deploy-with-auth.php -f

# 情境 4: 檢查系統狀態
php deploy-with-auth.php -c
```

## ⚙️ 自動化設定

### 排程任務 (Crontab)

設定每 6 小時自動更新認證：

```bash
# 編輯 crontab
crontab -e

# 添加以下行
0 */6 * * * cd /path/to/contenta-auto && node auth-updater.js >> logs/auth-cron.log 2>&1
```

或使用快速設定：

```bash
php deploy-with-auth.php --setup-cron
```

### Systemd 服務

創建系統服務來管理部署：

```bash
# 創建服務檔案
sudo nano /etc/systemd/system/contenta-auto.service
```

```ini
[Unit]
Description=Contenta Auto Deployment Service
After=network.target

[Service]
Type=oneshot
User=your-username
WorkingDirectory=/path/to/contenta-auto
Environment=BTPANEL_USERNAME=your_username
Environment=BTPANEL_PASSWORD=your_password
ExecStart=/usr/bin/php deploy-with-auth.php

[Install]
WantedBy=multi-user.target
```

啟用服務：

```bash
sudo systemctl daemon-reload
sudo systemctl enable contenta-auto.service
sudo systemctl start contenta-auto.service
```

## 🔧 故障排除

### 常見問題

#### 1. Playwright 安裝失敗

```bash
# 手動安裝瀏覽器
npx playwright install chromium

# 安裝系統依賴
sudo apt install -y libnss3-dev libatk-bridge2.0-dev libdrm-dev
```

#### 2. 無頭模式登入失敗

```bash
# 使用可見模式調試
node auth-updater.js --visible

# 檢查系統字體
sudo apt install -y fonts-liberation fonts-noto-color-emoji
```

#### 3. 認證更新失敗

```bash
# 檢查網路連線
curl -k https://jp3.contenta.tw:8888/btpanel

# 檢查憑證是否正確
node auth-updater.js --username correct_user --password correct_pass --visible
```

#### 4. 權限問題

```bash
# 修復檔案權限
chmod +x auth-updater.js ubuntu-setup.sh
chmod 644 config/deploy-config.json

# 確保日誌目錄可寫
mkdir -p logs
chmod 755 logs
```

### 日誌檢查

```bash
# 檢查認證更新日誌
tail -f logs/deploy-with-auth.log

# 檢查 cron 日誌
tail -f logs/auth-cron.log

# 檢查系統日誌
sudo journalctl -u contenta-auto.service -f
```

## 📁 檔案結構

```
contenta-auto/
├── ubuntu-setup.sh              # Ubuntu 環境設定腳本
├── auth-updater.js              # 認證更新器
├── deploy-with-auth.php         # 整合部署腳本
├── config/
│   ├── deploy-config.json       # 主設定檔（含認證）
│   └── deploy-config.example.json
├── logs/
│   ├── deploy-with-auth.log     # 部署日誌
│   └── auth-cron.log           # Cron 執行日誌
└── step-*.php                   # 各個部署步驟
```

## 🔐 安全建議

1. **環境變數**: 使用環境變數而非硬編碼密碼
2. **檔案權限**: 確保設定檔僅限擁有者讀取 (chmod 600)
3. **日誌清理**: 定期清理含有敏感資訊的日誌
4. **VPN**: 在生產環境中建議通過 VPN 存取
5. **金鑰輪換**: 定期更換 API 金鑰和密碼

## 📊 監控與維護

### 健康檢查腳本

```bash
#!/bin/bash
# health-check.sh

echo "=== Contenta Auto 健康檢查 ==="

# 檢查認證狀態
echo "1. 檢查認證狀態..."
php deploy-with-auth.php --check

# 檢查 Node.js 環境
echo "2. 檢查 Node.js..."
node --version

# 檢查 Playwright
echo "3. 檢查 Playwright..."
npx playwright --version

# 檢查磁碟空間
echo "4. 檢查磁碟空間..."
df -h .

echo "=== 檢查完成 ==="
```

### 效能監控

```bash
# 監控認證更新性能
time node auth-updater.js

# 檢查記憶體使用
ps aux | grep node
```

## 🆘 支援

如果遇到問題：

1. 查看日誌檔案
2. 檢查網路連線
3. 確認帳號密碼正確
4. 嘗試可見模式調試
5. 檢查系統資源使用

記住：認證會在 12 小時後自動過期，系統會自動處理更新。