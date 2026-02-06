<?php
/**
 * 步驟 12 (Luke模式): 圖片路徑替換
 * 
 * 核心職責：將所有頁面和設定檔中的圖片佔位符，替換為 step-11 中上傳到 WordPress 後的真實 URL。
 * 
 * 執行工作流：
 * 1. 讀取 image-mapping.json 檔案。
 * 2. 遍歷所有 *-ai.json 頁面模板檔案。
 * 3. 在每個檔案中，搜尋圖片佔位符 (如 {{HERO_BG}})，並將其替換為對應的 WordPress 圖片 URL。
 * 4. 同時更新圖片對象中的 ID，以利於 Elementor 正確識別。
 * 
 * 注意：此步驟為本地檔案操作，與 bt 模式完全共用邏輯，理論上不需要獨立的 -luke.php 檔案。
 */

// 為了保持架構一致性，此檔案直接引入共通的 step-12.php 邏輯。
require __DIR__ . '/step-12.php';
