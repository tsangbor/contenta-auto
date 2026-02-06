<?php
/**
 * 步驟 09 (Luke模式): 頁面組裝與 AI 文案填充
 * 
 * 核心職責：將頁面模板與 AI 生成的文案結合，產生最終的頁面結構 JSON 檔案。
 * 
 * 執行工作流：
 * 1. 根據 site-config.json 的 layout_selection，合併頁面容器。
 * 2. 從合併後的頁面骨架中，提取所有文字佔位符，生成 text-mapping.json。
 * 3. 呼叫 AI，將 text-mapping.json 中的佔位符填充為具體的、符合品牌調性的文案。
 * 4. 將填充好的文案寫回頁面骨架，儲存為 *-ai.json 檔案。
 * 
 * 注意：此步驟為本地檔案操作，與 bt 模式完全共用邏輯，理論上不需要獨立的 -luke.php 檔案。
 */

// 為了保持架構一致性，此檔案直接引入共通的 step-09.php 邏輯。
require __DIR__ . '/step-09.php';
