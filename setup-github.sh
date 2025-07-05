#!/bin/bash

# 設定 GitHub 遠端倉庫的腳本
echo "📋 GitHub 設定助手"
echo ""
echo "請先在 GitHub 上建立一個新的 repository，然後執行以下步驟："
echo ""
echo "1. 執行以下命令來設定遠端倉庫："
echo "   git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git"
echo ""
echo "2. 設定預設分支為 main:"
echo "   git branch -M main"
echo ""
echo "3. 第一次推送:"
echo "   git push -u origin main"
echo ""
echo "完成以上步驟後，你就可以使用 './sync.sh' 或輸入 'sync' 來自動同步了！"