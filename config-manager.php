<?php
/**
 * 配置管理系統
 * 管理 API 憑證、部署參數等配置
 */

class ConfigManager
{
    private static $instance = null;
    private $config = [];
    private $config_file;
    
    private function __construct()
    {
        $this->config_file = DEPLOY_CONFIG_PATH . '/deploy-config.json';
        $this->loadConfig();
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 載入配置
     */
    private function loadConfig()
    {
        if (file_exists($this->config_file)) {
            $this->config = json_decode(file_get_contents($this->config_file), true);
        } else {
            $this->config = $this->getDefaultConfig();
            $this->saveConfig();
        }
    }
    
    /**
     * 取得預設配置
     */
    private function getDefaultConfig()
    {
        return [
            // 網站基本資訊
            'site' => [
                'domain' => 'example.tw',
                'name' => '我的網站',
                'description' => '網站描述',
                'admin_email' => 'admin@example.com',
                'user_email' => 'user@example.com',
                'keywords' => ['關鍵字1', '關鍵字2'],
                'target_audience' => '目標受眾描述',
                'brand_personality' => '品牌個性描述',
                'unique_value' => '獨特價值主張',
                'service_categories' => ['服務1', '服務2']
            ],
            
            // API 憑證
            'api_credentials' => [
                'cloudflare' => [
                    'email' => '',
                    'api_key' => '',
                    'endpoint' => 'https://api.cloudflare.com/client/v4/'
                ],
                'lihi_domain' => [
                    'api_key' => 'nlX6ZANNyHGdLuKrCP1sMg4g0hziqTA1Pc6JMMkj',
                    'endpoint' => 'https://app.lihi.io/api/domain/'
                ],
                'btcn' => [
                    'api_key' => '',
                    'panel_url' => '',
                    'username' => '',
                    'password' => ''
                ],
                'openai' => [
                    'api_key' => '',
                    'model' => 'gpt-4',
                    'base_url' => 'https://api.openai.com/v1/'
                ],
                'gemini' => [
                    'api_key' => '',
                    'model' => 'gemini-pro-vision'
                ],
                'ideogram' => [
                    'api_key' => ''
                ]
            ],
            
            // AI 圖片生成設定
            'ai_image_generation' => [
                'primary_service' => 'openai', // 主要使用的服務: openai, ideogram, gemini
                'fallback_order' => ['openai', 'ideogram', 'gemini'], // 備援順序
                'quality' => 'high', // 圖片品質: standard, high
                'style' => 'professional' // 圖片風格
            ],
            
            // 部署參數
            'deployment' => [
                'server_host' => '',
                'server_ip' => '',
                'ssh_user' => 'root',
                'ssh_port' => 22,
                'ssh_key_path' => '',
                'wordpress_version' => 'latest',
                'wordpress_locale' => 'zh_TW',
                'admin_user' => 'contentatw@gmail.com',
                'admin_password' => 'ContentaTW2025!',
                'limited_admin_password' => 'LimitedAdmin2025!'
            ],
            
            // 外掛設定
            'plugins' => [
                'required' => [
                    'advanced-custom-fields',
                    'auto-upload-images',
                    'better-search-replace',
                    'contact-form-7',
                    'elementor',
                    'elementor-pro',
                    'flying-press',
                    'one-user-avatar',
                    'performance-lab',
                    'astra-pro-sites',
                    'seo-by-rank-math',
                    'seo-by-rank-math-pro',
                    'google-site-kit',
                    'ultimate-elementor',
                    'insert-headers-and-footers'
                ],
                'license_required' => [
                    'elementor-pro' => '',
                    'flying-press' => ''
                ]
            ],
            
            // 快取檔案
            'cache_files' => [
                'advanced-cache.php',
                'object-cache.php'
            ],
            
            // 上傳設定
            'upload' => [
                'max_file_size' => '64M',
                'max_execution_time' => 300,
                'memory_limit' => '512M'
            ]
        ];
    }
    
    /**
     * 儲存配置
     */
    private function saveConfig()
    {
        file_put_contents($this->config_file, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 取得配置值
     */
    public function get($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * 設定配置值
     */
    public function set($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
        $this->saveConfig();
    }
    
    /**
     * 檢查必要配置是否完整
     */
    public function validateConfig()
    {
        $required_keys = [
            'api_credentials.btcn.api_key',
            'api_credentials.cloudflare.api_token',
            'deployment.server_host'
        ];
        
        $missing = [];
        foreach ($required_keys as $key) {
            if (empty($this->get($key))) {
                $missing[] = $key;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("缺少必要配置: " . implode(', ', $missing));
        }
        
        return true;
    }
    
    /**
     * 重新載入配置
     */
    public function reload()
    {
        $this->loadConfig();
    }
    
    /**
     * 取得所有配置
     */
    public function getAll()
    {
        return $this->config;
    }
}