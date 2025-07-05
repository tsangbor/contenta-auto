# 圖片提示詞品質測試指南

## 📋 測試工具概覽

您現在有三個強大的測試工具來驗證 AI 圖片提示詞的品質：

### 1. 🔍 提示詞品質評估工具
**檔案**: `test-prompt-quality.php`  
**功能**: 快速評估提示詞的品牌整合度和專業水準  
**適用**: 日常品質檢查，對比分析

### 2. 🎨 完整圖片生成測試
**檔案**: `test-complete-image-generation.php`  
**功能**: 端到端測試，包含實際 AI 圖片生成  
**適用**: 完整系統驗證，實際圖片效果測試

### 3. ⚡ 系統邏輯測試
**檔案**: `test-image-prompt-generation.php`  
**功能**: 驗證提示詞生成邏輯和結構  
**適用**: 開發階段的功能驗證

## 🚀 快速開始指南

### 步驟 1: 提示詞品質快速檢查

```bash
php test-prompt-quality.php
```

**這個測試會顯示**：
- ✅ 優化後提示詞評分：85% (A 級)
- ❌ 傳統通用提示詞：40% (F 級)
- 📈 改進幅度：+45 分

**評分標準**：
- **85分以上**：🎉 專業級（可直接使用）
- **70-84分**：👍 標準級（建議優化）
- **70分以下**：⚠️ 需要改進

### 步驟 2: 完整系統測試（需要 API 配置）

```bash
# 首先檢查 API 配置
cat config/deploy-config.json | grep -A 3 '"api_key"'

# 執行完整測試
php test-complete-image-generation.php
```

**如果您有配置 API**，這個測試會：
1. ✅ 檢查 API 可用性
2. 🏗️ 創建測試環境
3. 🎨 生成 AI 提示詞
4. 📊 分析提示詞品質
5. 🖼️ 實際生成圖片
6. 📝 提供詳細報告

**如果沒有配置 API**：
- 系統會跳過實際圖片生成
- 但仍會完成提示詞品質分析
- 提供模擬的高品質提示詞範例

## 📊 品質評估指標詳解

### 1. 品牌關鍵字整合 (30分)
**檢查內容**：
- 是否包含用戶提供的品牌關鍵字
- 中英文關鍵字自動翻譯匹配
- 關鍵字使用的自然度

**範例**：
- ✅ 好："human design and energy healing"
- ❌ 差："generic business services"

### 2. 配色方案應用 (20分)
**檢查內容**：
- 是否使用用戶指定的色碼
- 配色描述的準確性
- 色彩與品牌調性的匹配

**範例**：
- ✅ 好："deep green (#2D4C4A) and warm beige (#BFAA96)"
- ❌ 差："neutral colors"

### 3. 專業術語使用 (20分)
**檢查內容**：
- 攝影、設計專業術語的使用
- 描述的具體性和技術性
- 術語使用的恰當性

**範例**：
- ✅ 好："soft ambient lighting, gradient transitions, minimalist aesthetic"
- ❌ 差："nice colors, good design"

### 4. 目標受眾相關性 (15分)
**檢查內容**：
- 是否反映目標受眾特徵
- 受眾關鍵詞的自然融入
- 情感訴求的準確性

**範例**：
- ✅ 好："suitable for self-discovery and personal growth audience"
- ❌ 差："for everyone"

### 5. 英文語法品質 (15分)
**檢查內容**：
- 語法正確性
- 形容詞使用豐富度
- 句子結構的複雜性
- 提示詞長度適當性

## 🎯 實際使用場景

### 場景 1: 開發階段驗證
```bash
# 修改提示詞生成邏輯後
php test-image-prompt-generation.php

# 檢查邏輯是否正確，結構是否完整
```

### 場景 2: 品質快速檢查
```bash
# 每次優化提示詞後
php test-prompt-quality.php

# 確認品質評分是否達到 85 分以上
```

### 場景 3: 客戶交付前驗證
```bash
# 為特定客戶生成提示詞後
php test-complete-image-generation.php

# 完整驗證整個流程，確保無誤
```

### 場景 4: A/B 測試對比
```bash
# 修改 test-prompt-quality.php 中的測試提示詞
# 對比不同版本的品質評分
```

## 🔧 自訂測試範例

### 新增您自己的測試案例

編輯 `test-prompt-quality.php`：

```php
// 在檔案末尾新增您的測試
echo "\n測試範例 3: 您的自訂提示詞\n";
echo "=" . str_repeat("=", 50) . "\n";

$your_prompt = "您的圖片提示詞內容...";

$your_brand_data = [
    'brand_keywords' => ['您的', '品牌', '關鍵字'],
    'target_audience' => '您的目標受眾描述',
    'color_scheme' => [
        'primary' => '#您的主色碼',
        'secondary' => '#您的次要色碼'
    ]
];

$evaluation = evaluatePromptQuality($your_prompt, $your_brand_data);
// ... 顯示結果
```

## 📈 品質改進建議

### 如果評分低於 70 分：

1. **檢查品牌關鍵字**：
   - 確保包含 3-5 個核心品牌關鍵字
   - 使用自然的英文表達方式

2. **加入配色方案**：
   - 明確指定色碼
   - 描述色彩的情感表達

3. **豐富專業術語**：
   - 加入攝影術語 (lighting, composition, etc.)
   - 使用設計術語 (aesthetic, minimalist, etc.)

4. **針對目標受眾**：
   - 反映受眾的生活方式
   - 加入情感共鳴元素

### 範例改進過程：

**原始提示詞 (40分)**：
```
Professional business background, modern design, clean style
```

**第一次改進 (65分)**：
```
Professional business background for wellness consultancy, 
modern design with green colors, clean minimalist style
```

**第二次改進 (85分)**：
```
Professional abstract background for holistic wellness consultancy 
specializing in human design and energy healing, deep green (#2D4C4A) 
and warm beige (#BFAA96) color palette, soft ambient lighting creating 
healing atmosphere, modern spiritual aesthetic suitable for 
self-discovery audience seeking balance
```

## 🖼️ 實際圖片生成測試

### 前置需求：
1. **API 配置**：在 `config/deploy-config.json` 中設定
   ```json
   {
     "api_credentials": {
       "openai": {
         "api_key": "您的OpenAI API金鑰"
       },
       "gemini": {
         "api_key": "您的Gemini API金鑰"
       }
     }
   }
   ```

2. **執行測試**：
   ```bash
   php test-complete-image-generation.php
   ```

3. **檢查結果**：
   - 測試目錄：`temp/test-YYYYMMDD-HHMMSS/`
   - 生成圖片：`temp/test-YYYYMMDD-HHMMSS/images/`
   - 提示詞檔案：`temp/test-YYYYMMDD-HHMMSS/json/image-prompts.json`

### 圖片品質驗證清單：

✅ **色彩準確性**：圖片是否使用了指定的配色方案？  
✅ **風格一致性**：是否符合品牌個性？  
✅ **目標受眾適配**：圖片是否能引起目標受眾共鳴？  
✅ **專業度**：圖片品質是否達到商業使用標準？  
✅ **品牌識別度**：是否能清楚傳達品牌特色？  

## 🎉 成功案例分析

### 優化前 vs 優化後對比

**優化前 (傳統方式)**：
- 品質評分：40% (F 級)
- 品牌關鍵字：0/30 分 
- 配色方案：0/20 分
- 生成圖片：通用商業風格，缺乏個性

**優化後 (v1.14.4)**：
- 品質評分：85% (A 級)
- 品牌關鍵字：18/30 分 (+18)
- 配色方案：20/20 分 (+20)
- 生成圖片：深度個性化，完美反映品牌調性

**改進幅度**：+45 分 (提升 112.5%)

## 🔮 進階使用技巧

### 1. 批次測試多個品牌
修改測試腳本來循環測試多個品牌資料

### 2. 自動化品質檢查
整合到 CI/CD 流程中，確保每次更新都通過品質檢查

### 3. A/B 測試不同風格
創建多個提示詞變體，對比效果

### 4. 行業特化優化
針對不同行業建立專門的評估標準

---

**記住**：提示詞品質直接影響最終圖片效果。建議每次優化後都執行品質評估，確保達到 85 分以上的專業級標準！

**工具作者**：Claude AI v1.14.4  
**最後更新**：2025-07-02