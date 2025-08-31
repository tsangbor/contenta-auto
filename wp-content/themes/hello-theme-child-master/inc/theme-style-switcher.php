<?php
/**
 * 進階主題樣式切換器
 * 
 * 提供 Elementor 主題風格切換功能，支援直接修改 Global Colors
 * 包含配色主題切換、字體設定、Logo 切換等功能
 * 
 * @package HelloElementorChild
 * @subpackage Modules/ThemeStyleSwitcher
 * @version 1.3.0
 * @since 2.0.0
 * @author Your Name
 * 
 * === WP-CLI 配色切換指令使用指南 ===
 * 
 * 本模組提供強大的 WP-CLI 指令來管理 Elementor 配色和字體，
 * 可直接修改 Elementor Global Colors 並立即應用到整個網站。
 * 
 * 🎨 基本配色操作指令：
 * 
 * 1. 📋 列出所有可用配色方案
 *    wp theme colors list --allow-root
 *    # 顯示 10 種配色方案的完整表格（5種專家導向 + 5種生活導向）
 * 
 * 2. 🔍 查看目前使用的配色
 *    wp theme colors current --allow-root
 *    # 顯示目前配色方案、字體和顏色詳細資訊
 * 
 * 3. 🎯 切換配色方案（核心功能）
 *    wp theme colors switch <配色key> --allow-root
 *    # 範例：wp theme colors switch expert-theme-1 --allow-root
 *    # 範例：wp theme colors switch lifestyle-theme-2 --allow-root
 * 
 * 4. 🔤 切換配色並同時更改字體
 *    wp theme colors switch <配色key> --font="字體名稱" --allow-root
 *    # 範例：wp theme colors switch expert-theme-1 --font="Roboto" --allow-root
 *    # 範例：wp theme colors switch lifestyle-theme-3 --font="思源黑體" --allow-root
 * 
 * 5. 👁️ 預覽模式（不實際執行）
 *    wp theme colors switch <配色key> --dry-run --allow-root
 *    # 查看將要執行的操作而不實際套用
 * 
 * 🔧 系統管理指令：
 * 
 * 6. 📊 檢查 Elementor Kit 狀態
 *    wp theme colors kit-info --allow-root
 *    # 顯示 Kit ID 和目前的 system_colors 設定
 * 
 * 7. 🧹 清除 Elementor 快取
 *    wp theme colors clear-cache --allow-root
 *    # 清除 Elementor 檔案快取和 WordPress 物件快取
 * 
 * 8. ⚙️ 控制樣式覆蓋狀態
 *    wp theme colors override status --allow-root
 *    wp theme colors override disable --allow-root
 *    wp theme colors override enable --allow-root
 *    # 停用/啟用主題樣式覆蓋，讓 Elementor 全域配色正常運作
 * 
 * 9. 🎨 管理自訂配色 CSS
 *    wp theme colors custom get --allow-root
 *    wp theme colors custom set "--e-global-color-primary: #727171;" --allow-root
 *    wp theme colors custom clear --allow-root
 *    wp theme colors custom validate --allow-root
 *    # 管理手動輸入的自訂配色 CSS 變數
 * 
 * === 可用配色方案列表 ===
 * 
 * 🎯 專家導向配色（適合專業服務、顧問、企業）：
 * • expert-theme-1   - 🟦 鈦金藍 × 銀灰系（科技感／專業系統類）
 * • expert-theme-2   - 🟫 黑金銅 × 暖感奢華（精品顧問／高價值感）
 * • expert-theme-3   - 🟩 濃墨綠 × 銀湖藍（理性專業／ESG 顧問類）
 * • expert-theme-4   - 🟧 橘磚紅 × 霧灰（品牌經營／設計師導向）
 * • expert-theme-5   - 🟪 靛紫黑 × 鉻銀（策略／金融／控管感）
 * 
 * 🌿 生活導向配色（適合個人品牌、生活服務、創意工作）：
 * • lifestyle-theme-1 - 🟢 春日橄欖 × 深綠對比
 * • lifestyle-theme-2 - 🧡 柔粉米 × 木紅對比
 * • lifestyle-theme-3 - 🔵 海岸藍綠 × 深藍對比
 * • lifestyle-theme-4 - 🟤 黃昏杏橘 × 焦糖棕對比
 * • lifestyle-theme-5 - 🔷 湖水粉藍 × 暗靛跳色
 * 
 * === 支援的字體選項 ===
 * 
 * • "Noto Sans TC" （預設中文字體）
 * • "Roboto" （現代無襯線）
 * • "Open Sans" （易讀無襯線）
 * • "思源黑體" （Adobe 中文字體）
 * • "微軟正黑體" （Windows 中文字體）
 * • "Lato" （優雅無襯線）
 * • "Playfair Display" （古典襯線）
 * 
 * === 完整部署工作流程 ===
 * 
 * 新網站配色設定建議流程：
 * 1. wp theme colors current --allow-root           # 檢查目前狀態
 * 2. wp theme colors list --allow-root              # 瀏覽所有配色選項
 * 3. wp theme colors switch expert-theme-1 --dry-run --allow-root  # 預覽配色
 * 4. wp theme colors switch expert-theme-1 --font="Roboto" --allow-root  # 套用配色和字體
 * 5. wp theme colors kit-info --allow-root          # 驗證設定結果
 * 6. wp theme colors clear-cache --allow-root       # 清除快取確保生效
 * 
 * === 顏色對應說明 ===
 * 
 * 每個配色方案包含 4 個主要顏色，會自動對應到 Elementor Global Colors：
 * • primary   → Global Color Primary   (主要色彩)
 * • secondary → Global Color Secondary (次要色彩)
 * • text      → Global Color Text      (文字顏色)
 * • accent    → Global Color Accent    (強調色彩)
 * 
 * === 技術實作細節 ===
 * 
 * 指令執行時的系統操作：
 * 1. 自動偵測最新的 Elementor Default Kit ID
 * 2. 直接修改 Kit 的 _elementor_page_settings meta 資料
 * 3. 更新 system_colors 陣列中的顏色值
 * 4. 遞迴更新所有 typography_font_family 設定
 * 5. 同步更新 WordPress 選項（theme_color_class, theme_font_family）
 * 6. 清除 Elementor 檔案快取和 WordPress 物件快取
 * 
 * === 故障排除 ===
 * 
 * 如果指令執行失敗，請檢查：
 * 1. Elementor 外掛是否正確安裝並啟用
 * 2. 是否存在 Default Kit（wp theme colors kit-info 檢查）
 * 3. 用戶是否有足夠權限（需要 manage_options 權限）
 * 4. 配色 key 是否正確（wp theme colors list 查看所有可用選項）
 * 
 * Features:
 * - 自動抓取最新 Default Kit
 * - 修改 Elementor system_colors
 * - 配色主題切換（專家導向、生活導向）
 * - 字體設定與即時套用
 * - Logo 切換（淺底/深底）
 * - 完整 WP-CLI 指令支援
 * - 預覽模式和批次操作
 * 
 * Changelog:
 * 1.3.0 - 2025-07-18
 * - 新增手動輸入自訂配色功能
 * - 支援直接輸入 CSS 變數定義配色方案
 * - 管理介面新增自訂配色文字框和輔助按鈕
 * - CSS 解析和安全驗證機制
 * - WP-CLI 新增 custom 指令管理自訂配色
 * - 載入範例配色和格式驗證功能
 * 
 * 1.2.0 - 2025-07-18
 * - 新增樣式覆蓋停用機制
 * - 允許用戶停用主題樣式覆蓋，讓 Elementor 全域配色正常運作
 * - 管理介面新增停用開關選項
 * - WP-CLI 新增 override 指令控制停用/啟用狀態
 * - 修正 current 指令的變數名稱錯誤
 * 
 * 1.1.1 - 2025-07-07
 * - 新增完整 WP-CLI 指令使用指南
 * - 詳細的配色方案和字體選項說明
 * - 新增部署工作流程和故障排除指引
 * - 強化開發者文檔和技術實作說明
 * 
 * 1.1.0 - 2025-07-06
 * - 更新配色方案至 v3 版本
 * - 新增 Emoji 圖示標識配色類型
 * - 專家導向配色：5種專業配色方案
 * - 生活導向配色：5種溫馨配色方案
 * - 改善配色方案名稱描述
 * 
 * 1.0.0 - 2025-01-06
 * - 初始版本
 * - 基本樣式切換功能
 * - 10 種預設配色方案
 * - Elementor Global Colors 整合
 * - 字體設定功能
 * - 管理介面整合
 * - AJAX 即時套用功能
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ElementorThemeStyleSwitcher Class
 * 
 * 主要負責處理主題樣式切換的核心功能
 * 
 * @since 2.0.0
 * @version 1.1.0
 */
class ElementorThemeStyleSwitcher {
    
    /**
     * 預設配色方案（v3 更新版）
     * 
     * 包含專家導向和生活導向兩大類配色系統
     * 每組配色都針對特定的品牌定位和使用場景
     * 
     * @var array
     * @since 1.0.0
     * @version 1.1.0 - 2025-07-06 更新配色方案至 v3
     */
    private $color_schemes = [
        // 🎨 專家導向配色組合（v3）- 背景預設為白底
        'expert-theme-1' => [
            'name' => '🟦 專家導向 1：鈦金藍 × 銀灰系（科技感／專業系統類）',
            'colors' => [
                'primary' => '#1A2B4C',
                'secondary' => '#CBD5E1',
                'text' => '#1E293B',
                'accent' => '#2563EB'
            ]
        ],
        'expert-theme-2' => [
            'name' => '🟫 專家導向 2：黑金銅 × 暖感奢華（精品顧問／高價值感）',
            'colors' => [
                'primary' => '#3B2F2F',
                'secondary' => '#D6C39A',
                'text' => '#3B2F2F',
                'accent' => '#B7791F'
            ]
        ],
        'expert-theme-3' => [
            'name' => '🟩 專家導向 3：濃墨綠 × 銀湖藍（理性專業／ESG 顧問類）',
            'colors' => [
                'primary' => '#22372B',
                'secondary' => '#B8D8D8',
                'text' => '#2A3C34',
                'accent' => '#3AA17E'
            ]
        ],
        'expert-theme-4' => [
            'name' => '🟧 專家導向 4：橘磚紅 × 霧灰（品牌經營／設計師導向）',
            'colors' => [
                'primary' => '#B64926',
                'secondary' => '#D3D3D3',
                'text' => '#4B2E21',
                'accent' => '#D97706'
            ]
        ],
        'expert-theme-5' => [
            'name' => '🟪 專家導向 5：靛紫黑 × 鉻銀（策略／金融／控管感）',
            'colors' => [
                'primary' => '#2E1A47',
                'secondary' => '#DADADA',
                'text' => '#32283C',
                'accent' => '#8B5CF6'
            ]
        ],
        
        // 🌿 生活導向配色組合（v3）
        'lifestyle-theme-1' => [
            'name' => '🟢 生活導向 1：春日橄欖 × 深綠對比',
            'colors' => [
                'primary' => '#A3B18A',
                'secondary' => '#DAD7CD',
                'text' => '#3B4B2B',
                'accent' => '#6B8E23'
            ]
        ],
        'lifestyle-theme-2' => [
            'name' => '🧡 生活導向 2：柔粉米 × 木紅對比',
            'colors' => [
                'primary' => '#FCE5CD',
                'secondary' => '#FFF8F0',
                'text' => '#6A3B2E',
                'accent' => '#C94C4C'
            ]
        ],
        'lifestyle-theme-3' => [
            'name' => '🔵 生活導向 3：海岸藍綠 × 深藍對比',
            'colors' => [
                'primary' => '#9AD1D4',
                'secondary' => '#E3F2FD',
                'text' => '#1C3B5A',
                'accent' => '#0077B6'
            ]
        ],
        'lifestyle-theme-4' => [
            'name' => '🟤 生活導向 4：黃昏杏橘 × 焦糖棕對比',
            'colors' => [
                'primary' => '#FFBC80',
                'secondary' => '#FFF2E0',
                'text' => '#5C3A21',
                'accent' => '#E76F51'
            ]
        ],
        'lifestyle-theme-5' => [
            'name' => '🔷 生活導向 5：湖水粉藍 × 暗靛跳色',
            'colors' => [
                'primary' => '#B5EAEA',
                'secondary' => '#EDF6F9',
                'text' => '#223344',
                'accent' => '#3D5A80'
            ]
        ]
    ];

    /**
     * 建構函式
     * 
     * @since 1.0.0
     */
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
        register_setting('advanced_theme_style_settings_group', 'theme_style_override_disabled');
        register_setting('advanced_theme_style_settings_group', 'theme_custom_colors_css');
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
                    <tr>
                        <th scope="row">停用樣式覆蓋</th>
                        <td>
                            <label>
                                <input type="checkbox" name="theme_style_override_disabled" value="1" <?php checked(get_option('theme_style_override_disabled'), 1); ?> />
                                停用主題樣式覆蓋功能
                            </label>
                            <p class="description">
                                <strong>⚠️ 重要：</strong>勾選此項將停用所有主題樣式覆蓋，讓您可以在 Elementor 中自由設定全域配色。<br>
                                停用後，主題將不會注入任何 CSS 變數覆蓋 Elementor 的全域配色設定。
                            </p>
                        </td>
                    </tr>
                    <tr id="custom_colors_row" style="<?php echo (get_option('theme_color_class', 'expert-theme-1') !== 'custom') ? 'display: none;' : ''; ?>">
                        <th scope="row">自訂配色 CSS</th>
                        <td>
                            <textarea name="theme_custom_colors_css" rows="15" cols="80" class="large-text code" 
                                      placeholder="請輸入 Elementor 全域配色的 CSS 變數定義...&#10;例如：&#10;--e-global-color-primary: #727171;&#10;--e-global-color-secondary: #B3ADA0;&#10;--e-global-color-text: #1C1C1C;&#10;--e-global-color-accent: #D56C4A;&#10;--e-global-color-background: #FCFAF2;&#10;--e-global-color-backgroundAccent: #434343;&#10;--e-global-color-transparent: #00000000;"
                                      style="font-family: monospace; font-size: 13px;"><?php echo esc_textarea(get_option('theme_custom_colors_css', '')); ?></textarea>
                            <p class="description">
                                <strong>🎨 自訂配色說明：</strong>在此輸入 Elementor 全域配色的 CSS 變數定義。<br>
                                • 每行一個 CSS 變數，格式：<code>--變數名稱: 值;</code><br>
                                • 支援的變數：<code>--e-global-color-primary</code>、<code>--e-global-color-secondary</code> 等<br>
                                • 顏色格式支援：十六進位 (#FFFFFF)、RGB、HSL 等<br>
                                • 註解以 <code>/*</code> 開頭，可用於說明配色風格
                            </p>
                            <p>
                                <button type="button" id="load_sample_colors" class="button">載入範例配色</button>
                                <button type="button" id="clear_custom_colors" class="button">清空內容</button>
                                <button type="button" id="validate_css" class="button">驗證 CSS</button>
                            </p>
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
                // 控制自訂配色區域的顯示/隱藏
                $('#theme_color_class_select').on('change', function() {
                    var selectedValue = $(this).val();
                    if (selectedValue === 'custom') {
                        $('#custom_colors_row').show();
                    } else {
                        $('#custom_colors_row').hide();
                    }
                });
                
                // 載入範例配色
                $('#load_sample_colors').click(function() {
                    var sampleCSS = `/* 風格：靜默的權威 (Quiet Authority) */
--e-global-color-primary: #727171;          /* 主色: 鈍色 (Nibi-iro) - 不搶眼的標示 */
--e-global-color-secondary: #B3ADA0;         /* 次色: 白鼠 (Shironezumi) - 更輕的層次 */
--e-global-color-text: #1C1C1C;            /* 文字: 墨 (Sumi) - 知識的載體 */
--e-global-color-accent: #D56C4A;           /* 點綴: 真赭 (Masoho) - 如同印章般的點睛一筆 */
--e-global-color-background: #FCFAF2;        /* 背景: 白練 (Shironeri) - 溫潤的紙白 */
--e-global-color-backgroundAccent: #434343; /* 區塊背景: 消炭色 (Keshizumi-iro) - 深沉的基底 */
--e-global-color-transparent: #00000000;`;
                    $('textarea[name="theme_custom_colors_css"]').val(sampleCSS);
                });
                
                // 清空自訂配色內容
                $('#clear_custom_colors').click(function() {
                    if (confirm('確定要清空自訂配色內容嗎？')) {
                        $('textarea[name="theme_custom_colors_css"]').val('');
                    }
                });
                
                // 驗證 CSS
                $('#validate_css').click(function() {
                    var cssContent = $('textarea[name="theme_custom_colors_css"]').val();
                    var errors = [];
                    
                    if (!cssContent.trim()) {
                        alert('請先輸入 CSS 內容');
                        return;
                    }
                    
                    // 簡單的 CSS 驗證
                    var lines = cssContent.split('\n');
                    var validVariables = [
                        '--e-global-color-primary',
                        '--e-global-color-secondary', 
                        '--e-global-color-text',
                        '--e-global-color-accent',
                        '--e-global-color-background',
                        '--e-global-color-backgroundAccent',
                        '--e-global-color-transparent'
                    ];
                    
                    lines.forEach(function(line, index) {
                        line = line.trim();
                        if (line && !line.startsWith('/*') && !line.startsWith('*/') && line !== '') {
                            // 檢查是否為有效的 CSS 變數格式
                            if (!line.match(/^--[\w-]+:\s*[^;]+;?\s*(?:\/\*.*\*\/)?$/)) {
                                errors.push('第 ' + (index + 1) + ' 行格式不正確: ' + line);
                            }
                        }
                    });
                    
                    if (errors.length === 0) {
                        alert('✅ CSS 格式驗證通過！');
                    } else {
                        alert('❌ CSS 格式錯誤：\n\n' + errors.join('\n'));
                    }
                });
                
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
        echo '<select name="theme_color_class" id="theme_color_class_select">';
        foreach ($this->color_schemes as $key => $scheme) {
            $selected = ($current === $key) ? 'selected' : '';
            echo "<option value='$key' $selected>{$scheme['name']}</option>";
        }
        // 新增自訂配色選項
        $selected = ($current === 'custom') ? 'selected' : '';
        echo "<option value='custom' $selected>🎨 自訂配色（手動輸入 CSS）</option>";
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
        // 檢查是否停用樣式覆蓋
        $is_disabled = get_option('theme_style_override_disabled', false);
        if ($is_disabled) {
            return; // 如果停用，直接返回不注入任何樣式
        }
        
        $font = esc_html(get_option('theme_font_family', 'Noto Sans TC'));
        $theme = get_option('theme_color_class', 'expert-theme-1');
        
        // 開始輸出樣式
        echo "<style>";
        echo "body { font-family: '{$font}', sans-serif; }";
        
        if ($theme === 'custom') {
            // 處理自訂配色
            $custom_css = get_option('theme_custom_colors_css', '');
            if (!empty($custom_css)) {
                $parsed_css = $this->parse_custom_css($custom_css);
                if (!empty($parsed_css)) {
                    echo ".custom { {$parsed_css} }";
                }
            }
        } elseif (isset($this->color_schemes[$theme])) {
            // 處理預設配色方案
            $colors = $this->color_schemes[$theme]['colors'];
            echo ".{$theme} {
                --e-global-color-primary: {$colors['primary']};
                --e-global-color-secondary: {$colors['secondary']};
                --e-global-color-text: {$colors['text']};
                --e-global-color-accent: {$colors['accent']};
            }";
        }
        
        echo "</style>";
    }
    
    /**
     * 解析自訂 CSS 變數
     * 
     * @param string $css_content 原始 CSS 內容
     * @return string 解析後的 CSS 變數
     * @since 1.3.0
     */
    private function parse_custom_css($css_content) {
        if (empty($css_content)) {
            return '';
        }
        
        $parsed_lines = [];
        $lines = explode("\n", $css_content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳過空行和註解
            if (empty($line) || strpos($line, '/*') === 0) {
                continue;
            }
            
            // 驗證 CSS 變數格式
            if (preg_match('/^(--[\w-]+):\s*([^;]+);?\s*(?:\/\*.*\*\/)?$/', $line, $matches)) {
                $variable = trim($matches[1]);
                $value = trim($matches[2]);
                
                // 安全檢查：只允許特定的 Elementor 全域色彩變數
                $allowed_variables = [
                    '--e-global-color-primary',
                    '--e-global-color-secondary',
                    '--e-global-color-text',
                    '--e-global-color-accent',
                    '--e-global-color-background',
                    '--e-global-color-backgroundAccent',
                    '--e-global-color-transparent'
                ];
                
                if (in_array($variable, $allowed_variables)) {
                    // 基本的值安全檢查
                    if (preg_match('/^[#a-fA-F0-9\s\(\),\.%rgbhsl]+$/', $value)) {
                        $parsed_lines[] = $variable . ': ' . esc_attr($value) . ';';
                    }
                }
            }
        }
        
        return implode(' ', $parsed_lines);
    }
}

// 初始化
new ElementorThemeStyleSwitcher();

/**
 * WP-CLI 配色切換指令支援
 * 
 * 新增 WP-CLI 指令來管理 Elementor 配色切換
 * 
 * @since 1.1.0
 */

/**
 * WP-CLI 配色管理指令類別
 * 
 * @since 1.1.0
 */
class WP_CLI_Theme_Colors_Command {
    
    /**
     * 列出所有可用的配色方案
     * 
     * ## EXAMPLES
     * 
     *     wp theme colors list
     * 
     * @since 1.1.0
     */
    public function list( $args, $assoc_args ) {
        $switcher = new ElementorThemeStyleSwitcher();
        $schemes = $this->get_color_schemes();
        
        $table_data = [];
        foreach ($schemes as $key => $scheme) {
            $table_data[] = [
                'Key' => $key,
                'Name' => $scheme['name'],
                'Primary' => $scheme['colors']['primary'],
                'Secondary' => $scheme['colors']['secondary'],
                'Text' => $scheme['colors']['text'],
                'Accent' => $scheme['colors']['accent']
            ];
        }
        
        WP_CLI\Utils\format_items('table', $table_data, ['Key', 'Name', 'Primary', 'Secondary', 'Text', 'Accent']);
    }
    
    /**
     * 取得目前使用的配色方案
     * 
     * ## EXAMPLES
     * 
     *     wp theme colors current
     * 
     * @since 1.1.0
     */
    public function current( $args, $assoc_args ) {
        $current_theme = get_option('theme_color_class', 'expert-theme-1');
        $current_font = get_option('theme_font_family', 'Noto Sans TC');
        $is_disabled = get_option('theme_style_override_disabled', false);
        $schemes = $this->get_color_schemes();
        
        WP_CLI::success("目前配色方案狀態：");
        WP_CLI::line("Key: {$current_theme}");
        WP_CLI::line("Font: {$current_font}");
        WP_CLI::line("Style Override: " . ($is_disabled ? "❌ 已停用" : "✅ 已啟用"));
        
        if ($is_disabled) {
            WP_CLI::line("");
            WP_CLI::warning("⚠️  樣式覆蓋已停用，Elementor 全域配色將不受主題影響");
        } elseif ($current_theme === 'custom') {
            // 顯示自訂配色
            WP_CLI::line("Name: 🎨 自訂配色（手動輸入 CSS）");
            $custom_css = get_option('theme_custom_colors_css', '');
            if (!empty($custom_css)) {
                WP_CLI::line("Custom CSS:");
                $lines = explode("\n", $custom_css);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (!empty($line)) {
                        WP_CLI::line("  " . $line);
                    }
                }
            } else {
                WP_CLI::warning("自訂配色內容為空");
            }
        } elseif (isset($schemes[$current_theme])) {
            WP_CLI::line("Name: {$schemes[$current_theme]['name']}");
            WP_CLI::line("Colors:");
            foreach ($schemes[$current_theme]['colors'] as $color_name => $color_value) {
                WP_CLI::line("  {$color_name}: {$color_value}");
            }
        } else {
            WP_CLI::error("找不到配色方案: {$current_theme}");
        }
    }
    
    /**
     * 切換配色方案並自動套用到 Elementor
     * 
     * ## OPTIONS
     * 
     * <scheme>
     * : 配色方案 key (例如: expert-theme-1, lifestyle-theme-2)
     * 
     * [--font=<font>]
     * : 字體名稱 (可選)
     * 
     * [--dry-run]
     * : 僅顯示將要執行的操作，不實際執行
     * 
     * ## EXAMPLES
     * 
     *     # 切換到專家導向配色 1
     *     wp theme colors switch expert-theme-1
     * 
     *     # 切換配色並變更字體
     *     wp theme colors switch lifestyle-theme-2 --font="Roboto"
     * 
     *     # 預覽將要執行的操作
     *     wp theme colors switch expert-theme-3 --dry-run
     * 
     * @since 1.1.0
     */
    public function switch( $args, $assoc_args ) {
        if (empty($args[0])) {
            WP_CLI::error("請指定配色方案 key");
        }
        
        $scheme_key = $args[0];
        $font_family = WP_CLI\Utils\get_flag_value($assoc_args, 'font', get_option('theme_font_family', 'Noto Sans TC'));
        $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        
        $schemes = $this->get_color_schemes();
        
        if (!isset($schemes[$scheme_key])) {
            WP_CLI::error("無效的配色方案: {$scheme_key}");
        }
        
        $scheme = $schemes[$scheme_key];
        $colors = $scheme['colors'];
        
        if ($dry_run) {
            WP_CLI::line("=== 預覽模式 - 將要執行的操作 ===");
            WP_CLI::line("配色方案: {$scheme['name']}");
            WP_CLI::line("字體: {$font_family}");
            WP_CLI::line("顏色配置:");
            foreach ($colors as $color_name => $color_value) {
                WP_CLI::line("  {$color_name}: {$color_value}");
            }
            return;
        }
        
        // 開始執行切換
        WP_CLI::line("正在切換配色方案...");
        
        // 1. 取得 Elementor Kit ID
        $kit_id = $this->get_latest_default_kit_id();
        if (!$kit_id) {
            WP_CLI::error("找不到 Elementor Default Kit");
        }
        
        WP_CLI::line("找到 Elementor Kit ID: {$kit_id}");
        
        // 2. 更新 Elementor Global Colors
        $success = $this->update_elementor_colors($kit_id, $colors, $font_family);
        
        if (!$success) {
            WP_CLI::error("更新 Elementor 設定失敗");
        }
        
        // 3. 更新 WordPress 選項
        update_option('theme_color_class', $scheme_key);
        update_option('theme_font_family', $font_family);
        
        // 4. 清除 Elementor 快取
        $this->clear_elementor_cache();
        
        WP_CLI::success("配色切換完成！");
        WP_CLI::line("配色方案: {$scheme['name']}");
        WP_CLI::line("字體: {$font_family}");
        WP_CLI::line("已清除 Elementor 快取");
    }
    
    /**
     * 取得 Elementor Kit 的狀態資訊
     * 
     * ## EXAMPLES
     * 
     *     wp theme colors kit-info
     * 
     * @since 1.1.0
     */
    public function kit_info( $args, $assoc_args ) {
        $kit_id = $this->get_latest_default_kit_id();
        
        if (!$kit_id) {
            WP_CLI::error("找不到 Elementor Default Kit");
        }
        
        $settings = $this->get_kit_page_settings($kit_id);
        
        WP_CLI::success("Elementor Kit 資訊:");
        WP_CLI::line("Kit ID: {$kit_id}");
        WP_CLI::line("是否有 system_colors: " . (isset($settings['system_colors']) ? 'Yes' : 'No'));
        
        if (isset($settings['system_colors'])) {
            WP_CLI::line("目前的顏色設定:");
            foreach ($settings['system_colors'] as $index => $color_setting) {
                $title = $color_setting['title'] ?? "Color {$index}";
                $color = $color_setting['color'] ?? 'N/A';
                WP_CLI::line("  {$title}: {$color}");
            }
        }
    }
    
    /**
     * 清除 Elementor 快取
     * 
     * ## EXAMPLES
     * 
     *     wp theme colors clear-cache
     * 
     * @since 1.1.0
     */
    public function clear_cache( $args, $assoc_args ) {
        $this->clear_elementor_cache();
        WP_CLI::success("Elementor 快取已清除");
    }
    
    /**
     * 控制樣式覆蓋的啟用/停用狀態
     * 
     * ## OPTIONS
     * 
     * <action>
     * : 操作類型 (enable|disable|status)
     * 
     * ## EXAMPLES
     * 
     *     # 停用樣式覆蓋
     *     wp theme colors override disable
     * 
     *     # 啟用樣式覆蓋
     *     wp theme colors override enable
     * 
     *     # 查看目前狀態
     *     wp theme colors override status
     * 
     * @since 1.2.0
     */
    public function override( $args, $assoc_args ) {
        if (empty($args[0])) {
            WP_CLI::error("請指定操作類型：enable|disable|status");
        }
        
        $action = $args[0];
        
        switch ($action) {
            case 'disable':
                update_option('theme_style_override_disabled', true);
                WP_CLI::success("✅ 主題樣式覆蓋已停用");
                WP_CLI::line("現在您可以在 Elementor 中自由設定全域配色，不會被主題樣式覆蓋。");
                break;
                
            case 'enable':
                update_option('theme_style_override_disabled', false);
                WP_CLI::success("✅ 主題樣式覆蓋已啟用");
                WP_CLI::line("主題樣式將會覆蓋 Elementor 的全域配色設定。");
                break;
                
            case 'status':
                $is_disabled = get_option('theme_style_override_disabled', false);
                $status = $is_disabled ? "❌ 已停用" : "✅ 已啟用";
                WP_CLI::line("樣式覆蓋狀態: {$status}");
                
                if ($is_disabled) {
                    WP_CLI::line("說明: Elementor 全域配色不會被主題樣式覆蓋");
                } else {
                    WP_CLI::line("說明: 主題樣式會覆蓋 Elementor 全域配色");
                }
                break;
                
            default:
                WP_CLI::error("無效的操作類型。請使用：enable|disable|status");
                break;
        }
    }
    
    /**
     * 管理自訂配色 CSS
     * 
     * ## OPTIONS
     * 
     * <action>
     * : 操作類型 (get|set|clear|validate)
     * 
     * [<css_content>]
     * : CSS 內容（僅在 set 操作時需要）
     * 
     * ## EXAMPLES
     * 
     *     # 取得目前的自訂配色 CSS
     *     wp theme colors custom get
     * 
     *     # 設定自訂配色 CSS
     *     wp theme colors custom set "--e-global-color-primary: #727171;"
     * 
     *     # 清除自訂配色 CSS
     *     wp theme colors custom clear
     * 
     *     # 驗證自訂配色 CSS
     *     wp theme colors custom validate
     * 
     * @since 1.3.0
     */
    public function custom( $args, $assoc_args ) {
        if (empty($args[0])) {
            WP_CLI::error("請指定操作類型：get|set|clear|validate");
        }
        
        $action = $args[0];
        
        switch ($action) {
            case 'get':
                $custom_css = get_option('theme_custom_colors_css', '');
                if (!empty($custom_css)) {
                    WP_CLI::success("目前的自訂配色 CSS：");
                    WP_CLI::line($custom_css);
                } else {
                    WP_CLI::warning("尚未設定自訂配色 CSS");
                }
                break;
                
            case 'set':
                if (empty($args[1])) {
                    WP_CLI::error("請提供 CSS 內容");
                }
                
                $css_content = $args[1];
                update_option('theme_custom_colors_css', $css_content);
                update_option('theme_color_class', 'custom');
                
                WP_CLI::success("✅ 自訂配色 CSS 已設定");
                WP_CLI::line("配色方案已切換至自訂模式");
                break;
                
            case 'clear':
                update_option('theme_custom_colors_css', '');
                if (get_option('theme_color_class') === 'custom') {
                    update_option('theme_color_class', 'expert-theme-1');
                }
                
                WP_CLI::success("✅ 自訂配色 CSS 已清除");
                WP_CLI::line("配色方案已重置為預設方案");
                break;
                
            case 'validate':
                $custom_css = get_option('theme_custom_colors_css', '');
                if (empty($custom_css)) {
                    WP_CLI::warning("沒有自訂配色 CSS 需要驗證");
                    return;
                }
                
                $errors = $this->validate_custom_css($custom_css);
                if (empty($errors)) {
                    WP_CLI::success("✅ 自訂配色 CSS 格式正確");
                } else {
                    WP_CLI::error("❌ 自訂配色 CSS 格式錯誤：\n" . implode("\n", $errors));
                }
                break;
                
            default:
                WP_CLI::error("無效的操作類型。請使用：get|set|clear|validate");
                break;
        }
    }
    
    /**
     * 驗證自訂 CSS 內容
     * 
     * @param string $css_content CSS 內容
     * @return array 錯誤列表
     * @since 1.3.0
     */
    private function validate_custom_css($css_content) {
        $errors = [];
        $lines = explode("\n", $css_content);
        
        $allowed_variables = [
            '--e-global-color-primary',
            '--e-global-color-secondary',
            '--e-global-color-text',
            '--e-global-color-accent',
            '--e-global-color-background',
            '--e-global-color-backgroundAccent',
            '--e-global-color-transparent'
        ];
        
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            
            // 跳過空行和註解
            if (empty($line) || strpos($line, '/*') === 0) {
                continue;
            }
            
            // 檢查格式
            if (!preg_match('/^--[\w-]+:\s*[^;]+;?\s*(?:\/\*.*\*\/)?$/', $line)) {
                $errors[] = "第 " . ($line_num + 1) . " 行格式不正確: {$line}";
                continue;
            }
            
            // 檢查變數名稱
            if (preg_match('/^(--[\w-]+):/', $line, $matches)) {
                $variable = $matches[1];
                if (!in_array($variable, $allowed_variables)) {
                    $errors[] = "第 " . ($line_num + 1) . " 行使用了不允許的變數: {$variable}";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * 取得配色方案（私有方法）
     */
    private function get_color_schemes() {
        // 這裡複製主題樣式切換器中的配色方案
        return [
            // 🎨 專家導向配色組合（v3）
            'expert-theme-1' => [
                'name' => '🟦 專家導向 1：鈦金藍 × 銀灰系（科技感／專業系統類）',
                'colors' => [
                    'primary' => '#1A2B4C',
                    'secondary' => '#CBD5E1',
                    'text' => '#1E293B',
                    'accent' => '#2563EB'
                ]
            ],
            'expert-theme-2' => [
                'name' => '🟫 專家導向 2：黑金銅 × 暖感奢華（精品顧問／高價值感）',
                'colors' => [
                    'primary' => '#3B2F2F',
                    'secondary' => '#D6C39A',
                    'text' => '#3B2F2F',
                    'accent' => '#B7791F'
                ]
            ],
            'expert-theme-3' => [
                'name' => '🟩 專家導向 3：濃墨綠 × 銀湖藍（理性專業／ESG 顧問類）',
                'colors' => [
                    'primary' => '#22372B',
                    'secondary' => '#B8D8D8',
                    'text' => '#2A3C34',
                    'accent' => '#3AA17E'
                ]
            ],
            'expert-theme-4' => [
                'name' => '🟧 專家導向 4：橘磚紅 × 霧灰（品牌經營／設計師導向）',
                'colors' => [
                    'primary' => '#B64926',
                    'secondary' => '#D3D3D3',
                    'text' => '#4B2E21',
                    'accent' => '#D97706'
                ]
            ],
            'expert-theme-5' => [
                'name' => '🟪 專家導向 5：靛紫黑 × 鉻銀（策略／金融／控管感）',
                'colors' => [
                    'primary' => '#2E1A47',
                    'secondary' => '#DADADA',
                    'text' => '#32283C',
                    'accent' => '#8B5CF6'
                ]
            ],
            
            // 🌿 生活導向配色組合（v3）
            'lifestyle-theme-1' => [
                'name' => '🟢 生活導向 1：春日橄欖 × 深綠對比',
                'colors' => [
                    'primary' => '#A3B18A',
                    'secondary' => '#DAD7CD',
                    'text' => '#3B4B2B',
                    'accent' => '#6B8E23'
                ]
            ],
            'lifestyle-theme-2' => [
                'name' => '🧡 生活導向 2：柔粉米 × 木紅對比',
                'colors' => [
                    'primary' => '#FCE5CD',
                    'secondary' => '#FFF8F0',
                    'text' => '#6A3B2E',
                    'accent' => '#C94C4C'
                ]
            ],
            'lifestyle-theme-3' => [
                'name' => '🔵 生活導向 3：海岸藍綠 × 深藍對比',
                'colors' => [
                    'primary' => '#9AD1D4',
                    'secondary' => '#E3F2FD',
                    'text' => '#1C3B5A',
                    'accent' => '#0077B6'
                ]
            ],
            'lifestyle-theme-4' => [
                'name' => '🟤 生活導向 4：黃昏杏橘 × 焦糖棕對比',
                'colors' => [
                    'primary' => '#FFBC80',
                    'secondary' => '#FFF2E0',
                    'text' => '#5C3A21',
                    'accent' => '#E76F51'
                ]
            ],
            'lifestyle-theme-5' => [
                'name' => '🔷 生活導向 5：湖水粉藍 × 暗靛跳色',
                'colors' => [
                    'primary' => '#B5EAEA',
                    'secondary' => '#EDF6F9',
                    'text' => '#223344',
                    'accent' => '#3D5A80'
                ]
            ]
        ];
    }
    
    /**
     * 取得最新的 Default Kit ID（私有方法）
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
     * 取得 Kit 的 page_settings（私有方法）
     */
    private function get_kit_page_settings($kit_id) {
        $settings_data = get_post_meta($kit_id, '_elementor_page_settings', true);
        
        if (empty($settings_data)) {
            return [];
        }
        
        if (is_string($settings_data)) {
            $settings_data = maybe_unserialize($settings_data);
        }
        
        return $settings_data;
    }
    
    /**
     * 更新 Elementor 顏色設定（私有方法）
     */
    private function update_elementor_colors($kit_id, $colors, $font_family = null) {
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
        
        foreach ($colors as $color_name => $color_value) {
            if (isset($color_mapping[$color_name])) {
                $index = $color_mapping[$color_name];
                
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
        return update_post_meta($kit_id, '_elementor_page_settings', $settings);
    }
    
    /**
     * 遞迴更新字體設定（私有方法）
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
     * 清除 Elementor 快取（私有方法）
     */
    private function clear_elementor_cache() {
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            WP_CLI::line("Elementor 檔案快取已清除");
        }
        
        // 清除其他相關快取
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            WP_CLI::line("WordPress 物件快取已清除");
        }
    }
}

// 註冊 WP-CLI 指令
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('theme colors', 'WP_CLI_Theme_Colors_Command');
}