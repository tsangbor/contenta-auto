<?php
/**
 * 用戶角色管理系統
 * 
 * 創建和管理受限的網站管理員角色，提供精細化權限控制
 * 包含角色創建、權限管理、選單限制、外掛保護等功能
 * 
 * @package HelloElementorChild
 * @subpackage Modules/UserRoleManager
 * @version 1.0.2
 * @since 2.0.0
 * @author Your Name
 * 
 * === WP-CLI 使用指南 ===
 * 
 * 本模組提供完整的 WP-CLI 指令來管理用戶角色，以下為常用操作順序：
 * 
 * 1. 📋 檢查目前角色狀態
 *    wp user-role status --allow-root
 *    # 顯示 limited_admin 角色是否存在及其權限配置
 * 
 * 2. 🔧 創建受限管理員角色
 *    wp user-role create --allow-root
 *    # 創建具有內容管理權限但受限系統權限的角色
 * 
 * 3. 👥 查看所有用戶列表
 *    wp user-role list-users --allow-root
 *    # 顯示所有用戶及其角色的表格
 * 
 * 4. 🎯 指派用戶為受限管理員
 *    wp user-role assign <user_id> --allow-root
 *    wp user-role assign <username> --allow-root
 *    # 範例：wp user-role assign 2 --allow-root
 *    # 範例：wp user-role assign admin_user --allow-root
 * 
 * 5. 🔄 重置角色權限（重新創建）
 *    wp user-role reset --allow-root
 *    # 刪除現有角色並重新創建，用於更新權限配置
 * 
 * 6. 🗑️ 刪除受限管理員角色
 *    wp user-role delete --allow-root
 *    # 完全移除 limited_admin 角色（謹慎使用）
 * 
 * === 完整部署流程 ===
 * 
 * 新站點設置建議順序：
 * 1. wp user-role status --allow-root          # 檢查現狀
 * 2. wp user-role create --allow-root          # 創建角色
 * 3. wp user-role list-users --allow-root      # 查看用戶
 * 4. wp user-role assign [用戶ID] --allow-root # 指派角色
 * 5. wp user-role status --allow-root          # 驗證結果
 * 
 * === 權限說明 ===
 * 
 * limited_admin 角色權限配置：
 * ✅ 允許權限：
 * - 文章與頁面的完整管理（創建、編輯、刪除、發布）
 * - 媒體庫管理和檔案上傳
 * - 分類和標籤管理
 * - 留言審核
 * - Elementor 編輯權限
 * - Rank Math SEO 完整權限
 * - 用戶管理（創建、編輯、刪除用戶）
 * - 網站設定管理
 * - 外掛和主題管理
 * 
 * ❌ 限制權限：
 * - WordPress 核心更新（update_core = false）
 * 
 * === 安全機制 ===
 * 
 * 1. 選單限制：非管理員用戶無法看到敏感設定頁面
 * 2. 外掛保護：核心外掛被保護，無法被停用或刪除
 * 3. 超級管理員隱藏：ID=1 的用戶對其他管理員隱藏
 * 4. 頁面重定向：限制訪問敏感管理頁面
 * 
 * === 故障排除 ===
 * 
 * 如果 WP-CLI 指令無法使用，可使用以下替代方案：
 * 
 * 直接創建角色：
 * wp eval 'create_limited_admin_role(); echo "角色創建完成\n";' --allow-root
 * 
 * 檢查角色是否存在：
 * wp eval 'var_dump(get_role("limited_admin"));' --allow-root
 * 
 * 列出所有角色：
 * wp role list --allow-root
 * 
 * Features:
 * - 受限管理員角色創建
 * - 精細化權限控制
 * - 管理選單限制
 * - 外掛保護機制
 * - 超級管理員隱藏
 * - 管理欄項目控制
 * - 頁面訪問重定向
 * - 完整 WP-CLI 指令支援
 * 
 * Changelog:
 * 1.0.2 - 2025-07-18
 * - 新增 ShortPixel API Key 隱藏功能
 * - 只有 USER ID=1 可以看到 ShortPixel 設定頁面的 API Key 區塊
 * - 新增 Elementor 官方範本庫隱藏功能
 * - 只有 USER ID=1 可以看到 Elementor 官方範本（Cloud templates、Site templates）
 * - 多重保護機制：CSS 隱藏 + JavaScript 備用方案
 * - 自動檢測頁面並套用使用者限制
 * 
 * 1.0.1 - 2025-07-07
 * - 新增完整 WP-CLI 指令系統
 * - 新增詳細使用指南和操作流程
 * - 改善權限配置說明
 * - 新增故障排除指引
 * - 強化開發者文檔
 * 
 * 1.0.0 - 2025-01-06
 * - 初始版本
 * - 專用管理員角色創建
 * - 基本權限控制
 * - Elementor 權限整合
 * - Rank Math SEO 權限
 * - 外掛保護機制
 * - 選單訪問限制
 * - 系統安全加固
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * WordPress 核心函數檢查
 * 
 * 確保在 WordPress 環境中運行，提供備用函數避免錯誤
 * 
 * @since 1.0.0
 */
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
 * 
 * 在主題啟用時和插件激活時執行
 * 提供完整的內容管理權限，但限制系統級操作
 * 
 * @since 1.0.0
 * @version 1.0.0
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

            // 🔧 新增：選單管理權限
            'edit_theme_options' => true,   // 關鍵權限：主題選項編輯（包含選單管理）

            // Elementor 權限（使用實際存在的權限）
            'create_notes_elementor-pro' => true,
            'edit_notes_elementor-pro' => true,
            'delete_notes_elementor-pro' => true,
            'read_notes_elementor-pro' => true,
            'edit_others_notes_elementor-pro' => true,
            'delete_others_notes_elementor-pro' => true,
            'read_others_private_notes_elementor-pro' => true,
            
            // Rank Math SEO 權限
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

            // 系統管理權限（謹慎設定）
            'manage_options' => true,     // 能管理網站設置
            'edit_users' => true,          // 可以編輯用戶
            'create_users' => true,        // 可以創建用戶
            'delete_users' => true,        // 可以刪除用戶
            'list_users' => true,          // 可以查看用戶列表
            'promote_users' => true,       // 可以變更用戶角色
            'remove_users' => true,        // 可以移除用戶
            'edit_themes' => true,        // 能編輯主題
            'install_plugins' => true,    // 能安裝插件
            'activate_plugins' => true,   // 能啟用插件
            'edit_plugins' => true,       // 能編輯插件
            'delete_plugins' => true,     // 能刪除插件
            'update_plugins' => true,     // 能更新插件
            'update_themes' => true,      // 能更新主題
            'update_core' => false,        // 不能更新核心
        )
    );
}

/**
 * 設置受限管理員可以看到的管理頁面
 * 
 * 限制非管理員角色的選單訪問權限
 * 
 * @since 1.0.0
 */
function limit_admin_menu_access() {
    // 只要不是Administrator就生效
    if (current_user_can('administrator')) {
        return;
    }

    // 移除敏感的子選單項目
    remove_submenu_page( 'elementor', 'elementor-system-info');
    remove_submenu_page( 'elementor', 'elementor-role-manager' ); // Elementor 角色管理
    remove_submenu_page( 'elementor', 'elementor-license' );      // Elementor 許可證

    remove_submenu_page('themes.php', 'theme-json-import');
    remove_submenu_page('themes.php', 'modular-page-manager');
    
    remove_menu_page('uaepro');
    remove_menu_page('edit.php?post_type=acf-field-group');
    remove_menu_page('one-user-avatar');
}

/**
 * 隱藏管理欄中的某些項目
 * 
 * 限制非管理員角色的管理欄功能
 * 
 * @since 1.0.0
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
 * 
 * 防止受限角色訪問敏感頁面
 * 
 * @since 1.0.0
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
 * 
 * 保護主管理員帳戶不被其他用戶發現
 * 
 * @param WP_User_Query $user_search 用戶查詢物件
 * @since 1.0.0
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
 * 
 * 主題停用時的清理工作
 * 
 * @since 1.0.0
 */
function remove_limited_admin_role() {
    remove_role( 'limited_admin' );
}

/**
 * 外掛管理控制類別
 * 
 * 隱藏和保護特定外掛不被非管理員用戶操作
 * 提供完整的外掛保護機制
 * 
 * @since 1.0.0
 * @version 1.0.0
 */
class PluginManagerControl {

    /**
     * 需要隱藏的外掛列表
     * 
     * @var array
     * @since 1.0.0
     */
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
    
    /**
     * 受保護的外掛列表
     * 
     * @var array
     * @since 1.0.0
     */
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
    
    /**
     * 建構函式
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_filter('all_plugins', array($this, 'hide_plugins'));
        add_filter('plugin_action_links', array($this, 'modify_plugin_actions'), 10, 4);
        add_filter('network_admin_plugin_action_links', array($this, 'modify_plugin_actions'), 10, 4);
        add_action('delete_plugin', array($this, 'prevent_deletion'));
        add_action('admin_head-plugins.php', array($this, 'add_custom_styles'));
        
        // 新增 ShortPixel API Key 隱藏功能
        add_action('admin_footer', array($this, 'hide_shortpixel_apikey_for_non_admin'));
        
        // 新增 Elementor 官方範本庫隱藏功能
        add_action('init', array($this, 'hide_elementor_library_for_non_admin'));
    }
    
    /**
     * 隱藏外掛列表
     * 
     * @param array $plugins 所有外掛
     * @return array 修改後的外掛列表
     * @since 1.0.0
     */
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
    
    /**
     * 修改外掛操作連結
     * 
     * @param array $actions 操作連結
     * @param string $plugin_file 外掛檔案
     * @param array $plugin_data 外掛資料
     * @param string $context 上下文
     * @return array 修改後的操作連結
     * @since 1.0.0
     */
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
    
    /**
     * 防止外掛被刪除
     * 
     * @param string $file 外掛檔案
     * @since 1.0.0
     */
    public function prevent_deletion($file) {
        if (current_user_can('administrator')) {
            return;
        }
        
        if (in_array($file, $this->protected_plugins)) {
            wp_die('您沒有權限刪除此外掛。');
        }
    }
    
    /**
     * 添加自訂樣式
     * 
     * @since 1.0.0
     */
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
    
    /**
     * 隱藏 ShortPixel API Key 設定（僅 USER ID=1 可見）
     * 
     * 只有 USER ID=1 的用戶可以在 ShortPixel 設定頁面看到 API Key 相關設定
     * 其他用戶將無法看到包含 API Key 的 settinglist 區塊
     * 
     * @since 1.0.2
     */
    public function hide_shortpixel_apikey_for_non_admin() {
        // 檢查是否在 ShortPixel 設定頁面
        global $pagenow;
        if ($pagenow !== 'options-general.php' || !isset($_GET['page']) || $_GET['page'] !== 'wp-shortpixel-settings') {
            return;
        }
        
        // 取得目前用戶 ID
        $current_user_id = get_current_user_id();
        
        // 如果是 USER ID=1，顯示所有內容
        if ($current_user_id === 1) {
            return;
        }
        
        // 其他用戶隱藏 API Key 相關設定
        ?>
        <style>
        /* 隱藏包含 API Key 的 settinglist */
        settinglist:has(closed-apikey-dropdown) {
            display: none !important;
        }
        
        /* 備用方案：直接隱藏 API Key 相關元素 */
        closed-apikey-dropdown,
        settinglist closed-apikey-dropdown,
        settinglist content .apifield,
        settinglist content #validate {
            display: none !important;
        }
        
        /* 隱藏包含 API Key 文字的 settinglist */
        settinglist:has([id="key"]) {
            display: none !important;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // JavaScript 備用方案：隱藏包含 API Key 的 settinglist
            $('settinglist').each(function() {
                var $settinglist = $(this);
                
                // 檢查是否包含 API Key 相關元素
                if ($settinglist.find('closed-apikey-dropdown').length > 0 ||
                    $settinglist.find('#key').length > 0 ||
                    $settinglist.find('.apifield').length > 0 ||
                    $settinglist.text().indexOf('API Key') !== -1) {
                    
                    $settinglist.hide();
                    console.log('ShortPixel API Key setting hidden for non-admin user');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * 隱藏 Elementor 官方範本庫（僅 USER ID=1 可見）
     * 
     * 只有 USER ID=1 的用戶可以看到 Elementor 編輯器中的官方範本庫
     * 其他用戶（包括管理員）都無法看到 Cloud templates、Site templates 等官方範本
     * 
     * @since 1.0.2
     */
    public function hide_elementor_library_for_non_admin() {
        // 取得目前用戶 ID
        $current_user_id = get_current_user_id();
        
        // 如果是 USER ID=1，顯示所有內容
        if ($current_user_id === 1) {
            return;
        }
        
        // 其他用戶隱藏 Elementor 官方範本庫
        add_action('elementor/editor/footer', function() {
            ?>
            <style>
                /* 隱藏「Cloud templates」和「Site templates」按鈕 */
                .elementor-template-library-header-menu a:first-of-type,
                .elementor-template-library-header-menu a:nth-of-type(2),
                [data-tab="templates/my-templates"] {
                    display: none !important;
                }

                /* 讓剩餘的按鈕填滿寬度 */
                .elementor-template-library-header-menu {
                    width: 100%;
                }
                
                /* 隱藏其他可能的官方範本相關元素 */
                .elementor-template-library-template-remote,
                .elementor-template-library-template[data-template-source="remote"] {
                    display: none !important;
                }
            </style>
            <script>
            jQuery(document).ready(function($) {
                // JavaScript 備用方案：動態隱藏官方範本
                function hideElementorOfficialTemplates() {
                    // 隱藏官方範本庫標籤
                    $('.elementor-template-library-header-menu a').each(function() {
                        var text = $(this).text().trim();
                        if (text.indexOf('Cloud') !== -1 || 
                            text.indexOf('Site') !== -1 || 
                            text.indexOf('templates') !== -1) {
                            $(this).hide();
                        }
                    });
                    
                    // 隱藏遠端範本
                    $('.elementor-template-library-template-remote').hide();
                    $('[data-template-source="remote"]').hide();
                    
                    console.log('Elementor official templates hidden for non-admin user');
                }
                
                // 立即執行
                hideElementorOfficialTemplates();
                
                // 監聽 Elementor 範本庫載入
                $(document).on('DOMNodeInserted', function(e) {
                    if ($(e.target).hasClass('elementor-template-library-header-menu')) {
                        setTimeout(hideElementorOfficialTemplates, 100);
                    }
                });
            });
            </script>
            <?php
        });
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

/**
 * WP-CLI 用戶角色管理指令
 * 
 * 提供 WP-CLI 指令來管理受限管理員角色
 * 
 * @since 1.0.1
 */
class WP_CLI_User_Role_Command {
    
    /**
     * 創建受限管理員角色
     * 
     * ## EXAMPLES
     * 
     *     wp user-role create
     * 
     * @since 1.0.1
     */
    public function create( $args, $assoc_args ) {
        if ( get_role( 'limited_admin' ) ) {
            WP_CLI::warning( '受限管理員角色已經存在' );
            return;
        }
        
        create_limited_admin_role();
        
        if ( get_role( 'limited_admin' ) ) {
            WP_CLI::success( '成功創建受限管理員角色 (limited_admin)' );
        } else {
            WP_CLI::error( '創建受限管理員角色失敗' );
        }
    }
    
    /**
     * 刪除受限管理員角色
     * 
     * ## EXAMPLES
     * 
     *     wp user-role delete
     * 
     * @since 1.0.1
     */
    public function delete( $args, $assoc_args ) {
        if ( ! get_role( 'limited_admin' ) ) {
            WP_CLI::warning( '受限管理員角色不存在' );
            return;
        }
        
        remove_limited_admin_role();
        
        if ( ! get_role( 'limited_admin' ) ) {
            WP_CLI::success( '成功刪除受限管理員角色 (limited_admin)' );
        } else {
            WP_CLI::error( '刪除受限管理員角色失敗' );
        }
    }
    
    /**
     * 檢查受限管理員角色狀態
     * 
     * ## EXAMPLES
     * 
     *     wp user-role status
     * 
     * @since 1.0.1
     */
    public function status( $args, $assoc_args ) {
        $role = get_role( 'limited_admin' );
        
        if ( $role ) {
            WP_CLI::success( '受限管理員角色已存在' );
            WP_CLI::line( '角色名稱: 專用管理員' );
            WP_CLI::line( '角色 ID: limited_admin' );
            
            // 顯示關鍵權限狀態
            $key_caps = [
                'manage_options' => '管理網站設定',
                'edit_users' => '編輯用戶',
                'create_users' => '創建用戶',
                'delete_users' => '刪除用戶',
                'install_plugins' => '安裝外掛',
                'edit_themes' => '編輯主題',
                'edit_posts' => '編輯文章',
                'edit_pages' => '編輯頁面'
            ];
            
            WP_CLI::line( '關鍵權限狀態:' );
            foreach ( $key_caps as $cap => $desc ) {
                $has_cap = isset( $role->capabilities[$cap] ) && $role->capabilities[$cap];
                $status = $has_cap ? '✅ 有' : '❌ 無';
                WP_CLI::line( "  {$desc}: {$status}" );
            }
            
        } else {
            WP_CLI::warning( '受限管理員角色不存在' );
        }
    }
    
    /**
     * 列出所有用戶及其角色
     * 
     * ## EXAMPLES
     * 
     *     wp user-role list-users
     * 
     * @since 1.0.1
     */
    public function list_users( $args, $assoc_args ) {
        $users = get_users();
        
        $table_data = [];
        foreach ( $users as $user ) {
            $roles = implode( ', ', $user->roles );
            $table_data[] = [
                'ID' => $user->ID,
                'Username' => $user->user_login,
                'Email' => $user->user_email,
                'Roles' => $roles,
                'Display Name' => $user->display_name
            ];
        }
        
        WP_CLI\Utils\format_items( 'table', $table_data, [ 'ID', 'Username', 'Email', 'Roles', 'Display Name' ] );
    }
    
    /**
     * 將用戶設為受限管理員
     * 
     * ## OPTIONS
     * 
     * <user>
     * : 用戶 ID 或用戶名
     * 
     * ## EXAMPLES
     * 
     *     wp user-role assign 2
     *     wp user-role assign admin_user
     * 
     * @since 1.0.1
     */
    public function assign( $args, $assoc_args ) {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( '請指定用戶 ID 或用戶名' );
        }
        
        $user = get_user_by( is_numeric( $args[0] ) ? 'ID' : 'login', $args[0] );
        
        if ( ! $user ) {
            WP_CLI::error( "找不到用戶: {$args[0]}" );
        }
        
        if ( ! get_role( 'limited_admin' ) ) {
            WP_CLI::warning( '受限管理員角色不存在，正在創建...' );
            create_limited_admin_role();
        }
        
        $user->set_role( 'limited_admin' );
        
        WP_CLI::success( "成功將用戶 {$user->user_login} (ID: {$user->ID}) 設為受限管理員" );
    }
    
    /**
     * 重置角色（重新創建）
     * 
     * ## EXAMPLES
     * 
     *     wp user-role reset
     * 
     * @since 1.0.1
     */
    public function reset( $args, $assoc_args ) {
        // 先刪除
        if ( get_role( 'limited_admin' ) ) {
            remove_limited_admin_role();
            WP_CLI::line( '已刪除現有角色' );
        }
        
        // 再創建
        create_limited_admin_role();
        
        if ( get_role( 'limited_admin' ) ) {
            WP_CLI::success( '成功重置受限管理員角色' );
        } else {
            WP_CLI::error( '重置角色失敗' );
        }
    }
}

// 註冊 WP-CLI 指令
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'user-role', 'WP_CLI_User_Role_Command' );
}