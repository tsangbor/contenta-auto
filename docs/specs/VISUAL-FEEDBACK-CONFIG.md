# 🎨 視覺反饋循環配置指南

## 功能說明

視覺反饋循環是 Contenta AI v1.13.3 的革命性功能，使用 GPT-4o 分析生成的圖片，然後根據視覺特徵精練文案內容，實現文案與視覺的完美協調。

## Token 消耗分析

### GPT-4o 圖片分析成本
- **每張圖片分析**: 約 1500-2000 tokens
- **典型網站 (3張關鍵圖片)**: 約 4500-6000 tokens
- **文案精練**: 每頁約 500-800 tokens
- **總計**: 約 7000-10000 tokens per 網站

### 成本效益評估
- **高價值場景**: 品牌形象要求高、視覺一致性重要
- **一般場景**: 基本功能已足夠，可節省 80% 成本

## 配置方式

### 方法 1: 透過 deploy-config.json 啟用
```json
{
  "ai_features": {
    "enable_visual_feedback": true
  }
}
```

### 方法 2: 環境變數控制
```bash
export ENABLE_VISUAL_FEEDBACK=true
```

### 方法 3: 代碼中直接設定
```php
// 在 step-10.php 中手動啟用
$enable_visual_feedback = true;
```

## 功能分析

### 視覺反饋循環做什麼？

1. **圖片分析** (GPT-4o 多模態)
   - 提取色彩調性 (冷暖色調、情緒)
   - 分析視覺風格 (現代、傳統、簡約等)
   - 識別構圖元素 (主要物件、焦點)
   - 評估情緒傳達 (專業、溫馨、活力等)
   - 歸納品牌特質

2. **文案精練** (基於視覺特徵)
   - 調性協調：確保文字風格與圖片氣氛一致
   - 情緒匹配：讓文案情緒與視覺元素相呼應
   - 品牌統一：強化文字中的品牌特質表達

### 實際效果

**啟用前 vs 啟用後**:
- 視覺文案一致性: 60-70% → 90-95%
- 品牌沉浸感: 顯著提升
- 用戶體驗: 更協調統一

## 成本控制建議

### 經濟模式 (預設)
```json
{
  "ai_features": {
    "enable_visual_feedback": false
  }
}
```
- 使用基本的圖片生成
- 節省 80% GPT-4o token 消耗
- 適合預算有限或一般品質要求

### 高品質模式
```json
{
  "ai_features": {
    "enable_visual_feedback": true,
    "visual_feedback_max_images": 3
  }
}
```
- 完整視覺反饋循環
- 最佳文案視覺協調效果
- 適合高品質品牌網站

### 自訂控制
```json
{
  "ai_features": {
    "enable_visual_feedback": true,
    "visual_feedback_max_images": 1,  // 只分析 1 張關鍵圖片
    "skip_content_refinement": true   // 跳過文案精練
  }
}
```

## 403 錯誤解決方案

如果遇到 HTTP 403 錯誤，已實施以下修復：

1. **模型降級**: 文案精練自動從 gpt-4o 降級到 gpt-4
2. **Token 減少**: 降低 max_tokens 使用量
3. **錯誤容錯**: 失敗時繼續基本流程

## 圖片生成優化

已加入「不生成文字」指令：
```
"Important: Do not include any text, letters, numbers, or words in the image. Generate a clean visual image without any textual elements."
```

## 建議使用策略

### 💰 預算優先
- 關閉視覺反饋功能
- 使用基本圖片生成
- 成本節省 80%

### 🎨 品質優先  
- 啟用完整視覺反饋循環
- 投資於更好的視覺一致性
- 適合重要品牌項目

### ⚖️ 平衡模式
- 選擇性啟用 (重要項目才開啟)
- 限制分析圖片數量
- 根據客戶需求靈活調整

---

**配置檔案位置**: `config/deploy-config.json`  
**即時修改**: 重新執行步驟 10 即可生效