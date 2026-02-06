<?php
/**
 * 步驟 10 (Luke模式): AI 圖片生成
 * 
 * 核心職責：根據 image-prompts.json 的指示，呼叫 AI 服務生成所有網站所需的圖片。
 * 
 * 執行工作流：
 * 1. 讀取 image-prompts.json 檔案。
 * 2. 迴圈處理每個圖片生成任務。
 * 3. 呼叫指定的 AI 圖片生成服務 (如 DALL-E 3, Ideogram)。
 * 4. 將生成的圖片下載並儲存到本地的 images 目錄。
 * 5. 建立 image-mapping.json，記錄佔位符與實際生成圖片檔案的對應關係。
 * 
 * 注意：此步驟為本地檔案操作和對外 API 呼叫，與 bt 模式完全共用邏輯，理論上不需要獨立的 -luke.php 檔案。
 */

// 為了保持架構一致性，此檔案直接引入共通的 step-10.php 邏輯。
require __DIR__ . '/step-10.php';
