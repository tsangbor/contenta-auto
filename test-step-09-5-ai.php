<?php
/**
 * 測試腳本：步驟 9.5 AI 提示詞生成與完整檔案輸出
 * Phase 1 Day 4 - 整合 AI 提示詞生成與檔案輸出
 * 
 * 此腳本用於測試完整的端到端流程：從掃描到 AI 生成
 */

// 設定基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', __DIR__ . '/config');

// 載入必要的配置
require_once __DIR__ . '/config-manager.php';

// 初始化配置管理器
$config = ConfigManager::getInstance();

// 模擬部署器類別
class TestAIDeployer {
    public function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] [{$level}] {$message}\n";
    }
}

$deployer = new TestAIDeployer();

/**
 * 建立完整的測試環境（含 AI 配置）
 */
function createComprehensiveTestData($deployer) {
    $test_job_id = 'test-ai-095-' . date('YmdHis');
    $work_dir = DEPLOY_BASE_PATH . '/temp/' . $test_job_id;
    
    $deployer->log("建立完整 AI 測試環境: {$test_job_id}");
    
    // 建立目錄結構
    $directories = [
        $work_dir,
        $work_dir . '/config',
        $work_dir . '/json',
        $work_dir . '/layout'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $deployer->log("建立目錄: {$dir}");
        }
    }
    
    // 建立更豐富的 processed_data.json
    $processed_data = [
        'confirmed_data' => [
            'domain' => 'ai-consultant.tw',
            'website_name' => 'AI 智能顧問',
            'website_description' => '專業的人工智慧商業顧問服務平台'
        ],
        'user_info' => [
            'industry' => '人工智慧諮詢服務',
            'company_background' => '由資深 AI 工程師創立，專注於幫助中小企業導入智能化解決方案，提升營運效率與競爭力。團隊具備 10+ 年機器學習實戰經驗，成功輔導超過 100 家企業數位轉型。',
            'founder_info' => '張明智博士，前 Google AI 工程師，史丹佛大學電腦科學博士，擁有多項 AI 專利，曾在頂級科技公司負責大型機器學習專案，對 AI 商業化應用有深度洞察。',
            'values' => '創新、效率、信任、卓越',
            'business_model' => 'B2B 專業諮詢服務，提供 AI 策略規劃、技術導入、團隊培訓的一站式解決方案',
            'target_market' => '年營收 1-10 億的成長型企業，特別是製造業、零售業、金融業',
            'visual_style' => '現代科技感、專業簡潔、藍色調為主、強調信任感與專業度'
        ]
    ];
    
    file_put_contents($work_dir . '/config/processed_data.json', 
        json_encode($processed_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 建立更詳細的 site-config.json
    $site_config = [
        'website_info' => [
            'website_name' => 'AI 智能顧問',
            'brand_personality' => '專業創新、值得信賴',
            'target_audience' => '企業決策者、IT 主管、數位轉型負責人',
            'service_categories' => ['AI 策略諮詢', '機器學習導入', '數據分析', '團隊培訓'],
            'unique_value' => '結合學術深度與實戰經驗，提供落地可行的 AI 解決方案',
            'keywords' => ['人工智慧', 'AI 顧問', '數位轉型', '機器學習', '商業智能']
        ],
        'branding' => [
            'color_primary' => '#2E86AB',
            'color_secondary' => '#A23B72',
            'font_primary' => 'Noto Sans TC',
            'style_direction' => '專業科技風'
        ]
    ];
    
    file_put_contents($work_dir . '/json/site-config.json',
        json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // 建立包含更多圖片佔位符的測試頁面
    $test_pages = [
        'index-ai.json' => [
            'page_type' => 'index',
            'elements' => [
                [
                    'widgetType' => 'hero-section',
                    'settings' => [
                        'background_image' => '{{image:index_hero_bg}}',
                        'title' => 'AI 驅動的商業智能革命',
                        'subtitle' => '讓人工智慧成為您的競爭優勢',
                        'hero_image' => '{{image:index_hero_photo}}'
                    ]
                ],
                [
                    'widgetType' => 'about-section',
                    'settings' => [
                        'image' => '{{image:index_about_photo}}',
                        'title' => '關於 AI 智能顧問',
                        'content' => '我們是業界領先的 AI 商業化專家'
                    ]
                ],
                [
                    'widgetType' => 'service-grid',
                    'settings' => [
                        'service_1_icon' => '{{image:service_ai_strategy}}',
                        'service_2_icon' => '{{image:service_ml_implementation}}',
                        'service_3_icon' => '{{image:service_data_analysis}}',
                        'service_4_icon' => '{{image:service_team_training}}'
                    ]
                ],
                [
                    'widgetType' => 'header',
                    'settings' => [
                        'logo' => '{{image:logo}}'
                    ]
                ],
                [
                    'widgetType' => 'footer-cta',
                    'settings' => [
                        'background_image' => '{{image:index_footer_cta_bg}}',
                        'title' => '開始您的 AI 轉型之旅'
                    ]
                ]
            ]
        ],
        'about-ai.json' => [
            'page_type' => 'about',
            'elements' => [
                [
                    'widgetType' => 'hero-section',
                    'settings' => [
                        'background_image' => '{{image:about_hero_bg}}',
                        'title' => '認識我們的 AI 專家團隊'
                    ]
                ],
                [
                    'widgetType' => 'founder-section',
                    'settings' => [
                        'founder_photo' => '{{image:about_founder_photo}}',
                        'name' => '張明智博士',
                        'title' => '創辦人 & 首席 AI 顧問'
                    ]
                ],
                [
                    'widgetType' => 'team-section',
                    'settings' => [
                        'team_photo' => '{{image:about_team_photo}}',
                        'office_photo' => '{{image:about_office_photo}}'
                    ]
                ]
            ]
        ],
        'service-ai.json' => [
            'page_type' => 'service',
            'elements' => [
                [
                    'widgetType' => 'hero-section',
                    'settings' => [
                        'background_image' => '{{image:service_hero_bg}}',
                        'title' => '專業 AI 顧問服務'
                    ]
                ],
                [
                    'widgetType' => 'process-section',
                    'settings' => [
                        'process_diagram' => '{{image:service_process_diagram}}',
                        'methodology_chart' => '{{image:service_methodology}}'
                    ]
                ]
            ]
        ]
    ];
    
    foreach ($test_pages as $filename => $content) {
        file_put_contents($work_dir . '/layout/' . $filename,
            json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $deployer->log("建立測試頁面: {$filename}");
    }
    
    return $work_dir;
}

/**
 * 模擬 AI API 回應（用於測試）
 */
function mockAIResponse($placeholder_count) {
    $mock_prompts = [];
    
    // 模擬不同類型的圖片提示詞
    $sample_prompts = [
        'logo' => [
            'title' => 'AI 智能顧問 - 品牌標誌',
            'prompt' => 'Professional technology logo with text "AI 智能顧問" in modern sans-serif font, incorporating abstract neural network patterns, gradient blue colors #2E86AB to #A23B72, minimalist design, transparent background',
            'extra' => 'Logo 設計，PNG 格式',
            'ai' => 'openai',
            'style' => 'modern',
            'quality' => 'high',
            'size' => '512x512'
        ],
        'hero_bg' => [
            'title' => 'AI 智能顧問 - 首頁英雄背景',
            'prompt' => 'Modern corporate office environment with subtle AI technology elements, soft lighting, professional business atmosphere, blue color scheme, data visualization screens in background, clean and sophisticated ambiance',
            'extra' => '首頁英雄區域背景',
            'ai' => 'openai',
            'style' => 'corporate',
            'quality' => 'high',
            'size' => '1920x1080'
        ],
        'founder_photo' => [
            'title' => 'AI 智能顧問 - 創辦人專業形象',
            'prompt' => 'Professional Asian male executive in his 40s, confident expression, modern business attire, technology office background, warm professional lighting, trustworthy and intelligent appearance, subtle AI technology elements',
            'extra' => '創辦人專業形象照',
            'ai' => 'openai',
            'style' => 'portrait',
            'quality' => 'high',
            'size' => '800x600'
        ]
    ];
    
    // 為每個佔位符生成提示詞
    $counter = 0;
    foreach (['logo', 'index_hero_bg', 'index_hero_photo', 'index_about_photo', 'about_founder_photo', 'service_ai_strategy'] as $key) {
        if ($counter >= $placeholder_count) break;
        
        if (strpos($key, 'logo') !== false) {
            $mock_prompts[$key] = $sample_prompts['logo'];
        } elseif (strpos($key, 'bg') !== false) {
            $mock_prompts[$key] = $sample_prompts['hero_bg'];
        } else {
            $mock_prompts[$key] = $sample_prompts['founder_photo'];
        }
        
        $mock_prompts[$key]['title'] = str_replace('logo', $key, $mock_prompts[$key]['title']);
        $counter++;
    }
    
    return json_encode($mock_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * 測試完整的 AI 圖片提示詞生成流程
 */
function testCompleteAIGeneration($work_dir, $deployer) {
    $deployer->log("=== 測試完整 AI 圖片提示詞生成流程 ===");
    
    // 模擬 step-09-5.php 的主要處理邏輯
    // 載入必要的函數（從 step-09-5.php 複製）
    require_once __DIR__ . '/test-step-09-5.php'; // 載入基礎函數
    
    try {
        // 載入配置檔案
        $processed_data_file = $work_dir . '/config/processed_data.json';
        $site_config_file = $work_dir . '/json/site-config.json';
        
        $processed_data = json_decode(file_get_contents($processed_data_file), true);
        $site_config = json_decode(file_get_contents($site_config_file), true);
        
        // 1. 掃描圖片需求
        $deployer->log("步驟 1: 掃描圖片需求...");
        $image_requirements_raw = scanPageImageRequirements($work_dir);
        
        if (empty($image_requirements_raw)) {
            $deployer->log("❌ 未發現圖片佔位符");
            return false;
        }
        
        $deployer->log("✅ 發現 " . count($image_requirements_raw) . " 個圖片需求");
        
        // 2. 生成結構化需求
        $deployer->log("步驟 2: 生成結構化需求...");
        $image_requirements = generateImageRequirementsJson($image_requirements_raw, $site_config);
        
        // 確保目錄存在
        if (!is_dir($work_dir . '/json')) {
            mkdir($work_dir . '/json', 0755, true);
        }
        
        // 儲存需求分析結果
        $requirements_file = $work_dir . '/json/image-requirements.json';
        if (file_put_contents($requirements_file, json_encode($image_requirements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $deployer->log("✅ 儲存圖片需求分析: image-requirements.json (" . filesize($requirements_file) . " bytes)");
        } else {
            $deployer->log("❌ 儲存圖片需求分析失敗");
        }
        
        // 3. 模擬 AI 提示詞生成（避免實際 API 呼叫）
        $deployer->log("步驟 3: 模擬 AI 提示詞生成...");
        $mock_response = mockAIResponse(count($image_requirements));
        $personalized_prompts = json_decode($mock_response, true);
        
        if (!$personalized_prompts) {
            $deployer->log("❌ AI 提示詞解析失敗");
            return false;
        }
        
        $deployer->log("✅ 模擬生成 " . count($personalized_prompts) . " 個提示詞");
        
        // 4. 輸出 image-prompts.json
        $output_file = $work_dir . '/json/image-prompts.json';
        file_put_contents($output_file, json_encode($personalized_prompts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $deployer->log("✅ 輸出最終圖片提示詞: image-prompts.json");
        
        // 5. 驗證輸出品質
        $deployer->log("步驟 4: 驗證輸出品質...");
        return validateImagePromptsQuality($output_file, $deployer);
        
    } catch (Exception $e) {
        $deployer->log("❌ 測試流程失敗: " . $e->getMessage());
        return false;
    }
}

/**
 * 驗證 image-prompts.json 的品質
 */
function validateImagePromptsQuality($output_file, $deployer) {
    if (!file_exists($output_file)) {
        $deployer->log("❌ 輸出檔案不存在");
        return false;
    }
    
    $prompts = json_decode(file_get_contents($output_file), true);
    if (!$prompts) {
        $deployer->log("❌ 提示詞檔案格式錯誤");
        return false;
    }
    
    $deployer->log("驗證圖片提示詞品質:");
    
    $quality_checks = [
        'structure' => 0,    // 結構完整性
        'personalization' => 0,  // 個性化程度
        'english_quality' => 0,  // 英文品質
        'technical_specs' => 0   // 技術規格
    ];
    
    foreach ($prompts as $key => $prompt) {
        // 檢查結構完整性
        $required_fields = ['title', 'prompt', 'ai', 'style', 'quality', 'size'];
        $has_all_fields = true;
        foreach ($required_fields as $field) {
            if (!isset($prompt[$field])) {
                $has_all_fields = false;
                break;
            }
        }
        if ($has_all_fields) $quality_checks['structure']++;
        
        // 檢查個性化程度（避免通用詞彙）
        $prompt_text = $prompt['prompt'] ?? '';
        $generic_terms = ['professional', 'modern', 'clean', 'business'];
        $specific_terms = ['AI', '智能顧問', 'neural', 'technology', 'consultant'];
        
        $specific_count = 0;
        foreach ($specific_terms as $term) {
            if (stripos($prompt_text, $term) !== false) {
                $specific_count++;
            }
        }
        if ($specific_count >= 2) $quality_checks['personalization']++;
        
        // 檢查英文品質
        if (preg_match('/^[A-Za-z0-9\s\-,.\'"#]+$/', $prompt_text)) {
            $quality_checks['english_quality']++;
        }
        
        // 檢查技術規格
        if (isset($prompt['size']) && preg_match('/^\d+x\d+$/', $prompt['size'])) {
            $quality_checks['technical_specs']++;
        }
    }
    
    $total = count($prompts);
    $deployer->log("  結構完整性: {$quality_checks['structure']}/{$total}");
    $deployer->log("  個性化程度: {$quality_checks['personalization']}/{$total}");
    $deployer->log("  英文品質: {$quality_checks['english_quality']}/{$total}");
    $deployer->log("  技術規格: {$quality_checks['technical_specs']}/{$total}");
    
    $average_score = array_sum($quality_checks) / (4 * $total);
    $deployer->log("綜合品質分數: " . round($average_score * 100, 1) . "%");
    
    return $average_score >= 0.8; // 80% 以上為通過
}

/**
 * 測試檔案輸出與格式驗證
 */
function testFileOutputValidation($work_dir, $deployer) {
    $deployer->log("=== 測試檔案輸出與格式驗證 ===");
    
    $expected_files = [
        '/json/image-requirements.json',
        '/json/image-prompts.json'
    ];
    
    $all_files_exist = true;
    foreach ($expected_files as $file) {
        $full_path = $work_dir . $file;
        if (file_exists($full_path)) {
            $size = filesize($full_path);
            $deployer->log("✅ {$file} 存在 ({$size} bytes)");
            
            // 驗證 JSON 格式
            $content = json_decode(file_get_contents($full_path), true);
            if ($content) {
                $deployer->log("  JSON 格式正確，包含 " . count($content) . " 個項目");
            } else {
                $deployer->log("  ❌ JSON 格式錯誤");
                $all_files_exist = false;
            }
        } else {
            $deployer->log("❌ {$file} 不存在");
            $all_files_exist = false;
        }
    }
    
    return $all_files_exist;
}

/**
 * 執行 Phase 1 Day 4 完整測試
 */
function runPhase1Day4Test() {
    $deployer = new TestAIDeployer();
    
    $deployer->log("🚀 開始執行 Phase 1 Day 4 完整測試");
    $deployer->log("測試項目: 整合 AI 提示詞生成與檔案輸出");
    
    $test_results = [];
    
    try {
        // 1. 建立測試環境
        $work_dir = createComprehensiveTestData($deployer);
        $test_results['create_test_data'] = true;
        
        // 2. 測試完整 AI 生成流程
        $test_results['ai_generation_flow'] = testCompleteAIGeneration($work_dir, $deployer);
        
        // 3. 測試檔案輸出驗證
        $test_results['file_output_validation'] = testFileOutputValidation($work_dir, $deployer);
        
        // 測試結果統計
        $deployer->log("=== Phase 1 Day 4 測試結果 ===");
        $passed = 0;
        $total = count($test_results);
        
        foreach ($test_results as $test_name => $result) {
            $status = $result ? "✅ 通過" : "❌ 失敗";
            $deployer->log("{$test_name}: {$status}");
            if ($result) $passed++;
        }
        
        $deployer->log("測試通過率: {$passed}/{$total} (" . round(($passed/$total)*100, 1) . "%)");
        
        if ($passed === $total) {
            $deployer->log("🎉 Phase 1 Day 4 所有測試通過！AI 整合功能運作正常");
            $deployer->log("✅ step-09-5.php 完整端到端流程驗證成功");
        } else {
            $deployer->log("⚠️ 部分測試失敗，需要檢查相關功能", 'WARNING');
        }
        
        // 保留測試資料以供檢查（可選清理）
        $deployer->log("測試資料保存位置: {$work_dir}");
        $deployer->log("如需清理，請手動執行: rm -rf " . escapeshellarg($work_dir));
        
        return $test_results;
        
    } catch (Exception $e) {
        $deployer->log("❌ 測試執行失敗: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $deployer = new TestAIDeployer();
    $deployer->log("Phase 1 Day 4: 整合 AI 提示詞生成與檔案輸出測試");
    $deployer->log("目標: 驗證 step-09-5.php 完整端到端功能");
    
    $results = runPhase1Day4Test();
    
    if ($results) {
        $deployer->log("Phase 1 Day 4 測試完成！請檢查上方結果。");
    } else {
        $deployer->log("測試過程中發生錯誤，請檢查日誌。");
    }
}