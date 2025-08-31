<?php
/**
 * 主題存取控制模組
 * 
 * 限制主題變更權限，只允許 ID=1 的管理員可以變更主題
 * 其他管理員無法存取主題相關功能
 * 
 * @package HelloElementorChild
 * @subpackage Modules/ThemeAccessControl
 * @version 1.0.0
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ThemeAccessControl Class
 * 
 * 控制主題變更權限
 * 
 * @since 2.0.0
 * @version 1.0.0
 */
class ThemeAccessControl {
    
    /**
     * 建構函式
     */
    public function __construct() {
        // 檢查並限制主題相關權限
        add_action('admin_init', [$this, 'restrict_theme_access']);
        add_action('admin_menu', [$this, 'remove_theme_menus'], 999);
        add_filter('user_has_cap', [$this, 'filter_theme_capabilities'], 10, 3);
        
        // 隱藏主題相關的管理列選項
        add_action('admin_bar_menu', [$this, 'remove_admin_bar_theme_options'], 999);
        
        // 限制主題自訂器存取
        add_action('load-customize.php', [$this, 'restrict_customizer_access']);
        
        // 限制主題編輯器
        add_action('load-theme-editor.php', [$this, 'restrict_theme_editor']);
    }
    
    /**
     * 檢查是否為 ID=1 的管理員
     * 
     * @return bool
     */
    private function is_super_admin() {
        $current_user = wp_get_current_user();
        return ($current_user->ID === 1);
    }
    
    /**
     * 限制主題相關頁面的存取
     */
    public function restrict_theme_access() {
        // 如果是 ID=1 的管理員，不做任何限制
        if ($this->is_super_admin()) {
            return;
        }
        
        global $pagenow;
        
        // 需要限制的頁面列表
        $restricted_pages = [
            'themes.php',
            'theme-install.php',
            'update.php' // 當更新主題時
        ];
        
        // 檢查當前頁面
        if (in_array($pagenow, $restricted_pages)) {
            // 允許存取某些子頁面（如選單、小工具等）
            $allowed_subpages = [
                'nav-menus.php',
                'widgets.php',
                'theme-json-import', // 保留 JSON 匯入頁面的判斷（已在其他地方移除）
                'advanced-theme-style-settings', // 配色設定頁面
                'modular-page-manager' // 模組化頁面管理
            ];
            
            $current_page = isset($_GET['page']) ? $_GET['page'] : '';
            
            // 如果不是允許的子頁面，且正在嘗試變更主題
            if (!in_array($current_page, $allowed_subpages)) {
                // 檢查是否正在嘗試啟用主題
                if (isset($_GET['action']) && $_GET['action'] === 'activate') {
                    wp_die('您沒有權限變更主題。只有超級管理員（ID=1）可以執行此操作。');
                }
                
                // 如果在主題列表頁面，重定向到儀表板
                if ($pagenow === 'themes.php' && empty($current_page)) {
                    wp_redirect(admin_url());
                    exit;
                }
            }
        }
        
        // 限制主題更新
        if ($pagenow === 'update.php' && isset($_GET['action']) && $_GET['action'] === 'upgrade-theme') {
            wp_die('您沒有權限更新主題。只有超級管理員（ID=1）可以執行此操作。');
        }
    }
    
    /**
     * 移除主題相關的選單項目
     */
    public function remove_theme_menus() {
        // 如果是 ID=1 的管理員，不移除選單
        if ($this->is_super_admin()) {
            return;
        }
        
        // 移除外觀下的主題選單
        remove_submenu_page('themes.php', 'themes.php');
        
        // 移除主題編輯器
        remove_submenu_page('themes.php', 'theme-editor.php');
        
        // 移除自訂選單（如果需要的話）
        // remove_submenu_page('themes.php', 'customize.php');
    }
    
    /**
     * 過濾使用者權限
     * 
     * @param array $allcaps 所有權限
     * @param array $caps 需要的權限
     * @param array $args 參數
     * @return array
     */
    public function filter_theme_capabilities($allcaps, $caps, $args) {
        // 如果是 ID=1 的管理員，保持所有權限
        if ($this->is_super_admin()) {
            return $allcaps;
        }
        
        // 需要限制的權限列表
        $restricted_caps = [
            'switch_themes',
            'edit_themes',
            'install_themes',
            'update_themes',
            'delete_themes'
        ];
        
        // 移除這些權限
        foreach ($restricted_caps as $cap) {
            if (isset($allcaps[$cap])) {
                $allcaps[$cap] = false;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * 從管理列移除主題相關選項
     * 
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function remove_admin_bar_theme_options($wp_admin_bar) {
        // 如果是 ID=1 的管理員，不移除
        if ($this->is_super_admin()) {
            return;
        }
        
        // 移除自訂連結
        $wp_admin_bar->remove_node('customize');
        
        // 移除主題選項
        $wp_admin_bar->remove_node('themes');
    }
    
    /**
     * 限制自訂器存取
     */
    public function restrict_customizer_access() {
        // 如果不是 ID=1 的管理員，禁止存取
        if (!$this->is_super_admin()) {
            wp_die('您沒有權限存取主題自訂器。只有超級管理員（ID=1）可以使用此功能。');
        }
    }
    
    /**
     * 限制主題編輯器存取
     */
    public function restrict_theme_editor() {
        // 如果不是 ID=1 的管理員，禁止存取
        if (!$this->is_super_admin()) {
            wp_die('您沒有權限存取主題編輯器。只有超級管理員（ID=1）可以使用此功能。');
        }
    }
}

// 初始化主題存取控制
new ThemeAccessControl();