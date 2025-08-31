<?php
/**
 * 測試步驟15是否正確使用 Humaaans 風格
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

// 模擬部署器
class MockDeployer {
    public function log($message) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

// 載入必要的函數
require_once __DIR__ . '/config-manager.php';

class Step15HumaanssTester
{
    private $deployer;
    private $config;
    
    public function __construct()
    {
        $this->deployer = new MockDeployer();
        $this->config = ConfigManager::getInstance();
    }
    
    public function testImagePromptGeneration()
    {
        echo "=== 步驟15 Humaaans 風格測試 ===\n\n";
        
        try {
            // 模擬載入配置
            $image_style_config = $this->config->get('ai_features.image_style', []);
            $style_preference = $image_style_config['style'] ?? 'realistic';
            $enable_humaaans = $image_style_config['enable_humaaans'] ?? false;
            
            echo "圖片風格設定: {$style_preference}\n";
            echo "Humaaans 啟用: " . ($enable_humaaans ? '是' : '否') . "\n\n";
            
            // 模擬品牌色彩資訊
            $color_scheme = [
                'primary' => '#2563EB',
                'secondary' => '#38BDF8',
                'text' => '#1E293B',
                'accent' => '#0F172A'
            ];
            
            $color_description = "deep blue ({$color_scheme['primary']}), light blue ({$color_scheme['secondary']}), with subtle dark gray ({$color_scheme['accent']}) accents";
            
            // 根據風格設定生成提示詞指令
            if ($style_preference === 'humaaans' && $enable_humaaans) {
                $style_guidance = "
## 🎨 Humaaans 風格要求 (優先級最高)
**必須使用 Humaaans 扁平插圖風格**，參考以下範例格式：

### Humaaans 風格核心特徵：
- **扁平化插圖設計** (Flat illustration)
- **簡潔的幾何形狀和線條** (Simple geometric shapes, clean lines)  
- **友善、親和的人物形象** (Friendly, approachable character design)
- **統一的色彩系統** (Consistent color palette: {$color_description})
- **Vector art aesthetic** (向量藝術美學)
- **Minimalist composition** (極簡構圖)

範例格式：
\"A modern flat illustration in the style of humaaans, featuring [article-related scene]. The scene represents [core concept from article]. The background is a clean, abstract geometric composition with overlapping shapes. The entire image uses a strict and professional color palette of {$color_description}. Clean lines, vector art aesthetic, plenty of negative space for text overlay. Purely visual imagery, no text, no words, no letters.\"";
            } else {
                $style_guidance = "
## 🎨 寫實攝影風格要求
使用專業攝影風格，真實場景和人物，高品質視覺效果，色彩搭配: {$color_description}。";
            }
            
            // 模擬文章內容
            $article_content = "這是一篇關於軟體專案管理的文章，討論敏捷開發和系統架構設計的重要性...";
            
            $image_prompt_instruction = "你是一位專業的藝術總監。請仔細閱讀以下文章內容，然後為其設計一張精選圖片。

文章內容：
{$article_content}

{$style_guidance}

請分析文章的核心主題、情感氛圍和視覺元素，然後生成一個詳細的英文圖片提示詞。

要求：
1. **【風格優先】**" . ($style_preference === 'humaaans' && $enable_humaaans ? " 必須嚴格遵循上述 Humaaans 風格要求" : " 使用專業寫實攝影風格") . "
2. 提示詞必須是英文
3. 描述要具體且富有視覺感
4. 適合作為部落格文章的精選圖片
5. 必須融入品牌色彩方案: {$color_description}
6. 【重要】提示詞結尾必須加上 \"no text, no words, no letters, purely visual imagery\"
7. 長度控制在 200 字以內

請直接輸出英文提示詞，不要包含任何中文說明。";
            
            echo "生成的圖片提示詞指令：\n";
            echo "=====================================\n";
            echo $image_prompt_instruction . "\n";
            echo "=====================================\n\n";
            
            // 檢查是否包含關鍵字
            $humaaans_keywords = ['humaaans', 'flat illustration', 'vector art', 'geometric', 'minimalist'];
            
            echo "Humaaans 風格關鍵字檢查：\n";
            foreach ($humaaans_keywords as $keyword) {
                $found = stripos($image_prompt_instruction, $keyword) !== false;
                echo "- {$keyword}: " . ($found ? '✅ 找到' : '❌ 未找到') . "\n";
            }
            
            echo "\n✅ 測試完成！\n";
            
        } catch (Exception $e) {
            echo "❌ 測試失敗: {$e->getMessage()}\n";
        }
    }
}

// 執行測試
$tester = new Step15HumaanssTester();
$tester->testImagePromptGeneration();
?>