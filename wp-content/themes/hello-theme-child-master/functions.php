<?php
/**
 * Theme functions and definitions.
 *
 * For additional information on potential customization options,
 * read the developers' documentation:
 *
 * https://developers.elementor.com/docs/hello-elementor-theme/
 *
 * @package HelloElementorChild
 * @version 2.0.0
 * @author Your Name
 * @since 1.0.0
 * 
 * Changelog:
 * 2.0.0 - 2025-01-06
 * - 加入模組化載入系統
 * - 新增主題樣式切換器模組
 * - 新增預設設定管理模組
 * - 新增 Elementor 動態標籤模組
 * - 新增模組化頁面系統
 * - 新增用戶角色管理模組
 * - 改善錯誤處理和除錯功能
 * 
 * 1.0.0 - Initial release
 * - 基本子主題設定
 * - 樣式載入功能
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'HELLO_ELEMENTOR_CHILD_VERSION', '2.0.0' );

/**
 * Load child theme scripts & styles.
 *
 * @return void
 * @since 1.0.0
 * @version 2.0.0
 */
function hello_elementor_child_scripts_styles() {

	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[
			'hello-elementor-theme-style',
		],
		HELLO_ELEMENTOR_CHILD_VERSION
	);

}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_scripts_styles', 20 );

function add_elementor_custom_styles() {
    $custom_css = '
    .elementor-widget-nav-menu .elementor-nav-menu--main .elementor-item {
        color: var(--e-global-color-primary);
    }
    
    .elementor-menu-toggle .e-font-icon-svg {
        fill: var(--e-global-color-primary);
    }
    
    .elementor-scrolling-tracker {
        --scrolling-track-default-progress-color: var(--e-global-color-secondary);
    }
    
    .elementor-widget-button .elementor-button {
        background-color: var(--e-global-color-accent);
    }
    ';
    
    // 將 CSS 添加到子主題樣式表
    wp_add_inline_style('hello-elementor-child-style', $custom_css);
}
add_action('wp_enqueue_scripts', 'add_elementor_custom_styles', 21);

/**
 * 模組化載入主題功能
 * 
 * 載入 inc/ 目錄下的功能模組
 * 
 * @since 2.0.0
 * @version 2.0.0
 * 
 * 模組清單：
 * - theme-style-switcher.php: v1.0.0 - 主題樣式切換器
 * - theme-default-settings.php: v1.0.0 - 主題預設設定管理
 * - elementor-dynamic-tags.php: v1.0.0 - Elementor 動態標籤擴展
 * - modular-page-system.php: v1.0.0 - 模組化頁面系統
 * - user-role-manager.php: v1.0.0 - 用戶角色權限管理
 */
$modules = [
    'theme-style-switcher.php',      // 主題樣式切換功能
    'theme-default-settings.php',    // 預設設定管理
    'elementor-dynamic-tags.php',    // 動態標籤擴展
    'user-role-manager.php'          // 用戶角色管理
];

foreach ($modules as $file) {
    $path = __DIR__ . '/inc/' . $file;
    if (file_exists($path)) {
        require_once $path;
    } else {
        // 開發環境除錯資訊
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Hello Elementor Child: 模組檔案不存在 - {$file}");
        }
    }
}

/**
 * 主題初始化完成後的動作
 * 
 * @since 2.0.0
 */
add_action('after_setup_theme', function() {
    // 記錄主題載入完成
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Hello Elementor Child Theme v' . HELLO_ELEMENTOR_CHILD_VERSION . ' 載入完成');
    }
});

