# 即夢 AI 整合指南

## 概述
本指南說明如何測試和整合即夢 AI 到 contenta-auto 的圖片生成流程中。

## 前置準備

### 1. 申請即夢 AI API 金鑰
1. 訪問[火山引擎控制台](https://console.volcengine.com/)
2. 註冊並登入帳號
3. 開通視覺智能服務
4. 獲取 Access Key 和 Secret Key

### 2. 安裝依賴
```bash
# 執行設置腳本
./setup-jimeng.sh

# 或手動安裝
npm install -g jimeng-ai-mcp
```

### 3. 配置環境變數
```bash
export JIMENG_ACCESS_KEY='your_access_key'
export JIMENG_SECRET_KEY='your_secret_key'
```

## 測試 Humaaans 風格

### 執行測試
```bash
php test-jimeng-humaaans.php
```

### 測試內容
測試腳本會生成三種場景的圖片：
1. **首頁主視覺** - 團隊協作場景
2. **關於我們** - 多元團隊展示
3. **服務說明** - 科技創新概念

每個場景會測試：
- 英文 Humaaans 風格提示詞
- 中文扁平插畫風格提示詞

### 評估標準
檢查生成的圖片是否符合以下 Humaaans 風格特徵：
- ✅ 扁平化設計
- ✅ 簡單幾何形狀
- ✅ 友善的人物形象
- ✅ 統一的色彩系統
- ✅ 向量藝術美學
- ✅ 極簡構圖

## 提示詞優化建議

### 英文提示詞模板
```
A modern flat illustration in the style of humaaans, featuring [場景描述]. 
Minimalist characters with simple geometric features, [動作描述]. 
Clean vector art style, [背景描述]. 
Color palette: deep blue (#2563EB), light blue (#38BDF8), dark gray (#0F172A). 
[其他特定需求]. No text, no words, purely visual imagery.
```

### 中文提示詞模板
```
扁平插畫風格，[場景描述]。
極簡角色設計，簡單幾何特徵，[動作描述]。
乾淨向量藝術風格，[背景描述]。
色彩配置：深藍色(#2563EB)、淺藍色(#38BDF8)、深灰色(#0F172A)。
[其他特定需求]。無文字，純視覺。
```

## API 響應格式
```json
{
    "data": {
        "binary_data_base64": ["base64_encoded_image_data"],
        "image_urls": [],
        "status_code": 10000,
        "status_message": "Success"
    },
    "code": 200,
    "message": "Success"
}
```

## 整合到 Step-10

### 1. 添加即夢 AI 配置
在 `config/deploy-config.json` 中添加：
```json
"jimeng": {
    "access_key": "your_access_key",
    "secret_key": "your_secret_key",
    "api_endpoint": "https://visual.volcengineapi.com",
    "service": "visual",
    "region": "cn-north-1",
    "version": "2022-08-31"
}
```

### 2. 修改圖片生成服務優先級
```json
"ai_image_generation": {
    "primary_service": "jimeng",
    "fallback_order": ["jimeng", "ideogram", "openai", "gemini"]
}
```

## 成本比較
| 服務 | 每張圖片成本 | 生成速度 | 風格支援 |
|------|------------|---------|---------|
| 即夢 AI | ¥0.05-0.10 | 快速 | 優秀 |
| Ideogram | $0.08 | 中等 | 優秀 |
| DALL-E 3 | $0.04-0.08 | 慢 | 良好 |

## 注意事項
1. 即夢 AI 對中文提示詞理解較好
2. 支援批量生成，可提高效率
3. 建議使用詳細的風格描述以獲得最佳效果
4. API 有併發限制，建議加入延遲機制

## 後續步驟
1. 執行測試並檢視生成效果
2. 根據測試結果調整提示詞策略
3. 確認風格符合需求後進行完整整合
4. 更新 step-10.php 支援即夢 AI