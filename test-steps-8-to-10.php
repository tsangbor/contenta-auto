<?php
/**
 * 測試步驟 8 到步驟 10 的完整流程 (含步驟 9.5)
 * 
 * 使用方式: php test-steps-8-to-10.php [job_id]
 * 範例: php test-steps-8-to-10.php 2506290730-3450
 * 
 * 會自動讀取 data/{job_id}.json 的真實資料進行測試
 */

// 定義常數
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
}
if (!defined('DEPLOY_CONFIG_PATH')) {
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

// 取得命令列參數
$job_id = $argv[1] ?? null;

if (!$job_id) {
    echo "❌ 請提供 job_id 參數\n";
    echo "使用方式: php test-steps-8-to-10.php [job_id]\n";
    echo "範例: php test-steps-8-to-10.php 2506290730-3450\n";
    exit(1);
}

echo "=== 步驟 8-10 完整測試流程 ===\n";
echo "Job ID: {$job_id}\n\n";

// 驗證 job_id 資料檔案是否存在
$data_file = DEPLOY_BASE_PATH . "/data/{$job_id}.json";
if (!file_exists($data_file)) {
    echo "❌ 找不到資料檔案: {$data_file}\n";
    echo "請確認 job_id 正確且資料檔案存在\n";
    exit(1);
}

// 讀取並驗證資料
$user_data = json_decode(file_get_contents($data_file), true);
if (!$user_data) {
    echo "❌ 資料檔案格式錯誤或無法解析\n";
    exit(1);
}

echo "✅ 成功載入資料檔案: {$data_file}\n";
if (isset($user_data['confirmed_data']['website_name'])) {
    echo "📄 網站名稱: " . $user_data['confirmed_data']['website_name'] . "\n";
}
if (isset($user_data['confirmed_data']['domain'])) {
    echo "🌐 網域: " . $user_data['confirmed_data']['domain'] . "\n";
}
echo "\n";

// 部署器類別
class RealDeployer {
    public $job_id;
    public $work_dir;
    
    public function __construct($job_id) {
        $this->job_id = $job_id;
        $this->work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$level}: {$message}\n";
    }
    
    public function updateProgress($step, $substep = '', $progress = 0) {
        echo "📊 進度更新: 步驟 {$step} - {$substep} ({$progress}%)\n";
    }
}

// 顯示選單
function showMenu() {
    echo "\n請選擇測試選項:\n";
    echo "1. 執行步驟 8 - AI 生成網站配置\n";
    echo "2. 執行步驟 9 - 頁面 JSON 生成與文字替換\n";
    echo "3. 執行步驟 9.5 - 動態圖片需求分析 🆕\n";
    echo "4. 執行步驟 10 - 智能圖片生成\n";
    echo "5. 執行完整流程 (8→9→9.5→10)\n";
    echo "6. 檢查目前狀態\n";
    echo "0. 退出\n";
    echo "\n請輸入選項 (0-6): ";
}

// 初始化真實測試環境
function initializeRealEnvironment($job_id, $user_data) {
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $job_id;
    
    echo "🔧 初始化測試環境...\n";
    
    // 建立必要目錄結構
    $subdirs = ['config', 'scripts', 'json', 'images', 'logs', 'layout'];
    if (!is_dir($work_dir)) {
        mkdir($work_dir, 0755, true);
        echo "✅ 建立工作目錄: {$work_dir}\n";
    }
    
    foreach ($subdirs as $subdir) {
        $subdir_path = $work_dir . '/' . $subdir;
        if (!is_dir($subdir_path)) {
            mkdir($subdir_path, 0755, true);
        }
    }
    
    // 建立 processed_data.json (使用真實的用戶資料)
    $processed_data_path = $work_dir . '/config/processed_data.json';
    file_put_contents($processed_data_path, json_encode($user_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ 建立 processed_data.json\n";
    
    // 複製用戶原始資料到工作目錄
    $original_data_path = $work_dir . '/config/original_data.json';
    copy(DEPLOY_BASE_PATH . "/data/{$job_id}.json", $original_data_path);
    echo "✅ 複製原始資料檔案\n";
    
    echo "📂 建立目錄: " . implode(', ', $subdirs) . "\n";
    echo "📁 工作目錄準備完成\n\n";
    
    return $work_dir;
}

// 檢查檔案狀態
function checkStatus($job_id, $deployer) {
    $work_dir = $deployer->work_dir;
    
    echo "\n📊 目前狀態檢查:\n";
    echo "================\n";
    echo "Job ID: {$job_id}\n";
    echo "工作目錄: {$work_dir}\n\n";
    
    // 檢查步驟 8 的輸出
    $step8_files = [
        'json/site-config.json' => '網站配置',
        'json/article-prompts.json' => '文章提示'
    ];
    
    echo "步驟 8 輸出:\n";
    foreach ($step8_files as $file => $desc) {
        $path = $work_dir . '/' . $file;
        if (file_exists($path)) {
            $size = filesize($path);
            echo "✅ {$desc}: " . round($size/1024, 1) . " KB\n";
        } else {
            echo "❌ {$desc}: 不存在\n";
        }
    }
    
    // 檢查步驟 9 的輸出
    echo "\n步驟 9 輸出:\n";
    $layout_dir = $work_dir . '/layout';
    if (is_dir($layout_dir)) {
        $json_files = glob($layout_dir . '/*-ai.json');
        echo "✅ 生成的頁面 JSON: " . count($json_files) . " 個檔案\n";
        foreach ($json_files as $file) {
            $size = filesize($file);
            echo "   - " . basename($file) . " (" . round($size/1024, 1) . " KB)\n";
        }
    } else {
        echo "❌ Layout 目錄不存在\n";
    }
    
    // 檢查步驟 9.5 的輸出
    echo "\n步驟 9.5 輸出:\n";
    $step9_5_files = [
        'json/image-requirements.json' => '圖片需求分析',
        'json/image-prompts.json' => '動態圖片提示詞'
    ];
    
    foreach ($step9_5_files as $file => $desc) {
        $path = $work_dir . '/' . $file;
        if (file_exists($path)) {
            $size = filesize($path);
            echo "✅ {$desc}: " . round($size/1024, 1) . " KB\n";
            
            // 特別顯示 image-prompts.json 的圖片數量
            if ($file === 'json/image-prompts.json') {
                $content = json_decode(file_get_contents($path), true);
                if ($content) {
                    echo "   📸 包含 " . count($content) . " 個圖片提示詞\n";
                }
            }
        } else {
            echo "❌ {$desc}: 不存在\n";
        }
    }
    
    // 檢查步驟 10 的輸出
    echo "\n步驟 10 輸出:\n";
    $images_dir = $work_dir . '/images';
    if (is_dir($images_dir)) {
        $png_files = glob($images_dir . '/*.png');
        $jpg_files = glob($images_dir . '/*.jpg');
        $total_images = count($png_files) + count($jpg_files);
        echo "✅ 生成的圖片: {$total_images} 個檔案\n";
        
        if ($total_images > 0) {
            $all_images = array_merge($png_files, $jpg_files);
            $display_count = min(5, count($all_images));
            for ($i = 0; $i < $display_count; $i++) {
                $size = filesize($all_images[$i]);
                echo "   - " . basename($all_images[$i]) . " (" . round($size/1024/1024, 2) . " MB)\n";
            }
            if (count($all_images) > 5) {
                echo "   ... 還有 " . (count($all_images) - 5) . " 個圖片\n";
            }
        }
    } else {
        echo "❌ Images 目錄不存在\n";
    }
}

// 執行步驟 8
function runStep8($job_id, $config, $deployer) {
    echo "\n🚀 開始執行步驟 8: AI 生成網站配置\n";
    echo "================================\n";
    
    $script_path = DEPLOY_BASE_PATH . '/step-08.php';
    if (!file_exists($script_path)) {
        echo "❌ 找不到 step-08.php\n";
        return false;
    }
    
    try {
        // 直接包含並執行步驟8邏輯
        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['step-08.php', $job_id];
        $GLOBALS['argv'] = $_SERVER['argv'];
        $GLOBALS['argc'] = $_SERVER['argc'];
        
        // 重新定向輸出以捕獲執行結果
        ob_start();
        include $script_path;
        $output = ob_get_clean();
        
        echo $output;
        
        // 檢查是否成功生成必要檔案
        $site_config_path = $deployer->work_dir . '/json/site-config.json';
        $article_prompts_path = $deployer->work_dir . '/json/article-prompts.json';
        
        if (file_exists($site_config_path) && file_exists($article_prompts_path)) {
            echo "✅ 步驟 8 執行成功\n";
            return true;
        } else {
            echo "❌ 步驟 8 執行失敗 - 缺少必要輸出檔案\n";
            return false;
        }
        
    } catch (Exception $e) {
        echo "❌ 步驟 8 執行異常: " . $e->getMessage() . "\n";
        return false;
    }
}

// 執行步驟 9
function runStep9($job_id, $config, $deployer) {
    echo "\n🚀 開始執行步驟 9: 頁面 JSON 生成與文字替換\n";
    echo "========================================\n";
    
    $script_path = DEPLOY_BASE_PATH . '/step-09.php';
    if (!file_exists($script_path)) {
        echo "❌ 找不到 step-09.php\n";
        return false;
    }
    
    try {
        // 設定命令列參數
        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['step-09.php', $job_id];
        $GLOBALS['argv'] = $_SERVER['argv'];
        $GLOBALS['argc'] = $_SERVER['argc'];
        
        ob_start();
        include $script_path;
        $output = ob_get_clean();
        
        echo $output;
        
        // 檢查是否成功生成頁面檔案
        $layout_dir = $deployer->work_dir . '/layout';
        if (is_dir($layout_dir)) {
            $json_files = glob($layout_dir . '/*-ai.json');
            if (count($json_files) > 0) {
                echo "✅ 步驟 9 執行成功 - 生成 " . count($json_files) . " 個頁面檔案\n";
                return true;
            }
        }
        
        echo "❌ 步驟 9 執行失敗 - 沒有生成頁面檔案\n";
        return false;
        
    } catch (Exception $e) {
        echo "❌ 步驟 9 執行異常: " . $e->getMessage() . "\n";
        return false;
    }
}

// 執行步驟 9.5
function runStep9_5($job_id, $config, $deployer) {
    echo "\n🚀 開始執行步驟 9.5: 動態圖片需求分析\n";
    echo "====================================\n";
    
    $script_path = DEPLOY_BASE_PATH . '/step-09-5.php';
    if (!file_exists($script_path)) {
        echo "❌ 找不到 step-09-5.php\n";
        return false;
    }
    
    try {
        // 設定命令列參數
        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['step-09-5.php', $job_id];
        $GLOBALS['argv'] = $_SERVER['argv'];
        $GLOBALS['argc'] = $_SERVER['argc'];
        
        ob_start();
        include $script_path;
        $output = ob_get_clean();
        
        echo $output;
        
        // 檢查是否成功生成 image-prompts.json
        $image_prompts_path = $deployer->work_dir . '/json/image-prompts.json';
        if (file_exists($image_prompts_path)) {
            $content = json_decode(file_get_contents($image_prompts_path), true);
            if ($content && count($content) > 0) {
                echo "✅ 步驟 9.5 執行成功 - 生成 " . count($content) . " 個圖片提示詞\n";
                return true;
            }
        }
        
        echo "❌ 步驟 9.5 執行失敗 - 沒有生成有效的圖片提示詞\n";
        return false;
        
    } catch (Exception $e) {
        echo "❌ 步驟 9.5 執行異常: " . $e->getMessage() . "\n";
        return false;
    }
}

// 執行步驟 10
function runStep10($job_id, $config, $deployer) {
    echo "\n🚀 開始執行步驟 10: 智能圖片生成\n";
    echo "===============================\n";
    
    echo "\n⚠️  注意事項:\n";
    echo "- 圖片生成需要 OpenAI API 金鑰\n";
    echo "- 每張圖片成本約 $0.04 (HD 品質)\n";
    echo "- 完整生成可能需要 5-10 分鐘\n";
    
    echo "\n是否繼續？ (y/N): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'y') {
        echo "已取消圖片生成\n";
        return false;
    }
    
    $script_path = DEPLOY_BASE_PATH . '/step-10.php';
    if (!file_exists($script_path)) {
        echo "❌ 找不到 step-10.php\n";
        return false;
    }
    
    try {
        // 設定命令列參數
        $_SERVER['argc'] = 2;
        $_SERVER['argv'] = ['step-10.php', $job_id];
        $GLOBALS['argv'] = $_SERVER['argv'];
        $GLOBALS['argc'] = $_SERVER['argc'];
        
        ob_start();
        include $script_path;
        $output = ob_get_clean();
        
        echo $output;
        
        // 檢查是否成功生成圖片
        $images_dir = $deployer->work_dir . '/images';
        if (is_dir($images_dir)) {
            $image_files = array_merge(
                glob($images_dir . '/*.png'),
                glob($images_dir . '/*.jpg')
            );
            
            if (count($image_files) > 0) {
                echo "✅ 步驟 10 執行成功 - 生成 " . count($image_files) . " 張圖片\n";
                return true;
            }
        }
        
        echo "❌ 步驟 10 執行失敗 - 沒有生成圖片檔案\n";
        return false;
        
    } catch (Exception $e) {
        echo "❌ 步驟 10 執行異常: " . $e->getMessage() . "\n";
        return false;
    }
}

// 主程式
try {
    $config = ConfigManager::getInstance();
    $deployer = new RealDeployer($job_id);
    
    // 初始化真實測試環境
    $work_dir = initializeRealEnvironment($job_id, $user_data);
    
    // 檢查 API 憑證
    $openai_key = $config->get('api_credentials.openai.api_key');
    $gemini_key = $config->get('api_credentials.gemini.api_key');
    
    echo "🔑 API 憑證狀態:\n";
    echo "OpenAI: " . ($openai_key ? "✅ 已設定" : "❌ 未設定") . "\n";
    echo "Gemini: " . ($gemini_key ? "✅ 已設定" : "❌ 未設定") . "\n";
    
    // 主選單循環
    while (true) {
        showMenu();
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                runStep8($job_id, $config, $deployer);
                break;
                
            case '2':
                runStep9($job_id, $config, $deployer);
                break;
                
            case '3':
                runStep9_5($job_id, $config, $deployer);
                break;
                
            case '4':
                runStep10($job_id, $config, $deployer);
                break;
                
            case '5':
                echo "\n🔄 執行完整流程 (8→9→9.5→10)\n";
                echo "============================\n";
                
                if (runStep8($job_id, $config, $deployer)) {
                    echo "\n✅ 步驟 8 完成，繼續步驟 9...\n";
                    sleep(2);
                    
                    if (runStep9($job_id, $config, $deployer)) {
                        echo "\n✅ 步驟 9 完成，繼續步驟 9.5...\n";
                        sleep(2);
                        
                        if (runStep9_5($job_id, $config, $deployer)) {
                            echo "\n✅ 步驟 9.5 完成，繼續步驟 10...\n";
                            sleep(2);
                            
                            if (runStep10($job_id, $config, $deployer)) {
                                echo "\n🎉 完整流程執行成功！\n";
                                echo "📁 所有檔案已生成在: {$deployer->work_dir}\n";
                            }
                        }
                    }
                }
                break;
                
            case '6':
                checkStatus($job_id, $deployer);
                break;
                
            case '0':
                echo "\n👋 感謝使用，再見！\n";
                exit(0);
                
            default:
                echo "\n❌ 無效的選項，請重新選擇\n";
        }
        
        echo "\n按 Enter 繼續...";
        fgets(STDIN);
    }
    
} catch (Exception $e) {
    echo "\n❌ 發生錯誤: " . $e->getMessage() . "\n";
    exit(1);
}
?>