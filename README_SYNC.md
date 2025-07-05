# 同步設定說明

這個專案已設定自動同步功能，方便你快速提交並推送變更到 GitHub。

## 設定檔案

- `.sync` - 專案同步偏好設定
- `sync.sh` - 自動同步腳本
- `.gitignore` - Git 忽略檔案設定

## 使用方法

### 初次設定

1. 執行設定助手查看 GitHub 設定步驟：
   ```bash
   ./setup-github.sh
   ```

2. 載入 shell 設定 (擇一執行)：
   ```bash
   # 如果使用 bash
   source ~/.bashrc
   
   # 如果使用 zsh
   source ~/.zshrc
   ```

### 日常使用

在任何地方輸入以下命令即可同步：
```bash
sync
```

或在專案目錄執行：
```bash
./sync.sh
```

## 功能特點

- 自動檢測變更並提交
- 顯示變更摘要
- 自動推送到 GitHub
- 記錄最後同步時間
- 智能跳過無變更情況

## 同步偏好設定

`.sync` 檔案記錄了以下設定：
- 專案名稱：contenta-auto
- 預設分支：main
- 提交訊息格式：自動同步: {時間戳記}
- 排除檔案：系統檔案、日誌、暫存檔等

## 注意事項

- 請確保已設定 GitHub 遠端倉庫
- 首次使用前需要設定 GitHub 認證
- `.bashrc` 和 `.zshrc` 已加入 .gitignore，不會被同步