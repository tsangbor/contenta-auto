<?php
/**
 * Elementor 動態標籤擴展
 * 
 * 提供主題設定值的 Elementor 動態標籤功能
 * 包含文字、連結、圖片、服務項目等多種動態標籤類型
 * 
 * @package HelloElementorChild
 * @subpackage Modules/ElementorDynamicTags
 * @version 1.0.2
 * @since 2.0.0
 * @author Your Name
 * 
 * === WP-CLI Elementor 動態標籤檢測指令使用指南 ===
 * 
 * 本模組為 Elementor 提供 8 種自訂動態標籤，透過以下指令檢測和管理：
 * 
 * 🔍 系統檢測指令：
 * 
 * 1. 📋 檢查 Elementor 狀態
 *    wp plugin status elementor --allow-root
 *    wp plugin status elementor-pro --allow-root
 *    # 檢查 Elementor 外掛是否安裝並啟用
 * 
 * 2. 🔧 檢查 Elementor 版本
 *    wp eval 'if (defined("ELEMENTOR_VERSION")) echo "Elementor 版本: " . ELEMENTOR_VERSION . "\n"; else echo "Elementor 未啟用\n";' --allow-root
 *    # 確認 Elementor 版本是否支援動態標籤
 * 
 * 3. 🎯 檢查動態標籤類別可用性
 *    wp eval 'echo class_exists("\Elementor\Core\DynamicTags\Tag") ? "✅ 動態標籤基礎類別可用" : "❌ 動態標籤類別不可用"; echo "\n";' --allow-root
 *    # 驗證動態標籤核心類別是否存在
 * 
 * 4. 📊 檢查主題動態標籤檔案
 *    wp eval 'echo file_exists(get_stylesheet_directory() . "/inc/elementor-dynamic-tags.php") ? "✅ 動態標籤檔案存在" : "❌ 動態標籤檔案不存在"; echo "\n";' --allow-root
 *    # 確認主題動態標籤檔案是否正確載入
 * 
 * 🛠️ 資料來源檢測指令：
 * 
 * 5. 📋 檢查主題設定資料完整性
 *    wp option list --search="index_*" --format=count --allow-root
 *    # 統計所有主題設定項目數量
 * 
 * 6. 🔍 檢查服務項目列表結構
 *    wp option get index_service_list --format=json --allow-root
 *    # 以 JSON 格式查看服務項目的完整結構
 * 
 * 7. 🎨 檢查圖片設定項目
 *    wp eval 'foreach(["index_hero_bg", "index_hero_photo", "index_about_photo", "index_footer_cta_bg"] as $key) { $val = get_option($key); echo "$key: " . ($val ? "有設定" : "未設定") . "\n"; }' --allow-root
 *    # 檢查所有圖片相關設定是否有值
 * 
 * 8. 🔗 檢查連結設定項目  
 *    wp eval 'foreach(["index_hero_cta_link", "index_about_cta_link", "index_footer_fb", "index_footer_ig"] as $key) { $val = get_option($key); echo "$key: " . ($val ? $val : "未設定") . "\n"; }' --allow-root
 *    # 檢查所有連結相關設定
 * 
 * 📦 Elementor Kit 檢測指令：
 * 
 * 9. 🎯 檢查 Elementor Active Kit
 *    wp option get elementor_active_kit --allow-root
 *    # 取得目前啟用的 Elementor Kit ID
 * 
 * 10. 🔧 檢查 Kit 設定
 *     wp post meta get $(wp option get elementor_active_kit --allow-root) _elementor_page_settings --format=json --allow-root
 *     # 查看 Kit 的完整設定（包含 Global Colors 等）
 * 
 * 11. 🧹 清除 Elementor 快取
 *     wp eval 'if (class_exists("\Elementor\Plugin")) { \Elementor\Plugin::$instance->files_manager->clear_cache(); echo "✅ Elementor 快取已清除\n"; } else { echo "❌ Elementor 不可用\n"; }' --allow-root
 *     # 清除 Elementor 檔案快取，確保動態標籤更新
 * 
 * === 可用的動態標籤類型 ===
 * 
 * 🏷️ 文字類動態標籤：
 * • Theme Setting - 主題設定文字值
 *   支援所有文字設定項目 (index_hero_title, index_about_content 等)
 *   包含服務項目個別欄位存取
 * 
 * 🔗 連結類動態標籤：
 * • Theme Setting (Link) - 主題設定連結值
 *   支援 CTA 連結和社群媒體連結
 *   自動處理 mailto: 前綴
 * 
 * 🖼️ 圖片類動態標籤：
 * • Theme Setting (Image) - 主題設定圖片
 * • Theme Setting (Image URL) - 圖片 URL 文字版
 *   支援相對路徑自動轉換為完整 URL
 *   包含 fallback 機制
 * 
 * 🛠️ 服務項目專用標籤：
 * • Service List - 服務項目列表（JSON/HTML/計數）
 * • Service Icon - 服務項目圖示（多種格式）
 * • Service Item HTML - 單一服務項目完整 HTML
 * • All Services HTML - 所有服務項目完整 HTML
 * 
 * === 動態標籤測試指令 ===
 * 
 * 12. 🧪 測試特定設定值
 *     wp eval 'echo "Hero 標題: " . get_option("index_hero_title", "未設定") . "\n";' --allow-root
 *     wp eval 'echo "Hero 副標題: " . get_option("index_hero_subtitle", "未設定") . "\n";' --allow-root
 *     # 測試動態標籤的資料來源
 * 
 * 13. 🔧 測試服務項目結構
 *     wp eval '$services = get_option("index_service_list", []); echo "服務項目數量: " . count($services) . "\n"; if(!empty($services)) echo "第一個項目: " . print_r($services[0], true);' --allow-root
 *     # 測試服務項目動態標籤的資料結構
 * 
 * 14. 📊 生成動態標籤測試報告
 *     wp eval 'echo "=== 動態標籤資料檢測報告 ===\n"; $keys = ["index_hero_title", "index_hero_subtitle", "index_about_title", "index_service_title"]; foreach($keys as $key) { $val = get_option($key); echo "$key: " . (empty($val) ? "❌ 空值" : "✅ 有資料") . "\n"; }' --allow-root
 *     # 產生完整的動態標籤可用性報告
 * 
 * === 故障排除指令 ===
 * 
 * 如果動態標籤無法正常顯示：
 * 
 * 1. 檢查 Elementor 狀態：
 *    wp plugin status elementor --allow-root
 * 
 * 2. 檢查主題檔案：
 *    wp eval 'echo file_exists(get_stylesheet_directory() . "/inc/elementor-dynamic-tags.php") ? "檔案存在" : "檔案不存在"; echo "\n";' --allow-root
 * 
 * 3. 檢查 PHP 錯誤：
 *    wp eval 'error_reporting(E_ALL); ini_set("display_errors", 1); require_once get_stylesheet_directory() . "/inc/elementor-dynamic-tags.php"; echo "檔案載入成功\n";' --allow-root
 * 
 * 4. 重新啟用主題：
 *    wp theme activate hello-elementor-child --allow-root
 * 
 * 5. 清除所有快取：
 *    wp cache flush --allow-root
 *    wp eval 'if (class_exists("\Elementor\Plugin")) \Elementor\Plugin::$instance->files_manager->clear_cache();' --allow-root
 * 
 * === 開發者除錯指令 ===
 * 
 * 15. 🔍 檢查動態標籤註冊狀態
 *     wp eval 'add_action("elementor/dynamic_tags/register_tags", function($tags) { echo "動態標籤管理器已載入\n"; $registered = $tags->get_tags(); echo "已註冊標籤數量: " . count($registered) . "\n"; });' --allow-root
 *     # 檢查動態標籤是否正確註冊到 Elementor
 * 
 * 16. 🧪 測試動態標籤類別實例化
 *     wp eval 'if (class_exists("Theme_Setting_Dynamic_Tag")) { echo "✅ Theme_Setting_Dynamic_Tag 類別可用\n"; } else { echo "❌ 類別不存在\n"; }' --allow-root
 *     # 測試自訂動態標籤類別是否正確載入
 * 
 * === 實際使用範例 ===
 * 
 * 在 Elementor 編輯器中使用：
 * 1. 編輯任何元素的文字屬性
 * 2. 點擊動態內容圖示（魔術棒）
 * 3. 選擇 "Theme Settings" 群組
 * 4. 選擇適合的動態標籤類型
 * 5. 配置相關設定（如服務項目索引）
 * 
 * 支援的元素類型：
 * • 標題元素 → Theme Setting (文字)
 * • 按鈕元素 → Theme Setting (Link)  
 * • 圖片元素 → Theme Setting (Image)
 * • 圖示元素 → Service Icon
 * • HTML 元素 → Service Item HTML / All Services HTML
 * 
 * Features:
 * - 主題設定文字動態標籤
 * - 連結專用動態標籤
 * - 圖片專用動態標籤
 * - 服務項目動態標籤
 * - 完整 HTML 服務列表
 * - 響應式 CSS 支援
 * - 自訂模板功能
 * - WP-CLI 檢測支援
 * 
 * Changelog:
 * 1.0.2 - 2025-07-07
 * - 新增完整的 WP-CLI 檢測指令使用指南
 * - 詳細的動態標籤類型和功能說明
 * - 故障排除和開發者除錯指令
 * - 實際使用範例和最佳實踐
 * - 系統相容性檢測機制
 * 
 * 1.0.1 - 2025-07-07
 * - 新增 Elementor 可用性檢查
 * - 修復 Elementor 停用時的致命錯誤
 * - 改善錯誤提示和用戶體驗
 * - 確保在外掛停用時安全降級
 * 
 * 1.0.0 - 2025-01-06
 * - 初始版本
 * - 基本動態標籤功能
 * - 8 種不同類型標籤
 * - 服務項目完整支援
 * - HTML 模板系統
 * - 響應式樣式整合
 * - 圖示格式轉換
 * - URL 自動處理
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 檢查 Elementor 是否啟用且相關類別可用
 * 
 * @since 1.0.1
 */
if (!function_exists('is_elementor_available')) {
    function is_elementor_available() {
        return class_exists('\Elementor\Plugin') && 
               class_exists('\Elementor\Core\DynamicTags\Tag') && 
               class_exists('\Elementor\Core\DynamicTags\Data_Tag') && 
               class_exists('\Elementor\Controls_Manager');
    }
}

// 如果 Elementor 不可用，提前返回避免錯誤
if (!is_elementor_available()) {
    // 在管理後台顯示通知
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>主題動態標籤模組：</strong>需要 Elementor 外掛才能正常運作。</p>';
            echo '</div>';
        });
    }
    return; // 停止載入此檔案的剩餘內容
}

// 只有在 Elementor 可用時才引入這些類別
use Elementor\Core\DynamicTags\Tag;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;

/**
 * 註冊自訂動態標籤群組
 * 
 * @since 1.0.0
 */
add_action('elementor/dynamic_tags/register_tags', function($dynamic_tags) {
    // 註冊自訂群組
    \Elementor\Plugin::$instance->dynamic_tags->register_group(
        'theme',
        [
            'title' => __('Theme Settings', 'textdomain')
        ]
    );
});

/**
 * 主要文字動態標籤類別
 * 
 * 用於顯示主題設定中的文字內容
 * 
 * @since 1.0.0
 * @version 1.0.0
 */
class Theme_Setting_Dynamic_Tag extends Tag {

    /**
     * 取得動態標籤名稱
     * 
     * @return string
     * @since 1.0.0
     */
    public function get_name() {
        return 'theme-setting';
    }

    /**
     * 取得動態標籤標題
     * 
     * @return string
     * @since 1.0.0
     */
    public function get_title() {
        return __('Theme Setting', 'textdomain');
    }

    /**
     * 取得動態標籤群組
     * 
     * @return string
     * @since 1.0.0
     */
    public function get_group() {
        return 'theme';
    }

    /**
     * 取得動態標籤類別
     * 
     * @return array
     * @since 1.0.0
     */
    public function get_categories() {
        return ['text'];
    }

    /**
     * 註冊控制項
     */
    protected function _register_controls() {
        // 設定鍵值選擇
        $this->add_control(
            'setting_key',
            [
                'label' => __('Setting Key', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'index_hero_title' => '首頁Hero標題',
                    'index_hero_subtitle' => '首頁Hero副標題',
                    'index_hero_cta_text' => '首頁Hero CTA文字',
                    'index_header_cta_title' => '頁首CTA標題',
                    'index_about_title' => '關於我標題',
                    'index_about_subtitle' => '關於我副標題',
                    'index_about_content' => '關於我內容',
                    'index_about_cta_text' => '關於我CTA文字',
                    'index_service_title' => '服務標題',
                    'index_service_subtitle' => '服務副標題',
                    'index_service_list' => '服務項目列表',
                    'index_service_cta_text' => '服務CTA文字',
                    'index_archive_title' => '文章列表標題',
                    'index_footer_cta_title' => '頁尾CTA標題',
                    'index_footer_cta_subtitle' => '頁尾CTA副標題',
                    'index_footer_cta_button' => '頁尾CTA按鈕',
                    'index_footer_title' => '頁尾標題',
                    'index_footer_subtitle' => '頁尾副標題',
                    'seo_title' => 'SEO標題',
                    'seo_description' => 'SEO描述',
                    'website_blogname' => '網站名稱',
                    'website_blogdescription' => '網站描述',
                    'website_author_nickname' => '作者暱稱',
                    'website_author_description' => '作者描述'
                ],
                'default' => 'index_hero_title',
            ]
        );

        // 服務列表索引（當選擇 service_list 時顯示）
        $this->add_control(
            'service_index',
            [
                'label' => __('Service Index', 'textdomain'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'condition' => [
                    'setting_key' => 'index_service_list'
                ]
            ]
        );

        // 服務列表欄位選擇（當選擇 service_list 時顯示）
        $this->add_control(
            'service_field',
            [
                'label' => __('Service Field', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'icon' => '圖示',
                    'title' => '標題',
                    'description' => '描述'
                ],
                'default' => 'title',
                'condition' => [
                    'setting_key' => 'index_service_list'
                ]
            ]
        );

        // 預設值
        $this->add_control(
            'fallback',
            [
                'label' => __('Fallback', 'textdomain'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Fallback text if setting is empty', 'textdomain'),
            ]
        );
    }

    /**
     * 渲染動態標籤內容
     */
    public function render() {
        $settings = $this->get_settings();
        $setting_key = $settings['setting_key'];
        $fallback = $settings['fallback'];

        if ($setting_key === 'index_service_list') {
            // 處理服務列表
            $service_index = isset($settings['service_index']) ? intval($settings['service_index']) : 0;
            $service_field = isset($settings['service_field']) ? $settings['service_field'] : 'title';
            
            $value = ThemeDefaultSettings::get_service_item($service_index, $service_field);
        } else {
            // 處理一般設定 - 直接用 get_option
            $value = get_option($setting_key, '');
        }

        // 如果值為空且有設定 fallback，使用 fallback
        if (empty($value) && !empty($fallback)) {
            $value = $fallback;
        }

        // 處理換行符號
        $value = nl2br(esc_html($value));

        echo $value;
    }
}

/**
 * 所有服務項目 HTML 動態標籤 - 一次顯示全部
 */
class All_Services_HTML_Dynamic_Tag extends Tag {

    public function get_name() {
        return 'all-services-html';
    }

    public function get_title() {
        return __('All Services HTML', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return ['text'];
    }

    protected function _register_controls() {
        $this->add_control(
            'item_template',
            [
                'label' => __('Single Item Template', 'textdomain'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '<div class="service-item">
    <div class="service-icon">
        <i class="{icon}"></i>
    </div>
    <h3 class="service-title">{title}</h3>
    <p class="service-description">{description}</p>
</div>',
                'description' => __('單個服務項目的 HTML 模板', 'textdomain'),
                'rows' => 8,
            ]
        );

        $this->add_control(
            'wrapper_template',
            [
                'label' => __('Wrapper Template', 'textdomain'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '<div class="services-grid">
{items}
</div>',
                'description' => __('外層包裝 HTML，使用 {items} 作為服務項目的佔位符', 'textdomain'),
                'rows' => 4,
            ]
        );

        $this->add_control(
            'responsive_css',
            [
                'label' => __('Responsive CSS', 'textdomain'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '.services-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 30px;
    margin: 20px 0;
}

.service-item {
    text-align: center;
    padding: 20px;
}

.service-icon {
    margin-bottom: 15px;
}
.service-icon i {
    font-size: 48px;
    color: var( --e-global-color-text );
}

.service-title {
    margin: 15px 0 10px 0;
    font-size: 1.2em;
    font-weight: bold;
    color: var( --e-global-color-text );
}

.service-description {
    margin: 0;
    line-height: 1.6;
    color: #666;
}

/* 手機版：一欄顯示 */
@media (max-width: 768px) {
    .services-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}',
                'description' => __('響應式 CSS 樣式', 'textdomain'),
                'rows' => 20,
            ]
        );
    }

    public function render() {
        $settings = $this->get_settings();
        $item_template = $settings['item_template'];
        $wrapper_template = $settings['wrapper_template'];
        $responsive_css = $settings['responsive_css'];

        $service_list = get_option('index_service_list', []);
        
        // 如果沒有服務項目
        if (empty($service_list)) {
            echo '<div class="no-services">尚未設定服務項目</div>';
            return;
        }

        $items_html = '';
        
        // 遍歷所有服務項目
        foreach ($service_list as $index => $service) {
            $icon = isset($service['icon']) ? $service['icon'] : 'fas fa-star';
            $title = isset($service['title']) ? $service['title'] : '服務標題';
            $description = isset($service['description']) ? $service['description'] : '服務描述';

            // 替換單個項目模板的變數
            $item_html = str_replace(
                ['{icon}', '{title}', '{description}', '{index}'],
                [esc_attr($icon), esc_html($title), esc_html($description), $index],
                $item_template
            );

            $items_html .= $item_html;
        }

        // 替換外層模板的變數
        $final_html = str_replace('{items}', $items_html, $wrapper_template);

        // 產生唯一的 CSS ID
        $unique_id = 'all-services-' . uniqid();

        // 輸出 CSS 樣式
        if (!empty($responsive_css)) {
            echo '<style>';
            // 將 CSS 規則加上唯一 ID 前綴
            $scoped_css = preg_replace('/\.services-grid/', "#{$unique_id} .services-grid", $responsive_css);
            $scoped_css = preg_replace('/\.service-item/', "#{$unique_id} .service-item", $scoped_css);
            $scoped_css = preg_replace('/\.service-icon/', "#{$unique_id} .service-icon", $scoped_css);
            $scoped_css = preg_replace('/\.service-title/', "#{$unique_id} .service-title", $scoped_css);
            $scoped_css = preg_replace('/\.service-description/', "#{$unique_id} .service-description", $scoped_css);
            $scoped_css = preg_replace('/\.no-services/', "#{$unique_id} .no-services", $scoped_css);
            echo $scoped_css;
            echo '</style>';
        }

        // 輸出 HTML 並加上唯一 ID
        $html_with_id = preg_replace('/<div class="services-grid">/', '<div id="' . $unique_id . '"><div class="services-grid">', $final_html);
        $html_with_id .= '</div>'; // 關閉 wrapper div

        echo $html_with_id;
    }
}

/**
 * 完整服務項目 HTML 動態標籤 - 用於內容編輯器
 */
class Service_Item_HTML_Dynamic_Tag extends Tag {

    public function get_name() {
        return 'service-item-html';
    }

    public function get_title() {
        return __('Service Item HTML', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return ['text'];
    }

    protected function _register_controls() {
        $this->add_control(
            'service_index',
            [
                'label' => __('Service Index', 'textdomain'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'description' => __('服務項目索引 (0=第一個, 1=第二個, 2=第三個)', 'textdomain'),
            ]
        );

        $this->add_control(
            'template',
            [
                'label' => __('HTML Template', 'textdomain'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '<div class="service-item">
    <div class="service-icon">
        <i class="{icon}"></i>
    </div>
    <h3 class="service-title">{title}</h3>
    <p class="service-description">{description}</p>
</div>',
                'description' => __('使用 {icon}, {title}, {description} 作為變數', 'textdomain'),
                'rows' => 10,
            ]
        );

        $this->add_control(
            'icon_size',
            [
                'label' => __('Icon Size', 'textdomain'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range' => [
                    'px' => [
                        'min' => 16,
                        'max' => 100,
                        'step' => 2,
                    ],
                    'em' => [
                        'min' => 1,
                        'max' => 6,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 48,
                ],
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label' => __('Icon Color', 'textdomain'),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
            ]
        );

        $this->add_control(
            'custom_css',
            [
                'label' => __('Custom CSS', 'textdomain'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '.service-item {
    text-align: center;
    padding: 20px;
}
.service-icon {
    margin-bottom: 15px;
}
.service-title {
    margin: 15px 0 10px 0;
    font-size: 1.2em;
    font-weight: bold;
}
.service-description {
    margin: 0;
    line-height: 1.6;
}',
                'description' => __('自訂 CSS 樣式', 'textdomain'),
                'rows' => 8,
            ]
        );
    }

    public function render() {
        $settings = $this->get_settings();
        $service_index = intval($settings['service_index']);
        $template = $settings['template'];
        $icon_size = $settings['icon_size'];
        $icon_color = $settings['icon_color'];
        $custom_css = $settings['custom_css'];

        $service_list = get_option('index_service_list', []);
        
        // 檢查服務項目是否存在
        if (!isset($service_list[$service_index])) {
            echo '<div class="service-item-error">服務項目 ' . $service_index . ' 不存在</div>';
            return;
        }

        $service = $service_list[$service_index];
        $icon = isset($service['icon']) ? $service['icon'] : 'fas fa-star';
        $title = isset($service['title']) ? $service['title'] : '服務標題';
        $description = isset($service['description']) ? $service['description'] : '服務描述';

        // 替換模板變數
        $html = str_replace(
            ['{icon}', '{title}', '{description}'],
            [esc_attr($icon), esc_html($title), esc_html($description)],
            $template
        );

        // 產生唯一的 CSS ID
        $unique_id = 'service-item-' . $service_index . '-' . uniqid();

        // 輸出 CSS 樣式
        if (!empty($custom_css) || !empty($icon_size) || !empty($icon_color)) {
            echo '<style>';
            
            // 圖示大小和顏色
            if (!empty($icon_size)) {
                $size_value = $icon_size['size'] . $icon_size['unit'];
                echo "#{$unique_id} .service-icon i { font-size: {$size_value}; }";
            }
            
            if (!empty($icon_color)) {
                echo "#{$unique_id} .service-icon i { color: {$icon_color}; }";
            }
            
            // 自訂 CSS
            if (!empty($custom_css)) {
                // 將 CSS 規則加上唯一 ID 前綴
                $scoped_css = preg_replace('/([^{}]+){/', "#{$unique_id} $1{", $custom_css);
                echo $scoped_css;
            }
            
            echo '</style>';
        }

        // 輸出 HTML 並加上唯一 ID
        $html_with_id = preg_replace('/class="([^"]*service-item[^"]*)"/', 'id="' . $unique_id . '" class="$1"', $html, 1);
        if ($html_with_id === $html) {
            // 如果沒有找到 service-item class，就在第一個 div 加上 ID
            $html_with_id = preg_replace('/<div/', '<div id="' . $unique_id . '"', $html, 1);
        }

        echo $html_with_id;
    }
}

/**
 * 服務圖示專用動態標籤 - 用於圖示元素
 */
class Service_Icon_Dynamic_Tag extends Tag {

    public function get_name() {
        return 'service-icon';
    }

    public function get_title() {
        return __('Service Icon', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return ['text'];
    }

    protected function _register_controls() {
        $this->add_control(
            'service_index',
            [
                'label' => __('Service Index', 'textdomain'),
                'type' => Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'description' => __('服務項目索引 (0=第一個, 1=第二個, 2=第三個)', 'textdomain'),
            ]
        );

        $this->add_control(
            'output_format',
            [
                'label' => __('Output Format', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'class' => '僅 CSS 類別 (fas fa-lightbulb)',
                    'html' => '完整 HTML (<i class="fas fa-lightbulb"></i>)',
                    'elementor' => 'Elementor 格式 ({"value":"fas fa-lightbulb","library":"fa-solid"})'
                ],
                'default' => 'class',
            ]
        );

        $this->add_control(
            'fallback_icon',
            [
                'label' => __('Fallback Icon', 'textdomain'),
                'type' => Controls_Manager::TEXT,
                'default' => 'fas fa-star',
                'placeholder' => __('預設圖示類別', 'textdomain'),
            ]
        );
    }

    public function render() {
        $settings = $this->get_settings();
        $service_index = intval($settings['service_index']);
        $output_format = $settings['output_format'];
        $fallback_icon = $settings['fallback_icon'];

        $service_list = get_option('index_service_list', []);
        
        // 取得圖示
        if (isset($service_list[$service_index]['icon'])) {
            $icon = $service_list[$service_index]['icon'];
        } else {
            $icon = $fallback_icon;
        }

        // 根據格式輸出
        switch ($output_format) {
            case 'html':
                echo '<i class="' . esc_attr($icon) . '"></i>';
                break;
            
            case 'elementor':
                // Elementor 圖示控制項格式
                $icon_parts = explode(' ', $icon);
                $library = 'fa-solid';
                if (isset($icon_parts[0])) {
                    switch ($icon_parts[0]) {
                        case 'far':
                            $library = 'fa-regular';
                            break;
                        case 'fab':
                            $library = 'fa-brands';
                            break;
                        case 'fas':
                        default:
                            $library = 'fa-solid';
                            break;
                    }
                }
                echo json_encode([
                    'value' => $icon,
                    'library' => $library
                ]);
                break;
            
            case 'class':
            default:
                echo esc_attr($icon);
                break;
        }
    }
}

/**
 * 除錯用的圖片 URL 動態標籤 - 直接回傳 URL
 */
class Theme_Setting_Image_URL_Dynamic_Tag extends Tag {

    public function get_name() {
        return 'theme-setting-image-url';
    }

    public function get_title() {
        return __('Theme Setting (Image URL)', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return ['text', 'url'];
    }

    protected function _register_controls() {
        $this->add_control(
            'setting_key',
            [
                'label' => __('Setting Key', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'index_hero_bg' => '首頁Hero背景圖片',
                    'index_hero_photo' => '首頁Hero照片',
                    'index_about_photo' => '關於我照片',
                    'index_footer_cta_bg' => '頁尾CTA背景'
                ],
                'default' => 'index_hero_bg',
            ]
        );
    }

    public function render() {
        $settings = $this->get_settings();
        $setting_key = $settings['setting_key'];
        $image_url = get_option($setting_key, '');

        // 如果是相對路徑，轉換為完整 URL
        if (!empty($image_url) && !str_starts_with($image_url, 'http')) {
            $image_url = home_url($image_url);
        }

        echo esc_url($image_url);
    }
}

/**
 * 連結專用動態標籤 - 用於連結欄位
 */
class Theme_Setting_URL_Dynamic_Tag extends Tag {

    public function get_name() {
        return 'theme-setting-url';
    }

    public function get_title() {
        return __('Theme Setting (Link)', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return ['url'];
    }

    protected function _register_controls() {
        // 只顯示適合連結的設定項目
        $this->add_control(
            'setting_key',
            [
                'label' => __('Setting Key', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'index_hero_cta_link' => '首頁Hero CTA連結',
                    'index_header_cta_link' => '頁首CTA連結',
                    'index_about_cta_link' => '關於我CTA連結',
                    'index_service_cta_link' => '服務CTA連結',
                    'index_footer_fb' => '頁尾Facebook',
                    'index_footer_ig' => '頁尾Instagram',
                    'index_footer_line' => '頁尾Line',
                    'index_footer_yt' => '頁尾YouTube',
                    'index_footer_email' => '頁尾Email'
                ],
                'default' => 'index_hero_cta_link',
            ]
        );

        $this->add_control(
            'fallback',
            [
                'label' => __('Fallback URL', 'textdomain'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Fallback URL if setting is empty', 'textdomain'),
            ]
        );
    }

    public function render() {
        $settings = $this->get_settings();
        $setting_key = $settings['setting_key'];
        $fallback = $settings['fallback'];

        $value = get_option($setting_key, '');

        // 如果值為空且有設定 fallback，使用 fallback
        if (empty($value) && !empty($fallback)) {
            $value = $fallback;
        }

        // 對於 email，加上 mailto: 前綴
        if ($setting_key === 'index_footer_email' && !empty($value) && !str_starts_with($value, 'mailto:')) {
            $value = 'mailto:' . $value;
        }

        echo esc_url($value);
    }
}

/**
 * 圖片專用動態標籤 - 用於圖片欄位
 */
class Theme_Setting_Image_Dynamic_Tag extends \Elementor\Core\DynamicTags\Data_Tag {

    public function get_name() {
        return 'theme-setting-image';
    }

    public function get_title() {
        return __('Theme Setting (Image)', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return [\Elementor\Modules\DynamicTags\Module::IMAGE_CATEGORY];
    }

    protected function _register_controls() {
        // 只顯示圖片相關的設定項目
        $this->add_control(
            'setting_key',
            [
                'label' => __('Setting Key', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'index_hero_bg' => '首頁Hero背景圖片',
                    'index_hero_photo' => '首頁Hero照片',
                    'index_about_photo' => '關於我照片',
                    'index_footer_cta_bg' => '頁尾CTA背景'
                ],
                'default' => 'index_hero_bg',
            ]
        );

        $this->add_control(
            'fallback_url',
            [
                'label' => __('Fallback Image URL', 'textdomain'),
                'type' => Controls_Manager::TEXT,
                'placeholder' => __('Fallback image URL if setting is empty', 'textdomain'),
            ]
        );
    }

    public function get_value(array $options = []) {
        $settings = $this->get_settings();
        $setting_key = $settings['setting_key'];
        $fallback = isset($settings['fallback_url']) ? $settings['fallback_url'] : '';

        $image_url = get_option($setting_key, '');

        // 如果值為空且有設定 fallback，使用 fallback
        if (empty($image_url) && !empty($fallback)) {
            $image_url = $fallback;
        }

        // 如果是相對路徑，轉換為完整 URL
        if (!empty($image_url) && !str_starts_with($image_url, 'http')) {
            $image_url = home_url($image_url);
        }

        // 嘗試從 URL 獲取附件 ID
        $attachment_id = attachment_url_to_postid($image_url);

        if (!empty($image_url)) {
            return [
                'id' => $attachment_id ?: '',
                'url' => $image_url,
            ];
        }

        return [];
    }
}

/**
 * 服務列表動態標籤（用於重複器或特殊用途）
 */
class Service_List_Dynamic_Tag extends Tag {

    public function get_name() {
        return 'service-list';
    }

    public function get_title() {
        return __('Service List', 'textdomain');
    }

    public function get_group() {
        return 'theme';
    }

    public function get_categories() {
        return ['text'];
    }

    protected function _register_controls() {
        $this->add_control(
            'list_format',
            [
                'label' => __('List Format', 'textdomain'),
                'type' => Controls_Manager::SELECT,
                'options' => [
                    'json' => 'JSON 格式',
                    'html' => 'HTML 列表',
                    'count' => '項目數量'
                ],
                'default' => 'json',
            ]
        );
    }

    public function render() {
        $settings = $this->get_settings();
        $format = $settings['list_format'];
        $service_list = get_option('index_service_list', []);

        switch ($format) {
            case 'html':
                echo '<ul>';
                foreach ($service_list as $service) {
                    echo '<li><strong>' . esc_html($service['title']) . '</strong>: ' . esc_html($service['description']) . '</li>';
                }
                echo '</ul>';
                break;
            
            case 'count':
                echo count($service_list);
                break;
            
            case 'json':
            default:
                echo json_encode($service_list, JSON_UNESCAPED_UNICODE);
                break;
        }
    }
}

/**
 * 註冊所有動態標籤
 */
add_action('elementor/dynamic_tags/register_tags', function($dynamic_tags) {
    $dynamic_tags->register_tag('Theme_Setting_Dynamic_Tag');
    $dynamic_tags->register_tag('Theme_Setting_URL_Dynamic_Tag');
    $dynamic_tags->register_tag('Theme_Setting_Image_Dynamic_Tag');
    $dynamic_tags->register_tag('Theme_Setting_Image_URL_Dynamic_Tag');
    $dynamic_tags->register_tag('Service_List_Dynamic_Tag');
    $dynamic_tags->register_tag('Service_Icon_Dynamic_Tag');
    $dynamic_tags->register_tag('Service_Item_HTML_Dynamic_Tag');
    $dynamic_tags->register_tag('All_Services_HTML_Dynamic_Tag');  // 新增所有服務項目標籤
});