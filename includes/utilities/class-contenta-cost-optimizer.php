<?php
/**
 * Contenta 成本優化工具
 * 
 * 基於 OpenAI 2025年最新定價模型的智能成本優化系統
 * 
 * @package Contenta
 * @version 1.0.0
 */

// 在 CLI 環境中不需要 ABSPATH 檢查
// if (!defined('ABSPATH')) {
//     exit;
// }

class Contenta_Cost_Optimizer 
{
    /**
     * AI 模型定價表 (美元/1M tokens)
     */
    const MODEL_PRICING = [
        // OpenAI 模型
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4o' => ['input' => 5.00, 'output' => 15.00],
        'gpt-4' => ['input' => 30.00, 'output' => 60.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        
        // Gemini 模型 (🏆 最佳性價比)
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash' => ['input' => 0.35, 'output' => 1.05],
        
        // 圖片生成
        'dall-e-3' => [
            '1024x1024' => ['standard' => 0.040, 'hd' => 0.080],
            '1792x1024' => ['standard' => 0.080, 'hd' => 0.120],
            '1024x1792' => ['standard' => 0.080, 'hd' => 0.120]
        ],
        'ideogram' => [
            'turbo' => 0.025,     // 🏆 最具成本效益
            'standard' => 0.080   // 高品質但較貴
        ],
        'imagen-3' => ['per_image' => 0.030] // 比 DALL-E 3 便宜 25%
    ];
    
    /**
     * 批次 API 折扣率
     */
    const BATCH_API_DISCOUNT = 0.5; // 50% 折扣
    
    /**
     * 建議最佳模型用於不同任務 (2025年優化版)
     * 
     * @param string $task_type 任務類型
     * @param string $quality_level 品質要求 (basic|standard|premium)
     * @return string 建議的模型
     */
    public static function recommend_model($task_type, $quality_level = 'standard')
    {
        $recommendations = [
            'config_generation' => [
                'basic' => 'gemini-2.5-flash-lite',    // 🏆 最便宜 $0.10
                'standard' => 'gemini-2.5-flash',      // 🏆 平衡性價比 $0.30
                'premium' => 'gemini-1.5-pro'          // 高品質 $1.25
            ],
            'content_generation' => [
                'basic' => 'gemini-2.5-flash-lite',    // 大量文章生成
                'standard' => 'gemini-2.5-flash',      // 標準品質
                'premium' => 'gpt-4o'                   // 最高品質
            ],
            'image_generation' => [
                'basic' => 'ideogram-turbo',            // 🏆 $0.025/圖 最便宜
                'standard' => 'ideogram-turbo',         // 平衡品質與成本
                'premium' => 'dall-e-3'                 // 最高品質
            ],
            'image_analysis' => [
                'basic' => 'gemini-2.5-flash',         // 多模態能力
                'standard' => 'gemini-1.5-pro',        // 更好理解
                'premium' => 'gpt-4o'                   // 最佳分析
            ]
        ];
        
        return $recommendations[$task_type][$quality_level] ?? 'gemini-2.5-flash';
    }
    
    /**
     * 計算 API 調用成本
     * 
     * @param string $model 模型名稱
     * @param int $input_tokens 輸入 tokens
     * @param int $output_tokens 輸出 tokens
     * @param bool $use_batch 是否使用批次 API
     * @return array 成本詳情
     */
    public static function calculate_cost($model, $input_tokens, $output_tokens, $use_batch = false)
    {
        if (!isset(self::MODEL_PRICING[$model])) {
            return ['error' => "未知模型: {$model}"];
        }
        
        $pricing = self::MODEL_PRICING[$model];
        
        $input_cost = ($input_tokens / 1000000) * $pricing['input'];
        $output_cost = ($output_tokens / 1000000) * $pricing['output'];
        $total_cost = $input_cost + $output_cost;
        
        // 批次 API 折扣
        if ($use_batch) {
            $total_cost *= self::BATCH_API_DISCOUNT;
        }
        
        return [
            'model' => $model,
            'input_tokens' => $input_tokens,
            'output_tokens' => $output_tokens,
            'input_cost' => round($input_cost, 6),
            'output_cost' => round($output_cost, 6),
            'total_cost' => round($total_cost, 6),
            'batch_discount' => $use_batch ? '50%' : '0%',
            'currency' => 'USD'
        ];
    }
    
    /**
     * 計算圖片生成成本
     * 
     * @param string $size 圖片尺寸
     * @param string $quality 品質 (standard|hd)
     * @param int $count 數量
     * @return array 成本詳情
     */
    public static function calculate_image_cost($size = '1024x1024', $quality = 'standard', $count = 1)
    {
        $pricing = self::MODEL_PRICING['dall-e-3'];
        
        if (!isset($pricing[$size])) {
            $size = '1024x1024'; // 預設尺寸
        }
        
        $unit_cost = $pricing[$size][$quality] ?? $pricing[$size]['standard'];
        $total_cost = $unit_cost * $count;
        
        return [
            'model' => 'dall-e-3',
            'size' => $size,
            'quality' => $quality,
            'count' => $count,
            'unit_cost' => $unit_cost,
            'total_cost' => round($total_cost, 3),
            'currency' => 'USD'
        ];
    }
    
    /**
     * 優化建議：比較不同模型成本
     * 
     * @param int $input_tokens 輸入 tokens
     * @param int $output_tokens 輸出 tokens
     * @return array 各模型成本比較
     */
    public static function cost_comparison($input_tokens, $output_tokens)
    {
        $models = ['gpt-4o-mini', 'gpt-4o', 'gpt-4', 'gpt-3.5-turbo'];
        $comparison = [];
        
        foreach ($models as $model) {
            $cost = self::calculate_cost($model, $input_tokens, $output_tokens);
            $batch_cost = self::calculate_cost($model, $input_tokens, $output_tokens, true);
            
            $comparison[$model] = [
                'regular' => $cost['total_cost'],
                'batch' => $batch_cost['total_cost'],
                'savings' => round(($cost['total_cost'] - $batch_cost['total_cost']), 6)
            ];
        }
        
        // 排序：最便宜的在前
        uasort($comparison, function($a, $b) {
            return $a['regular'] <=> $b['regular'];
        });
        
        return $comparison;
    }
    
    /**
     * 獲取成本優化建議
     * 
     * @param array $config 目前配置
     * @return array 優化建議
     */
    public static function get_optimization_suggestions($config)
    {
        $suggestions = [];
        
        // 模型優化建議
        $current_model = $config['model'] ?? 'gpt-4';
        if ($current_model === 'gpt-4') {
            $suggestions[] = [
                'type' => 'model_switch',
                'message' => '建議使用 gpt-4o-mini 替代 gpt-4，可節省 95% 成本',
                'current' => $current_model,
                'recommended' => 'gpt-4o-mini',
                'savings' => '95%'
            ];
        }
        
        // 批次 API 建議
        if (!($config['use_batch'] ?? false)) {
            $suggestions[] = [
                'type' => 'batch_api',
                'message' => '啟用批次 API 可享 50% 折扣',
                'savings' => '50%'
            ];
        }
        
        // 圖片品質建議
        $image_quality = $config['image_quality'] ?? 'hd';
        if ($image_quality === 'hd') {
            $suggestions[] = [
                'type' => 'image_quality',
                'message' => '使用標準品質圖片可節省 50% 圖片生成成本',
                'current' => 'hd',
                'recommended' => 'standard',
                'savings' => '50%'
            ];
        }
        
        // 視覺反饋建議
        if ($config['enable_visual_feedback'] ?? false) {
            $suggestions[] = [
                'type' => 'visual_feedback',
                'message' => '關閉視覺反饋功能可節省 80% tokens',
                'savings' => '80%'
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * 預估完整網站部署成本
     * 
     * @param array $config 配置
     * @return array 成本預估
     */
    public static function estimate_deployment_cost($config)
    {
        $model = $config['model'] ?? 'gpt-4o-mini';
        $use_batch = $config['use_batch'] ?? false;
        $image_quality = $config['image_quality'] ?? 'standard';
        $enable_visual_feedback = $config['enable_visual_feedback'] ?? false;
        
        // 步驟08：配置生成
        $step08_cost = self::calculate_cost($model, 5000, 8000, false);
        
        // 步驟09：文章生成 (6篇)
        $step09_cost = self::calculate_cost($model, 12000, 12000, $use_batch);
        
        // 步驟10：圖片生成 (13張)
        $step10_cost = self::calculate_image_cost('1024x1024', $image_quality, 13);
        
        // 視覺反饋 (如果啟用)
        $visual_feedback_cost = ['total_cost' => 0];
        if ($enable_visual_feedback) {
            $visual_feedback_cost = self::calculate_cost('gpt-4o', 3000, 1500, false);
        }
        
        $total_cost = $step08_cost['total_cost'] + 
                     $step09_cost['total_cost'] + 
                     $step10_cost['total_cost'] + 
                     $visual_feedback_cost['total_cost'];
        
        return [
            'breakdown' => [
                'step08_config' => $step08_cost,
                'step09_content' => $step09_cost,
                'step10_images' => $step10_cost,
                'visual_feedback' => $visual_feedback_cost
            ],
            'total_cost' => round($total_cost, 4),
            'currency' => 'USD',
            'config' => [
                'model' => $model,
                'batch_api' => $use_batch,
                'image_quality' => $image_quality,
                'visual_feedback' => $enable_visual_feedback
            ]
        ];
    }
    
    /**
     * 生成成本報告
     * 
     * @param array $usage_data 實際使用數據
     * @return string 格式化報告
     */
    public static function generate_cost_report($usage_data)
    {
        $report = "# Contenta AI 成本報告\n\n";
        $report .= "生成時間: " . date('Y-m-d H:i:s') . "\n\n";
        
        $total_cost = 0;
        
        foreach ($usage_data as $step => $data) {
            $report .= "## {$step}\n";
            $report .= "- 模型: {$data['model']}\n";
            $report .= "- 輸入 Tokens: " . number_format($data['input_tokens']) . "\n";
            $report .= "- 輸出 Tokens: " . number_format($data['output_tokens']) . "\n";
            $report .= "- 成本: $" . number_format($data['cost'], 4) . "\n\n";
            
            $total_cost += $data['cost'];
        }
        
        $report .= "## 總成本\n";
        $report .= "**$" . number_format($total_cost, 4) . " USD**\n\n";
        
        return $report;
    }
}