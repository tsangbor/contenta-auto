<?php
/**
 * 多層次內容解析器 (Multi-layered Content Resolver)
 * 
 * 按照優先級順序解析佔位符內容：
 * 1. 精確映射 (Exact Match)
 * 2. 智能模式匹配 (Intelligent Pattern Matching) 
 * 3. AI 上下文生成 (AI Contextual Generation)
 * 4. 通用備用值 (Generic Fallback)
 */
class ContentResolver {
    
    private $deployer;
    private $ai_call_count = 0;
    
    public function __construct($deployer = null) {
        $this->deployer = $deployer;
    }
    
    /**
     * 解析佔位符內容的主要函數
     * 
     * @param string $placeholder 佔位符名稱 (不含 {{}} )
     * @param array $context 上下文資料
     * @return string 解析後的內容
     */
    public function resolve_placeholder_content(string $placeholder, array $context): string {
        
        $this->log("開始解析佔位符: $placeholder");
        
        // 載入 site-config.json 內容
        $site_config = $this->load_site_config($context);
        $context['site_config'] = $site_config;
        
        // 第一層：精確映射 (Exact Match - 最高優先級)
        $exact_result = $this->exact_match_layer($placeholder, $context);
        if ($exact_result !== null) {
            $this->log("✓ 精確映射成功: $placeholder -> $exact_result");
            return $exact_result;
        }
        
        // 第二層：智能模式匹配 (Intelligent Pattern Matching)
        $pattern_result = $this->intelligent_pattern_layer($placeholder, $context);
        if ($pattern_result !== null) {
            $this->log("✓ 智能模式匹配成功: $placeholder -> $pattern_result");
            return $pattern_result;
        }
        
        // 第三層：AI 上下文生成 (AI Contextual Generation - 核心)
        $ai_result = $this->ai_contextual_layer($placeholder, $context);
        if ($ai_result !== null) {
            $this->log("✓ AI 上下文生成成功: $placeholder -> $ai_result");
            return $ai_result;
        }
        
        // 第四層：通用備用值 (Generic Fallback - 最低優先級)
        $fallback_result = $this->generic_fallback_layer($placeholder, $context);
        $this->log("⚠️ 使用備用值: $placeholder -> $fallback_result");
        return $fallback_result;
    }
    
    /**
     * 載入 site-config.json 內容
     */
    private function load_site_config(array $context): array {
        // 嘗試從 work_dir 推測 site-config.json 路徑
        $confirmed_data = $context['confirmed_data'] ?? [];
        
        if (isset($confirmed_data['work_dir'])) {
            $site_config_path = $confirmed_data['work_dir'] . '/json/site-config.json';
        } else {
            // 備用路徑構建
            $template_name = $context['template_name'] ?? '';
            if (defined('DEPLOY_BASE_PATH')) {
                $site_config_path = DEPLOY_BASE_PATH . '/temp/*/json/site-config.json';
                $possible_paths = glob($site_config_path);
                $site_config_path = !empty($possible_paths) ? $possible_paths[0] : '';
            } else {
                return [];
            }
        }
        
        if (file_exists($site_config_path)) {
            $site_config = json_decode(file_get_contents($site_config_path), true);
            return $site_config ?? [];
        }
        
        return [];
    }
    
    /**
     * 第一層：精確映射 (Exact Match)
     * 處理那些有固定、明確值的佔位符
     */
    private function exact_match_layer(string $placeholder, array $context): ?string {
        
        $confirmed_data = $context['confirmed_data'] ?? [];
        $site_config = $context['site_config'] ?? [];
        
        // 從 site-config 提取內容
        $content_options = $site_config['content_options'] ?? [];
        $website_info = $site_config['website_info'] ?? [];
        $contact_info = $site_config['contact_info'] ?? [];
        
        $exact_mappings = [
            // 公司基本資訊
            'COMPANY_VAT_NUMBER' => $confirmed_data['vat_number'] ?? null,
            'CONTACT_PHONE' => $contact_info['phone'] ?? $confirmed_data['contact_phone'] ?? $confirmed_data['phone'] ?? null,
            'CONTACT_EMAIL' => $contact_info['email'] ?? $confirmed_data['user_email'] ?? null,
            'COMPANY_NAME' => $website_info['website_blogname'] ?? $confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? null,
            'COMPANY_ADDRESS' => $contact_info['address'] ?? $confirmed_data['company_address'] ?? $confirmed_data['address'] ?? null,
            
            // 網站基本資訊  
            'SITE_URL' => $website_info['website_url'] ?? ($confirmed_data['domain'] ? "https://{$confirmed_data['domain']}" : null),
            'SITE_NAME' => $website_info['website_blogname'] ?? $confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? null,
            
            // 版權與法律
            'COPYRIGHT_YEAR' => date('Y'),
            'COPYRIGHT_TEXT' => '© ' . date('Y') . ' ' . ($website_info['website_blogname'] ?? $confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? '我的公司') . '. 版權所有。',
            
            // Footer 相關內容
            'FOOTER_CONTACT_TITLE' => $content_options['index_footer_title'] ?? '聯絡我們',
            'FOOTER_CONTACT_SUBTITLE' => $content_options['index_footer_subtitle'] ?? '隨時與我們保持聯繫',
            'FOOTER_TITLE' => $content_options['index_footer_title'] ?? null,
            'FOOTER_SUBTITLE' => $content_options['index_footer_subtitle'] ?? null,
            
            // Hero 相關內容
            'HERO_TITLE' => $content_options['index_hero_title'] ?? null,
            'HERO_SUBTITLE' => $content_options['index_hero_subtitle'] ?? null,
            'HERO_CTA_TEXT' => $content_options['index_hero_cta_text'] ?? '立即開始',
            
            // About 相關內容
            'ABOUT_TITLE' => $content_options['about_title'] ?? $content_options['index_about_title'] ?? '關於我們',
            'ABOUT_SUBTITLE' => $content_options['index_about_subtitle'] ?? null,
            'ABOUT_CONTENT' => $content_options['about_content'] ?? $content_options['index_about_content'] ?? null,
            
            // Header 相關內容
            'HEADER_CTA_BUTTON' => $content_options['index_header_cta_title'] ?? '聯絡我們',
            'HEADER_CTA_LINK' => $content_options['index_header_cta_link'] ?? '/contact',
            
            // Service 相關內容
            'SERVICE_TITLE' => $content_options['index_service_title'] ?? '我們的服務',
            'SERVICE_SUBTITLE' => $content_options['index_service_subtitle'] ?? null,
            'SERVICE_CTA_BUTTON' => $content_options['index_service_cta_text'] ?? '了解更多',
            'SERVICE_CTA_LINK' => $content_options['index_service_cta_link'] ?? '/service',
            
            // Archive 相關內容
            'ARCHIVE_TITLE' => $content_options['index_archive_title'] ?? '最新消息',
            'ARCHIVE_SUBTITLE' => $content_options['index_archive_subtitle'] ?? null,
        ];
        
        return $exact_mappings[$placeholder] ?? null;
    }
    
    /**
     * 第二層：智能模式匹配 (Intelligent Pattern Matching)
     * 處理那些有規律、可推斷的佔位符
     */
    private function intelligent_pattern_layer(string $placeholder, array $context): ?string {
        
        $confirmed_data = $context['confirmed_data'] ?? [];
        $template_name = $context['template_name'] ?? 'unknown';
        
        // 模式 1: PREFIX_SUFFIX 結構解析
        if (preg_match('/^(HEADER|FOOTER|SITE|COMPANY|CONTACT)_(.+)/', $placeholder, $matches)) {
            $prefix = $matches[1];
            $suffix = $matches[2];
            
            switch($suffix) {
                case 'TITLE':
                case 'NAME':
                    return $confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? null;
                    
                case 'PHONE':
                    return $confirmed_data['contact_phone'] ?? $confirmed_data['phone'] ?? null;
                    
                case 'EMAIL':
                    return $confirmed_data['user_email'] ?? null;
                    
                case 'ADDRESS':
                    return $confirmed_data['company_address'] ?? $confirmed_data['address'] ?? null;
                    
                case 'DESCRIPTION':
                    return $confirmed_data['website_description'] ?? $confirmed_data['company_description'] ?? $confirmed_data['brand_description'] ?? null;
                    
                case 'TAGLINE':
                case 'SLOGAN':
                    return $confirmed_data['tagline'] ?? $confirmed_data['brand_tagline'] ?? null;
            }
            
            // 前綴特定的處理
            switch($prefix) {
                case 'FOOTER':
                    return $this->resolve_footer_content($suffix, $confirmed_data);
                    
                case 'HEADER':
                    return $this->resolve_header_content($suffix, $confirmed_data);
                    
                case 'CONTACT':
                    return $this->resolve_contact_content($suffix, $confirmed_data);
            }
        }
        
        // 模式 2: 特殊格式處理
        if (preg_match('/^(\d+)_(.+)/', $placeholder, $matches)) {
            // 處理如 404_TITLE, 404_SUBTITLE 等
            $code = $matches[1];
            $type = $matches[2];
            
            if ($code === '404') {
                switch($type) {
                    case 'TITLE':
                        return '找不到頁面';
                    case 'SUBTITLE':
                        return '很抱歉，您所尋找的頁面不存在';
                    case 'BUTTON_TEXT':
                        return '返回首頁';
                }
            }
        }
        
        return null;
    }
    
    /**
     * 第三層：AI 上下文生成 (AI Contextual Generation)
     * 處理所有前面層級無法解析的、需要創造力的佔位符
     */
    private function ai_contextual_layer(string $placeholder, array $context): ?string {
        
        $this->ai_call_count++;
        $this->log("🤖 AI 呼叫 #{$this->ai_call_count}: 處理佔位符 '$placeholder'");
        
        // 1. 收集上下文
        $confirmed_data = $context['confirmed_data'] ?? [];
        $template_name = $context['template_name'] ?? 'unknown';
        
        $ai_context = sprintf(
            "公司名稱: %s. 業務類型: %s. 所在頁面/模板: %s.",
            $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? '未知公司',
            $confirmed_data['business_type'] ?? $confirmed_data['industry'] ?? '未知業務',
            $template_name
        );
        
        // 2. 構造提示詞
        $prompt = "你是一個專業的品牌文案專家。請為以下佔位符生成一段簡潔、專業、符合上下文的中文內容。\n\n上下文: {$ai_context}\n佔位符: `{$placeholder}`\n\n請直接返回生成的文字，不要包含任何額外解釋。";
        
        // 3. 呼叫 AI 模型
        try {
            $ai_result = $this->call_ai_model($prompt);
            
            if (!empty($ai_result)) {
                $this->log("💰 AI API 使用統計 - 總呼叫次數: {$this->ai_call_count}");
                return trim($ai_result);
            }
            
        } catch (Exception $e) {
            $this->log("ERROR: AI 呼叫失敗 - " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * 第四層：通用備用值 (Generic Fallback)
     * 作為最後一道防線，確保系統不會因為空值而崩潰
     */
    private function generic_fallback_layer(string $placeholder, array $context): string {
        
        // 記錄錯誤日誌
        $this->log("ERROR: Failed to resolve placeholder '{$placeholder}' after all attempts.");
        
        // 返回安全的預設值
        return "內容生成中...";
    }
    
    /**
     * Footer 內容解析
     */
    private function resolve_footer_content(string $suffix, array $confirmed_data): ?string {
        
        $footer_mappings = [
            'CONTACT_TITLE' => '聯絡我們',
            'CONTACT_SUBTITLE' => '隨時與我們保持聯繫',
            'COMPANY_NAME' => $confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? null,
            'COPYRIGHT' => '© ' . date('Y') . ' ' . ($confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? '我的公司') . '. 版權所有。',
            'DESCRIPTION' => $confirmed_data['website_description'] ?? $confirmed_data['company_description'] ?? $confirmed_data['brand_description'] ?? null,
        ];
        
        return $footer_mappings[$suffix] ?? null;
    }
    
    /**
     * Header 內容解析
     */
    private function resolve_header_content(string $suffix, array $confirmed_data): ?string {
        
        $header_mappings = [
            'COMPANY_NAME' => $confirmed_data['website_name'] ?? $confirmed_data['company_name'] ?? $confirmed_data['brand_name'] ?? null,
            'TAGLINE' => $confirmed_data['tagline'] ?? $confirmed_data['brand_tagline'] ?? null,
            'CTA_TEXT' => '立即聯繫',
            'MENU_HOME' => '首頁',
            'MENU_ABOUT' => '關於我們',
            'MENU_SERVICES' => '服務項目',
            'MENU_CONTACT' => '聯絡我們',
        ];
        
        return $header_mappings[$suffix] ?? null;
    }
    
    /**
     * Contact 內容解析
     */
    private function resolve_contact_content(string $suffix, array $confirmed_data): ?string {
        
        $contact_mappings = [
            'TITLE' => '聯絡我們',
            'SUBTITLE' => '隨時與我們保持聯繫',
            'DESCRIPTION' => '期待您的來信與交流',
            'PHONE' => $confirmed_data['contact_phone'] ?? $confirmed_data['phone'] ?? null,
            'EMAIL' => $confirmed_data['user_email'] ?? null,
            'ADDRESS' => $confirmed_data['company_address'] ?? $confirmed_data['address'] ?? null,
        ];
        
        return $contact_mappings[$suffix] ?? null;
    }
    
    /**
     * 呼叫 AI 模型
     * 使用快速且便宜的模型進行短文本生成
     */
    private function call_ai_model(string $prompt): ?string {
        
        // 檢查是否有 OpenAI API 金鑰
        $api_key = getenv('OPENAI_API_KEY');
        
        // 如果環境變數沒有，嘗試從配置檔案讀取
        if (empty($api_key)) {
            // 嘗試從 deploy-config.json 讀取
            if (defined('DEPLOY_BASE_PATH')) {
                $config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
                if (file_exists($config_file)) {
                    $config = json_decode(file_get_contents($config_file), true);
                    $api_key = $config['api_credentials']['openai']['api_key'] ?? '';
                }
            }
            
            if (empty($api_key)) {
                $this->log("WARNING: 沒有設定 OpenAI API 金鑰，跳過 AI 生成");
                return null;
            }
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => 'gpt-3.5-turbo',  // 快速且便宜的模型
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 100,  // 限制短文本
            'temperature' => 0.7,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // 載入部署配置以檢查代理設定
        if (defined('DEPLOY_BASE_PATH')) {
            $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
            if (file_exists($deploy_config_file)) {
                $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
                // 檢查是否需要使用代理
                if (isset($deploy_config['network']['use_proxy']) && 
                    $deploy_config['network']['use_proxy'] === true && 
                    !empty($deploy_config['network']['proxy'])) {
                    curl_setopt($ch, CURLOPT_PROXY, $deploy_config['network']['proxy']);
                }
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return $result['choices'][0]['message']['content'];
            }
        }
        
        $this->log("ERROR: OpenAI API 呼叫失敗 - HTTP $http_code: $response");
        return null;
    }
    
    /**
     * 取得 AI 呼叫統計
     */
    public function get_ai_call_count(): int {
        return $this->ai_call_count;
    }
    
    /**
     * 重置 AI 呼叫計數器
     */
    public function reset_ai_call_count(): void {
        $this->ai_call_count = 0;
    }
    
    /**
     * 日誌記錄
     */
    private function log(string $message): void {
        if ($this->deployer && method_exists($this->deployer, 'log')) {
            $this->deployer->log("    [ContentResolver] $message");
        }
    }
}