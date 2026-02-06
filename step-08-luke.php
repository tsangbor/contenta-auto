<?php
/**
 * 步驟 08 (Luke模式): AI 生成網站配置
 * 
 * 核心職責：呼叫 AI，生成 site-config.json 和 article-prompts.json。
 * 
 * 執行工作流：
 * 1. 讀取用戶提供的品牌資料。
 * 2. 組合一個完整的提示詞 (Prompt)，包含品牌資訊和輸出格式要求。
 * 3. 呼叫大型語言模型 (如 GPT-4o, Gemini) API。
 * 4. 解析 AI 回應，並將結果分別儲存為 site-config.json 和 article-prompts.json。
 * 
 * 注意：此步驟與 bt 模式完全共用邏輯，理論上不需要獨立的 -luke.php 檔案。
 * 除非未來需要針對 Luke 模式的 AI 配置做特殊調整，否則建議直接使用 step-08.php。
 */

// 為了保持架構一致性，此檔案直接引入共通的 step-08.php 邏輯。
require __DIR__ . '/step-08.php';
