<?php
/**
 * 步驟依賴關係定義與檢查
 * 
 * 定義每個步驟的前置條件與依賴關係
 */

/**
 * 步驟依賴關係映射表
 * 
 * 格式：
 * 'step_number' => [
 *     'depends_on' => ['step_01', 'step_02'], // 依賴的步驟
 *     'required_files' => [                    // 需要的檔案
 *         'file_path' => 'description'
 *     ]
 * ]
 */
function getStepDependencies() {
    return [
        '00' => [
            'depends_on' => [],
            'required_files' => []
        ],
        '01' => [
            'depends_on' => ['00'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料'
            ]
        ],
        '02' => [
            'depends_on' => ['00', '01'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'cloudflare_info.json' => 'Cloudflare 網站資訊'
            ]
        ],
        '03' => [
            'depends_on' => ['00', '01', '02'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'cloudflare_info.json' => 'Cloudflare 網站資訊'
            ]
        ],
        '04' => [
            'depends_on' => ['00', '03'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'bt_site_info.json' => 'BT.cn 網站資訊'
            ]
        ],
        '05' => [
            'depends_on' => ['00', '03'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'bt_site_info.json' => 'BT.cn 網站資訊'
            ]
        ],
        '06' => [
            'depends_on' => ['00', '03', '05'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'bt_site_info.json' => 'BT.cn 網站資訊',
                'bt_database_info.json' => '資料庫資訊'
            ]
        ],
        '07' => [
            'depends_on' => ['00', '06'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'bt_site_info.json' => 'BT.cn 網站資訊'
            ]
        ],
        '08' => [
            'depends_on' => ['00'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料'
            ]
        ],
        '09' => [
            'depends_on' => ['00', '08'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'json/site-config.json' => 'AI 生成的網站配置'
            ]
        ],
        '10' => [
            'depends_on' => ['00', '08', '09'],
            'required_files' => [
                'config/processed_data.json' => '處理後的配置資料',
                'json/image-prompts.json' => 'AI 圖片提示詞'
            ]
        ]
    ];
}

/**
 * 檢查步驟的前置條件
 * 
 * @param string $current_step 當前步驟編號
 * @param string $work_dir 工作目錄
 * @param object $deployer 部署器實例
 * @param string $job_id Job ID
 * @return array 檢查結果 ['status' => 'ok'|'error', 'message' => '...']
 */
function checkStepDependencies($current_step, $work_dir, $deployer, $job_id = null) {
    $dependencies = getStepDependencies();
    
    // 如果沒有定義依賴關係，預設通過
    if (!isset($dependencies[$current_step])) {
        return ['status' => 'ok'];
    }
    
    $step_deps = $dependencies[$current_step];
    $missing_steps = [];
    $missing_files = [];
    
    // 檢查必要檔案
    foreach ($step_deps['required_files'] as $file_path => $description) {
        $full_path = $work_dir . '/' . $file_path;
        if (!file_exists($full_path)) {
            $missing_files[] = [
                'file' => $file_path,
                'description' => $description
            ];
            
            // 找出哪個步驟會生成這個檔案
            foreach ($step_deps['depends_on'] as $dep_step) {
                if (!in_array($dep_step, $missing_steps)) {
                    $missing_steps[] = $dep_step;
                }
            }
        }
    }
    
    // 如果有缺失的檔案
    if (!empty($missing_files)) {
        $deployer->log("錯誤: 步驟 {$current_step} 缺少必要的前置檔案：");
        foreach ($missing_files as $missing) {
            $deployer->log("  - {$missing['file']} ({$missing['description']})");
        }
        
        $deployer->log("\n請先執行以下步驟：");
        foreach ($missing_steps as $step) {
            $deployer->log("  php contenta-deploy.php {$job_id} --step={$step}");
        }
        
        return [
            'status' => 'error',
            'message' => '缺少前置步驟的輸出檔案',
            'missing_steps' => $missing_steps,
            'missing_files' => $missing_files
        ];
    }
    
    return ['status' => 'ok'];
}

/**
 * 在步驟開始時進行依賴檢查
 * 
 * 使用範例：
 * $dep_check = checkStepDependencies('02', $work_dir, $deployer);
 * if ($dep_check['status'] !== 'ok') {
 *     return $dep_check;
 * }
 */