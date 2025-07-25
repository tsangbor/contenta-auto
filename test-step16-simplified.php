<?php
/**
 * 簡化版步驟16 Logo生成測試
 * - 直接使用AI生成完整Logo
 * - 參考"grow"樣板風格
 * - 包含主題相關元素
 */

define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');

require_once __DIR__ . '/config-manager.php';

class SimplifiedLogoTester
{
    private $config;
    private $job_id;
    private $work_dir;
    private $images_dir;
    private $use_real_job = false;
    
    public function __construct($job_id = null)
    {
        $this->config = ConfigManager::getInstance();
        
        if ($job_id) {
            $this->job_id = $job_id;
            $this->use_real_job = true;
            echo "使用實際 Job 資料: {$job_id}\n\n";
        } else {
            $this->job_id = 'test-simplified';
            echo "使用內建測試資料\n\n";
        }
        
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
    
    public function test()
    {
        echo "=== 簡化版步驟16 Logo生成測試 ===\n\n";
        
        if ($this->use_real_job) {
            $this->testRealJob();
        } else {
            $this->testBuiltInCases();
        }
    }
    
    private function testRealJob()
    {
        try {
            // 載入實際Job資料
            $job_data = $this->loadJobData();
            if (!$job_data) {
                echo "❌ 無法載入Job資料\n";
                return;
            }
            
            $confirmed_data = $job_data['confirmed_data'];
            $domain = $confirmed_data['domain'] ?? '';
            
            // 提取網域名稱（移除.tw等後綴）
            $domain_name = preg_replace('/\.(tw|com|org|net|info|biz)$/i', '', $domain);
            
            echo "測試網站: {$domain_name} (來源: {$domain})\n";
            echo "網站名稱: {$confirmed_data['website_name']}\n";
            echo "業務描述: " . substr($confirmed_data['website_description'], 0, 100) . "...\n";
            echo "主色調: {$confirmed_data['color_scheme']['primary']} → {$confirmed_data['color_scheme']['secondary']}\n\n";
            
            // 轉換為測試格式
            $test_data = $this->convertJobDataToTestFormat($confirmed_data);
            
            $prompt = $this->buildLogoPrompt($domain_name, $test_data);
            echo "提示詞:\n{$prompt}\n\n";
            
            $result = $this->generateLogo($domain_name, $test_data);
            
            if ($result['success']) {
                echo "✅ Logo生成成功: {$result['path']}\n";
                if (isset($result['optimized_prompt'])) {
                    echo "優化後提示詞: " . substr($result['optimized_prompt'], 0, 200) . "...\n";
                }
            } else {
                echo "❌ Logo生成失敗: {$result['error']}\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 測試失敗: {$e->getMessage()}\n";
        }
    }
    
    private function testBuiltInCases()
    {
        $test_cases = [
            'yylab' => [
                'business_type' => '軟體專案管理顧問',
                'brand_keywords' => ['專案管理', '敏捷開發', '系統架構'],
                'brand_personality' => '專業、理性、實務導向',
                'color_scheme' => [
                    'primary' => '#2563EB',
                    'secondary' => '#38BDF8'
                ]
            ],
            'growtech' => [
                'business_type' => '科技創新',
                'brand_keywords' => ['AI', '成長', '創新'],
                'brand_personality' => '創新、前瞻、活力',
                'color_scheme' => [
                    'primary' => '#10B981',
                    'secondary' => '#34D399'
                ]
            ],
            'medcare' => [
                'business_type' => '醫療保健',
                'brand_keywords' => ['健康', '醫療', '照護'],
                'brand_personality' => '專業、可靠、溫暖',
                'color_scheme' => [
                    'primary' => '#3B82F6',
                    'secondary' => '#60A5FA'
                ]
            ]
        ];
        
        foreach ($test_cases as $domain => $data) {
            echo "測試網站: {$domain}\n";
            echo "業務類型: {$data['business_type']}\n";
            echo "主色調: {$data['color_scheme']['primary']} → {$data['color_scheme']['secondary']}\n";
            
            $prompt = $this->buildLogoPrompt($domain, $data);
            echo "提示詞:\n{$prompt}\n\n";
            
            $result = $this->generateLogo($domain, $data);
            
            if ($result['success']) {
                echo "✅ Logo生成成功: {$result['path']}\n";
                if (isset($result['optimized_prompt'])) {
                    echo "優化後提示詞: " . substr($result['optimized_prompt'], 0, 200) . "...\n";
                }
            } else {
                echo "❌ Logo生成失敗: {$result['error']}\n";
            }
            
            echo str_repeat('-', 80) . "\n\n";
        }
    }
    
    private function loadJobData()
    {
        // 支援新的目錄結構：data/{job_id}/{job_id}.json
        $job_dir = DEPLOY_BASE_PATH . '/data/' . $this->job_id;
        $job_data_file = $job_dir . '/' . $this->job_id . '.json';
        
        // 檢查新的檔案位置
        if (!file_exists($job_data_file)) {
            // 向後相容：檢查舊的檔案位置
            $old_job_data_file = DEPLOY_BASE_PATH . '/data/' . $this->job_id . '.json';
            if (file_exists($old_job_data_file)) {
                $job_data_file = $old_job_data_file;
                echo "使用舊位置的 job 檔案: $job_data_file\n";
            } else {
                echo "Job配置檔案不存在: $job_data_file (也檢查了 $old_job_data_file)\n";
                return null;
            }
        } else {
            echo "使用新位置的 job 檔案: $job_data_file\n";
        }
        
        $job_data = json_decode(file_get_contents($job_data_file), true);
        if (!$job_data) {
            echo "無法解析job配置檔案\n";
            return null;
        }
        
        return $job_data;
    }
    
    private function convertJobDataToTestFormat($confirmed_data)
    {
        // 從job資料提取業務類型
        $business_type = '軟體專案管理顧問'; // 根據描述推斷
        if (stripos($confirmed_data['website_description'], '醫療') !== false) {
            $business_type = '醫療保健';
        } elseif (stripos($confirmed_data['website_description'], '科技') !== false) {
            $business_type = '科技創新';
        } elseif (stripos($confirmed_data['website_description'], '專案管理') !== false) {
            $business_type = '軟體專案管理顧問';
        }
        
        return [
            'business_type' => $business_type,
            'brand_keywords' => $confirmed_data['brand_keywords'] ?? [],
            'brand_personality' => $confirmed_data['brand_personality'] ?? '專業、現代',
            'color_scheme' => $confirmed_data['color_scheme'] ?? [
                'primary' => '#2563EB',
                'secondary' => '#38BDF8'
            ],
            'website_description' => $confirmed_data['website_description'] ?? ''
        ];
    }
    
    private function buildLogoPrompt($domain_name, $data)
    {
        $primary_color = $data['color_scheme']['primary'];
        $secondary_color = $data['color_scheme']['secondary'];
        $business_type = $data['business_type'];
        $keywords = $data['brand_keywords'];
        $personality = $data['brand_personality'];
        $description = $data['website_description'] ?? '';
        
        // 建立一個較為通用的基礎提示詞，讓 Gemini 有更多創意空間
        $prompt = "Create a logo design prompt for the brand name '{$domain_name}' following this style template:

Reference template:
'A modern logo design featuring the word \"grow\" rendered in a flowing, rounded typeface with smooth organic curves that create a natural, approachable feel. The font has a subtle upward motion blur effect. The letters are a vibrant gradient of forest green to sky blue, suggesting upward movement and vitality. Below the text, a single stylized leaf subtly emerges from the base of the letter \"o\", further reinforcing the theme of growth. The background is a clean white, allowing the logo to pop and maintain a professional, minimalist aesthetic.'

Brand information to incorporate:
- Brand name: {$domain_name}
- Business type: {$business_type}
- Primary color: {$primary_color}
- Secondary color: {$secondary_color}
- Brand keywords: " . implode(', ', $keywords) . "
- Brand personality: {$personality}
- Description: " . substr($description, 0, 200) . "

Please generate a similar prompt that:
1. Uses the brand name '{$domain_name}'
2. MUST specify a flowing, rounded typeface with smooth organic curves (never geometric or sharp fonts)
3. Describes a subtle motion or visual effect that relates to the business
4. Specifies a gradient from {$primary_color} to {$secondary_color}
5. Includes a small decorative element emerging from one of the letters that symbolizes the business
6. Maintains the professional, minimalist aesthetic with clean white background

IMPORTANT: The font MUST be described as flowing, rounded, organic, or curved - never geometric, sharp, or angular.";

        return $prompt;
    }
    
    private function generateLogo($domain, $data)
    {
        try {
            // 載入API配置
            $deploy_config_file = DEPLOY_BASE_PATH . '/config/deploy-config.json';
            if (!file_exists($deploy_config_file)) {
                throw new Exception("部署配置檔案不存在");
            }
            
            $deploy_config = json_decode(file_get_contents($deploy_config_file), true);
            $api_credentials = $deploy_config['api_credentials'] ?? [];
            
            // 階段1：建立基礎描述提示詞
            $base_prompt = $this->buildLogoPrompt($domain, $data);
            echo "基礎提示詞:\n{$base_prompt}\n\n";
            
            // 階段2：使用Gemini優化提示詞
            $optimized_prompt = null;
            if (isset($api_credentials['gemini']['api_key']) && !empty($api_credentials['gemini']['api_key'])) {
                echo "使用Gemini優化提示詞...\n";
                $optimized_prompt = $this->optimizePromptWithGemini($base_prompt, $api_credentials['gemini']);
                
                if ($optimized_prompt) {
                    echo "Gemini優化後的提示詞:\n{$optimized_prompt}\n\n";
                } else {
                    echo "Gemini優化失敗，使用原始提示詞\n";
                    $optimized_prompt = $base_prompt;
                }
            } else {
                echo "Gemini API未配置，使用原始提示詞\n";
                $optimized_prompt = $base_prompt;
            }
            
            // 階段3：使用Ideogram生成圖片
            $image_data = null;
            if (isset($api_credentials['ideogram']['api_key']) && !empty($api_credentials['ideogram']['api_key'])) {
                echo "使用Ideogram生成Logo...\n";
                $image_data = $this->callIdeogram($optimized_prompt, $api_credentials['ideogram']);
            }
            
            // 備用方案：如果Ideogram失敗，嘗試OpenAI
            if (!$image_data && isset($api_credentials['openai']['api_key']) && !empty($api_credentials['openai']['api_key'])) {
                echo "Ideogram失敗，嘗試使用OpenAI...\n";
                $image_data = $this->callOpenAI($optimized_prompt, $api_credentials['openai']);
            }
            
            if (!$image_data) {
                echo "AI生成失敗，使用備用方案\n";
                return $this->generateFallbackLogo($domain, $data);
            }
            
            // 儲存Logo
            $filename = "ai_logo_{$domain}.png";
            $file_path = $this->images_dir . '/' . $filename;
            
            if ($this->saveImageData($image_data, $file_path)) {
                // 調整為標準尺寸
                $resized_path = $this->resizeImage($file_path, 540, 210);
                
                return [
                    'success' => true,
                    'path' => $resized_path ?: $file_path,
                    'type' => 'Gemini+Ideogram Generated',
                    'optimized_prompt' => $optimized_prompt
                ];
            }
            
            throw new Exception("圖片儲存失敗");
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateFallbackLogo($domain, $data)
    {
        try {
            $width = 540;
            $height = 210;
            
            $image = imagecreatetruecolor($width, $height);
            $white_bg = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $white_bg);
            
            // 解析顏色
            $primary_rgb = $this->hexToRgb($data['color_scheme']['primary']);
            $text_color = imagecolorallocate($image, $primary_rgb['r'], $primary_rgb['g'], $primary_rgb['b']);
            
            // 字體檔案
            $font_file = DEPLOY_BASE_PATH . '/logo/font/PottaOne-Regular.ttf';
            if (!file_exists($font_file)) {
                // 使用內建字體
                $font_size = 5;
                $text_width = strlen($domain) * imagefontwidth($font_size);
                $text_height = imagefontheight($font_size);
                
                $x = ($width - $text_width) / 2;
                $y = ($height - $text_height) / 2;
                
                imagestring($image, $font_size, $x, $y, $domain, $text_color);
            } else {
                // 使用TTF字體
                $font_size = $this->calculateFontSize($domain, $font_file, $width - 60);
                
                $text_box = imagettfbbox($font_size, 0, $font_file, $domain);
                $text_width = $text_box[4] - $text_box[0];
                $text_height = $text_box[1] - $text_box[7];
                
                $x = ($width - $text_width) / 2;
                $y = ($height / 2) + ($text_height * 0.35);
                
                imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_file, $domain);
            }
            
            $filename = "fallback_logo_{$domain}.png";
            $file_path = $this->images_dir . '/' . $filename;
            
            if (imagepng($image, $file_path)) {
                imagedestroy($image);
                return [
                    'success' => true,
                    'path' => $file_path,
                    'type' => 'Fallback Generated'
                ];
            }
            
            throw new Exception("備用Logo儲存失敗");
            
        } catch (Exception $e) {
            if (isset($image)) imagedestroy($image);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 使用Gemini優化提示詞
     */
    private function optimizePromptWithGemini($base_prompt, $config)
    {
        try {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $config['api_key'];
            
            $optimization_instruction = "You are a professional logo designer. Based on the brand information provided, create a concise, artistic logo design prompt in the style of this example:

Example style:
'A modern logo design featuring the word \"grow\" rendered in a flowing, rounded typeface with smooth organic curves that create a natural, approachable feel. The font has a subtle upward motion blur effect. The letters are a vibrant gradient of forest green to sky blue, suggesting upward movement and vitality. Below the text, a single stylized leaf subtly emerges from the base of the letter \"o\", further reinforcing the theme of growth. The background is a clean white, allowing the logo to pop and maintain a professional, minimalist aesthetic.'

{$base_prompt}

Create a logo prompt that:
- Follows the exact structure and tone of the example
- Is concise (2-3 sentences max)
- Uses artistic, flowing language
- MUST describe the font as flowing, rounded, organic curves (NEVER geometric, sharp, or angular)
- Describes motion/visual effects naturally
- Integrates the decorative element organically
- Maintains professional minimalist aesthetic

CRITICAL: Always describe fonts as flowing, rounded, organic, or curved. Never use geometric, sharp, angular, or technical font descriptions.

Provide ONLY the final prompt, no explanations.";

            $data = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $optimization_instruction
                            ]
                        ]
                    ]
                ]
            ];
            
            $response = $this->makeHttpRequest($url, $data, ['Content-Type: application/json']);
            
            if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                $optimized = trim($response['candidates'][0]['content']['parts'][0]['text']);
                // 移除可能的引號或格式化字符
                $optimized = trim($optimized, '"\'`');
                return $optimized;
            }
            
            return null;
            
        } catch (Exception $e) {
            echo "Gemini優化錯誤: {$e->getMessage()}\n";
            return null;
        }
    }
    
    /**
     * 調用Ideogram生成圖片
     */
    private function callIdeogram($prompt, $config)
    {
        try {
            $url = 'https://api.ideogram.ai/generate';
            
            $data = [
                'image_request' => [
                    'prompt' => $prompt,
                    'aspect_ratio' => 'ASPECT_16_9', // 接近 540x210 的比例
                    'model' => 'V_2',
                    'magic_prompt_option' => 'ON',
                    'seed' => rand(1, 1000000)
                ]
            ];
            
            $headers = [
                'Api-Key: ' . $config['api_key'],
                'Content-Type: application/json'
            ];
            
            $response = $this->makeHttpRequest($url, $data, $headers);
            
            if ($response && isset($response['data'][0]['url'])) {
                return $response['data'][0]['url'];
            }
            
            return null;
            
        } catch (Exception $e) {
            echo "Ideogram調用錯誤: {$e->getMessage()}\n";
            return null;
        }
    }
    
    // 工具方法
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
    
    private function calculateFontSize($text, $font_file, $max_width)
    {
        $low = 10;
        $high = 100;
        $best_size = $low;
        
        while ($low <= $high) {
            $mid = (int)(($low + $high) / 2);
            $text_box = imagettfbbox($mid, 0, $font_file, $text);
            $text_width = $text_box[4] - $text_box[0];
            
            if ($text_width <= $max_width * 0.9) {
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
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $job_id = isset($argv[1]) ? $argv[1] : null;
    $tester = new SimplifiedLogoTester($job_id);
    $tester->test();
} else {
    echo "<pre>";
    $job_id = isset($_GET['job_id']) ? $_GET['job_id'] : null;
    $tester = new SimplifiedLogoTester($job_id);
    $tester->test();
    echo "</pre>";
}
?>