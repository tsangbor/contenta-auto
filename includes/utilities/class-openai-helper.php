<?php
/**
 * OpenAI API 輔助類別
 * 處理 OpenAI API 的版本兼容性問題
 *
 * 主要功能：
 * 1. 自動判斷新舊模型
 * 2. 使用正確的 token 限制參數（max_tokens vs max_completion_tokens）
 * 3. 統一的 API 請求資料建構
 *
 * @since 2026-01-31
 * @version 1.0
 */

class OpenAIHelper
{
    /**
     * 新一代模型清單
     * 這些模型使用 max_completion_tokens 而不是 max_tokens
     */
    private static $new_generation_models = [
        'gpt-5',        // GPT-5 系列（gpt-5, gpt-5-mini, gpt-5-nano 等）
        'gpt-4.1',      // GPT-4.1 系列（gpt-4.1, gpt-4.1-mini, gpt-4.1-nano 等）
        'o3',           // o3 推理模型系列
        'o4',           // o4 推理模型系列
        'gpt-realtime', // 即時模型
        'gpt-audio',    // 音訊模型
    ];

    /**
     * 不支援自訂 temperature 的模型清單
     * 這些模型只支援預設 temperature = 1
     */
    private static $fixed_temperature_models = [
        'gpt-5',        // 整個 GPT-5 系列（gpt-5, gpt-5-mini, gpt-5-nano 等）
        'gpt-4.1',      // 整個 GPT-4.1 系列（gpt-4.1, gpt-4.1-mini, gpt-4.1-nano 等）
        'o3',           // o3 推理模型系列
        'o4',           // o4 推理模型系列
        'o1',           // o1 推理模型系列
    ];

    /**
     * 判斷是否為新一代 OpenAI 模型
     *
     * @param string $model 模型名稱
     * @return bool 是否為新一代模型
     */
    public static function isNewGenerationModel($model)
    {
        if (empty($model)) {
            return false;
        }

        // 檢查是否匹配新一代模型前綴
        foreach (self::$new_generation_models as $prefix) {
            if (strpos($model, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判斷模型是否支援自訂 temperature 參數
     *
     * @param string $model 模型名稱
     * @return bool 是否支援自訂 temperature
     */
    public static function supportsCustomTemperature($model)
    {
        if (empty($model)) {
            return true;  // 預設支援
        }

        // 檢查是否在固定 temperature 的模型清單中
        foreach (self::$fixed_temperature_models as $fixed_model) {
            if (strpos($model, $fixed_model) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 建立 OpenAI API 請求資料
     * 自動處理新舊模型的參數差異
     *
     * @param string $model 模型名稱
     * @param array $messages 訊息陣列 [['role' => 'user', 'content' => '...']]
     * @param array $options 選項參數
     *   - max_tokens: token 限制（預設不限制）
     *   - temperature: 溫度參數（預設 0.7）
     *   - top_p: top_p 參數（可選）
     *   - frequency_penalty: 頻率懲罰（可選）
     *   - presence_penalty: 存在懲罰（可選）
     * @return array API 請求資料
     */
    public static function buildRequestData($model, $messages, $options = [])
    {
        // 基礎請求結構
        $data = [
            'model' => $model,
            'messages' => $messages,
        ];

        // 只在模型支援時設定溫度參數
        if (self::supportsCustomTemperature($model)) {
            $data['temperature'] = $options['temperature'] ?? 0.7;
        }
        // 否則使用預設值（不設定，讓 API 使用預設值 1）

        // 智慧選擇 token 限制參數
        if (isset($options['max_tokens']) && $options['max_tokens'] > 0) {
            $is_new_model = self::isNewGenerationModel($model);

            if ($is_new_model) {
                // 新模型使用 max_completion_tokens
                $data['max_completion_tokens'] = $options['max_tokens'];
            } else {
                // 舊模型使用 max_tokens
                $data['max_tokens'] = $options['max_tokens'];
            }
        }

        // 其他可選參數
        if (isset($options['top_p'])) {
            $data['top_p'] = $options['top_p'];
        }

        if (isset($options['frequency_penalty'])) {
            $data['frequency_penalty'] = $options['frequency_penalty'];
        }

        if (isset($options['presence_penalty'])) {
            $data['presence_penalty'] = $options['presence_penalty'];
        }

        return $data;
    }

    /**
     * 取得參數名稱（用於除錯）
     *
     * @param string $model 模型名稱
     * @return string 'max_tokens' 或 'max_completion_tokens'
     */
    public static function getTokenLimitParamName($model)
    {
        return self::isNewGenerationModel($model) ? 'max_completion_tokens' : 'max_tokens';
    }

    /**
     * 驗證模型名稱格式
     *
     * @param string $model 模型名稱
     * @return bool 是否有效
     */
    public static function isValidModelName($model)
    {
        if (empty($model) || !is_string($model)) {
            return false;
        }

        // 基本格式檢查：應該以 gpt 或 o 開頭
        return (strpos($model, 'gpt') === 0 || strpos($model, 'o') === 0);
    }

    /**
     * 取得模型系列名稱（用於日誌）
     *
     * @param string $model 模型名稱
     * @return string 模型系列（如 'GPT-5', 'GPT-4.1', 'o3'）
     */
    public static function getModelFamily($model)
    {
        if (strpos($model, 'gpt-5') === 0) {
            return 'GPT-5';
        } elseif (strpos($model, 'gpt-4.1') === 0) {
            return 'GPT-4.1';
        } elseif (strpos($model, 'gpt-4o') === 0) {
            return 'GPT-4o';
        } elseif (strpos($model, 'gpt-4') === 0) {
            return 'GPT-4';
        } elseif (strpos($model, 'gpt-3.5') === 0) {
            return 'GPT-3.5';
        } elseif (strpos($model, 'o4') === 0) {
            return 'o4';
        } elseif (strpos($model, 'o3') === 0) {
            return 'o3';
        } elseif (strpos($model, 'o1') === 0) {
            return 'o1';
        } else {
            return 'Unknown';
        }
    }
}
