#!/bin/bash

# 讀取 .sync 配置檔案
SYNC_CONFIG=".sync"
PROJECT_ROOT="/Users/huminim4/Downloads/work/wwwroot/contenta-auto"

# 切換到專案目錄
cd "$PROJECT_ROOT"

# 自動提交並推送所有變更到 GitHub
echo "🔄 開始同步 contenta-auto 專案到 GitHub..."

# 添加所有變更
git add -A

# 檢查是否有變更需要提交
if git diff --staged --quiet; then
    echo "📝 沒有新的變更需要同步"
    exit 0
fi

# 獲取當前時間作為提交訊息
TIMESTAMP=$(date "+%Y-%m-%d %H:%M:%S")
COMMIT_MSG="自動同步: $TIMESTAMP"

# 顯示變更摘要
echo "📊 變更摘要:"
git status --short

# 提交變更
git commit -m "$COMMIT_MSG"

# 推送到遠端 (假設已設定 origin)
if git remote | grep -q origin; then
    git push origin main
    
    # 更新 .sync 檔案的 last_sync 時間
    if [ -f "$SYNC_CONFIG" ]; then
        # 使用 sed 更新 last_sync 欄位
        sed -i '' "s/\"last_sync\": .*/\"last_sync\": \"$TIMESTAMP\"/" "$SYNC_CONFIG"
    fi
    
    echo "✅ 同步完成！"
else
    echo "⚠️  警告: 尚未設定 GitHub 遠端倉庫"
    echo "請執行 ./setup-github.sh 查看設定步驟"
fi