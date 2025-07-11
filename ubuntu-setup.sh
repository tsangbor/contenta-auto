#!/bin/bash

# Ubuntu 主機 Playwright 環境設定腳本

echo "🐧 Ubuntu 主機 Playwright 環境設定"
echo "=================================="

# 檢查是否為 root 使用者
if [ "$EUID" -eq 0 ]; then
    echo "⚠️  建議不要使用 root 執行此腳本"
    read -p "是否繼續？(y/N): " confirm
    if [[ $confirm != [yY] ]]; then
        exit 1
    fi
fi

# 更新系統
echo "📦 更新系統套件..."
sudo apt update && sudo apt upgrade -y

# 安裝 Node.js 和 npm
echo "📦 安裝 Node.js 和 npm..."
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# 驗證安裝
echo "✅ Node.js 版本: $(node --version)"
echo "✅ npm 版本: $(npm --version)"

# 安裝必要的系統依賴
echo "📦 安裝 Playwright 系統依賴..."
sudo apt-get install -y \
    libnss3-dev \
    libatk-bridge2.0-dev \
    libdrm-dev \
    libxcomposite-dev \
    libxdamage-dev \
    libxrandr-dev \
    libgbm-dev \
    libxss-dev \
    libasound2-dev \
    libatspi2.0-dev \
    libgtk-3-dev \
    fonts-liberation \
    fonts-noto-color-emoji \
    libappindicator3-1 \
    libasound2 \
    libatk1.0-0 \
    libc6 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgcc1 \
    libgconf-2-4 \
    libgdk-pixbuf2.0-0 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    ca-certificates \
    fonts-liberation \
    libappindicator1 \
    libnss3 \
    lsb-release \
    xdg-utils \
    wget

# 安裝專案依賴
if [ -f "package.json" ]; then
    echo "📦 安裝專案 npm 依賴..."
    npm install
else
    echo "📦 初始化專案依賴..."
    npm init -y
    npm install playwright
fi

# 安裝 Playwright 瀏覽器
echo "🌐 安裝 Playwright 瀏覽器..."
npx playwright install chromium

# 建立認證更新腳本的目錄
mkdir -p auth

echo "✅ Ubuntu Playwright 環境設定完成！"
echo ""
echo "📋 接下來可以執行："
echo "  node auth-updater.js    # 更新認證資訊"
echo "  node contenta-deploy.php # 執行部署流程"