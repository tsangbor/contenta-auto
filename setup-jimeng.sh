#!/bin/bash
# 即夢 AI 測試環境設置腳本

echo "=== 即夢 AI 測試環境設置 ==="
echo ""

# 檢查是否已安裝 Node.js
if ! command -v node &> /dev/null; then
    echo "❌ 錯誤: 請先安裝 Node.js"
    echo "訪問 https://nodejs.org/ 下載安裝"
    exit 1
fi

echo "✅ Node.js 已安裝: $(node -v)"

# 檢查是否已安裝 npm
if ! command -v npm &> /dev/null; then
    echo "❌ 錯誤: 請先安裝 npm"
    exit 1
fi

echo "✅ npm 已安裝: $(npm -v)"

# 安裝即夢 AI MCP
echo ""
echo "正在安裝即夢 AI MCP..."
npm install -g jimeng-ai-mcp

if [ $? -eq 0 ]; then
    echo "✅ 即夢 AI MCP 安裝成功"
else
    echo "❌ 安裝失敗，請檢查錯誤訊息"
    exit 1
fi

# 檢查環境變數
echo ""
echo "=== 配置檢查 ==="

if [ -z "$JIMENG_ACCESS_KEY" ]; then
    echo "❌ 未設置 JIMENG_ACCESS_KEY"
    echo "請執行: export JIMENG_ACCESS_KEY='your_access_key'"
else
    echo "✅ JIMENG_ACCESS_KEY 已設置"
fi

if [ -z "$JIMENG_SECRET_KEY" ]; then
    echo "❌ 未設置 JIMENG_SECRET_KEY"
    echo "請執行: export JIMENG_SECRET_KEY='your_secret_key'"
else
    echo "✅ JIMENG_SECRET_KEY 已設置"
fi

echo ""
echo "=== 設置說明 ==="
echo "1. 訪問火山引擎控制台獲取 API 金鑰"
echo "   https://console.volcengine.com/"
echo ""
echo "2. 設置環境變數:"
echo "   export JIMENG_ACCESS_KEY='your_access_key'"
echo "   export JIMENG_SECRET_KEY='your_secret_key'"
echo ""
echo "3. 或將以下內容加入 ~/.bashrc 或 ~/.zshrc:"
echo "   export JIMENG_ACCESS_KEY='your_access_key'"
echo "   export JIMENG_SECRET_KEY='your_secret_key'"
echo ""
echo "4. 運行測試:"
echo "   php test-jimeng-humaaans.php"
echo ""
echo "=== 設置完成 ==="