<?php
/**
 * 圖片提示詞品質評估工具
 * 專注於評估提示詞的品牌整合度和專業水準
 */

// 定義基礎路徑常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', dirname(__FILE__));
}

// 載入必要檔案
require_once DEPLOY_BASE_PATH . '/step-09-5.php';

echo "=== 圖片提示詞品質評估工具 ===\n\n";

/**
 * 提示詞品質評估函數
 */
function evaluatePromptQuality($prompt, $expected_brand_data) {
    $score = 0;
    $max_score = 0;
    $details = [];
    
    // 1. 品牌關鍵字檢查 (30分)
    $max_score += 30;
    $brand_keywords = $expected_brand_data['brand_keywords'] ?? [];
    $keyword_matches = 0;
    
    foreach ($brand_keywords as $keyword) {
        if (stripos($prompt, $keyword) !== false || 
            stripos($prompt, translateKeyword($keyword)) !== false) {
            $keyword_matches++;
        }
    }
    
    $keyword_score = min(30, ($keyword_matches / max(1, count($brand_keywords))) * 30);
    $score += $keyword_score;
    $details['brand_keywords'] = [
        'score' => $keyword_score,
        'max' => 30,
        'matches' => $keyword_matches,
        'total' => count($brand_keywords),
        'status' => $keyword_score >= 20 ? '✅' : '⚠️'
    ];
    
    // 2. 配色方案整合 (20分)
    $max_score += 20;
    $color_scheme = $expected_brand_data['color_scheme'] ?? [];
    $color_mentions = 0;
    
    if (!empty($color_scheme)) {
        foreach ($color_scheme as $color_name => $color_code) {
            if (stripos($prompt, $color_code) !== false || 
                stripos($prompt, $color_name) !== false) {
                $color_mentions++;
            }
        }
    }
    
    $color_score = min(20, ($color_mentions > 0) ? 20 : 0);
    $score += $color_score;
    $details['color_scheme'] = [
        'score' => $color_score,
        'max' => 20,
        'mentions' => $color_mentions,
        'status' => $color_score >= 15 ? '✅' : '⚠️'
    ];
    
    // 3. 專業術語使用 (20分)
    $max_score += 20;
    $professional_terms = [
        'professional', 'modern', 'aesthetic', 'lighting', 'design',
        'composition', 'atmosphere', 'gradient', 'texture', 'minimalist'
    ];
    $term_matches = 0;
    
    foreach ($professional_terms as $term) {
        if (stripos($prompt, $term) !== false) {
            $term_matches++;
        }
    }
    
    $professional_score = min(20, ($term_matches / 5) * 20);
    $score += $professional_score;
    $details['professional_terms'] = [
        'score' => $professional_score,
        'max' => 20,
        'matches' => $term_matches,
        'status' => $professional_score >= 15 ? '✅' : '⚠️'
    ];
    
    // 4. 目標受眾相關性 (15分)
    $max_score += 15;
    $target_audience = $expected_brand_data['target_audience'] ?? '';
    $audience_keywords = extractAudienceKeywords($target_audience);
    $audience_matches = 0;
    
    foreach ($audience_keywords as $keyword) {
        if (stripos($prompt, $keyword) !== false) {
            $audience_matches++;
        }
    }
    
    $audience_score = min(15, ($audience_matches > 0) ? 15 : 0);
    $score += $audience_score;
    $details['target_audience'] = [
        'score' => $audience_score,
        'max' => 15,
        'matches' => $audience_matches,
        'keywords' => $audience_keywords,
        'status' => $audience_score >= 10 ? '✅' : '⚠️'
    ];
    
    // 5. 英文語法品質 (15分)
    $max_score += 15;
    $grammar_score = evaluateEnglishGrammar($prompt);
    $score += $grammar_score;
    $details['english_grammar'] = [
        'score' => $grammar_score,
        'max' => 15,
        'status' => $grammar_score >= 12 ? '✅' : '⚠️'
    ];
    
    $final_score = round(($score / $max_score) * 100);
    
    return [
        'total_score' => $final_score,
        'raw_score' => $score,
        'max_score' => $max_score,
        'details' => $details,
        'grade' => getGrade($final_score)
    ];
}

/**
 * 翻譯中文關鍵字到英文
 */
function translateKeyword($keyword) {
    $translations = [
        '人類圖' => 'human design',
        '能量調頻' => 'energy healing',
        '自我成長' => 'personal growth',
        '靈性探索' => 'spiritual exploration',
        '個人品牌' => 'personal branding',
        '身心靈' => 'holistic wellness',
        '諮詢服務' => 'consultation service',
        '個人成長' => 'personal development',
        '能量工作' => 'energy work'
    ];
    
    return $translations[$keyword] ?? $keyword;
}

/**
 * 從目標受眾描述中提取關鍵詞
 */
function extractAudienceKeywords($target_audience) {
    $keywords = [];
    
    if (stripos($target_audience, '上班族') !== false) {
        $keywords[] = 'professional';
        $keywords[] = 'office';
        $keywords[] = 'working';
    }
    
    if (stripos($target_audience, '自我探索') !== false) {
        $keywords[] = 'self-discovery';
        $keywords[] = 'personal exploration';
    }
    
    if (stripos($target_audience, '平衡') !== false) {
        $keywords[] = 'balance';
        $keywords[] = 'harmony';
    }
    
    if (stripos($target_audience, '成長') !== false) {
        $keywords[] = 'growth';
        $keywords[] = 'development';
    }
    
    return array_unique($keywords);
}

/**
 * 評估英文語法品質
 */
function evaluateEnglishGrammar($prompt) {
    $score = 15; // 從滿分開始扣分
    
    // 檢查基本語法問題
    $issues = [];
    
    // 檢查文章使用
    if (!preg_match('/\b(a|an|the)\s+\w+/', $prompt)) {
        $issues[] = 'Missing articles (a, an, the)';
        $score -= 2;
    }
    
    // 檢查逗號使用
    if (substr_count($prompt, ',') < 2) {
        $issues[] = 'Insufficient use of commas for complex descriptions';
        $score -= 1;
    }
    
    // 檢查形容詞使用
    $adjectives = ['professional', 'modern', 'elegant', 'sophisticated', 'warm', 'natural'];
    $adj_count = 0;
    foreach ($adjectives as $adj) {
        if (stripos($prompt, $adj) !== false) $adj_count++;
    }
    if ($adj_count < 2) {
        $issues[] = 'Insufficient descriptive adjectives';
        $score -= 2;
    }
    
    // 檢查長度合理性
    if (strlen($prompt) < 100) {
        $issues[] = 'Prompt too short for detailed description';
        $score -= 3;
    } elseif (strlen($prompt) > 400) {
        $issues[] = 'Prompt might be too long for optimal AI processing';
        $score -= 1;
    }
    
    return max(0, $score);
}

/**
 * 根據分數給出等級
 */
function getGrade($score) {
    if ($score >= 90) return 'A+';
    if ($score >= 85) return 'A';
    if ($score >= 80) return 'A-';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'B-';
    if ($score >= 60) return 'C+';
    if ($score >= 55) return 'C';
    return 'F';
}

// 測試範例
echo "測試範例 1: 優化後的提示詞\n";
echo "=" . str_repeat("=", 50) . "\n";

$optimized_prompt = "Professional abstract background for holistic wellness consultancy specializing in human design and energy healing, incorporating mystical geometric patterns inspired by energy charts and spiritual symbols, deep green (#2D4C4A) and warm beige (#BFAA96) color palette, soft ambient lighting creating healing atmosphere, modern spiritual aesthetic with flowing energy lines, sacred geometry elements, gradient transitions, suitable for self-discovery and personal growth audience seeking balance and harmony, 1920x1080 aspect ratio";

$brand_data = [
    'brand_keywords' => ['人類圖', '能量調頻', '自我成長', '靈性探索', '個人品牌'],
    'target_audience' => '對自我認識、能量調整及靈性成長有興趣的上班族與自我探索者，尋找生活方向與內在平衡的人群',
    'color_scheme' => [
        'primary' => '#2D4C4A',
        'secondary' => '#7A8370',
        'accent' => '#BFAA96'
    ]
];

$evaluation = evaluatePromptQuality($optimized_prompt, $brand_data);

echo "提示詞長度: " . strlen($optimized_prompt) . " 字元\n";
echo "提示詞內容: " . substr($optimized_prompt, 0, 100) . "...\n\n";

echo "評估結果:\n";
echo "總分: {$evaluation['total_score']}% ({$evaluation['raw_score']}/{$evaluation['max_score']}) - 等級 {$evaluation['grade']}\n\n";

echo "詳細評分:\n";
foreach ($evaluation['details'] as $category => $detail) {
    $category_name = [
        'brand_keywords' => '品牌關鍵字整合',
        'color_scheme' => '配色方案應用',
        'professional_terms' => '專業術語使用',
        'target_audience' => '目標受眾相關性',
        'english_grammar' => '英文語法品質'
    ][$category] ?? $category;
    
    echo "  {$detail['status']} {$category_name}: {$detail['score']}/{$detail['max']} 分\n";
    
    if (isset($detail['matches'])) {
        echo "    匹配數量: {$detail['matches']}" . (isset($detail['total']) ? "/{$detail['total']}" : "") . "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

echo "測試範例 2: 傳統通用提示詞\n";
echo "=" . str_repeat("=", 50) . "\n";

$generic_prompt = "Professional business background image with modern design, clean and corporate style, neutral colors, high quality, suitable for website header";

$evaluation2 = evaluatePromptQuality($generic_prompt, $brand_data);

echo "提示詞長度: " . strlen($generic_prompt) . " 字元\n";
echo "提示詞內容: {$generic_prompt}\n\n";

echo "評估結果:\n";
echo "總分: {$evaluation2['total_score']}% ({$evaluation2['raw_score']}/{$evaluation2['max_score']}) - 等級 {$evaluation2['grade']}\n\n";

echo "詳細評分:\n";
foreach ($evaluation2['details'] as $category => $detail) {
    $category_name = [
        'brand_keywords' => '品牌關鍵字整合',
        'color_scheme' => '配色方案應用',
        'professional_terms' => '專業術語使用',
        'target_audience' => '目標受眾相關性',
        'english_grammar' => '英文語法品質'
    ][$category] ?? $category;
    
    echo "  {$detail['status']} {$category_name}: {$detail['score']}/{$detail['max']} 分\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

echo "對比分析:\n";
echo "優化後提示詞 vs 傳統提示詞\n";
echo "總分: {$evaluation['total_score']}% vs {$evaluation2['total_score']}%\n";
echo "改進幅度: +" . ($evaluation['total_score'] - $evaluation2['total_score']) . "分\n\n";

$improvements = [];
foreach ($evaluation['details'] as $category => $detail) {
    $old_score = $evaluation2['details'][$category]['score'];
    $new_score = $detail['score'];
    if ($new_score > $old_score) {
        $improvements[] = [
            'category' => $category,
            'improvement' => $new_score - $old_score,
            'name' => [
                'brand_keywords' => '品牌關鍵字整合',
                'color_scheme' => '配色方案應用',
                'professional_terms' => '專業術語使用',
                'target_audience' => '目標受眾相關性',
                'english_grammar' => '英文語法品質'
            ][$category]
        ];
    }
}

echo "主要改進項目:\n";
foreach ($improvements as $improvement) {
    echo "  ✅ {$improvement['name']}: +{$improvement['improvement']} 分\n";
}

echo "\n品質評估總結:\n";
if ($evaluation['total_score'] >= 85) {
    echo "🎉 優秀！提示詞品質達到專業級標準\n";
    echo "  ✅ 品牌個性化充分\n";
    echo "  ✅ 配色方案整合完整\n";
    echo "  ✅ 專業術語使用恰當\n";
    echo "  ✅ 目標受眾針對性強\n";
} elseif ($evaluation['total_score'] >= 70) {
    echo "👍 良好！提示詞品質符合標準\n";
    echo "建議進一步優化低分項目以達到專業級水準\n";
} else {
    echo "⚠️  需要改進！提示詞品質有待提升\n";
    echo "建議重新檢查品牌資料整合和專業術語使用\n";
}

echo "\n使用說明:\n";
echo "1. 本工具評估提示詞的品牌整合度和專業水準\n";
echo "2. 評分標準包含品牌關鍵字、配色方案、專業術語、目標受眾、英文語法\n";
echo "3. 85分以上為專業級，70分以上為標準級\n";
echo "4. 可用於比較不同提示詞的品質差異\n";

echo "\n測試完成！\n";