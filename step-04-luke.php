<?php
/**
 * 步驟 04: SSL 憑證設置 (Luke API 模式 - 略過)
 * Luke API 在步驟 03 中已自動處理 SSL 憑證設置，此步驟跳過
 */

$deployer->log("=== 步驟 04: SSL 憑證設置 (Luke API 模式 - 略過) ===");
$deployer->log("Luke API 已在步驟 03 中自動處理 SSL 憑證設置，跳過此步驟");

return ['status' => 'skipped', 'reason' => 'handled_by_luke_api'];