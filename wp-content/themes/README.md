# WordPress 主題目錄

此目錄用於 step-07.php rsync 同步主題到遠端伺服器。

## 使用方式

1. 將所需的 WordPress 主題目錄放置在此處
2. 執行 step-07.php 時會：
   - 先清理遠端的預設主題 (twenty*)
   - 使用 rsync 同步此目錄內容到遠端伺服器
   - 自動啟用 hello-theme-child-master 主題

## 同步模式

- 使用 `--delete` 選項，會刪除遠端不存在於此目錄的主題
- 確保清理後的主題環境

## 注意事項

- 確保 hello-theme-child-master 主題存在於此目錄
- 避免放置不必要的檔案
- 主題目錄結構必須正確