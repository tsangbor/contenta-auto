<?php
/**
 * 步驟16 Logo生成測試程式
 * - 使用網域名稱(不含.tw)作為Logo文字
 * - AI生成切題的小圖示
 * - 參考用戶提供的gradient風格設計
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

require_once __DIR__ . '/config-manager.php';

class LogoGenerationTester
{
    private $config;
    private $job_id = 'test-logo';
    private $work_dir;
    private $images_dir;
    
    public function __construct()
    {
        $this->config = ConfigManager::getInstance();
        $this->work_dir = DEPLOY_BASE_PATH . '/temp/' . $this->job_id;
        $this->images_dir = $this->work_dir . '/images';
        
        // 建立測試目錄
        if (!is_dir($this->work_dir)) {
            mkdir($this->work_dir, 0755, true);
        }
        if (!is_dir($this->images_dir)) {
            mkdir($this->images_dir, 0755, true);
        }
    }
    
    /**
     * 主要測試函式
     */
    public function test()
    {
        echo "=== 步驟16 Logo生成測試 ===\n\n";
        
        // 測試用的網站資料
        $test_data = $this->getTestData();
        
        foreach ($test_data as $site_name => $site_info) {
            echo "測試網站: {$site_name}\n";
            echo "描述: {$site_info['description']}\n";
            echo "業務類型: {$site_info['business_type']}\n";
            echo "色彩: {$site_info['color_scheme']['primary']}\n\n";
            
            $result = $this->generateLogo($site_name, $site_info);
            
            if ($result['success']) {
                echo "✅ Logo生成成功: {$result['logo_path']}\n";
                echo "   檔案大小: {$result['file_size']}\n\n";
            } else {
                echo "❌ Logo生成失敗: {$result['error']}\n\n";
            }
            
            echo str_repeat('-', 50) . "\n\n";
        }
    }
    
    /**
     * 取得測試資料
     */
    private function getTestData()
    {
        return [
            'yylab' => [
                'description' => 'Zion Wu\'s 歪研所｜YY Lab 是由擁有逾二十年軟體產業實戰經驗的 Zion Wu 建立的個人知識平台',
                'business_type' => '軟體專案管理顧問',
                'brand_keywords' => ['專案管理', '敏捷開發', '系統架構', '實戰經驗', '知識分享'],
                'target_audience' => '軟體產業的專案經理、開發者、企業主',
                'brand_personality' => '專業、理性、知識型、實務導向',
                'color_scheme' => [
                    'primary' => '#2563EB',
                    'secondary' => '#38BDF8', 
                    'accent' => '#0F172A',
                    'text' => '#1E293B'
                ]
            ],
            'growtech' => [
                'description' => '新創科技公司，專注於AI驅動的成長解決方案',
                'business_type' => '科技創新',
                'brand_keywords' => ['AI', '成長', '創新', '科技', '解決方案'],
                'target_audience' => '新創企業、科技愛好者',
                'brand_personality' => '創新、前瞻、活力、專業',
                'color_scheme' => [
                    'primary' => '#10B981',
                    'secondary' => '#34D399',
                    'accent' => '#059669', 
                    'text' => '#065F46'
                ]
            ],
            'healcare' => [
                'description' => '專業醫療保健服務平台',
                'business_type' => '醫療保健',
                'brand_keywords' => ['健康', '醫療', '照護', '專業', '信賴'],
                'target_audience' => '患者、醫療專業人員',
                'brand_personality' => '專業、可靠、溫暖、關懷',
                'color_scheme' => [
                    'primary' => '#3B82F6',
                    'secondary' => '#60A5FA',
                    'accent' => '#1E40AF',
                    'text' => '#1E3A8A'
                ]
            ]
        ];
    }
    
    /**
     * 生成Logo
     */
    private function generateLogo($domain_name, $site_info)
    {
        try {
            echo "開始生成Logo...\n";
            
            // 1. 生成AI背景圖示
            $background_path = $this->generateAIBackground($domain_name, $site_info);
            if (!$background_path) {
                echo "AI背景生成失敗，使用漸層背景\n";
                $background_path = $this->createGradientBackground($site_info['color_scheme']);
            }
            
            // 2. 生成文字圖層
            $text_path = $this->generateTextLayer($domain_name, $site_info['color_scheme']);
            if (!$text_path) {
                throw new Exception("文字圖層生成失敗");
            }
            
            // 3. 合併圖層
            $final_path = $this->mergeLayers($background_path, $text_path, $domain_name);
            if (!$final_path) {
                throw new Exception("圖層合併失敗");
            }
            
            return [
                'success' => true,
                'logo_path' => $final_path,
                'file_size' => $this->formatFileSize(filesize($final_path))
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 生成AI背景圖示
     */
    private function generateAIBackground($domain_name, $site_info)
    {
        try {
            // 載入API配置
            $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
            if (!file_exists($deploy_config_file)) {
                throw new Exception("部署配置檔案不存在");
            }
            
            $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
            $api_credentials = $deploy_config['api_credentials'] ?? [];
            
            // 構建提示詞，參考用戶提供的圖片風格
            $prompt = $this->buildBackgroundPrompt($domain_name, $site_info);
            
            echo "AI提示詞: {$prompt}\n";
            
            // 嘗試使用可用的AI服務
            $image_data = null;
            
            // 嘗試OpenAI
            if (isset($api_credentials['openai']['api_key']) && !empty($api_credentials['openai']['api_key'])) {
                echo "嘗試使用OpenAI生成背景...\n";
                $image_data = $this->callOpenAI($prompt, $api_credentials['openai']);
            }
            
            // 嘗試Gemini
            if (!$image_data && isset($api_credentials['gemini']['api_key']) && !empty($api_credentials['gemini']['api_key'])) {
                echo "嘗試使用Gemini生成背景...\n";
                $image_data = $this->callGemini($prompt, $api_credentials['gemini']);
            }
            
            // 嘗試Ideogram
            if (!$image_data && isset($api_credentials['ideogram']['api_key']) && !empty($api_credentials['ideogram']['api_key'])) {
                echo "嘗試使用Ideogram生成背景...\n";
                $image_data = $this->callIdeogram($prompt, $api_credentials['ideogram']);
            }
            
            if (!$image_data) {
                return null;
            }
            
            // 儲存圖片
            $filename = "background_{$domain_name}.png";
            $file_path = $this->images_dir . '/' . $filename;
            
            if ($this->saveImageData($image_data, $file_path)) {
                // 調整為540x210尺寸
                return $this->resizeImage($file_path, 540, 210);
            }
            
            return null;
            
        } catch (Exception $e) {
            echo "AI背景生成錯誤: {$e->getMessage()}\n";
            return null;
        }
    }
    
    /**
     * 構建背景提示詞，參考用戶提供的風格
     */
    private function buildBackgroundPrompt($domain_name, $site_info)
    {
        $business_type = $site_info['business_type'];
        $keywords = implode(', ', $site_info['brand_keywords']);
        $primary_color = $site_info['color_scheme']['primary'];
        $secondary_color = $site_info['color_scheme']['secondary'];
        
        // 根據業務類型選擇相關圖示
        $relevant_icons = $this->getRelevantIcons($business_type, $site_info['brand_keywords']);
        
        $prompt = "Create a minimalist logo background design inspired by the style shown in the reference image. 

Design requirements:
- Gradient background from {$primary_color} to {$secondary_color}
- Include 2-3 small, subtle icons related to: {$keywords}
- Relevant icons: {$relevant_icons}
- Icons should be: very small (3-5% of canvas), translucent (30% opacity), positioned in corners or edges
- Modern flat design style with smooth gradients
- Clean, professional appearance suitable for logo background
- Canvas size: 540x210 pixels (landscape orientation)
- Leave 70% of central space empty for text overlay

Style inspiration: Similar to the \"grow\" logo with leaf element - modern, clean gradient with subtle thematic icons integrated naturally into the design.

Business context: {$business_type} - {$site_info['description']}

NO TEXT in the image - icons and gradient background only.";

        return $prompt;
    }
    
    /**
     * 根據業務類型取得相關圖示
     */
    private function getRelevantIcons($business_type, $keywords)
    {
        $icon_mapping = [
            '軟體專案管理' => 'gear icons, project timeline, code brackets, flowchart elements',
            '科技創新' => 'circuit patterns, data nodes, innovation arrows, tech symbols',
            '醫療保健' => 'medical cross, heart symbol, care hands, health plus',
            '教育' => 'book icons, graduation cap, lightbulb, knowledge tree',
            '金融' => 'growth chart, shield, balance scale, currency symbols',
            '餐飲' => 'leaf elements, food symbols, natural shapes, organic forms',
            '設計' => 'creative tools, color palette, design elements, artistic shapes'
        ];
        
        foreach ($icon_mapping as $type => $icons) {
            if (stripos($business_type, $type) !== false) {
                return $icons;
            }
        }
        
        // 基於關鍵字的備用選擇
        if (in_array('專案管理', $keywords)) {
            return 'project management icons, workflow symbols, organizational charts';
        }
        if (in_array('AI', $keywords) || in_array('科技', $keywords)) {
            return 'AI nodes, neural network, technology symbols, innovation icons';
        }
        if (in_array('健康', $keywords) || in_array('醫療', $keywords)) {
            return 'medical symbols, health icons, care elements';
        }
        
        return 'abstract geometric shapes, minimalist symbols, professional icons';
    }
    
    /**
     * 創建漸層背景（備用方案）
     */
    private function createGradientBackground($color_scheme)
    {
        try {
            $width = 540;
            $height = 210;
            
            $image = imagecreatetruecolor($width, $height);
            
            // 解析顏色
            $start_color = $this->hexToRgb($color_scheme['primary']);
            $end_color = $this->hexToRgb($color_scheme['secondary']);
            
            // 創建漸層效果
            for ($y = 0; $y < $height; $y++) {
                $ratio = $y / $height;
                $r = $start_color['r'] + ($end_color['r'] - $start_color['r']) * $ratio;
                $g = $start_color['g'] + ($end_color['g'] - $start_color['g']) * $ratio;
                $b = $start_color['b'] + ($end_color['b'] - $start_color['b']) * $ratio;
                
                $color = imagecolorallocate($image, (int)$r, (int)$g, (int)$b);
                imageline($image, 0, $y, $width - 1, $y, $color);
            }
            
            $filename = 'gradient_background.png';
            $file_path = $this->images_dir . '/' . $filename;
            
            if (imagepng($image, $file_path)) {
                imagedestroy($image);
                return $file_path;
            }
            
            imagedestroy($image);
            return null;
            
        } catch (Exception $e) {
            echo "漸層背景創建失敗: {$e->getMessage()}\n";
            return null;
        }
    }
    
    /**
     * 生成文字圖層
     */
    private function generateTextLayer($domain_name, $color_scheme)
    {
        try {
            $width = 540;
            $height = 210;
            
            $image = imagecreatetruecolor($width, $height);
            
            // 設定透明背景
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefill($image, 0, 0, $transparent);
            imagealphablending($image, true);
            
            // 使用白色文字（在漸層背景上較顯眼）
            $text_color = imagecolorallocate($image, 255, 255, 255);
            
            // 檢查字體檔案
            $font_file = DEPLOY_BASE_PATH . '/logo/font/PottaOne-Regular.ttf';
            if (!file_exists($font_file)) {
                throw new Exception("字體檔案不存在: {$font_file}");
            }
            
            // 計算字體大小
            $font_size = $this->calculateOptimalFontSize($domain_name, $font_file, $width - 60, $height - 50);
            
            // 計算文字位置（居中）
            $text_box = imagettfbbox($font_size, 0, $font_file, $domain_name);
            $text_width = $text_box[4] - $text_box[0];
            $text_height = $text_box[1] - $text_box[7];
            
            $x = ($width - $text_width) / 2;
            $y = ($height / 2) + ($text_height * 0.35);
            
            // 渲染文字
            imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_file, $domain_name);
            
            $filename = "text_{$domain_name}.png";
            $file_path = $this->images_dir . '/' . $filename;
            
            if (imagepng($image, $file_path)) {
                imagedestroy($image);
                return $file_path;
            }
            
            imagedestroy($image);
            return null;
            
        } catch (Exception $e) {
            echo "文字圖層生成失敗: {$e->getMessage()}\n";
            return null;
        }
    }
    
    /**
     * 合併圖層
     */
    private function mergeLayers($background_path, $text_path, $domain_name)
    {
        try {
            $background = imagecreatefrompng($background_path);
            $text_layer = imagecreatefrompng($text_path);
            
            if (!$background || !$text_layer) {
                throw new Exception("無法載入圖層");
            }
            
            // 合併圖層
            imagealphablending($background, true);
            imagesavealpha($background, true);
            imagecopy($background, $text_layer, 0, 0, 0, 0, imagesx($text_layer), imagesy($text_layer));
            
            $filename = "logo_{$domain_name}_final.png";
            $file_path = $this->images_dir . '/' . $filename;
            
            if (imagepng($background, $file_path)) {
                imagedestroy($background);
                imagedestroy($text_layer);
                return $file_path;
            }
            
            imagedestroy($background);
            imagedestroy($text_layer);
            return null;
            
        } catch (Exception $e) {
            echo "圖層合併失敗: {$e->getMessage()}\n";
            return null;
        }
    }
    
    /**
     * AI服務調用方法
     */
    private function callOpenAI($prompt, $config)
    {
        $url = 'https://api.openai.com/v1/images/generations';
        $data = [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1024x1024',
            'quality' => 'standard',
            'response_format' => 'url'
        ];
        
        $response = $this->makeHttpRequest($url, $data, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['api_key']
        ]);
        
        if ($response && isset($response['data'][0]['url'])) {
            return $response['data'][0]['url'];
        }
        
        return null;
    }
    
    private function callGemini($prompt, $config)
    {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-001:generateImage?key=' . $config['api_key'];
        $data = [
            'prompt' => $prompt,
            'sampleCount' => 1,
            'aspectRatio' => 'LANDSCAPE'
        ];
        
        $response = $this->makeHttpRequest($url, $data, ['Content-Type: application/json']);
        
        if ($response && isset($response['generatedImages'][0]['bytesBase64Encoded'])) {
            return 'data:image/png;base64,' . $response['generatedImages'][0]['bytesBase64Encoded'];
        }
        
        return null;
    }
    
    private function callIdeogram($prompt, $config)
    {
        // Ideogram實作較複雜，這裡簡化處理
        return null;
    }
    
    /**
     * HTTP請求工具
     */
    private function makeHttpRequest($url, $data, $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * 工具方法
     */
    private function saveImageData($image_data, $file_path)
    {
        if (strpos($image_data, 'data:image') === 0) {
            $base64_data = explode(',', $image_data)[1];
            $binary_data = base64_decode($base64_data);
        } else {
            $binary_data = file_get_contents($image_data);
        }
        
        return file_put_contents($file_path, $binary_data) !== false;
    }
    
    private function resizeImage($source_path, $target_width, $target_height)
    {
        $source = imagecreatefrompng($source_path);
        if (!$source) return null;
        
        $source_width = imagesx($source);
        $source_height = imagesy($source);
        
        $target = imagecreatetruecolor($target_width, $target_height);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        
        imagecopyresampled($target, $source, 0, 0, 0, 0, 
                          $target_width, $target_height, $source_width, $source_height);
        
        $resized_path = str_replace('.png', '_resized.png', $source_path);
        if (imagepng($target, $resized_path)) {
            imagedestroy($source);
            imagedestroy($target);
            return $resized_path;
        }
        
        imagedestroy($source);
        imagedestroy($target);
        return null;
    }
    
    private function calculateOptimalFontSize($text, $font_file, $max_width, $max_height)
    {
        $low = 10;
        $high = 120;
        $best_size = $low;
        
        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $text_box = imagettfbbox($mid, 0, $font_file, $text);
            $text_width = $text_box[4] - $text_box[0];
            $text_height = $text_box[1] - $text_box[7];
            
            if ($text_width <= $max_width * 0.95 && $text_height <= $max_height) {
                $best_size = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }
        
        return $best_size;
    }
    
    private function hexToRgb($hex)
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
    
    private function formatFileSize($size)
    {
        $units = ['B', 'KB', 'MB'];
        $unit = 0;
        
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        
        return round($size, 1) . ' ' . $units[$unit];
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $tester = new LogoGenerationTester();
    $tester->test();
} else {
    echo "<pre>";
    $tester = new LogoGenerationTester();
    $tester->test();
    echo "</pre>";
}