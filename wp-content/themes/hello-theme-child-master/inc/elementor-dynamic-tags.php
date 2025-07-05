<?php
/**
 * @file elementor-dynamic-tags.php
 * @description Elementor 動態標籤類別 - 用於顯示主題設定值
 * @path /inc/elementor-dynamic-tags.php
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Core\DynamicTags\Tag;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Controls_Manager;

/**
 * 註冊自訂動態標籤群組
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
 * 主要文字動態標籤 - 用於文字欄位
 */
class Theme_Setting_Dynamic_Tag extends Tag {

    /**
     * 取得動態標籤名稱
     */
    public function get_name() {
        return 'theme-setting';
    }

    /**
     * 取得動態標籤標題
     */
    public function get_title() {
        return __('Theme Setting', 'textdomain');
    }

    /**
     * 取得動態標籤群組
     */
    public function get_group() {
        return 'theme';
    }

    /**
     * 取得動態標籤類別
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