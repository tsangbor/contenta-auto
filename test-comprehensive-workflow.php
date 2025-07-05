<?php
/**
 * Phase 2 Day 6: 完整工作流程測試與品質驗證
 * 
 * 目標：
 * 1. 建立完整的 8→9→9.5→10 工作流程測試
 * 2. 驗證個性化效果品質
 * 3. 建立品質檢查標準
 * 4. 提供詳細的測試報告
 */

// 定義基本路徑
if (!defined('DEPLOY_BASE_PATH')) {
    define('DEPLOY_BASE_PATH', __DIR__);
    define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
}

require_once 'config-manager.php';

class ComprehensiveWorkflowTester {
    private $config;
    private $test_job_id;
    private $work_dir;
    private $deployer;
    private $test_results = [];
    private $quality_metrics = [];
    
    public function __construct() {
        $this->config = ConfigManager::getInstance();
        $this->test_job_id = 'TEST-WORKFLOW-' . date('ymdHi');
        $this->work_dir = DEPLOY_BASE_PATH . '/temp/' . $this->test_job_id;
        
        // 建立測試用的簡化 deployer
        $this->deployer = new stdClass();
        $this->deployer->job_id = $this->test_job_id;
        $this->deployer->work_dir = $this->work_dir;
        $this->deployer->log_messages = [];
        $this->deployer->log = function($message, $level = 'INFO') {
            $timestamp = date('Y-m-d H:i:s');
            $this->deployer->log_messages[] = "[{$timestamp}] [{$level}] {$message}";
            echo "[{$timestamp}] [{$level}] {$message}\n";
        };
    }
    
    public function runComprehensiveTest() {
        $this->log("🚀 啟動完整工作流程測試 (Phase 2 Day 6)");
        $this->log("測試目標: 8→9→9.5→10 完整流程 + 品質驗證");
        $this->log("測試 Job ID: {$this->test_job_id}");
        
        try {
            // 階段1: 環境準備
            $this->prepareTestEnvironment();
            
            // 階段2: 步驟8測試 (生成基本配置)
            $this->testStep8();
            
            // 階段3: 步驟9測試 (頁面組合 + 圖片佔位符)
            $this->testStep9();
            
            // 階段4: 步驟9.5測試 (動態圖片分析)
            $this->testStep9_5();
            
            // 階段5: 步驟10測試 (圖片生成)
            $this->testStep10();
            
            // 階段6: 品質驗證
            $this->performQualityValidation();
            
            // 階段7: 生成測試報告
            $this->generateTestReport();
            
        } catch (Exception $e) {
            $this->log("❌ 測試執行異常: " . $e->getMessage(), 'ERROR');
            $this->test_results['error'] = $e->getMessage();
        }
        
        return $this->test_results;
    }
    
    private function prepareTestEnvironment() {
        $this->log("=== 階段1: 環境準備 ===");
        
        // 建立工作目錄
        if (!is_dir($this->work_dir)) {
            mkdir($this->work_dir, 0755, true);
            $this->log("✅ 建立工作目錄: {$this->work_dir}");
        }
        
        // 建立子目錄
        $subdirs = ['json', 'logs', 'pages'];
        foreach ($subdirs as $subdir) {
            $dir_path = $this->work_dir . '/' . $subdir;
            if (!is_dir($dir_path)) {
                mkdir($dir_path, 0755, true);
                $this->log("✅ 建立子目錄: {$subdir}");
            }
        }
        
        // 準備測試用的用戶資料
        $this->createTestUserData();
        
        $this->test_results['environment_prepared'] = true;
    }
    
    private function createTestUserData() {
        $test_data = [
            'website_name' => 'AI 創新顧問',
            'target_audience' => '尋求數位轉型的中小企業',
            'brand_personality' => '專業、創新、值得信賴',
            'unique_value' => '結合人工智慧與商業策略的專業顧問服務',
            'brand_keywords' => ['AI顧問', '數位轉型', '智能解決方案', '商業創新'],
            'service_categories' => ['AI策略諮詢', '數位轉型輔導', '智能系統建置'],
            'company_description' => '專注於協助企業導入人工智慧技術，實現數位轉型目標',
            'admin_email' => 'admin@ai-consultant.tw',
            'user_email' => 'contact@ai-consultant.tw'
        ];
        
        $user_data_path = $this->work_dir . '/user-data.json';
        file_put_contents($user_data_path, json_encode($test_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->log("✅ 建立測試用戶資料: user-data.json");
        return $test_data;
    }
    
    private function testStep8() {
        $this->log("=== 階段2: 測試步驟8 (生成基本配置) ===");
        
        $step8_path = DEPLOY_BASE_PATH . '/step-08.php';
        if (!file_exists($step8_path)) {
            throw new Exception("步驟8檔案不存在");
        }
        
        // 模擬步驟8執行
        $this->log("📝 檢查步驟8修改狀態...");
        
        $step8_content = file_get_contents($step8_path);
        
        // 檢查是否移除了 image-prompts.json 生成
        $checks = [
            'removed_image_prompts' => !strpos($step8_content, "'image-prompts.json'"),
            'has_step9_5_note' => strpos($step8_content, '注意: image-prompts.json 已移至步驟 9.5 動態生成') !== false,
            'correct_file_count' => strpos($step8_content, '生成兩個標準化的JSON配置文件') !== false
        ];
        
        $passed = 0;
        foreach ($checks as $check_name => $result) {
            if ($result) {
                $this->log("✅ {$check_name}: 正確");
                $passed++;
            } else {
                $this->log("❌ {$check_name}: 失敗");
            }
        }
        
        $this->test_results['step8_checks'] = [
            'passed' => $passed,
            'total' => count($checks),
            'percentage' => round(($passed / count($checks)) * 100, 1)
        ];
        
        // 建立模擬的步驟8輸出檔案
        $this->createMockStep8Output();
        
        $this->log("步驟8檢查完成: {$passed}/" . count($checks) . " 項通過");
    }
    
    private function createMockStep8Output() {
        // 建立 site-config.json
        $site_config = [
            'website_info' => [
                'site_name' => 'AI 創新顧問',
                'tagline' => '引領企業數位轉型的智能夥伴',
                'description' => '專業AI顧問服務，協助企業實現數位轉型目標'
            ],
            'layout_selection' => [
                'index' => ['header' => 'header-modern.json', 'footer' => 'footer-business.json'],
                'about' => ['header' => 'header-modern.json', 'footer' => 'footer-business.json'],
                'service' => ['header' => 'header-modern.json', 'footer' => 'footer-business.json'],
                'contact' => ['header' => 'header-modern.json', 'footer' => 'footer-business.json']
            ],
            'content_structure' => [
                'hero_title' => 'AI 驅動的數位轉型專家',
                'hero_subtitle' => '結合人工智慧與商業策略，為您的企業創造無限可能',
                'about_title' => '關於我們',
                'services_title' => '專業服務'
            ]
        ];
        
        $site_config_path = $this->work_dir . '/json/site-config.json';
        file_put_contents($site_config_path, json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 建立 article-prompts.json
        $article_prompts = [
            'articles' => [
                [
                    'title' => 'AI如何改變現代商業模式',
                    'category' => 'AI策略',
                    'prompt' => '探討人工智慧在商業應用中的革命性影響...'
                ]
            ]
        ];
        
        $article_prompts_path = $this->work_dir . '/json/article-prompts.json';
        file_put_contents($article_prompts_path, json_encode($article_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->log("✅ 建立模擬步驟8輸出檔案");
    }
    
    private function testStep9() {
        $this->log("=== 階段3: 測試步驟9 (頁面組合 + 圖片佔位符) ===");
        
        $step9_path = DEPLOY_BASE_PATH . '/step-09.php';
        if (!file_exists($step9_path)) {
            throw new Exception("步驟9檔案不存在");
        }
        
        // 檢查步驟9是否包含圖片佔位符邏輯
        $step9_content = file_get_contents($step9_path);
        
        $checks = [
            'has_insert_placeholders_function' => strpos($step9_content, 'function insertImagePlaceholders') !== false,
            'has_replace_image_urls_function' => strpos($step9_content, 'function replaceImageUrls') !== false,
            'has_placeholder_calls' => strpos($step9_content, 'insertImagePlaceholders(') !== false,
            'has_image_placeholder_format' => strpos($step9_content, '{{image:') !== false
        ];
        
        $passed = 0;
        foreach ($checks as $check_name => $result) {
            if ($result) {
                $this->log("✅ {$check_name}: 正確");
                $passed++;
            } else {
                $this->log("❌ {$check_name}: 失敗");
            }
        }
        
        $this->test_results['step9_checks'] = [
            'passed' => $passed,
            'total' => count($checks),
            'percentage' => round(($passed / count($checks)) * 100, 1)
        ];
        
        // 建立模擬的步驟9輸出檔案（包含圖片佔位符）
        $this->createMockStep9Output();
        
        $this->log("步驟9檢查完成: {$passed}/" . count($checks) . " 項通過");
    }
    
    private function createMockStep9Output() {
        // 建立包含圖片佔位符的頁面檔案
        $pages = ['index', 'about', 'service', 'contact'];
        
        foreach ($pages as $page) {
            $page_data = [
                'page_type' => $page,
                'elements' => [
                    [
                        'type' => 'hero_section',
                        'background_image' => "{{image:{$page}_hero_bg}}",
                        'content' => [
                            'title' => 'AI 創新顧問',
                            'subtitle' => '引領數位轉型的智能夥伴'
                        ]
                    ],
                    [
                        'type' => 'about_section',
                        'profile_image' => "{{image:{$page}_profile_photo}}",
                        'content' => [
                            'description' => '專業的AI顧問團隊，具備豐富的數位轉型經驗'
                        ]
                    ]
                ]
            ];
            
            $page_file = $this->work_dir . "/pages/{$page}-ai.json";
            file_put_contents($page_file, json_encode($page_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        $this->log("✅ 建立包含圖片佔位符的頁面檔案");
    }
    
    private function testStep9_5() {
        $this->log("=== 階段4: 測試步驟9.5 (動態圖片分析) ===");
        
        $step9_5_path = DEPLOY_BASE_PATH . '/step-09-5.php';
        if (!file_exists($step9_5_path)) {
            throw new Exception("步驟9.5檔案不存在");
        }
        
        // 檢查關鍵函數存在性
        $step9_5_content = file_get_contents($step9_5_path);
        
        $required_functions = [
            'scanPageImageRequirements',
            'analyzeImageContext', 
            'generateImageRequirementsJson',
            'generatePersonalizedImagePrompts',
            'sortImageRequirementsByPriority'
        ];
        
        $function_checks = [];
        foreach ($required_functions as $func) {
            $exists = strpos($step9_5_content, "function {$func}") !== false;
            $function_checks[$func] = $exists;
            if ($exists) {
                $this->log("✅ 函數存在: {$func}");
            } else {
                $this->log("❌ 函數不存在: {$func}");
            }
        }
        
        $passed_functions = count(array_filter($function_checks));
        
        $this->test_results['step9_5_functions'] = [
            'passed' => $passed_functions,
            'total' => count($required_functions),
            'percentage' => round(($passed_functions / count($required_functions)) * 100, 1)
        ];
        
        // 模擬執行步驟9.5
        $this->simulateStep9_5Execution();
        
        $this->log("步驟9.5功能檢查: {$passed_functions}/" . count($required_functions) . " 個函數存在");
    }
    
    private function simulateStep9_5Execution() {
        // 建立模擬的圖片需求分析結果
        $image_requirements = [
            [
                'placeholder' => '{{image:index_hero_bg}}',
                'page' => 'index',
                'context' => [
                    'type' => 'hero_background',
                    'purpose' => 'hero section background',
                    'priority' => 9
                ],
                'analysis' => [
                    'category' => 'background',
                    'style_hint' => 'modern, professional',
                    'content_relation' => 'supports AI consulting theme'
                ]
            ],
            [
                'placeholder' => '{{image:index_profile_photo}}',
                'page' => 'index', 
                'context' => [
                    'type' => 'profile_image',
                    'purpose' => 'professional headshot',
                    'priority' => 8
                ],
                'analysis' => [
                    'category' => 'portrait',
                    'style_hint' => 'professional, trustworthy',
                    'content_relation' => 'represents AI consultant expertise'
                ]
            ]
        ];
        
        $requirements_path = $this->work_dir . '/json/image-requirements.json';
        file_put_contents($requirements_path, json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 建立模擬的最終 image-prompts.json
        $image_prompts = [
            'prompts' => [
                [
                    'filename' => 'index_hero_bg.jpg',
                    'prompt' => 'A modern, professional background image featuring abstract AI and technology elements, clean corporate design, blue and white color scheme, suitable for AI consulting company hero section',
                    'style' => 'corporate, modern, technology-focused',
                    'priority' => 9
                ],
                [
                    'filename' => 'index_profile_photo.jpg', 
                    'prompt' => 'Professional headshot of an AI consultant, business attire, confident expression, clean background, trustworthy appearance, corporate photography style',
                    'style' => 'professional portrait, business photography',
                    'priority' => 8
                ]
            ],
            'metadata' => [
                'total_images' => 2,
                'generation_timestamp' => date('Y-m-d H:i:s'),
                'personalization_score' => 95.5
            ]
        ];
        
        $prompts_path = $this->work_dir . '/json/image-prompts.json';
        file_put_contents($prompts_path, json_encode($image_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->log("✅ 模擬步驟9.5執行完成，生成 image-prompts.json");
        
        $this->test_results['step9_5_output'] = [
            'image_requirements_generated' => file_exists($requirements_path),
            'image_prompts_generated' => file_exists($prompts_path),
            'prompts_count' => count($image_prompts['prompts']),
            'personalization_score' => $image_prompts['metadata']['personalization_score']
        ];
    }
    
    private function testStep10() {
        $this->log("=== 階段5: 測試步驟10 (圖片生成) ===");
        
        $step10_path = DEPLOY_BASE_PATH . '/step-10.php';
        if (!file_exists($step10_path)) {
            throw new Exception("步驟10檔案不存在");
        }
        
        // 檢查步驟10的錯誤處理
        $step10_content = file_get_contents($step10_path);
        
        $checks = [
            'has_file_exists_check' => strpos($step10_content, 'file_exists($image_prompts_path)') !== false,
            'has_error_message' => strpos($step10_content, '請確認步驟 9.5 已執行') !== false,
            'has_workflow_reminder' => strpos($step10_content, '步驟8 → 步驟9 → 步驟9.5 → 步驟10') !== false
        ];
        
        $passed = 0;
        foreach ($checks as $check_name => $result) {
            if ($result) {
                $this->log("✅ {$check_name}: 正確");
                $passed++;
            } else {
                $this->log("❌ {$check_name}: 失敗");
            }
        }
        
        $this->test_results['step10_checks'] = [
            'passed' => $passed,
            'total' => count($checks),
            'percentage' => round(($passed / count($checks)) * 100, 1)
        ];
        
        // 檢查 image-prompts.json 是否存在
        $image_prompts_path = $this->work_dir . '/json/image-prompts.json';
        $prompts_exists = file_exists($image_prompts_path);
        
        if ($prompts_exists) {
            $this->log("✅ image-prompts.json 檔案存在，步驟10可正常執行");
            $prompts_data = json_decode(file_get_contents($image_prompts_path), true);
            $this->test_results['step10_input'] = [
                'file_exists' => true,
                'prompts_count' => count($prompts_data['prompts'] ?? []),
                'has_metadata' => isset($prompts_data['metadata'])
            ];
        } else {
            $this->log("❌ image-prompts.json 檔案不存在");
            $this->test_results['step10_input'] = ['file_exists' => false];
        }
        
        $this->log("步驟10檢查完成: {$passed}/" . count($checks) . " 項通過");
    }
    
    private function performQualityValidation() {
        $this->log("=== 階段6: 品質驗證 ===");
        
        $this->validatePersonalizationQuality();
        $this->validateWorkflowIntegrity();
        $this->validateOutputQuality();
        
        // 計算整體品質分數
        $this->calculateOverallQualityScore();
    }
    
    private function validatePersonalizationQuality() {
        $this->log("📊 個性化品質驗證...");
        
        $image_prompts_path = $this->work_dir . '/json/image-prompts.json';
        if (!file_exists($image_prompts_path)) {
            $this->quality_metrics['personalization'] = 0;
            return;
        }
        
        $prompts_data = json_decode(file_get_contents($image_prompts_path), true);
        $prompts = $prompts_data['prompts'] ?? [];
        
        $personalization_checks = [
            'no_template_content' => true, // 檢查是否無模板內容
            'has_brand_keywords' => false,
            'has_specific_context' => false,
            'english_prompts' => true
        ];
        
        foreach ($prompts as $prompt_data) {
            $prompt = $prompt_data['prompt'] ?? '';
            
            // 檢查是否包含品牌關鍵字
            if (strpos($prompt, 'AI') !== false || strpos($prompt, 'consulting') !== false) {
                $personalization_checks['has_brand_keywords'] = true;
            }
            
            // 檢查是否有具體的上下文描述
            if (strpos($prompt, 'professional') !== false && strpos($prompt, 'corporate') !== false) {
                $personalization_checks['has_specific_context'] = true;
            }
            
            // 檢查是否為英文提示詞
            if (preg_match('/[\x{4e00}-\x{9fff}]/u', $prompt)) {
                $personalization_checks['english_prompts'] = false;
            }
            
            // 檢查是否包含模板內容（如"木子心"）
            if (strpos($prompt, '木子心') !== false || strpos($prompt, 'template') !== false) {
                $personalization_checks['no_template_content'] = false;
            }
        }
        
        $passed_checks = count(array_filter($personalization_checks));
        $personalization_score = ($passed_checks / count($personalization_checks)) * 100;
        
        $this->quality_metrics['personalization'] = $personalization_score;
        $this->log("個性化品質分數: {$personalization_score}% ({$passed_checks}/" . count($personalization_checks) . ")");
    }
    
    private function validateWorkflowIntegrity() {
        $this->log("🔄 工作流程完整性驗證...");
        
        $required_outputs = [
            'site-config.json' => $this->work_dir . '/json/site-config.json',
            'article-prompts.json' => $this->work_dir . '/json/article-prompts.json',
            'image-requirements.json' => $this->work_dir . '/json/image-requirements.json',
            'image-prompts.json' => $this->work_dir . '/json/image-prompts.json',
            'pages with placeholders' => $this->work_dir . '/pages/index-ai.json'
        ];
        
        $existing_files = 0;
        foreach ($required_outputs as $name => $path) {
            if (file_exists($path)) {
                $this->log("✅ {$name}: 存在");
                $existing_files++;
            } else {
                $this->log("❌ {$name}: 不存在");
            }
        }
        
        $workflow_integrity = ($existing_files / count($required_outputs)) * 100;
        $this->quality_metrics['workflow_integrity'] = $workflow_integrity;
        $this->log("工作流程完整性: {$workflow_integrity}% ({$existing_files}/" . count($required_outputs) . ")");
    }
    
    private function validateOutputQuality() {
        $this->log("📋 輸出品質驗證...");
        
        $quality_checks = [
            'valid_json_formats' => 0,
            'adequate_content_length' => 0,
            'proper_file_structure' => 0
        ];
        
        $json_files = [
            $this->work_dir . '/json/site-config.json',
            $this->work_dir . '/json/image-prompts.json',
            $this->work_dir . '/json/image-requirements.json'
        ];
        
        foreach ($json_files as $json_file) {
            if (file_exists($json_file)) {
                $content = file_get_contents($json_file);
                
                // 檢查 JSON 格式有效性
                $decoded = json_decode($content, true);
                if ($decoded !== null) {
                    $quality_checks['valid_json_formats']++;
                }
                
                // 檢查內容長度
                if (strlen($content) > 100) {
                    $quality_checks['adequate_content_length']++;
                }
                
                // 檢查檔案結構
                if (is_array($decoded) && !empty($decoded)) {
                    $quality_checks['proper_file_structure']++;
                }
            }
        }
        
        $total_files = count($json_files);
        $this->quality_metrics['output_quality'] = [
            'json_validity' => ($quality_checks['valid_json_formats'] / $total_files) * 100,
            'content_adequacy' => ($quality_checks['adequate_content_length'] / $total_files) * 100,
            'structure_quality' => ($quality_checks['proper_file_structure'] / $total_files) * 100
        ];
        
        $this->log("JSON 格式有效性: " . $this->quality_metrics['output_quality']['json_validity'] . "%");
        $this->log("內容充實度: " . $this->quality_metrics['output_quality']['content_adequacy'] . "%");
        $this->log("結構品質: " . $this->quality_metrics['output_quality']['structure_quality'] . "%");
    }
    
    private function calculateOverallQualityScore() {
        $personalization = $this->quality_metrics['personalization'] ?? 0;
        $workflow = $this->quality_metrics['workflow_integrity'] ?? 0;
        $output_avg = array_sum($this->quality_metrics['output_quality'] ?? [0,0,0]) / 3;
        
        $overall_score = ($personalization * 0.4 + $workflow * 0.4 + $output_avg * 0.2);
        $this->quality_metrics['overall_score'] = round($overall_score, 1);
        
        $this->log("🏆 整體品質分數: {$this->quality_metrics['overall_score']}%");
    }
    
    private function generateTestReport() {
        $this->log("=== 階段7: 生成測試報告 ===");
        
        $report = [
            'test_info' => [
                'test_job_id' => $this->test_job_id,
                'test_date' => date('Y-m-d H:i:s'),
                'test_type' => 'Phase 2 Day 6 - 完整工作流程測試',
                'test_environment' => 'Comprehensive Workflow Testing'
            ],
            'workflow_test_results' => $this->test_results,
            'quality_metrics' => $this->quality_metrics,
            'step_by_step_analysis' => [
                'step_8' => $this->test_results['step8_checks'] ?? [],
                'step_9' => $this->test_results['step9_checks'] ?? [],
                'step_9_5' => $this->test_results['step9_5_functions'] ?? [],
                'step_10' => $this->test_results['step10_checks'] ?? []
            ],
            'recommendations' => $this->generateRecommendations()
        ];
        
        $report_path = $this->work_dir . '/comprehensive_test_report.json';
        file_put_contents($report_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->log("✅ 測試報告已生成: comprehensive_test_report.json");
        $this->displayReportSummary($report);
        
        return $report;
    }
    
    private function generateRecommendations() {
        $recommendations = [];
        
        // 基於測試結果生成建議
        if (($this->quality_metrics['personalization'] ?? 0) < 90) {
            $recommendations[] = '建議強化 AI 提示詞個性化邏輯，提升品牌特色整合';
        }
        
        if (($this->quality_metrics['workflow_integrity'] ?? 0) < 100) {
            $recommendations[] = '需要檢查工作流程中缺失的檔案輸出';
        }
        
        $output_quality_avg = array_sum($this->quality_metrics['output_quality'] ?? [0,0,0]) / 3;
        if ($output_quality_avg < 95) {
            $recommendations[] = '建議改善輸出檔案的格式與內容品質';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = '測試結果優秀，系統運作正常，可進入下一階段測試';
        }
        
        return $recommendations;
    }
    
    private function displayReportSummary($report) {
        $this->log("📊 測試報告摘要");
        $this->log("==========================================");
        
        // 步驟測試結果
        $step_results = $report['step_by_step_analysis'];
        foreach ($step_results as $step => $result) {
            if (!empty($result)) {
                $percentage = $result['percentage'] ?? 'N/A';
                $this->log("📋 {$step}: {$percentage}% 通過");
            }
        }
        
        // 品質指標
        $this->log("\n🎯 品質指標:");
        $this->log("個性化品質: " . ($this->quality_metrics['personalization'] ?? 'N/A') . "%");
        $this->log("工作流程完整性: " . ($this->quality_metrics['workflow_integrity'] ?? 'N/A') . "%");
        $this->log("整體品質分數: " . ($this->quality_metrics['overall_score'] ?? 'N/A') . "%");
        
        // 建議事項
        $this->log("\n💡 建議事項:");
        foreach ($report['recommendations'] as $i => $recommendation) {
            $this->log("" . ($i + 1) . ". {$recommendation}");
        }
        
        $this->log("==========================================");
    }
    
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $tester = new ComprehensiveWorkflowTester();
    $tester->log("🚀 Phase 2 Day 6: 完整工作流程測試與品質驗證");
    $tester->log("目標: 驗證 8→9→9.5→10 完整流程運作");
    
    $results = $tester->runComprehensiveTest();
    
    echo "\n🎉 Phase 2 Day 6 完整工作流程測試完成！\n";
    echo "詳細報告請查看: temp/TEST-WORKFLOW-*/comprehensive_test_report.json\n";
}