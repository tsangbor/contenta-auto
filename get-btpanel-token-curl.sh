#!/bin/bash

echo "🔐 使用 curl 登入 BT Panel..."

# 登入並保存 cookie
RESPONSE=$(curl -k -c cookies.txt -s -X POST \
  https://jp3.contenta.tw:8888/login \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "username=tsangbor&password=XSW2cde3" \
  -v 2>&1)

# 從 verbose 輸出中提取 set-cookie
echo "$RESPONSE" | grep -i "set-cookie" | while read line; do
    echo "🍪 Cookie: $line"
done

# 讀取保存的 cookie 文件
echo -e "\n📄 保存的 Cookies:"
cat cookies.txt

# 嘗試訪問主頁面獲取 token
echo -e "\n🔍 嘗試獲取 X-HTTP-Token..."
MAIN_PAGE=$(curl -k -b cookies.txt -s https://jp3.contenta.tw:8888/)

# 搜尋可能的 token
if echo "$MAIN_PAGE" | grep -o "http_token.*['\"].*['\"]" | head -1; then
    echo "✅ 找到 token"
elif echo "$MAIN_PAGE" | grep -o "csrf.*token.*['\"].*['\"]" | head -1; then
    echo "✅ 找到 CSRF token"
else
    echo "⚠️  無法自動找到 token，請手動從瀏覽器檢查"
fi

echo -e "\n💡 提示：如果無法找到 token，請："
echo "1. 在瀏覽器中登入 https://jp3.contenta.tw:8888/btpanel"
echo "2. 開啟開發者工具 (F12)"
echo "3. 在 Network 標籤中查看請求的 Headers"
echo "4. 尋找 X-HTTP-Token 或類似的 header"