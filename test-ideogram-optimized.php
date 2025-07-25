<?php
// 使用優化後的提示詞測試 Ideogram
require_once "config-manager.php";

$config = ConfigManager::getInstance();
$ideogram_key = $config->get("api_credentials.ideogram.api_key");

if (empty($ideogram_key)) {
    die("請配置 Ideogram API 金鑰\n");
}

// 優化的 Humaaans 風格提示詞
$prompt = "Flat vector illustration, humaaans style characters, simple geometric shapes, minimal design, professional team collaboration scene, abstract office environment, solid color blocks, no gradients, clean lines, modern flat design aesthetic. Color palette: #2563EB blue, #38BDF8 light blue, #0F172A dark accents. Characters with oval heads, simple facial features, geometric body shapes. White background with geometric patterns. No text, no realistic details, pure flat design illustration.";

echo "測試優化後的提示詞...\n";
// 這裡添加 Ideogram API 調用代碼
?>