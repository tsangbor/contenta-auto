<?php
/**
 * 步驟08獨立測試腳本
 * 測試 AI 網站配置檔案生成功能
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

echo "=== 步驟08 AI 配置生成測試 ===\n\n";

// 模擬部署器日誌類別
class MockDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
}

try {
    // 載入配置
    $config = ConfigManager::getInstance();
    $deployer = new MockDeployer();
    
    // 模擬 job_id (使用時間戳)
    $job_id = date('ymdHi') . '-TEST';
    echo "測試 Job ID: {$job_id}\n\n";
    
    // 檢查是否有現有的測試資料
    $data_dir = DEPLOY_BASE_PATH . '/data';
    if (!is_dir($data_dir)) {
        throw new Exception("❌ data 目錄不存在: {$data_dir}");
    }
    
    $data_files = scandir($data_dir);
    $data_files = array_filter($data_files, function($file) {
        return !in_array($file, ['.', '..']);
    });
    
    if (empty($data_files)) {
        throw new Exception("❌ data 目錄中沒有測試資料檔案");
    }
    
    echo "📁 發現資料檔案:\n";
    foreach ($data_files as $file) {
        echo "  - {$file}\n";
    }
    echo "\n";
    
    // 建立模擬的工作目錄（統一使用 temp）
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    
    // 建立必要目錄結構
    $subdirs = ['config', 'scripts', 'json', 'images', 'logs'];
    if (!is_dir($work_dir)) {
        mkdir($work_dir, 0755, true);
    }
    foreach ($subdirs as $subdir) {
        $subdir_path = $work_dir . '/' . $subdir;
        if (!is_dir($subdir_path)) {
            mkdir($subdir_path, 0755, true);
        }
    }
    
    // 建立模擬的 processed_data.json
    $processed_data = [
        'confirmed_data' => [
            'domain' => 'test-ai-generation.tw',
            'website_name' => 'AI 測試網站',
            'website_description' => '這是用於測試 AI 配置生成的網站',
            'user_email' => 'test@example.com'
        ],
        'work_dir' => $work_dir
    ];
    
    file_put_contents($work_dir . '/config/processed_data.json', 
        json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo "✅ 測試環境準備完成\n";
    echo "統一工作目錄: {$work_dir}\n";
    echo "目錄結構: " . implode(', ', $subdirs) . "\n\n";
    
    // 檢查 AI API 設定
    $openai_config = [
        'api_key' => $config->get('api_credentials.openai.api_key'),
        'model' => $config->get('api_credentials.openai.model') ?: 'gpt-4',
        'image_model' => $config->get('api_credentials.openai.image_model') ?: 'gpt-4o'
    ];
    
    $gemini_config = [
        'api_key' => $config->get('api_credentials.gemini.api_key'),
        'model' => $config->get('api_credentials.gemini.model') ?: 'gemini-1.5-flash',
        'image_model' => $config->get('api_credentials.gemini.image_model') ?: 'gemini-2.0-flash-preview-image-generation'
    ];
    
    echo "🔑 API 憑證檢查:\n";
    echo "OpenAI: " . ($openai_config['api_key'] ? "✅ 已設定 (模型: {$openai_config['model']})" : "❌ 未設定") . "\n";
    echo "Gemini: " . ($gemini_config['api_key'] ? "✅ 已設定 (模型: {$gemini_config['model']})" : "❌ 未設定") . "\n\n";
    
    // 預設優先使用 OpenAI
    if (!empty($openai_config['api_key'])) {
        echo "📝 將使用 OpenAI ({$openai_config['model']})\n";
    } elseif (!empty($gemini_config['api_key'])) {
        echo "📝 將使用 Gemini ({$gemini_config['model']})\n";
    } else {
        throw new Exception("❌ 未設定任何 AI API 憑證，請檢查 config/deploy-config.json");
    }
    echo "\n";
    
    // 詢問是否繼續執行
    echo "⚠️  注意: 此測試將呼叫 AI API，可能產生費用\n";
    echo "⏱️  API 呼叫超時設定已增加到 5 分鐘\n";
    echo "是否要繼續執行測試？ (y/N): ";
    $handle = fopen("php://stdin", "r");
    $response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($response) !== 'y') {
        echo "測試已取消\n";
        exit(0);
    }
    
    echo "\n🚀 開始執行步驟08...\n\n";
    
    // 執行步驟08
    ob_start();
    $result = include 'step-08.php';
    $output = ob_get_clean();
    
    echo $output;
    
    // 檢查結果
    if ($result && isset($result['status'])) {
        if ($result['status'] === 'user_abort') {
            echo "\n⏹️  步驟08已中止 - 品牌配置需要調整\n";
            echo "訊息: " . ($result['message'] ?? '用戶選擇中止執行') . "\n\n";
            
            echo "💡 調整建議:\n";
            echo "1. 修改 data/ 目錄中的用戶資料檔案\n";
            echo "2. 調整 json/ 目錄中的參考模板\n";
            echo "3. 重新執行步驟08直到品牌配置符合預期\n\n";
            
            echo "📁 已生成的檔案仍保留在: {$work_dir}/json/\n";
            echo "可以查看 AI 的分析結果作為調整參考\n\n";
            
            // 清理選項
            echo "🗑️  是否要清理此次生成的檔案？ (y/N): ";
            $handle = fopen("php://stdin", "r");
            $cleanup_response = trim(fgets($handle));
            fclose($handle);
            
            if (strtolower($cleanup_response) === 'y') {
                function deleteDirectory($dir) {
                    if (!is_dir($dir)) return false;
                    $files = array_diff(scandir($dir), array('.', '..'));
                    foreach ($files as $file) {
                        $path = $dir . DIRECTORY_SEPARATOR . $file;
                        is_dir($path) ? deleteDirectory($path) : unlink($path);
                    }
                    return rmdir($dir);
                }
                
                deleteDirectory($work_dir);
                echo "✅ 測試檔案已清理\n";
            } else {
                echo "📁 測試檔案保留在: {$work_dir}\n";
            }
            
            exit(0); // 中止後續步驟
            
        } elseif ($result['status'] === 'success') {
            echo "\n✅ 步驟08執行成功並已確認品牌配置！\n\n";
            
            // 檢查生成的檔案
            $expected_files = [
                'site-config.json',
                'article-prompts.json', 
                'image-prompts.json',
                'ai_prompt.txt',
                'ai_response.txt',
                'generation_info.json'
            ];
            
            echo "📋 檢查生成檔案:\n";
            foreach ($expected_files as $filename) {
                $file_path = $work_dir . '/json/' . $filename;
                if (file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    echo "✅ {$filename} ({$file_size} bytes)\n";
                } else {
                    echo "❌ {$filename} (不存在)\n";
                }
            }
            
            // 顯示生成資訊
            $generation_info_path = $work_dir . '/json/generation_info.json';
            if (file_exists($generation_info_path)) {
                $generation_info = json_decode(file_get_contents($generation_info_path), true);
                echo "\n📊 步驟08生成統計:\n";
                echo "AI 服務: " . ($generation_info['ai_service'] ?? 'unknown') . "\n";
                echo "AI 模型: " . ($generation_info['ai_model'] ?? 'unknown') . "\n";
                echo "資料檔案數: " . ($generation_info['data_files_count'] ?? 0) . "\n";
                echo "生成檔案數: " . count($generation_info['generated_files'] ?? []) . "\n";
                echo "提示詞長度: " . ($generation_info['prompt_length'] ?? 0) . " 字元\n";
                echo "回應長度: " . ($generation_info['response_length'] ?? 0) . " 字元\n";
            }
            
            // 檢查是否可以繼續執行步驟09
            $site_config_path = $work_dir . '/json/site-config.json';
            if (file_exists($site_config_path)) {
                echo "\n🔗 準備執行步驟09：頁面生成與 AI 文字替換\n";
                echo "是否要繼續執行步驟09？ (y/N): ";
                $handle = fopen("php://stdin", "r");
                $step09_response = trim(fgets($handle));
                fclose($handle);
                
                if (strtolower($step09_response) === 'y') {
                    echo "\n🚀 開始執行步驟09...\n\n";
                    
                    // 檢查必要的目錄
                    $template_dir = DEPLOY_BASE_PATH . '/template/container';
                    if (!is_dir($template_dir)) {
                        echo "❌ template/container 目錄不存在: {$template_dir}\n";
                        echo "請確保有容器 JSON 檔案才能進行頁面生成\n";
                    } else {
                        // 確保 layout 目錄存在
                        $layout_dir = $work_dir . '/layout';
                        if (!is_dir($layout_dir)) {
                            mkdir($layout_dir, 0755, true);
                        }
                        
                        try {
                            // 執行步驟09
                            ob_start();
                            $step09_result = include 'step-09.php';
                            $step09_output = ob_get_clean();
                            
                            echo $step09_output;
                            
                            // 檢查步驟09結果
                            if ($step09_result && isset($step09_result['status'])) {
                                if ($step09_result['status'] === 'success') {
                                    echo "\n✅ 步驟09執行成功！\n\n";
                                    
                                    // 檢查生成的頁面檔案
                                    $layout_files = scandir($layout_dir);
                                    $generated_files = array_filter($layout_files, function($file) {
                                        return $file !== '.' && $file !== '..' && preg_match('/\.json$/', $file);
                                    });
                                    
                                    echo "📋 檢查生成的頁面檔案:\n";
                                    foreach ($generated_files as $filename) {
                                        $file_path = $layout_dir . '/' . $filename;
                                        $file_size = filesize($file_path);
                                        echo "✅ {$filename} ({$file_size} bytes)\n";
                                    }
                                    
                                    echo "\n📊 步驟09生成統計:\n";
                                    echo "頁面數量: " . ($step09_result['pages_generated'] ?? 0) . "\n";
                                    echo "儲存位置: " . ($step09_result['layout_dir'] ?? 'unknown') . "\n";
                                    
                                    echo "\n💡 完整測試檔案位置:\n";
                                    echo "步驟08輸出: {$work_dir}/json/\n";
                                    echo "步驟09輸出: {$work_dir}/layout/\n";
                                    
                                    // 檢查是否可以繼續執行步驟10
                                    $image_prompts_path = $work_dir . '/json/image-prompts.json';
                                    if (file_exists($image_prompts_path)) {
                                        echo "\n🎨 準備執行步驟10：AI 圖片生成與路徑替換\n";
                                        echo "是否要繼續執行步驟10？ (y/N): ";
                                        $handle = fopen("php://stdin", "r");
                                        $step10_response = trim(fgets($handle));
                                        fclose($handle);
                                        
                                        if (strtolower($step10_response) === 'y') {
                                            echo "\n🚀 開始執行步驟10...\n\n";
                                            
                                            try {
                                                // 執行步驟10
                                                ob_start();
                                                $step10_result = include 'step-10.php';
                                                $step10_output = ob_get_clean();
                                                
                                                echo $step10_output;
                                                
                                                // 檢查步驟10結果
                                                if ($step10_result && isset($step10_result['status'])) {
                                                    if ($step10_result['status'] === 'success') {
                                                        echo "\n✅ 步驟10執行成功！\n\n";
                                                        
                                                        // 檢查生成的圖片檔案
                                                        $images_dir = $work_dir . '/images';
                                                        if (is_dir($images_dir)) {
                                                            $image_files = scandir($images_dir);
                                                            $generated_images = array_filter($image_files, function($file) {
                                                                return $file !== '.' && $file !== '..' && preg_match('/\.(png|jpg|jpeg)$/', $file);
                                                            });
                                                            
                                                            echo "📋 檢查生成的圖片檔案:\n";
                                                            foreach ($generated_images as $filename) {
                                                                $file_path = $images_dir . '/' . $filename;
                                                                $file_size = filesize($file_path);
                                                                echo "✅ {$filename} ({$file_size} bytes)\n";
                                                            }
                                                        }
                                                        
                                                        echo "\n📊 步驟10生成統計:\n";
                                                        echo "生成圖片數: " . ($step10_result['generated_count'] ?? 0) . "\n";
                                                        echo "圖片目錄: " . ($step10_result['images_dir'] ?? 'unknown') . "\n";
                                                        
                                                        echo "\n💡 完整 AI 工作流程已完成 (8→9→10):\n";
                                                        echo "步驟08: AI 配置生成 → {$work_dir}/json/\n";
                                                        echo "步驟09: 頁面生成與文字替換 → {$work_dir}/layout/\n";
                                                        echo "步驟10: 圖片生成與路徑替換 → {$work_dir}/images/\n";
                                                        
                                                    } else {
                                                        echo "\n❌ 步驟10執行失敗\n";
                                                        echo "錯誤訊息: " . ($step10_result['message'] ?? '未知錯誤') . "\n";
                                                    }
                                                } else {
                                                    echo "\n❌ 步驟10執行異常，無回傳結果\n";
                                                }
                                            } catch (Exception $e) {
                                                echo "\n❌ 步驟10執行發生錯誤: " . $e->getMessage() . "\n";
                                            }
                                        } else {
                                            echo "\n⏭️  跳過步驟10\n";
                                        }
                                    } else {
                                        echo "\n❌ image-prompts.json 不存在，無法執行步驟10\n";
                                    }
                                    
                                } else {
                                    echo "\n❌ 步驟09執行失敗\n";
                                    echo "錯誤訊息: " . ($step09_result['message'] ?? '未知錯誤') . "\n";
                                }
                            } else {
                                echo "\n❌ 步驟09執行異常，無回傳結果\n";
                            }
                        } catch (Exception $e) {
                            echo "\n❌ 步驟09執行發生錯誤: " . $e->getMessage() . "\n";
                        }
                    }
                } else {
                    echo "\n⏭️  跳過步驟09\n";
                    echo "\n💡 步驟08檔案已儲存到: {$work_dir}/json/\n";
                    echo "你可以查看以下檔案:\n";
                    foreach ($expected_files as $filename) {
                        echo "  - {$filename}\n";
                    }
                }
            } else {
                echo "\n❌ site-config.json 不存在，無法執行步驟09\n";
            }
            
        } else {
            echo "\n❌ 步驟08執行失敗\n";
            echo "錯誤訊息: " . ($result['message'] ?? '未知錯誤') . "\n";
            
            // 檢查錯誤檔案
            $error_file = $work_dir . '/json/generation_error.json';
            if (file_exists($error_file)) {
                $error_info = json_decode(file_get_contents($error_file), true);
                echo "詳細錯誤: " . ($error_info['error_message'] ?? '無詳細資訊') . "\n";
            }
        }
    } else {
        echo "\n❌ 步驟08執行異常，無回傳結果\n";
    }
    
    // 清理選項
    echo "\n🗑️  是否要清理測試檔案？ (y/N): ";
    $handle = fopen("php://stdin", "r");
    $cleanup_response = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($cleanup_response) === 'y') {
        // 遞迴刪除目錄
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return false;
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            return rmdir($dir);
        }
        
        deleteDirectory($work_dir);
        echo "✅ 測試檔案已清理\n";
    } else {
        echo "📁 測試檔案保留在: {$work_dir}\n";
    }
    
} catch (Exception $e) {
    echo "❌ 測試過程發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== 步驟08測試完成 ===\n";

// 使用說明
echo "\n💡 完整測試使用說明:\n";
echo "1. 確保 data/ 目錄中有測試資料檔案\n";
echo "2. 確保 config/deploy-config.json 中設定了 AI API 憑證\n";
echo "3. 確保 template/container/ 目錄中有容器 JSON 檔案（步驟09需要）\n";
echo "4. 執行: php test-step-08.php\n";
echo "5. 步驟08完成後確認 AI 生成的品牌配置\n";
echo "6. 如配置正確，選擇是否繼續執行步驟09\n";
echo "7. 步驟09完成後選擇是否繼續執行步驟10\n";
echo "8. 查看生成的檔案以驗證結果\n\n";

echo "🔧 調整說明:\n";
echo "- 步驟08 AI 提示詞: 編輯 step-08.php 中的 getAIPromptTemplate() 函數\n";
echo "- 步驟09 AI 提示詞: 編輯 step-09.php 中的 getTextReplacementPrompt() 函數\n";
echo "- 步驟10 AI 設定: 檢查 config/deploy-config.json 中的 OpenAI/Gemini API 設定\n\n";

echo "📁 輸出檔案說明:\n";
echo "步驟08: {工作目錄}/json/ - 網站配置檔案\n";
echo "步驟09: {工作目錄}/layout/ - 頁面 JSON 檔案\n";
echo "步驟10: {工作目錄}/images/ - 生成的圖片檔案\n";