#!/bin/bash
# 檔案清理腳本 - 2025-07-02 04:13:53

echo '🧹 開始清理舊檔案...'

# 建立備份目錄
mkdir -p backup_20250702_041353

# 清理舊版本檔案
mv step-01-old.php backup_20250702_041353/ 2>/dev/null
mv step-02-old.php backup_20250702_041353/ 2>/dev/null
mv step-03-old.php backup_20250702_041353/ 2>/dev/null
mv step-04-old.php backup_20250702_041353/ 2>/dev/null
mv step-05-old.php backup_20250702_041353/ 2>/dev/null
mv step-06-old.php backup_20250702_041353/ 2>/dev/null
mv step-07-old.php backup_20250702_041353/ 2>/dev/null
mv step-08-old.php backup_20250702_041353/ 2>/dev/null
mv step-08-simple.php backup_20250702_041353/ 2>/dev/null
mv step-09-new.php backup_20250702_041353/ 2>/dev/null
mv step-09-5-backup.php backup_20250702_041353/ 2>/dev/null
mv step-10-new.php backup_20250702_041353/ 2>/dev/null
mv step-10-optimized.php backup_20250702_041353/ 2>/dev/null
mv step-10-universal.php backup_20250702_041353/ 2>/dev/null
mv step-10-gpt4o-enhanced.php backup_20250702_041353/ 2>/dev/null
mv continue-step-09.php backup_20250702_041353/ 2>/dev/null

# 清理測試腳本
mv cleanup-test-files.php backup_20250702_041353/ 2>/dev/null
mv debug-ai-response.php backup_20250702_041353/ 2>/dev/null
mv fix-gemini-to-openai.php backup_20250702_041353/ 2>/dev/null
mv test-ai-parameter.php backup_20250702_041353/ 2>/dev/null
mv test-comprehensive-workflow.php backup_20250702_041353/ 2>/dev/null
mv test-full-integration.php backup_20250702_041353/ 2>/dev/null
mv test-full-pipeline.php backup_20250702_041353/ 2>/dev/null
mv test-gemini-api.php backup_20250702_041353/ 2>/dev/null
mv test-gpt4o-enhanced.php backup_20250702_041353/ 2>/dev/null
mv test-image-generation-single.php backup_20250702_041353/ 2>/dev/null
mv test-improved-detection-functions.php backup_20250702_041353/ 2>/dev/null
mv test-improved-detection.php backup_20250702_041353/ 2>/dev/null
mv test-logo-fix.php backup_20250702_041353/ 2>/dev/null
mv test-logo-generation.php backup_20250702_041353/ 2>/dev/null
mv test-optimizations.php backup_20250702_041353/ 2>/dev/null
mv test-personalization-optimized.php backup_20250702_041353/ 2>/dev/null
mv test-personalization-validation.php backup_20250702_041353/ 2>/dev/null
mv test-phase2-day5-simple.php backup_20250702_041353/ 2>/dev/null
mv test-placeholder-detection.php backup_20250702_041353/ 2>/dev/null
mv test-quality-benchmarks.php backup_20250702_041353/ 2>/dev/null
mv test-single-image.php backup_20250702_041353/ 2>/dev/null
mv test-size-conversion.php backup_20250702_041353/ 2>/dev/null
mv test-ssh-connection.php backup_20250702_041353/ 2>/dev/null
mv test-step-08.php backup_20250702_041353/ 2>/dev/null
mv test-step-09-5-ai.php backup_20250702_041353/ 2>/dev/null
mv test-step-09-5.php backup_20250702_041353/ 2>/dev/null
mv test-step-09-debug.php backup_20250702_041353/ 2>/dev/null
mv test-step-10-auto.php backup_20250702_041353/ 2>/dev/null
mv test-step-10-debug.php backup_20250702_041353/ 2>/dev/null
mv test-step-10-interactive.php backup_20250702_041353/ 2>/dev/null
mv test-step-10-optimized.php backup_20250702_041353/ 2>/dev/null
mv verify-step-10-results.php backup_20250702_041353/ 2>/dev/null

echo '✅ 清理完成！檔案已移至 backup_20250702_041353 目錄'
