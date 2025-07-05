<?php
/**
 * 用戶角色管理模組
 * 創建和管理受限的網站管理員角色
 *
 * @package HelloElementorChild
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// WordPress核心函数检查 - 确保在WordPress环境中运行
if ( ! function_exists( 'get_role' ) ) {
    function get_role( $role ) { return null; }
}
if ( ! function_exists( 'add_role' ) ) {
    function add_role( $role, $display_name, $capabilities = array() ) { return null; }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability, $object_id = null ) { return false; }
}
if ( ! function_exists( 'remove_menu_page' ) ) {
    function remove_menu_page( $menu_slug ) { return false; }
}
if ( ! function_exists( 'remove_submenu_page' ) ) {
    function remove_submenu_page( $menu_slug, $submenu_slug ) { return false; }
}
if ( ! function_exists( 'wp_redirect' ) ) {
    function wp_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) { return false; }
}
if ( ! function_exists( 'admin_url' ) ) {
    function admin_url( $path = '', $scheme = 'admin' ) { return ''; }
}
if ( ! function_exists( 'remove_role' ) ) {
    function remove_role( $role ) {}
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
}
if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
}
if ( ! function_exists( 'wp_die' ) ) {
    function wp_die( $message = '', $title = '', $args = array() ) { exit; }
}

/**
 * 創建受限的網站管理員角色
 * 在主題啟用時和插件激活時執行
 */
function create_limited_admin_role() {
    // 檢查角色是否已存在，避免重複創建
    if ( get_role( 'limited_admin' ) ) {
        return;
    }

    // 創建受限的一般管理員角色
    add_role(
        'limited_admin',
        '專用管理員',
        array(
            // 基本權限
            'read' => true,
            
            // 文章管理權限
            'edit_posts' => true,
            'edit_others_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => true,
            'delete_others_posts' => true,
            'delete_published_posts' => true,
            
            // 頁面管理權限
            'edit_pages' => true,
            'edit_others_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_others_pages' => true,
            'delete_published_pages' => true,
            
            // 內容管理權限
            'manage_categories' => true,
            'manage_links' => true,
            'moderate_comments' => true,
            'upload_files' => true,
            'unfiltered_html' => true,      

            // Elementor 權限（使用實際存在的權限）
            'create_notes_elementor-pro' => true,
            'edit_notes_elementor-pro' => true,
            'delete_notes_elementor-pro' => true,
            'read_notes_elementor-pro' => true,
            'edit_others_notes_elementor-pro' => true,
            'delete_others_notes_elementor-pro' => true,
            'read_others_private_notes_elementor-pro' => true,
            //Rank Math SEO
            'rank_math_edit_htaccess' => true,
            'rank_math_titles' => true,
            'rank_math_general' => true,
            'rank_math_sitemap' => true,
            'rank_math_404_monitor' => true,
            'rank_math_link_builder' => true,
            'rank_math_redirections' => true,
            'rank_math_role_manager' => true,
            'rank_math_analytics' => true,
            'rank_math_site_analysis' => true,
            'rank_math_onpage_analysis' => true,
            'rank_math_onpage_general' => true,
            'rank_math_onpage_advanced' => true,
            'rank_math_onpage_snippet' => true,
            'rank_math_onpage_social' => true,
            'rank_math_content_ai' => true,
            'rank_math_admin_bar' => true,


            // 重點：移除這些敏感權限
            'manage_options' => true,     // 不能管理網站設置
            'edit_users' => true,          // 可以編輯用戶
            'create_users' => true,        // 可以創建用戶
            'delete_users' => true,        // 可以刪除用戶
            'list_users' => true,          // 可以查看用戶列表
            'promote_users' => true,       // 可以變更用戶角色
            'remove_users' => true,        // 可以移除用戶
            'edit_themes' => true,        // 不能編輯主題
            'install_plugins' => true,    // 不能安裝插件
            'activate_plugins' => true,   // 不能啟用插件
            'edit_plugins' => true,       // 不能編輯插件
            'delete_plugins' => true,     // 不能刪除插件
            'update_plugins' => true,     // 不能更新插件
            'update_themes' => true,      // 不能更新主題
            'update_core' => false,        // 不能更新核心
        )
    );
}

/**
 * 設置受限管理員可以看到的管理頁面
 */
function limit_admin_menu_access() {
    // 只要不是Administrator就生效
    if (current_user_can('administrator')) {
        return;
    }

    // 移除不需要的管理選單項目
    //remove_menu_page( 'tools.php' );                    // 工具
    //remove_menu_page( 'options-general.php' );          // 設置
    // remove_menu_page( 'users.php' );                 // 用戶 - 允許一般管理員管理用戶
    //remove_menu_page( 'themes.php' );                   // 外觀
    //remove_menu_page( 'plugins.php' );                  // 插件
    
    // 保留 Elementor 主選單，但隱藏模板庫
    //remove_menu_page( 'edit.php?post_type=elementor_library' ); // Elementor 模板庫
    
    // 隱藏許可權相關頁面
    remove_submenu_page( 'elementor', 'elementor-system-info');
    remove_submenu_page( 'elementor', 'elementor-role-manager' ); // Elementor 角色管理
    remove_submenu_page( 'elementor', 'elementor-license' );      // Elementor 許可證

    remove_submenu_page('themes.php', 'theme-json-import');
    remove_submenu_page('themes.php', 'modular-page-manager');
    
    remove_menu_page('uaepro');
    remove_menu_page('edit.php?post_type=acf-field-group');
    remove_menu_page('one-user-avatar');
    // 移除設置子選單
    /*
    remove_submenu_page( 'options-general.php', 'options-writing.php' );
    remove_submenu_page( 'options-general.php', 'options-reading.php' );
    remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    remove_submenu_page( 'options-general.php', 'options-media.php' );
    remove_submenu_page( 'options-general.php', 'options-permalink.php' );
    remove_submenu_page( 'options-general.php', 'privacy.php' );
    */
}

/**
 * 隱藏管理欄中的某些項目
 */
function limit_admin_bar_items() {
    global $wp_admin_bar;
    
    if (current_user_can('administrator')) {
        return;
    }
    // 移除管理欄項目
    $wp_admin_bar->remove_node( 'themes' );             // 主題
    $wp_admin_bar->remove_node( 'customize' );          // 自定義
    $wp_admin_bar->remove_node( 'widgets' );            // 小工具
    $wp_admin_bar->remove_node( 'menus' );              // 選單
}

/**
 * 重定向受限管理員到儀表板
 */

function redirect_limited_admin_from_restricted_pages() {
    // 檢查是否為 limited_admin 角色
    if (!current_user_can('limited_admin') || current_user_can('administrator')) {
        return;
    }
    
    global $pagenow;
    
    // 受限的檔案頁面
    $restricted_files = [];
    
    // 受限的 admin.php?page= 頁面
    $restricted_pages = ['elementor-role-manager', 'elementor-license', 'uaepro'];
    
    // 檢查並重導向
    if (in_array($pagenow, $restricted_files) || 
        ($pagenow === 'admin.php' && isset($_GET['page']) && in_array($_GET['page'], $restricted_pages))) {
        
        wp_redirect(admin_url('index.php'));
        exit;
    }
}

/**
 * 隱藏超級管理員不讓其他管理員看到
 */
function hide_super_admin_from_admin($user_search) {
    global $current_user;
    
    // 假設A管理員的ID是1（通常是第一個管理員）
    if($current_user->ID != 1) {
        global $wpdb;
        $user_search->query_where = str_replace(
            'WHERE 1=1',
            "WHERE 1=1 AND {$wpdb->users}.ID != 1",
            $user_search->query_where
        );
    }
}

/**
 * 移除受限管理員角色（清理函數）
 */
function remove_limited_admin_role() {
    remove_role( 'limited_admin' );
}

/**
 * 外掛管理控制類別
 * 隱藏和保護特定外掛不被非管理員用戶操作
 */
class PluginManagerControl {


    private $hidden_plugins = array(
        'elementor/elementor.php',
        'elementor-pro/elementor-pro.php',
        'ultimate-elementor/ultimate-elementor.php',
        'advanced-custom-fields/acf.php',
        'flying-press/flying-press.php',
        'one-user-avatar/one-user-avatar.php',
        'seo-by-rank-math-pro/rank-math-pro.php',
        'insert-headers-and-footers/ihaf.php',
    );
    
    private $protected_plugins = array(
        'elementor/elementor.php',
        'elementor-pro/elementor-pro.php',
        'ultimate-elementor/ultimate-elementor.php',
        'advanced-custom-fields/acf.php',
        'flying-press/flying-press.php',
        'one-user-avatar/one-user-avatar.php',
        'seo-by-rank-math-pro/rank-math-pro.php',
        'google-site-kit/google-site-kit.php',
        'insert-headers-and-footers/ihaf.php',
    );
    
    public function __construct() {
        add_filter('all_plugins', array($this, 'hide_plugins'));
        add_filter('plugin_action_links', array($this, 'modify_plugin_actions'), 10, 4);
        add_filter('network_admin_plugin_action_links', array($this, 'modify_plugin_actions'), 10, 4);
        add_action('delete_plugin', array($this, 'prevent_deletion'));
        add_action('admin_head-plugins.php', array($this, 'add_custom_styles'));
    }
    
    public function hide_plugins($plugins) {
        if (current_user_can('administrator')) {
            return $plugins;
        }
        
        foreach ($this->hidden_plugins as $plugin) {
            if (isset($plugins[$plugin])) {
                unset($plugins[$plugin]);
            }
        }
        
        return $plugins;
    }
    
    public function modify_plugin_actions($actions, $plugin_file, $plugin_data, $context) {
        if (current_user_can('administrator')) {
            return $actions;
        }
        
        if (in_array($plugin_file, $this->protected_plugins)) {
            // 移除停用和刪除連結
            unset($actions['deactivate']);
            unset($actions['delete']);
            
            // 添加保護標記
            $actions['protected'] = '<span class="protected-plugin">🔒 受保護</span>';
        }
        
        return $actions;
    }
    
    public function prevent_deletion($file) {
        if (current_user_can('administrator')) {
            return;
        }
        
        if (in_array($file, $this->protected_plugins)) {
            wp_die('您沒有權限刪除此外掛。');
        }
    }
    
    public function add_custom_styles() {
        if (current_user_can('administrator')) {
            return;
        }
        ?>
        <style>
        .protected-plugin {
            color: #d63638;
            font-weight: bold;
        }
        </style>
        <?php
    }
}

// 註冊鉤子
add_action( 'after_switch_theme', 'create_limited_admin_role' );
register_activation_hook( __FILE__, 'create_limited_admin_role' );
add_action( 'admin_menu', 'limit_admin_menu_access', 999 );
add_action( 'wp_before_admin_bar_render', 'limit_admin_bar_items' );
add_action( 'admin_init', 'redirect_limited_admin_from_restricted_pages' );
add_action( 'pre_user_query', 'hide_super_admin_from_admin' );

// 初始化外掛管理控制
new PluginManagerControl();

// 主題停用時清理角色（可選）
// register_deactivation_hook( __FILE__, 'remove_limited_admin_role' );
/*
add_action('admin_menu', function () {
    global $menu;
    echo '<pre>';
    print_r($menu);
    echo '</pre>';
});*/