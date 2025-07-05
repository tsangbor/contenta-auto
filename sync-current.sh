#!/bin/bash

# 通用的同步腳本 - 同步當前目錄的 Git 專案

# 檢查當前目錄是否為 Git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo "❌ 錯誤：當前目錄不是 Git repository"
    exit 1
fi

# 獲取專案名稱
PROJECT_NAME=$(basename "$(pwd)")
SYNC_CONFIG=".sync"

echo "🔄 開始同步 $PROJECT_NAME 專案..."

# 添加所有變更
git add -A

# 檢查是否有變更需要提交
if git diff --staged --quiet; then
    echo "📝 沒有新的變更需要同步"
    exit 0
fi

# 獲取當前時間作為提交訊息
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")

# 檢查是否有 .sync 配置檔案
if [ -f "$SYNC_CONFIG" ]; then
    # 讀取配置中的提交訊息格式（這裡簡化處理）
    COMMIT_MSG="自動同步: $TIMESTAMP"
else
    COMMIT_MSG="自動同步: $TIMESTAMP"
fi

# 顯示變更摘要
echo "📊 變更摘要:"
git status --short

# 提交變更
git commit -m "$COMMIT_MSG"

# 獲取當前分支名稱
CURRENT_BRANCH=$(git branch --show-current)

# 推送到遠端
if git remote | grep -q origin; then
    echo "📤 推送到遠端分支: $CURRENT_BRANCH"
    git push origin "$CURRENT_BRANCH"
    
    # 更新 .sync 檔案的 last_sync 時間（如果存在）
    if [ -f "$SYNC_CONFIG" ]; then
        sed -i '' "s/\"last_sync\": .*/\"last_sync\": \"$TIMESTAMP\"/" "$SYNC_CONFIG" 2>/dev/null || true
    fi
    
    echo "✅ 同步完成！"
else
    echo "⚠️  警告: 尚未設定 GitHub 遠端倉庫"
    echo "請先設定遠端倉庫："
    echo "git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPO.git"
fi