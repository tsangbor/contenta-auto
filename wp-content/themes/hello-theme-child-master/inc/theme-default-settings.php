<?php
/**
 * 主題預設參數註冊系統
 * 
 * 提供主題預設參數管理、JSON 匯入、GPT 佈局選擇、Elementor 動態標籤支援
 * 包含完整的資料管理和 WordPress 系統設定同步功能
 * 
 * @package HelloElementorChild
 * @subpackage Modules/ThemeDefaultSettings
 * @version 1.0.1
 * @since 2.0.0
 * @author Your Name
 * 
 * === WP-CLI JSON 設定管理指令使用指南 ===
 * 
 * 本模組主要透過 WordPress 管理介面操作，但提供以下 WP-CLI 指令來檢查和管理設定：
 * 
 * 🔍 檢查和查看指令：
 * 
 * 1. 📋 檢查所有主題設定值
 *    wp option list --search="index_*" --allow-root
 *    wp option list --search="website_*" --allow-root
 *    wp option list --search="seo_*" --allow-root
 *    # 列出所有主題相關的設定選項
 * 
 * 2. 🔍 查看特定設定值
 *    wp option get index_hero_title --allow-root
 *    wp option get index_service_list --allow-root
 *    wp option get layout_selection --allow-root
 *    # 查看個別設定項目的值
 * 
 * 3. 📊 檢查佈局決策狀態
 *    wp option get last_layout_decision --allow-root
 *    wp option get layout_reasoning --allow-root
 *    wp option get selected_layout_option --allow-root
 *    # 查看 GPT 佈局選擇的相關資料
 * 
 * 🛠️ 設定管理指令：
 * 
 * 4. ✏️ 更新個別設定
 *    wp option update index_hero_title "新的標題" --allow-root
 *    wp option update website_blogname "新的網站名稱" --allow-root
 *    wp option update website_author_nickname "新暱稱" --allow-root
 *    # 手動更新特定設定值
 * 
 * 5. 🧹 清除特定設定
 *    wp option delete index_hero_title --allow-root
 *    wp option delete layout_selection --allow-root
 *    wp option delete last_layout_decision --allow-root
 *    # 刪除特定設定項目
 * 
 * 6. 🔄 重置所有主題設定為預設值
 *    wp option delete index_hero_title index_hero_subtitle index_hero_content index_about_title index_service_list layout_selection layout_reasoning --allow-root
 *    # 批次刪除多個設定項目
 * 
 * 🗄️ 資料庫操作指令：
 * 
 * 7. 💾 備份主題設定到 JSON 檔案
 *    wp option list --search="index_*" --format=json --allow-root > theme-settings-backup.json
 *    wp option list --search="layout_*" --format=json --allow-root > layout-settings-backup.json
 *    # 將設定匯出為 JSON 檔案備份
 * 
 * 8. 🔍 檢查服務列表結構
 *    wp option get index_service_list --format=json --allow-root
 *    # 以 JSON 格式查看服務列表的完整結構
 * 
 * 9. 📈 檢查 WordPress 系統設定同步狀態
 *    wp option get blogname --allow-root
 *    wp option get blogdescription --allow-root
 *    wp user meta get 1 nickname --allow-root
 *    wp user meta get 1 description --allow-root
 *    # 檢查與 WordPress 核心設定的同步狀態
 * 
 * === 主要設定項目列表 ===
 * 
 * 🎯 Hero 區塊設定：
 * • index_hero_bg        - Hero 背景圖片 URL
 * • index_hero_photo     - Hero 人物照片 URL
 * • index_hero_title     - Hero 主標題
 * • index_hero_subtitle  - Hero 副標題
 * • index_hero_cta_text  - Hero CTA 按鈕文字
 * • index_hero_cta_link  - Hero CTA 按鈕連結
 * 
 * 👤 關於我區塊設定：
 * • index_about_title    - 關於我標題
 * • index_about_subtitle - 關於我副標題
 * • index_about_content  - 關於我內容
 * • index_about_photo    - 關於我照片 URL
 * • index_about_cta_text - 關於我 CTA 文字
 * • index_about_cta_link - 關於我 CTA 連結
 * 
 * 🛠️ 服務項目設定：
 * • index_service_title    - 服務區塊標題
 * • index_service_subtitle - 服務區塊副標題
 * • index_service_list     - 服務項目陣列（JSON 格式）
 * • index_service_cta_text - 服務 CTA 文字
 * • index_service_cta_link - 服務 CTA 連結
 * 
 * 🦶 頁尾設定：
 * • index_footer_title     - 頁尾標題
 * • index_footer_subtitle  - 頁尾副標題
 * • index_footer_fb        - Facebook 連結
 * • index_footer_ig        - Instagram 連結
 * • index_footer_line      - LINE 連結
 * • index_footer_yt        - YouTube 連結
 * • index_footer_email     - Email 聯絡方式
 * 
 * 🎨 佈局管理設定：
 * • layout_selection       - GPT 選擇的佈局組合
 * • layout_reasoning       - 每個佈局的選擇理由
 * • last_layout_decision   - 最後的佈局決策記錄
 * • selected_layout_option - 用戶手動選擇的佈局選項
 * 
 * 🌐 網站基本設定：
 * • website_blogname         - 網站名稱（同步至 WordPress）
 * • website_blogdescription  - 網站描述（同步至 WordPress）
 * • website_author_nickname  - 作者暱稱（同步至用戶資料）
 * • website_author_description - 作者描述（同步至用戶資料）
 * 
 * === JSON 匯入格式範例 ===
 * 
 * 完整的 JSON 設定檔案格式：
 * {
 *   "layout_selection": {
 *     "header": "header001",
 *     "hero": "hero002",
 *     "about": "about001",
 *     "service": "service002",
 *     "archive": "archive001",
 *     "footer": "footer001"
 *   },
 *   "layout_reasoning": {
 *     "header": "簡約導航突出專業感",
 *     "hero": "居中文字強調核心訊息"
 *   },
 *   "index_hero_title": "主標題",
 *   "index_hero_subtitle": "副標題",
 *   "index_service_list": [
 *     {
 *       "icon": "fas fa-lightbulb",
 *       "title": "服務標題",
 *       "description": "服務描述"
 *     }
 *   ],
 *   "website_blogname": "網站名稱",
 *   "website_author_nickname": "作者暱稱"
 * }
 * 
 * === Elementor 動態標籤支援 ===
 * 
 * 本模組為 Elementor 提供以下動態標籤：
 * • Theme Setting          - 一般文字設定
 * • Theme Setting URL      - URL 連結設定
 * • Theme Setting Image    - 圖片設定
 * • Service List          - 服務項目列表
 * • Service Icon          - 服務項目圖示
 * • All Services HTML     - 完整服務列表 HTML
 * 
 * === 系統整合功能 ===
 * 
 * 自動同步機制：
 * 1. website_blogname → WordPress blogname 選項
 * 2. website_blogdescription → WordPress blogdescription 選項
 * 3. website_author_nickname → 管理員用戶的 nickname 和 display_name
 * 4. website_author_description → 管理員用戶的 description
 * 
 * === 管理介面功能 ===
 * 
 * 訪問管理介面：
 * • WordPress 管理後台 → 外觀 → JSON設定匯入
 * • 支援檔案上傳和直接文字輸入兩種 JSON 匯入方式
 * • 提供 GPT 佈局選擇器（三選一或自動選擇）
 * • 完整的資料清除和重置功能
 * • 即時的系統狀態檢查和除錯工具
 * 
 * Features:
 * - 主題預設參數註冊與管理
 * - JSON 檔案/文字內容匯入
 * - GPT 佈局方案選擇
 * - Elementor 動態標籤整合
 * - WordPress 系統設定同步
 * - 完整的資料清除功能
 * - 三組佈局方案支援
 * - WP-CLI 指令支援
 * 
 * Changelog:
 * 1.0.1 - 2025-07-07
 * - 新增完整的 WP-CLI 指令使用指南
 * - 詳細的設定項目和 JSON 格式說明
 * - 系統整合功能和動態標籤文檔
 * - 管理介面操作指引
 * 
 * 1.0.0 - 2025-01-06
 * - 初始版本
 * - 基本設定註冊功能
 * - JSON 匯入系統
 * - GPT 佈局選擇器
 * - 動態標籤支援
 * - 系統設定同步
 * - 佈局決策記錄
 * - 作者資料更新
 * - 除錯工具整合
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ThemeDefaultSettings Class
 * 
 * 主要負責處理主題預設設定的管理功能
 * 
 * @since 2.0.0
 * @version 1.0.0
 */
class ThemeDefaultSettings {
    
    /**
     * 預設參數配置 - 註冊時為空白，透過 JSON 匯入更新
     * 
     * @var array
     * @since 1.0.0
     */
    private $default_settings = [
        'index_hero_bg' => '',
        'index_hero_photo' => '',
        'index_hero_title' => '',
        'index_hero_subtitle' => '',
        'index_hero_cta_text' => '',
        'index_hero_cta_link' => '',
        'index_header_cta_title' => '',
        'index_header_cta_link' => '',
        'index_about_title' => '',
        'index_about_subtitle' => '',
        'index_about_content' => '',
        'index_about_cta_text' => '',
        'index_about_cta_link' => '',
        'index_about_photo' => '',
        'index_service_title' => '',
        'index_service_subtitle' => '',
        'index_service_list' => [],
        'index_service_cta_text' => '',
        'index_service_cta_link' => '',
        'index_archive_title' => '',
        'index_footer_cta_title' => '',
        'index_footer_cta_subtitle' => '',
        'index_footer_cta_button' => '',
        'index_footer_cta_bg' => '',
        'index_footer_title' => '',
        'index_footer_subtitle' => '',
        'index_footer_fb' => '',
        'index_footer_ig' => '',
        'index_footer_line' => '',
        'index_footer_yt' => '',
        'index_footer_email' => '',
        'seo_title' => '',
        'seo_description' => '',
        'website_blogname' => '',
        'website_blogdescription' => '',
        'website_author_nickname' => '',
        'website_author_description' => ''
    ];

    /**
     * 建構函式
     * 
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', [$this, 'register_theme_settings']);
        // 移除 JSON 匯入選單和功能
        // add_action('admin_menu', [$this, 'add_admin_menu']);
        // add_action('admin_init', [$this, 'register_admin_settings']);
        
        // 註冊 Elementor 動態標籤
        add_action('elementor/dynamic_tags/register_tags', [$this, 'register_elementor_dynamic_tags']);
        
        // 整合 AI 佈局系統
        // add_action('theme_json_import_ai_layout', [$this, 'handle_ai_layout_import']);
    }

    /**
     * 註冊主題設定並設定預設值
     */
    public function register_theme_settings() {
        foreach ($this->default_settings as $option_name => $default_value) {
            // 註冊設定
            register_setting('theme_default_text', $option_name);
            
            // 檢查資料庫是否已經有值，如果沒有則設定預設值
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
            '主題內容設定',
            'JSON設定匯入', 
            'manage_options',
            'theme-json-import',
            [$this, 'render_json_import_page']
        );
    }

    /**
     * 註冊管理員設定
     */
    public function register_admin_settings() {
        // 處理 JSON 上傳
        if (isset($_POST['import_json']) && check_admin_referer('import_theme_json')) {
            $this->handle_json_import();
        }
        
        // 處理資料清除
        if (isset($_POST['clear_data']) && check_admin_referer('clear_theme_data')) {
            $this->handle_data_clear();
        }
        
        // 處理測試清除（除錯用）
        if (isset($_POST['test_clear']) && check_admin_referer('test_clear_data')) {
            $this->handle_test_clear();
        }
        
        // 處理佈局選項選擇
        if (isset($_POST['action']) && $_POST['action'] === 'select_layout_option' && check_admin_referer('select_layout_option')) {
            $this->handle_layout_option_selection();
        }
    }

    /**
     * 處理 JSON 匯入
     */
    private function handle_json_import() {
        // 處理檔案上傳
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $file_content = file_get_contents($_FILES['json_file']['tmp_name']);
            $this->process_json_data($file_content, '檔案');
            return;
        }
        
        // 處理文字框輸入
        if (isset($_POST['json_text']) && !empty(trim($_POST['json_text']))) {
            $json_text = stripslashes($_POST['json_text']);
            $this->process_json_data($json_text, '文字框');
            return;
        }
        
        add_settings_error('theme_json_import', 'no_input', '請選擇檔案或輸入 JSON 內容');
    }

    /**
     * 處理 JSON 資料
     */
    private function process_json_data($json_content, $source) {
        $json_data = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('theme_json_import', 'json_error', 'JSON 格式錯誤：' . json_last_error_msg());
            return;
        }

        // 更新一般設定值 (先更新內容，再處理佈局)
        $updated_count = 0;
        foreach ($json_data as $key => $value) {
            // 跳過佈局相關的設定，這些會在後面特別處理
            if (!in_array($key, ['layout_selection', 'layout_reasoning', 'layout_options', 'recommended_option', 'recommendation_reason', 'ai_layout'])) {
                if (array_key_exists($key, $this->default_settings)) {
                    update_option($key, $value);
                    $updated_count++;
                    
                    // 特別處理：同步更新 WordPress 系統設定
                    $this->sync_wordpress_settings($key, $value);
                }
            }
        }

        // 處理佈局選擇（主要格式）
        $layout_applied = false;
        if (isset($json_data['layout_selection'])) {
            $layout_applied = $this->apply_layout_selection(
                $json_data['layout_selection'], 
                $json_data['layout_reasoning'] ?? []
            );
        }
        // 向下相容：處理三組佈局方案（舊格式）
        elseif (isset($json_data['layout_options'])) {
            $layout_applied = $this->handle_layout_options(
                $json_data['layout_options'], 
                $json_data['recommended_option'] ?? null, 
                $json_data['recommendation_reason'] ?? null
            );
        }
        // 向下相容：處理更舊格式的 AI 佈局
        elseif (isset($json_data['ai_layout'])) {
            do_action('theme_json_import_ai_layout', $json_data);
            $layout_applied = true;
        }

        // 建立成功訊息
        $layout_message = $layout_applied ? '，GPT 已自動選擇最適合的樣板佈局' : '';
        add_settings_error('theme_json_import', 'import_success', "成功從{$source}匯入 {$updated_count} 個設定項目{$layout_message}", 'success');
        
        // 強制重新導向
        wp_redirect(add_query_arg([
            'page' => 'theme-json-import', 
            'imported' => '1', 
            'layout_applied' => $layout_applied ? '1' : '0'
        ], admin_url('themes.php')));
        exit;
    }

    /**
     * 處理佈局選項（三組方案）
     */
    private function handle_layout_options($layout_options, $recommended_option = null, $recommendation_reason = null) {
        // 儲存所有佈局選項
        update_option('layout_options_data', [
            'options' => $layout_options,
            'recommended' => $recommended_option,
            'recommendation_reason' => $recommendation_reason,
            'timestamp' => current_time('mysql'),
            'source' => 'gpt_three_options'
        ]);

        // 如果有推薦方案，自動套用
        if ($recommended_option && isset($layout_options[$recommended_option])) {
            $recommended_layout = $layout_options[$recommended_option];
            if (isset($recommended_layout['templates'])) {
                $this->apply_layout_selection(
                    $recommended_layout['templates'], 
                    $recommended_layout['reasoning'] ?? []
                );
            }
        }

        return true;
    }

    /**
     * 套用佈局選擇 - 修正版本
     */
    private function apply_layout_selection($layout_selection, $layout_reasoning = []) {
        $applied_count = 0;
        $valid_sections = ['header', 'hero', 'about', 'service', 'archive', 'footer'];

        // 記錄除錯資訊
        error_log('開始套用佈局選擇...');
        error_log('Layout Selection: ' . print_r($layout_selection, true));
        error_log('Layout Reasoning: ' . print_r($layout_reasoning, true));

        // 先儲存完整的 layout_selection 和 layout_reasoning
        update_option('layout_selection', $layout_selection);
        update_option('layout_reasoning', $layout_reasoning);
        
        foreach ($layout_selection as $section => $template_id) {
            if (in_array($section, $valid_sections)) {
                $option_name = 'homepage_' . $section;
                
                // 更新樣板選擇
                $result = update_option($option_name, $template_id);
                error_log("更新 {$option_name} = {$template_id}: " . ($result ? '成功' : '失敗'));
                
                // 儲存選擇理由
                if (isset($layout_reasoning[$section])) {
                    $reasoning_data = [
                        'template_id' => $template_id,
                        'reasoning' => $layout_reasoning[$section],
                        'source' => 'gpt_selection',
                        'timestamp' => current_time('mysql')
                    ];
                    
                    $reasoning_result = update_option($option_name . '_reasoning', $reasoning_data);
                    error_log("更新 {$option_name}_reasoning: " . ($reasoning_result ? '成功' : '失敗'));
                }
                
                $applied_count++;
            } else {
                error_log("跳過無效的區塊: {$section}");
            }
        }

        // 記錄完整的佈局決策
        if ($applied_count > 0) {
            $decision_data = [
                'selections' => $layout_selection,
                'reasoning' => $layout_reasoning,
                'timestamp' => current_time('mysql'),
                'source' => 'gpt_ai_selection',
                'applied_count' => $applied_count
            ];
            
            $decision_result = update_option('last_layout_decision', $decision_data);
            error_log("記錄佈局決策: " . ($decision_result ? '成功' : '失敗'));
        }

        error_log("套用佈局完成，總共套用: {$applied_count} 個區塊");
        return $applied_count > 0;
    }

    /**
     * 同步更新 WordPress 系統設定
     */
    private function sync_wordpress_settings($key, $value) {
        switch ($key) {
            case 'website_blogname':
                // 更新 wp_options 的 blogname
                update_option('blogname', $value);
                error_log("同步更新 blogname: {$value}");
                break;
                
            case 'website_blogdescription':
                // 更新 wp_options 的 blogdescription
                update_option('blogdescription', $value);
                error_log("同步更新 blogdescription: {$value}");
                break;
                
            case 'website_author_nickname':
            case 'website_author_description':
                // 找到非 service@contenta.tw 的管理員並更新 usermeta
                $this->update_author_meta($key, $value);
                break;
        }
    }

    /**
     * 更新作者用戶的 meta 資訊
     */
    private function update_author_meta($key, $value) {
        // 直接查找所有管理員用戶，然後過濾排除 service@contenta.tw
        $admin_users = get_users(['role' => 'administrator']);
        $target_users = array_filter($admin_users, function($user) {
            return $user->user_email !== 'service@contenta.tw';
        });

        error_log("找到 " . count($admin_users) . " 個管理員用戶");
        error_log("排除 service@contenta.tw 後剩餘 " . count($target_users) . " 個用戶");

        if (!empty($target_users)) {
            // 取第一個符合條件的管理員
            $target_user = reset($target_users);
            
            error_log("目標用戶 ID: {$target_user->ID}, Email: {$target_user->user_email}");
            
            // 根據參數類型更新對應的 usermeta
            switch ($key) {
                case 'website_author_nickname':
                    $nickname_result = update_user_meta($target_user->ID, 'nickname', $value);
                    $display_result = update_user_meta($target_user->ID, 'display_name', $value);
                    error_log("同步更新用戶 {$target_user->ID} 的 nickname: {$value} (結果: " . ($nickname_result ? '成功' : '失敗') . ")");
                    error_log("同步更新用戶 {$target_user->ID} 的 display_name: {$value} (結果: " . ($display_result ? '成功' : '失敗') . ")");
                    break;
                    
                case 'website_author_description':
                    $desc_result = update_user_meta($target_user->ID, 'description', $value);
                    error_log("同步更新用戶 {$target_user->ID} 的 description: {$value} (結果: " . ($desc_result ? '成功' : '失敗') . ")");
                    break;
            }
        } else {
            error_log("警告：找不到符合條件的管理員用戶來更新 {$key}");
            // 列出所有管理員用戶的 email 供除錯
            foreach ($admin_users as $user) {
                error_log("管理員用戶: ID={$user->ID}, Email={$user->user_email}");
            }
        }
    }

    /**
     * 處理資料清除
     */
    private function handle_data_clear() {
        // 加入除錯訊息
        error_log('開始執行資料清除...');
        
        $cleared_count = 0;
        
        foreach (array_keys($this->default_settings) as $key) {
            // 根據原始預設值類型來重置
            if ($key === 'index_service_list') {
                // 服務列表重置為空陣列
                $result = update_option($key, []);
                error_log("清除 {$key}: " . ($result ? '成功' : '失敗'));
            } else {
                // 其他設定重置為空字串
                $result = update_option($key, '');
                error_log("清除 {$key}: " . ($result ? '成功' : '失敗'));
            }
            
            // 注意：清除資料時不同步清除 WordPress 系統設定，避免網站失去基本資訊
            
            $cleared_count++;
        }
        
        // 同時清除佈局相關的選項
        $layout_options = [
            'layout_selection',
            'layout_reasoning', 
            'layout_options_data',
            'last_layout_decision',
            'selected_layout_option'
        ];
        
        foreach ($layout_options as $option) {
            delete_option($option);
            error_log("清除佈局選項 {$option}");
        }
        
        // 清除各個區塊的選擇和理由
        $sections = ['header', 'hero', 'about', 'service', 'archive', 'footer'];
        foreach ($sections as $section) {
            delete_option('homepage_' . $section);
            delete_option('homepage_' . $section . '_reasoning');
            error_log("清除區塊選項 homepage_{$section}");
        }
        
        error_log("總共清除了 {$cleared_count} 個項目");
        
        // 先設定成功訊息到 transient，再重新導向
        set_transient('theme_clear_success', $cleared_count, 30);
        
        // 強制重新導向以避免快取問題
        wp_redirect(add_query_arg(['page' => 'theme-json-import', 'cleared' => '1'], admin_url('themes.php')));
        exit;
    }

    /**
     * 測試清除功能（除錯用）
     */
    private function handle_test_clear() {
        // 測試清除單一項目
        $test_key = 'index_hero_title';
        $old_value = get_option($test_key);
        $result = update_option($test_key, '');
        $new_value = get_option($test_key);
        
        $message = "測試清除結果：<br>";
        $message .= "項目：{$test_key}<br>";
        $message .= "舊值：" . (empty($old_value) ? '(空)' : $old_value) . "<br>";
        $message .= "update_option 結果：" . ($result ? '成功' : '失敗') . "<br>";
        $message .= "新值：" . (empty($new_value) ? '(空)' : $new_value) . "<br>";
        
        add_settings_error('theme_json_import', 'test_result', $message, 'info');
    }

    /**
     * 處理佈局選項選擇
     */
    private function handle_layout_option_selection() {
        $selected_option = sanitize_text_field($_POST['selected_option']);
        $layout_data = get_option('layout_options_data', null);
        
        if (!$layout_data || !isset($layout_data['options'][$selected_option])) {
            add_settings_error('theme_json_import', 'option_error', '無效的佈局選項');
            return;
        }
        
        $selected_layout = $layout_data['options'][$selected_option];
        
        if (isset($selected_layout['templates'])) {
            // 套用選擇的佈局
            $this->apply_layout_selection(
                $selected_layout['templates'], 
                $selected_layout['reasoning'] ?? []
            );
            
            // 更新選擇記錄
            update_option('selected_layout_option', [
                'option_key' => $selected_option,
                'option_name' => $selected_layout['name'] ?? $selected_option,
                'timestamp' => current_time('mysql'),
                'templates' => $selected_layout['templates']
            ]);
            
            add_settings_error('theme_json_import', 'option_success', 
                '成功套用「' . ($selected_layout['name'] ?? $selected_option) . '」佈局方案！', 'success');
                
            // 重新導向到模組化頁面管理
            wp_redirect(admin_url('themes.php?page=modular-page-manager&option_applied=1'));
            exit;
        }
    }

    /**
     * 渲染 JSON 匯入頁面
     */
    public function render_json_import_page() {
        // 檢查是否有待選擇的佈局選項
        $layout_options_data = get_option('layout_options_data', null);
        $show_layout_selector = $layout_options_data && isset($layout_options_data['options']) && is_array($layout_options_data['options']);
        
        ?>
        <div class="wrap">
            <h1>主題內容設定 - JSON 匯入</h1>
            
            <?php 
            // 顯示匯入成功訊息
            if (isset($_GET['imported']) && $_GET['imported'] == '1') {
                $layout_applied = isset($_GET['layout_applied']) && $_GET['layout_applied'] == '1';
                if ($layout_applied) {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ 資料已成功匯入，GPT 已自動選擇最適合的樣板佈局！ <a href="' . admin_url('themes.php?page=modular-page-manager') . '">查看選擇的佈局</a></p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ 資料已成功匯入！</p></div>';
                }
            }
            
            // 顯示清除成功訊息
            if (isset($_GET['cleared']) && $_GET['cleared'] == '1') {
                $cleared_count = get_transient('theme_clear_success');
                if ($cleared_count) {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ 成功清除 ' . $cleared_count . ' 個設定項目，已重置為空白值！</p></div>';
                    delete_transient('theme_clear_success');
                } else {
                    echo '<div class="notice notice-success is-dismissible"><p>✅ 所有設定數據已成功清除並重置為空白值！</p></div>';
                }
            }
            
            settings_errors('theme_json_import'); 
            ?>
            
            <?php 
            // 如果有佈局選項需要選擇，顯示選擇器
            if ($show_layout_selector) {
                $this->render_layout_options_selector();
            }
            ?>
            
            <div class="card">
                <h2>方法一：上傳 JSON 檔案</h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('import_theme_json'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">選擇 JSON 檔案</th>
                            <td>
                                <input type="file" name="json_file" accept=".json" />
                                <p class="description">請上傳包含主題設定的 JSON 檔案</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="import_json" class="button-primary" value="從檔案匯入" />
                    </p>
                </form>
            </div>

            <div class="card">
                <h2>方法二：直接輸入 JSON 內容</h2>
                <form method="post">
                    <?php wp_nonce_field('import_theme_json'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">JSON 內容</th>
                            <td>
                                <textarea name="json_text" rows="15" cols="80" class="large-text code" 
                                          placeholder="請在此貼上 JSON 內容..."
                                          style="font-family: monospace; font-size: 13px; white-space: pre;"><?php 
                                    echo isset($_POST['json_text']) ? esc_textarea(stripslashes($_POST['json_text'])) : ''; 
                                ?></textarea>
                                <p class="description">直接在此貼上 JSON 內容並匯入</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="import_json" class="button-primary" value="從文字匯入" />
                        <button type="button" id="format-json" class="button">格式化 JSON</button>
                        <button type="button" id="clear-json" class="button">清空內容</button>
                    </p>
                </form>
            </div>

            <div class="card">
                <h2>資料管理</h2>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: #856404;">⚠️ 危險操作區域</h3>
                    <p style="margin-bottom: 10px; color: #856404;">以下操作將會清除所有已匯入的主題設定數據，請謹慎使用。</p>
                </div>
                
                <form method="post" onsubmit="return confirm('⚠️ 警告！\n\n此操作將清除所有主題設定數據並重置為空白值。\n\n確定要繼續嗎？');">
                    <?php wp_nonce_field('clear_theme_data'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">清除所有數據</th>
                            <td>
                                <p class="description" style="margin-bottom: 10px;">
                                    此功能會將所有主題設定重置為空白值，包括：<br>
                                    • Hero 區塊所有內容<br>
                                    • 關於我區塊所有內容<br>
                                    • 服務項目列表<br>
                                    • 頁尾設定與社群連結<br>
                                    • SEO 設定<br>
                                    • 網站名稱與描述（僅主題設定）<br>
                                    • 作者暱稱與描述（僅主題設定）<br>
                                    • 佈局選擇與理由<br>
                                    <strong>注意：此操作不可復原！WordPress 系統的 blogname 和用戶資料不會被清除。</strong>
                                </p>
                                <input type="submit" name="clear_data" class="button button-secondary" 
                                       value="清除所有數據" 
                                       style="background: #dc3545; border-color: #dc3545; color: white;" />
                            </td>
                        </tr>
                    </table>
                </form>
                
                <!-- 測試清除功能 -->
                <hr style="margin: 20px 0;">
                <h3>🔧 除錯工具</h3>
                <form method="post">
                    <?php wp_nonce_field('test_clear_data'); ?>
                    <p>
                        <input type="submit" name="test_clear" class="button" value="測試清除功能" />
                        <span class="description">（僅清除 index_hero_title 來測試功能是否正常）</span>
                    </p>
                </form>
            </div>

            <div class="card">
                <h2>系統狀態檢查</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Elementor 動態標籤檔案</th>
                        <td>
                            <?php
                            $dynamic_tags_file = get_template_directory() . '/inc/elementor-dynamic-tags.php';
                            $child_dynamic_tags_file = get_stylesheet_directory() . '/inc/elementor-dynamic-tags.php';
                            
                            if (file_exists($dynamic_tags_file)) {
                                echo '✅ 找到檔案：' . $dynamic_tags_file;
                            } elseif (file_exists($child_dynamic_tags_file)) {
                                echo '✅ 找到檔案：' . $child_dynamic_tags_file;
                            } else {
                                echo '❌ 檔案不存在<br>';
                                echo '<strong>請建立以下檔案：</strong><br>';
                                echo '<code>' . get_stylesheet_directory() . '/inc/elementor-dynamic-tags.php</code>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Elementor 外掛</th>
                        <td>
                            <?php
                            if (class_exists('Elementor\Core\DynamicTags\Tag')) {
                                echo '✅ Elementor 已啟用且支援動態標籤';
                            } else {
                                echo '❌ Elementor 未啟用或版本過舊';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">佈局數據狀態</th>
                        <td>
                            <?php
                            $layout_selection = get_option('layout_selection');
                            $layout_reasoning = get_option('layout_reasoning');
                            $last_decision = get_option('last_layout_decision');
                            
                            if ($layout_selection) {
                                echo '✅ Layout Selection: ' . count($layout_selection) . ' 個區塊<br>';
                            } else {
                                echo '❌ 沒有 Layout Selection 數據<br>';
                            }
                            
                            if ($layout_reasoning) {
                                echo '✅ Layout Reasoning: ' . count($layout_reasoning) . ' 個理由<br>';
                            } else {
                                echo '❌ 沒有 Layout Reasoning 數據<br>';
                            }
                            
                            if ($last_decision) {
                                $timestamp = isset($last_decision['timestamp']) ? $last_decision['timestamp'] : '未知';
                                echo '✅ 最後決策時間: ' . $timestamp;
                            } else {
                                echo '❌ 沒有最後決策記錄';
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <h2>GPT 單一最佳方案 JSON 格式範例</h2>
                <p class="description">GPT 會根據內容分析並生成最適合的佈局方案：</p>
                <pre style="background: #f1f1f1; padding: 15px; overflow-x: auto;"><code>{
  "layout_selection": {
    "header": "header001",
    "hero": "hero002",
    "about": "about001",
    "service": "service002",
    "archive": "archive001",
    "footer": "footer001"
  },
  "layout_reasoning": {
    "header": "簡約導航突出專業感",
    "hero": "居中文字強調核心訊息",
    "about": "左圖右文建立信任",
    "service": "橫向卡片詳細說明服務",
    "archive": "格子布局展示專業內容",
    "footer": "簡約設計保持一致性"
  },
  "index_hero_title": "在城市與生活之間，找到你舒服的節奏",
  "index_hero_subtitle": "品牌顧問 × 生活策劃人 × 空間旅人",
  "index_service_list": [
    {
      "icon": "fas fa-lightbulb",
      "title": "品牌顧問服務",
      "description": "協助釐清品牌定位、內容策略與視覺語言。"
    }
  ],
  "website_blogname": "木子心的宇宙碎片",
  "website_blogdescription": "在城市與生活之間，找到你舒服的節奏",
  "website_author_nickname": "木子心",
  "website_author_description": "品牌顧問 × 生活策劃人，專注於策略整合與體驗設計。"
}</code></pre>
            </div>

            <div class="card">
                <h2>目前設定值</h2>
                <div style="max-height: 400px; overflow-y: auto; background: #f9f9f9; padding: 15px;">
                    <?php
                    echo '<pre>';
                    $current_settings = [];
                    foreach (array_keys($this->default_settings) as $key) {
                        $current_settings[$key] = get_option($key);
                    }
                    
                    // 加入佈局相關設定
                    $current_settings['layout_selection'] = get_option('layout_selection');
                    $current_settings['layout_reasoning'] = get_option('layout_reasoning');
                    $current_settings['last_layout_decision'] = get_option('last_layout_decision');
                    
                    echo json_encode($current_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    echo '</pre>';
                    ?>
                </div>
                <p class="description">以上是目前儲存在資料庫中的設定值</p>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // 格式化 JSON
                $('#format-json').click(function() {
                    var textarea = $('textarea[name="json_text"]');
                    var content = textarea.val().trim();
                    
                    if (content) {
                        try {
                            var parsed = JSON.parse(content);
                            var formatted = JSON.stringify(parsed, null, 2);
                            textarea.val(formatted);
                        } catch (e) {
                            alert('JSON 格式錯誤：' + e.message);
                        }
                    }
                });
                
                // 清空內容
                $('#clear-json').click(function() {
                    if (confirm('確定要清空內容嗎？')) {
                        $('textarea[name="json_text"]').val('');
                    }
                });
                
                // 增強清除數據的確認提示
                $('input[name="clear_data"]').click(function(e) {
                    e.preventDefault();
                    
                    var confirmMsg = "⚠️ 最後確認！\n\n";
                    confirmMsg += "您即將清除以下所有數據：\n";
                    confirmMsg += "• 首頁 Hero 區塊內容\n";
                    confirmMsg += "• 關於我區塊內容\n";
                    confirmMsg += "• 服務項目列表\n";
                    confirmMsg += "• 頁尾設定與社群連結\n";
                    confirmMsg += "• SEO 設定\n";
                    confirmMsg += "• 所有佈局選擇與理由\n\n";
                    confirmMsg += "此操作無法復原！\n\n";
                    confirmMsg += "請輸入 'CLEAR' 確認執行：";
                    
                    var userInput = prompt(confirmMsg);
                    
                    if (userInput === 'CLEAR') {
                        $(this).closest('form').submit();
                    } else if (userInput !== null) {
                        alert('輸入不正確，操作已取消。');
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    /**
     * 渲染佈局選項選擇器
     */
    private function render_layout_options_selector() {
        $layout_data = get_option('layout_options_data', null);
        
        if (!$layout_data || !isset($layout_data['options'])) {
            echo '<div class="notice notice-warning"><p>沒有找到佈局選項數據</p></div>';
            return;
        }

        $options = $layout_data['options'];
        $recommended = $layout_data['recommended'] ?? null;
        $recommendation_reason = $layout_data['recommendation_reason'] ?? null;
        
        ?>
        <div class="card" style="margin-top: 20px;">
            <h2>🎨 選擇您喜歡的佈局方案</h2>
            <p class="description">GPT 為您生成了 3 組不同風格的佈局方案，請選擇最適合的一組：</p>
            
            <form method="post" id="layout-options-form">
                <?php wp_nonce_field('select_layout_option'); ?>
                <input type="hidden" name="action" value="select_layout_option">
                
                <div class="layout-options-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0;">
                    <?php foreach ($options as $option_key => $option_data): ?>
                        <div class="layout-option-card" style="border: 2px solid #ddd; border-radius: 8px; padding: 20px; background: white; position: relative;">
                            
                            <?php if ($recommended === $option_key): ?>
                                <div style="position: absolute; top: -1px; right: -1px; background: #00a32a; color: white; padding: 5px 10px; border-radius: 0 8px 0 8px; font-size: 12px; font-weight: bold;">
                                    推薦
                                </div>
                            <?php endif; ?>
                            
                            <label style="cursor: pointer; display: block;">
                                <input type="radio" name="selected_option" value="<?php echo $option_key; ?>" 
                                       <?php checked($recommended, $option_key); ?> 
                                       style="margin-bottom: 10px;">
                                
                                <h3 style="margin: 0 0 10px 0; color: #333;">
                                    <?php echo esc_html($option_data['name'] ?? $option_key); ?>
                                </h3>
                                
                                <p style="margin: 0 0 15px 0; color: #666; font-size: 14px;">
                                    <?php echo esc_html($option_data['description'] ?? ''); ?>
                                </p>
                                
                                <div class="template-preview-list" style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px;">
                                    <strong>樣板組合：</strong><br>
                                    <?php if (isset($option_data['templates'])): ?>
                                        <?php foreach ($option_data['templates'] as $section => $template_id): ?>
                                            <span style="display: inline-block; background: #e0e0e0; padding: 2px 6px; margin: 2px; border-radius: 3px;">
                                                <?php echo ucfirst($section); ?>: <?php echo $template_id; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (isset($option_data['reasoning'])): ?>
                                    <details style="margin-top: 10px;">
                                        <summary style="cursor: pointer; font-size: 13px; color: #666;">查看選擇理由</summary>
                                        <div style="margin-top: 8px; font-size: 12px; color: #666;">
                                            <?php foreach ($option_data['reasoning'] as $section => $reason): ?>
                                                <div style="margin: 4px 0;">
                                                    <strong><?php echo ucfirst($section); ?>:</strong> <?php echo esc_html($reason); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button type="submit" class="button-primary button-large">套用選擇的佈局方案</button>
                    <a href="<?php echo admin_url('themes.php?page=modular-page-manager'); ?>" class="button" style="margin-left: 10px;">
                        直接前往佈局管理
                    </a>
                </div>
            </form>
            
            <?php if ($recommended && $recommendation_reason): ?>
                <div style="background: #e7f5e7; border: 1px solid #00a32a; border-radius: 4px; padding: 15px; margin-top: 15px;">
                    <strong>💡 GPT 推薦理由：</strong>
                    <?php echo esc_html($recommendation_reason); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#layout-options-form').on('submit', function(e) {
                var selected = $('input[name="selected_option"]:checked').val();
                if (!selected) {
                    e.preventDefault();
                    alert('請選擇一個佈局方案');
                    return false;
                }
                
                // 確認選擇
                var optionName = $('input[name="selected_option"]:checked').closest('.layout-option-card').find('h3').text();
                if (!confirm('確定要套用「' + optionName + '」佈局方案嗎？')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * 註冊 Elementor 動態標籤
     */
    public function register_elementor_dynamic_tags($dynamic_tags) {
        // 檢查 Elementor 是否可用
        if (!function_exists('is_elementor_available') || !is_elementor_available()) {
            return;
        }
        
        if (class_exists('Elementor\Core\DynamicTags\Tag')) {
            $dynamic_tags_file = get_template_directory() . '/inc/elementor-dynamic-tags.php';
            
            // 檢查檔案是否存在，如果不存在則使用子主題路徑
            if (!file_exists($dynamic_tags_file)) {
                $dynamic_tags_file = get_stylesheet_directory() . '/inc/elementor-dynamic-tags.php';
            }
            
            // 如果還是不存在，就不載入（避免錯誤）
            if (file_exists($dynamic_tags_file)) {
                require_once $dynamic_tags_file;
                $dynamic_tags->register_tag('Theme_Setting_Dynamic_Tag');
                $dynamic_tags->register_tag('Theme_Setting_URL_Dynamic_Tag');
                $dynamic_tags->register_tag('Theme_Setting_Image_Dynamic_Tag');
                $dynamic_tags->register_tag('Theme_Setting_Image_URL_Dynamic_Tag');
                $dynamic_tags->register_tag('Service_List_Dynamic_Tag');
                $dynamic_tags->register_tag('Service_Icon_Dynamic_Tag');
                $dynamic_tags->register_tag('Service_Item_HTML_Dynamic_Tag');
                $dynamic_tags->register_tag('All_Services_HTML_Dynamic_Tag');
            } else {
                // 在管理後台顯示警告
                if (is_admin()) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-warning"><p>⚠️ Elementor 動態標籤檔案不存在：/inc/elementor-dynamic-tags.php</p></div>';
                    });
                }
            }
        }
    }

    /**
     * 處理 AI 佈局匯入
     */
    public function handle_ai_layout_import($json_data) {
        if (class_exists('AILayoutSystem')) {
            // 觸發 AI 佈局分析
            $ai_system = new AILayoutSystem();
            $ai_system->process_ai_layout($json_data, 'json_import');
        }
    }

    /**
     * 取得服務列表項目的輔助函數
     */
    public static function get_service_item($index, $field = null) {
        $service_list = get_option('index_service_list', []);
        
        if (!isset($service_list[$index])) {
            return '';
        }
        
        if ($field && isset($service_list[$index][$field])) {
            return $service_list[$index][$field];
        }
        
        return $service_list[$index];
    }
}

// 初始化主題設定
new ThemeDefaultSettings();

/**
 * 輔助函數：取得服務項目（因為是陣列結構才需要）
 */
function get_service_item($index, $field = null) {
    return ThemeDefaultSettings::get_service_item($index, $field);
}

/**
 * 輔助函數：取得服務圖示
 */
function get_service_icon($index, $format = 'class') {
    $service_list = get_option('index_service_list', []);
    
    if (!isset($service_list[$index]['icon'])) {
        return '';
    }
    
    $icon = $service_list[$index]['icon'];
    
    switch ($format) {
        case 'html':
            return '<i class="' . esc_attr($icon) . '"></i>';
        case 'class':
        default:
            return $icon;
    }
}