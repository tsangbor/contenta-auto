<?php
/**
 * @file advanced-theme-style-switcher.php
 * @description 進階 Elementor 主題風格切換模組 - 直接修改 Elementor Global Colors
 * @features 支援：自動抓取最新 Default Kit、修改 system_colors、配色主題切換
 */

class ElementorThemeStyleSwitcher {
    
    private $color_schemes = [
        'expert-theme-1' => [
            'name' => '專家導向 1：鈦金藍 × 銀灰（科技感／專業系統類）',
            'colors' => [
                'primary' => '#1A2B4C',
                'secondary' => '#CBD5E1',
                'text' => '#1E293B',
                'accent' => '#2563EB'
            ]
        ],
        'expert-theme-2' => [
            'name' => '專家導向 2：黑金銅 × 暖感奢華（精品顧問／高價值感）',
            'colors' => [
                'primary' => '#3B2F2F',
                'secondary' => '#D6C39A',
                'text' => '#3B2F2F',
                'accent' => '#B7791F'
            ]
        ],
        'expert-theme-3' => [
            'name' => '專家導向 3：濃墨綠 × 銀湖藍（理性專業／ESG 顧問類）',
            'colors' => [
                'primary' => '#22372B',
                'secondary' => '#B8D8D8',
                'text' => '#2A3C34',
                'accent' => '#3AA17E'
            ]
        ],
        'expert-theme-4' => [
            'name' => '專家導向 4：橘磚紅 × 霧灰（品牌經營／設計師導向）',
            'colors' => [
                'primary' => '#B64926',
                'secondary' => '#D3D3D3',
                'text' => '#4B2E21',
                'accent' => '#D97706'
            ]
        ],
        'expert-theme-5' => [
            'name' => '專家導向 5：靛紫黑 × 鉻銀（策略／金融／控管感）',
            'colors' => [
                'primary' => '#2E1A47',
                'secondary' => '#DADADA',
                'text' => '#32283C',
                'accent' => '#8B5CF6'
            ]
        ],
        'lifestyle-theme-1' => [
            'name' => '生活導向 1：春日橄欖 × 深綠對比',
            'colors' => [
                'primary' => '#A3B18A',
                'secondary' => '#DAD7CD',
                'text' => '#3B4B2B',
                'accent' => '#6B8E23'
            ]
        ],
        'lifestyle-theme-2' => [
            'name' => '生活導向 2：柔粉米 × 木紅對比',
            'colors' => [
                'primary' => '#FCE5CD',
                'secondary' => '#FFF8F0',
                'text' => '#6A3B2E',
                'accent' => '#C94C4C'
            ]
        ],
        'lifestyle-theme-3' => [
            'name' => '生活導向 3：海岸藍綠 × 深藍對比',
            'colors' => [
                'primary' => '#9AD1D4',
                'secondary' => '#E3F2FD',
                'text' => '#1C3B5A',
                'accent' => '#0077B6'
            ]
        ],
        'lifestyle-theme-4' => [
            'name' => '生活導向 4：黃昏杏橘 × 焦糖棕對比',
            'colors' => [
                'primary' => '#FFBC80',
                'secondary' => '#FFF2E0',
                'text' => '#5C3A21',
                'accent' => '#E76F51'
            ]
        ],
        'lifestyle-theme-5' => [
            'name' => '生活導向 5：湖水粉藍 × 暗靛跳色',
            'colors' => [
                'primary' => '#B5EAEA',
                'secondary' => '#EDF6F9',
                'text' => '#223344',
                'accent' => '#3D5A80'
            ]
        ]
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('body_class', [$this, 'add_body_class']);
        add_action('wp_head', [$this, 'inject_custom_styles']);
        
        // AJAX 處理
        add_action('wp_ajax_apply_theme_colors', [$this, 'apply_theme_colors']);
    }

    /**
     * 取得最新的 Default Kit ID
     */
    private function get_latest_default_kit_id() {
        global $wpdb;
        
        $query = "
            SELECT p.ID 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'elementor_library'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_elementor_template_type'
            AND pm.meta_value = 'kit'
            ORDER BY p.post_date DESC
            LIMIT 1
        ";
        
        return $wpdb->get_var($query);
    }

    /**
     * 取得 Kit 的 page_settings
     */
    private function get_kit_page_settings($kit_id) {
        $settings_data = get_post_meta($kit_id, '_elementor_page_settings', true);
        
        if (empty($settings_data)) {
            return [];
        }
        
        // 如果是序列化資料，解序列化它
        if (is_string($settings_data)) {
            $settings_data = maybe_unserialize($settings_data);
        }
        
        return $settings_data;
    }

    /**
     * 更新 Kit 的 system_colors 和 typography
     */
    private function update_kit_system_colors($kit_id, $new_colors, $font_family = null) {
        $settings = $this->get_kit_page_settings($kit_id);
        
        // 確保 system_colors 結構存在
        if (!isset($settings['system_colors'])) {
            $settings['system_colors'] = [];
        }
        
        // 更新顏色設定
        $color_mapping = [
            'primary' => 0,
            'secondary' => 1, 
            'text' => 2,
            'accent' => 3
        ];
        
        foreach ($new_colors as $color_name => $color_value) {
            if (isset($color_mapping[$color_name])) {
                $index = $color_mapping[$color_name];
                
                // 確保該索引的顏色設定存在
                if (!isset($settings['system_colors'][$index])) {
                    $settings['system_colors'][$index] = [
                        '_id' => uniqid(),
                        'title' => ucfirst($color_name),
                        'color' => $color_value
                    ];
                } else {
                    $settings['system_colors'][$index]['color'] = $color_value;
                }
            }
        }
        
        // 更新字體設定
        if ($font_family) {
            $settings = $this->update_typography_font_family($settings, $font_family);
        }
        
        // 更新資料庫
        update_post_meta($kit_id, '_elementor_page_settings', $settings);
        
        // 清除 Elementor 快取
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        return true;
    }

    /**
     * 遞迴更新所有 typography_font_family 設定
     */
    private function update_typography_font_family($data, $new_font_family) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key === 'typography_font_family') {
                    $data[$key] = $new_font_family;
                } elseif (is_array($value)) {
                    $data[$key] = $this->update_typography_font_family($value, $new_font_family);
                }
            }
        }
        return $data;
    }

    /**
     * 新增管理員選單
     */
    public function add_admin_menu() {
        add_theme_page(
            '進階配色與樣式設定', 
            '進階配色設定', 
            'manage_options', 
            'advanced-theme-style-settings', 
            [$this, 'render_admin_page']
        );
    }

    /**
     * 註冊設定
     */
    public function register_settings() {
        register_setting('advanced_theme_style_settings_group', 'theme_color_class');
        register_setting('advanced_theme_style_settings_group', 'theme_logo_light');
        register_setting('advanced_theme_style_settings_group', 'theme_logo_dark');
        register_setting('advanced_theme_style_settings_group', 'theme_font_family');
    }

    /**
     * 渲染管理員頁面
     */
    public function render_admin_page() {
        $kit_id = $this->get_latest_default_kit_id();
        $kit_settings = $kit_id ? $this->get_kit_page_settings($kit_id) : [];
        
        ?>
        <div class="wrap">
            <h1>進階網站配色與樣式設定</h1>
            
            <?php if ($kit_id): ?>
                <div class="notice notice-info">
                    <p><strong>目前使用的 Default Kit ID:</strong> <?php echo $kit_id; ?></p>
                </div>
            <?php else: ?>
                <div class="notice notice-warning">
                    <p><strong>警告:</strong> 找不到 Default Kit，請確認 Elementor 已正確安裝。</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('advanced_theme_style_settings_group');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">配色組合</th>
                        <td>
                            <?php $this->render_color_select(); ?>
                            <p class="description">選擇配色後將自動更新 Elementor Global Colors</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">淺底 Logo URL</th>
                        <td>
                            <input type="text" name="theme_logo_light" value="<?php echo esc_attr(get_option('theme_logo_light')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">深底 Logo URL</th>
                        <td>
                            <input type="text" name="theme_logo_dark" value="<?php echo esc_attr(get_option('theme_logo_dark')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">網站主字體</th>
                        <td>
                            <?php $this->render_font_select(); ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('儲存設定'); ?>
            </form>
            
            <?php if ($kit_id): ?>
                <div class="postbox">
                    <h2 class="hndle">立即套用配色與字體到 Elementor</h2>
                    <div class="inside">
                        <p>點擊下方按鈕可立即將選定的配色與字體套用到 Elementor Global Settings：</p>
                        <button type="button" id="apply-colors-btn" class="button button-primary">
                            立即套用配色與字體到 Elementor
                        </button>
                        <div id="apply-result"></div>
                    </div>
                </div>
            <?php endif; ?>
            
            <script>
            jQuery(document).ready(function($) {
                $('#apply-colors-btn').click(function() {
                    var selectedTheme = $('select[name="theme_color_class"]').val();
                    var $btn = $(this);
                    var $result = $('#apply-result');
                    
                    $btn.prop('disabled', true).text('套用中...');
                    $result.html('<div class="notice notice-info"><p>正在套用配色與字體...</p></div>');
                    
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'apply_theme_colors',
                            theme: selectedTheme,
                            font_family: $('select[name="theme_font_family"]').val(),
                            kit_id: <?php echo intval($kit_id); ?>,
                            _ajax_nonce: '<?php echo wp_create_nonce('apply_theme_colors'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            } else {
                                $result.html('<div class="notice notice-error"><p>錯誤: ' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $result.html('<div class="notice notice-error"><p>發生未知錯誤</p></div>');
                        },
                        complete: function() {
                            $btn.prop('disabled', false).text('立即套用配色與字體到 Elementor');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * 渲染配色選擇器
     */
    private function render_color_select() {
        $current = get_option('theme_color_class', 'expert-theme-1');
        echo '<select name="theme_color_class">';
        foreach ($this->color_schemes as $key => $scheme) {
            $selected = ($current === $key) ? 'selected' : '';
            echo "<option value='$key' $selected>{$scheme['name']}</option>";
        }
        echo '</select>';
    }

    /**
     * 渲染字體選擇器
     */
    private function render_font_select() {
        $current = get_option('theme_font_family', 'Noto Sans TC');
        $fonts = [
            'Noto Sans TC' => 'Noto Sans TC（預設）',
            'Roboto' => 'Roboto',
            'Open Sans' => 'Open Sans',
            '思源黑體' => '思源黑體',
            '微軟正黑體' => '微軟正黑體',
            'Lato' => 'Lato',
            'Playfair Display' => 'Playfair Display',
        ];
        echo '<select name="theme_font_family">';
        foreach ($fonts as $val => $label) {
            $selected = ($current === $val) ? 'selected' : '';
            echo "<option value='$val' $selected>$label</option>";
        }
        echo '</select>';
    }

    /**
     * AJAX 處理：套用主題配色與字體
     */
    public function apply_theme_colors() {
        // 驗證 nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'apply_theme_colors')) {
            wp_die('安全驗證失敗');
        }
        
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die('權限不足');
        }
        
        $theme = sanitize_text_field($_POST['theme']);
        $font_family = sanitize_text_field($_POST['font_family']);
        $kit_id = intval($_POST['kit_id']);
        
        if (!isset($this->color_schemes[$theme])) {
            wp_send_json_error(['message' => '無效的主題選擇']);
        }
        
        if (!$kit_id) {
            wp_send_json_error(['message' => '找不到 Default Kit']);
        }
        
        $colors = $this->color_schemes[$theme]['colors'];
        $result = $this->update_kit_system_colors($kit_id, $colors, $font_family);
        
        if ($result) {
            // 同時更新選項
            update_option('theme_color_class', $theme);
            update_option('theme_font_family', $font_family);
            
            wp_send_json_success([
                'message' => '配色與字體已成功套用到 Elementor Global Settings！',
                'theme' => $this->color_schemes[$theme]['name'],
                'colors' => $colors,
                'font_family' => $font_family
            ]);
        } else {
            wp_send_json_error(['message' => '套用配色與字體時發生錯誤']);
        }
    }

    /**
     * 新增 body class
     */
    public function add_body_class($classes) {
        $classes[] = sanitize_html_class(get_option('theme_color_class', 'expert-theme-1'));
        return $classes;
    }

    /**
     * 注入自訂樣式
     */
    public function inject_custom_styles() {
        $font = esc_html(get_option('theme_font_family', 'Noto Sans TC'));
        $theme = get_option('theme_color_class', 'expert-theme-1');
        
        if (isset($this->color_schemes[$theme])) {
            $colors = $this->color_schemes[$theme]['colors'];
            
            echo "<style>
                body { font-family: '{$font}', sans-serif; }
                .{$theme} {
                    --e-global-color-primary: {$colors['primary']};
                    --e-global-color-secondary: {$colors['secondary']};
                    --e-global-color-text: {$colors['text']};
                    --e-global-color-accent: {$colors['accent']};
                }
            </style>";
        }
    }
}

// 初始化
new ElementorThemeStyleSwitcher();