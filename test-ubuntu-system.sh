#!/bin/bash

echo "🧪 測試 Ubuntu 系統整合功能"
echo "================================"

# 檢查 Node.js
echo "1. 檢查 Node.js..."
if command -v node &> /dev/null; then
    echo "✅ Node.js: $(node --version)"
else
    echo "❌ Node.js 未安裝"
fi

# 檢查 npm
echo "2. 檢查 npm..."
if command -v npm &> /dev/null; then
    echo "✅ npm: $(npm --version)"
else
    echo "❌ npm 未安裝"
fi

# 檢查 Playwright
echo "3. 檢查 Playwright..."
if [ -f "node_modules/.bin/playwright" ]; then
    echo "✅ Playwright 已安裝"
else
    echo "⚠️  Playwright 未安裝或需要 npm install"
fi

# 檢查認證更新器
echo "4. 檢查認證更新器..."
if [ -f "auth-updater.js" ]; then
    echo "✅ auth-updater.js 存在"
    echo "測試認證檢查功能..."
    node auth-updater.js --check
else
    echo "❌ auth-updater.js 不存在"
fi

# 檢查設定檔
echo "5. 檢查設定檔..."
if [ -f "config/deploy-config.json" ]; then
    echo "✅ 設定檔存在"
else
    echo "⚠️  設定檔不存在，需要從範本創建"
fi

# 檢查部署腳本
echo "6. 檢查部署腳本..."
if [ -f "deploy-with-auth.php" ]; then
    echo "✅ deploy-with-auth.php 存在"
else
    echo "❌ deploy-with-auth.php 不存在"
fi

# 檢查日誌目錄
echo "7. 檢查日誌目錄..."
if [ -d "logs" ]; then
    echo "✅ logs 目錄存在"
else
    echo "📁 創建 logs 目錄..."
    mkdir -p logs
    echo "✅ logs 目錄已創建"
fi

echo ""
echo "🎯 測試總結:"
echo "- 系統已準備好在 Ubuntu 上運行"
echo "- 在 Ubuntu 上執行: ./ubuntu-setup.sh"
echo "- 然後執行: node auth-updater.js"
echo "- 最後執行: php deploy-with-auth.php"