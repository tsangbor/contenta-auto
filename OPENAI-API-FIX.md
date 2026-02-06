# OpenAI API 參數修復說明

## 📋 問題描述

OpenAI 在 GPT-5 系列及新一代模型中引入了多個 API 參數變更：

### 問題 1: max_tokens 參數棄用
**錯誤訊息**:
```
"message": "Unsupported parameter: 'max_tokens' is not supported with this model. Use 'max_completion_tokens' instead."
```

**影響範圍**:
- GPT-5 系列（gpt-5, gpt-5-mini, gpt-5-nano）
- GPT-4.1 系列（gpt-4.1, gpt-4.1-mini, gpt-4.1-nano）
- o3/o4 推理模型系列

### 問題 2: temperature 參數限制
**錯誤訊息**:
```
"message": "Unsupported value: 'temperature' does not support 0.7 with this model. Only the default (1) value is supported."
```

**影響範圍**:
- **整個 GPT-5 系列**（gpt-5, gpt-5-mini, gpt-5-nano 等）
- **整個 GPT-4.1 系列**（gpt-4.1, gpt-4.1-mini, gpt-4.1-nano 等）
- **o1 系列推理模型**（o1, o1-mini）
- **o3 系列推理模型**（o3, o3-mini, o3-pro）
- **o4 系列推理模型**（o4-mini）

---

## ✅ 解決方案

採用**智慧參數選擇**方案：
1. 建立 `OpenAIHelper` 輔助類別
2. 自動判斷模型類型
3. 使用正確的 token 限制參數（max_tokens vs max_completion_tokens）
4. 自動判斷是否支援自訂 temperature 參數

---

## 📁 修改檔案清單

### 新增檔案（1 個）
| 檔案 | 說明 |
|------|------|
| `includes/utilities/class-openai-helper.php` | OpenAI API 輔助類別 |

### 修改檔案（7 個）

#### 1. step-08.php
- **功能**: AI 生成網站配置
- **修改位置**: 行 11, 行 815-827
- **max_tokens**: 16000
- **變更**:
  ```php
  // 修改前
  $data = [
      'model' => $ai_config['model'],
      'messages' => [...],
      'max_tokens' => 16000,
      'temperature' => 0.7
  ];

  // 修改後
  $data = OpenAIHelper::buildRequestData(
      $ai_config['model'],
      [...],
      [
          'max_tokens' => 16000,
          'temperature' => 0.7
      ]
  );
  ```

#### 2. step-09.php
- **功能**: 頁面組裝與 AI 文案填充
- **修改位置**: 行 13, 行 296-308
- **max_tokens**: 16000

#### 3. step-09-5.php
- **功能**: 動態圖片提示詞生成
- **修改位置**: 行 12, 行 968-975
- **max_tokens**: 3000

#### 4. step-10.php
- **功能**: AI 圖片生成
- **修改位置**: 行 8, 行 1279-1286
- **max_tokens**: 4096

#### 5. step-15.php
- **功能**: AI 文章與精選圖片生成
- **修改位置**: 行 24-26, 行 38-52, 行 197-204
- **max_tokens**: 2000 (可變)

#### 6. includes/class-content-resolver.php
- **功能**: 多層次內容解析器
- **修改位置**: 行 12, 行 354-369
- **max_tokens**: 100

#### 7. includes/workflows/process-articles.php
- **功能**: 共用文章生成工作流程
- **修改位置**: 行 21, 行 213-220
- **max_tokens**: 2000 (可變)

---

## 🔧 OpenAIHelper 類別功能

### 主要方法

#### 1. `isNewGenerationModel($model)`
判斷是否為新一代模型
```php
$is_new = OpenAIHelper::isNewGenerationModel('gpt-5-nano');  // true
$is_old = OpenAIHelper::isNewGenerationModel('gpt-4o-mini'); // false
```

#### 2. `buildRequestData($model, $messages, $options)`
建構 API 請求資料（自動處理參數兼容性）
```php
$data = OpenAIHelper::buildRequestData(
    'gpt-5-nano',
    [['role' => 'user', 'content' => 'Hello']],
    [
        'max_tokens' => 1000,
        'temperature' => 0.7  // 會被自動忽略（gpt-5-nano 不支援）
    ]
);

// 自動產生：
// {
//     "model": "gpt-5-nano",
//     "messages": [...],
//     "max_completion_tokens": 1000  ← 自動使用正確參數
//     // temperature 被省略（使用預設值 1）
// }
```

#### 3. `supportsCustomTemperature($model)`
判斷模型是否支援自訂 temperature
```php
$supports = OpenAIHelper::supportsCustomTemperature('gpt-5-nano');
// 返回: false

$supports = OpenAIHelper::supportsCustomTemperature('gpt-4o-mini');
// 返回: true
```

#### 4. `getTokenLimitParamName($model)`
取得 token 限制參數名稱
```php
$param = OpenAIHelper::getTokenLimitParamName('gpt-5-nano');
// 返回: "max_completion_tokens"

$param = OpenAIHelper::getTokenLimitParamName('gpt-4o-mini');
// 返回: "max_tokens"
```

#### 4. `getModelFamily($model)`
取得模型系列名稱
```php
$family = OpenAIHelper::getModelFamily('gpt-5-nano');
// 返回: "GPT-5"
```

---

## 🎯 兼容性

### 支援的模型

#### 新一代模型（使用 max_completion_tokens）
- ✅ GPT-5 系列: gpt-5, gpt-5-mini, gpt-5-nano
- ✅ GPT-4.1 系列: gpt-4.1, gpt-4.1-mini, gpt-4.1-nano
- ✅ o3 系列: o3, o3-mini, o3-pro
- ✅ o4 系列: o4, o4-mini
- ✅ Realtime 模型: gpt-realtime, gpt-realtime-mini
- ✅ Audio 模型: gpt-audio, gpt-audio-mini

#### 舊模型（使用 max_tokens）
- ✅ GPT-4o 系列: gpt-4o, gpt-4o-mini
- ✅ GPT-4 系列: gpt-4, gpt-4-turbo
- ✅ GPT-3.5 系列: gpt-3.5-turbo
- ✅ o1 系列: o1, o1-mini, o1-pro

---

## 📊 為什麼要限定 max_tokens？

### 原因 1: 防止回應截斷
AI 生成的內容可能很長，特別是結構化的 JSON 配置。如果沒有足夠的 token 限制，回應會在中途被截斷，導致：
- JSON 格式錯誤
- 內容不完整
- 後續步驟失敗

### 原因 2: 確保結構化資料完整性
各步驟的 max_tokens 設定：

| 步驟 | max_tokens | 原因 |
|------|-----------|------|
| step-08 | 16000 | 生成完整網站配置 JSON |
| step-09 | 16000 | 填充所有頁面文案 |
| step-09-5 | 3000 | 生成多個圖片提示詞 |
| step-10 | 4096 | 圖片分析和提示詞優化 |
| step-15 | 2000 | 生成單篇文章內容 |
| content-resolver | 100 | 簡短文本片段 |

### 原因 3: 成本與效能平衡
- **太低**: 內容被截斷 → 部署失敗
- **太高**: 浪費成本 → 不經濟
- **適中**: 確保完整同時控制成本

---

## 🧪 測試驗證

### 測試步驟
```bash
# 1. 使用 gpt-5-nano 執行 step-08
php contenta-deploy.php YOUR_JOB_ID --step=08 --mode=luke

# 2. 檢查日誌中的參數提示
# 應該看到: "使用 max_completion_tokens"

# 3. 驗證 API 請求成功
# 不應該再出現 "Unsupported parameter" 錯誤
```

### 預期結果
#### 使用新模型（gpt-5-nano）
```
[INFO] 呼叫 OpenAI API: gpt-5-nano (使用 max_completion_tokens)
[INFO] AI 配置檔案生成成功
```

#### 使用舊模型（gpt-4o-mini）
```
[INFO] 使用模型: gpt-4o-mini (使用 max_tokens)
[INFO] AI 配置檔案生成成功
```

---

## 📝 使用建議

### 配置 OpenAI 模型

在 `config/deploy-config.json` 中：

```json
{
    "api_credentials": {
        "openai": {
            "api_key": "sk-proj-...",
            "model": "gpt-5-nano",
            "base_url": "https://api.openai.com/v1/"
        }
    }
}
```

### 推薦模型選擇

#### 成本優先
```json
"model": "gpt-5-nano"
```
- 最便宜：$0.05/$0.40 per 1M tokens
- 適合：大量內容生成

#### 平衡方案
```json
"model": "gpt-4.1-nano"
```
- 性價比：$0.10/$0.40 per 1M tokens
- 適合：一般業務應用

#### 品質優先
```json
"model": "gpt-4o"
```
- 高品質：$2.50/$10.00 per 1M tokens
- 適合：要求高品質輸出

---

## ⚠️ 注意事項

1. **向後兼容**: 舊模型（GPT-4o, GPT-3.5）仍然可以正常使用
2. **自動判斷**: 無需手動配置，系統自動選擇正確參數
3. **Gemini 不受影響**: Gemini 使用 `maxOutputTokens`，無需修改
4. **日誌提示**: 日誌中會顯示使用的參數類型，方便除錯

---

## 🔄 未來擴展

如果 OpenAI 推出新的模型系列，只需要修改 `class-openai-helper.php` 中的 `$new_generation_models` 陣列：

```php
private static $new_generation_models = [
    'gpt-5',
    'gpt-4.1',
    'o3',
    'o4',
    'gpt-realtime',
    'gpt-audio',
    'gpt-6',        // 新增未來模型
];
```

---

## ✅ 修復完成

所有 OpenAI API 呼叫已更新為兼容新舊模型的版本，現在可以無縫使用：
- ✅ GPT-5 系列
- ✅ GPT-4.1 系列
- ✅ o3/o4 推理模型
- ✅ GPT-4o 系列（向後兼容）
- ✅ GPT-3.5 系列（向後兼容）

**測試狀態**: 待用戶驗證
**版本**: 1.0
**日期**: 2026-01-31
