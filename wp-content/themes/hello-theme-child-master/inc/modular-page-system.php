<?php
/**
 * @file modular-page-system.php
 * @description 模組化頁面管理系統 - 動態切換 Container 樣板
 * @features 支援：樣板管理、動態載入、後台選擇介面
 */

class ModularPageSystem {
    
    /**
     * 可用的區塊類型和樣板 - 可自由調整數量
     */
    private $section_templates = [
        'header' => [
            'header001' => ['name' => 'Header 001 - 簡約導航', 'template_id' => ''],
            'header002' => ['name' => 'Header 002 - 大型導航', 'template_id' => ''],
            'header003' => ['name' => 'Header 003 - 置中導航', 'template_id' => '']
        ],
        'hero' => [
            'hero001' => ['name' => 'Hero 001 - 左文右圖', 'template_id' => ''],
            'hero002' => ['name' => 'Hero 002 - 居中文字', 'template_id' => '']
            // hero 只有 2 款
        ],
        'about' => [
            'about001' => ['name' => 'About 001 - 左圖右文', 'template_id' => ''],
            'about002' => ['name' => 'About 002 - 上文下圖', 'template_id' => ''],
            'about003' => ['name' => 'About 003 - 卡片式', 'template_id' => ''],
            'about004' => ['name' => 'About 004 - 時間軸', 'template_id' => '']
            // about 有 4 款
        ],
        'service' => [
            'service001' => ['name' => 'Service 001 - 三欄格子', 'template_id' => ''],
            'service002' => ['name' => 'Service 002 - 橫向卡片', 'template_id' => ''],
            'service003' => ['name' => 'Service 003 - 圓形圖示', 'template_id' => '']
        ],
        'archive' => [
            'archive001' => ['name' => 'Archive 001 - 格子布局', 'template_id' => '']
            // archive 只有 1 款
        ],
        'footer' => [
            'footer001' => ['name' => 'Footer 001 - 簡約', 'template_id' => ''],
            'footer002' => ['name' => 'Footer 002 - 四欄資訊', 'template_id' => ''],
            'footer003' => ['name' => 'Footer 003 - 社群重點', 'template_id' => '']
        ]
    ];

    /**
     * 預設的區塊選擇 - 自動適應可用樣板
     */
    private function get_default_selections() {
        $defaults = [];
        foreach ($this->section_templates as $section_type => $templates) {
            // 自動選擇每個區塊的第一個樣板作為預設
            $template_keys = array_keys($templates);
            $defaults['homepage_' . $section_type] = reset($template_keys);
        }
        return $defaults;
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_default_selections']);
        
        // 註冊 Elementor 動態標籤
        add_action('elementor/dynamic_tags/register_tags', [$this, 'register_elementor_dynamic_tags']);
        
        // 處理 AJAX 請求
        add_action('wp_ajax_save_template_selection', [$this, 'save_template_selection']);
    }

    /**
     * 註冊預設選擇
     */
    public function register_default_selections() {
        $default_selections = $this->get_default_selections();
        
        foreach ($default_selections as $option_name => $default_value) {
            register_setting('modular_page_settings', $option_name);
            
            if (get_option($option_name) === false) {
                update_option($option_name, $default_value);
            }
        }
    }

    /**
     * 新增管理員選單
     */
    public function add_admin_menu() {
        add_theme_page(
            '模組化頁面管理',
            '頁面模組', 
            'manage_options',
            'modular-page-manager',
            [$this, 'render_admin_page']
        );
    }

    /**
     * 註冊設定
     */
    public function register_settings() {
        $default_selections = $this->get_default_selections();
        foreach (array_keys($default_selections) as $option_name) {
            register_setting('modular_page_settings', $option_name);
        }
    }

    /**
     * 渲染管理員頁面
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>模組化頁面管理系統</h1>
            <p>選擇首頁各區塊要使用的樣板。每個區塊都有多款設計可選擇。</p>
            
            <form method="post" action="options.php">
                <?php settings_fields('modular_page_settings'); ?>
                
                <div class="modular-sections">
                    <?php foreach ($this->section_templates as $section_type => $templates): ?>
                        <div class="modular-section">
                            <h2><?php echo $this->get_section_title($section_type); ?></h2>
                            
                            <div class="template-selector">
                                <?php
                                $default_selections = $this->get_default_selections();
                                $current_selection = get_option('homepage_' . $section_type, $default_selections['homepage_' . $section_type]);
                                ?>
                                
                                <div class="template-options">
                                    <?php foreach ($templates as $template_key => $template_info): ?>
                                        <label class="template-option">
                                            <input type="radio" 
                                                   name="homepage_<?php echo $section_type; ?>" 
                                                   value="<?php echo $template_key; ?>"
                                                   <?php checked($current_selection, $template_key); ?> />
                                            
                                            <div class="template-preview">
                                                <div class="template-thumbnail">
                                                    <div class="placeholder-<?php echo $section_type; ?>">
                                                        <?php echo $template_key; ?>
                                                    </div>
                                                </div>
                                                <div class="template-info">
                                                    <h4><?php echo $template_info['name']; ?></h4>
                                                    <p class="template-id">ID: <?php echo $template_key; ?></p>
                                                </div>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- 顯示樣板數量資訊 -->
                                <div class="template-count">
                                    <small style="color: #666;">
                                        <?php echo count($templates); ?> 款樣板可選擇
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php submit_button('儲存樣板選擇'); ?>
            </form>

            <div class="current-selections">
                <h2>目前選擇的樣板</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>區塊</th>
                            <th>樣板 ID</th>
                            <th>樣板名稱</th>
                            <th>選擇理由</th>
                            <th>動態標籤使用</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->section_templates as $section_type => $templates): ?>
                            <?php 
                            $current = get_option('homepage_' . $section_type);
                            $template_name = isset($templates[$current]) ? $templates[$current]['name'] : '未知';
                            $reasoning_data = get_option('homepage_' . $section_type . '_reasoning', null);
                            $reasoning = '';
                            if ($reasoning_data && isset($reasoning_data['reasoning'])) {
                                $reasoning = $reasoning_data['reasoning'];
                                $source = isset($reasoning_data['source']) ? $reasoning_data['source'] : 'manual';
                                $reasoning .= ' <small style="color: #666;">(' . ($source === 'gpt_selection' ? 'GPT AI' : 'AI分析') . ')</small>';
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo $this->get_section_title($section_type); ?></strong></td>
                                <td><code><?php echo $current; ?></code></td>
                                <td><?php echo $template_name; ?></td>
                                <td><?php echo $reasoning ?: '<span style="color: #999;">手動選擇</span>'; ?></td>
                                <td><code>[modular_section type="<?php echo $section_type; ?>"]</code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="usage-instructions">
                <h2>使用說明</h2>
                <ol>
                    <li><strong>建立樣板：</strong>在 Elementor 中建立不同的 Container 樣板</li>
                    <li><strong>記錄 ID：</strong>將樣板的 Elementor Template ID 記錄下來</li>
                    <li><strong>設定對應：</strong>在下方的樣板 ID 設定中，將 ID 對應到相應的樣板</li>
                    <li><strong>選擇樣板：</strong>在上方選擇要使用的樣板</li>
                    <li><strong>使用動態標籤：</strong>在頁面中使用 Modular Section 動態標籤載入內容</li>
                </ol>
            </div>
        </div>

        <style>
        .modular-sections {
            display: grid;
            gap: 30px;
            margin: 20px 0;
        }

        .modular-section {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
        }

        .modular-section h2 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }

        .template-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        /* 針對不同數量的樣板調整佈局 */
        .template-options:has(label:nth-child(1):last-child) {
            /* 只有 1 個樣板 */
            grid-template-columns: minmax(200px, 300px);
            justify-content: center;
        }

        .template-options:has(label:nth-child(2):last-child) {
            /* 只有 2 個樣板 */
            grid-template-columns: repeat(2, minmax(200px, 1fr));
            max-width: 600px;
        }

        .template-options:has(label:nth-child(4):last-child) {
            /* 有 4 個樣板 */
            grid-template-columns: repeat(2, minmax(200px, 1fr));
        }

        .template-count {
            margin-top: 10px;
            text-align: center;
            padding: 5px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .template-option {
            cursor: pointer;
            display: block;
        }

        .template-preview {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
            background: white;
        }

        .template-option input[type="radio"]:checked + .template-preview {
            border-color: #0073aa;
            background: #f0f6fc;
        }

        .template-option input[type="radio"] {
            display: none;
        }

        .template-thumbnail {
            height: 100px;
            background: #f5f5f5;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            position: relative;
            overflow: hidden;
        }

        .template-thumbnail div[class^="placeholder-"] {
            font-size: 18px;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
        }

        .placeholder-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .placeholder-hero { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .placeholder-about { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; }
        .placeholder-service { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; }
        .placeholder-archive { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }
        .placeholder-footer { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333; }

        .template-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }

        .template-id {
            margin: 0;
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }

        .current-selections {
            margin-top: 40px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        .usage-instructions {
            margin-top: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0073aa;
        }

        .usage-instructions ol li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        </style>
        <?php
    }

    /**
     * 取得區塊標題
     */
    private function get_section_title($section_type) {
        $titles = [
            'header' => 'Header 頁首',
            'hero' => 'Hero 主視覺',
            'about' => 'About 關於我',
            'service' => 'Service 服務',
            'archive' => 'Archive 文章列表',
            'footer' => 'Footer 頁尾'
        ];
        
        return isset($titles[$section_type]) ? $titles[$section_type] : ucfirst($section_type);
    }

    /**
     * 註冊 Elementor 動態標籤
     */
    public function register_elementor_dynamic_tags($dynamic_tags) {
        if (class_exists('Elementor\Core\DynamicTags\Tag')) {
            // 這裡需要動態標籤的檔案
            $dynamic_tags_file = get_stylesheet_directory() . '/inc/modular-dynamic-tags.php';
            if (file_exists($dynamic_tags_file)) {
                require_once $dynamic_tags_file;
                $dynamic_tags->register_tag('Modular_Section_Dynamic_Tag');
            }
        }
    }

    /**
     * 取得目前選擇的樣板
     */
    public static function get_selected_template($section_type) {
        return get_option('homepage_' . $section_type, '');
    }

    /**
     * 取得樣板內容（預留給未來擴展）
     */
    public static function get_template_content($template_id) {
        // 這裡可以擴展為實際載入 Elementor 樣板內容
        // 目前返回樣板 ID 以供動態標籤使用
        return $template_id;
    }
}

// 初始化模組化頁面系統
new ModularPageSystem();

/**
 * 輔助函數：取得選擇的樣板
 */
function get_modular_template($section_type) {
    return ModularPageSystem::get_selected_template($section_type);
}

/**
 * 輔助函數：輸出模組化區塊
 */
function modular_section($section_type) {
    $template_id = ModularPageSystem::get_selected_template($section_type);
    echo ModularPageSystem::get_template_content($template_id);
}