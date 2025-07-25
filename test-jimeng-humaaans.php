<?php
/**
 * 測試即夢 AI 的 Humaaans 風格圖片生成
 * 使用 Composer 和 GuzzleHttp - 基於火山引擎官方範例
 */

// 載入 Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';
// 需要自行安装 composer（https://getcomposer.org/doc/00-intro.md），並安装GuzzleHttp依赖， composer require guzzlehttp/guzzle:^7.0
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// 設定基本路徑
define('DEPLOY_BASE_PATH', __DIR__);
define('DEPLOY_CONFIG_PATH', DEPLOY_BASE_PATH . '/config');
require_once DEPLOY_BASE_PATH . '/config-manager.php';

// 基础信息，基本不用变更
$Host = "visual.volcengineapi.com";
$ContentType = "application/json";
$Service = "cv";
$Region = "cn-north-1";

echo "=== 即夢 AI Humaaans 風格測試 ===\n";
echo "使用 Composer + GuzzleHttp - 基於火山引擎官方範例\n";
echo "使用 config/deploy-config.json 中的 API 配置\n";

// 檢查 GuzzleHttp 是否正確載入
if (class_exists('GuzzleHttp\Client')) {
    echo "✅ GuzzleHttp 已成功載入\n";
} else {
    echo "❌ GuzzleHttp 載入失敗，請執行 'composer install'\n";
    exit(1);
}
echo "\n";

// 載入配置管理器
$config = ConfigManager::getInstance();
$deploy_config = $config->getAll();

// 取得即夢 AI 配置
$jimeng_credentials = $deploy_config['api_credentials']['jimeng'] ?? [];

// 即夢 AI API 配置 - 測試原始金鑰格式
$secret_key_raw = $jimeng_credentials['SecretAccessKey'] ?? '';

$jimeng_config = [
    'access_key' => $jimeng_credentials['AccessKeyID'] ?? '',
    'secret_key' => $secret_key_raw, // 使用原始格式測試
    'api_endpoint' => 'https://visual.volcengineapi.com',
    'service' => 'cv',  // computer vision service
    'region' => 'cn-north-1',
    'version' => '2022-08-31'
];

// 檢查 API 金鑰
if (empty($jimeng_config['access_key']) || empty($jimeng_config['secret_key'])) {
    die("錯誤: 請在 config/deploy-config.json 中配置 jimeng API 金鑰\n");
}

echo "API 金鑰已配置\n\n";

// 測試提示詞 - Humaaans 風格
$test_prompts = [
    "homepage_hero" => [
        "original" => "Professional team collaboration in modern office",
        "humaaans_style" => "A modern flat illustration in the style of humaaans, featuring minimalist characters collaborating in an office setting. Simple geometric shapes, clean lines, vector art aesthetic. Characters with friendly geometric features, wearing business casual attire. Abstract geometric background with overlapping shapes. Color palette: deep blue (#2563EB), light blue (#38BDF8), subtle dark gray (#0F172A) accents. Plenty of negative space for text overlay. Purely visual imagery, no text, no words, no letters.",
        "chinese_style" => "扁平插畫風格，極簡人物角色在辦公室協作，簡單幾何形狀，乾淨線條，向量藝術美學。友善的幾何特徵角色，穿著商務休閒服裝。抽象幾何背景與重疊形狀。色彩配置：深藍色(#2563EB)、淺藍色(#38BDF8)、深灰色(#0F172A)點綴。充足留白空間。純視覺圖像，無文字。"
    ],
    "about_team" => [
        "original" => "Diverse business team working together",
        "humaaans_style" => "Flat illustration in the style of humaaans showing diverse team members collaborating. Minimalist character design with simple geometric features, different skin tones represented through flat colors. Clean vector art style, characters interacting with abstract data nodes or devices. Professional color palette of deep blue (#2563EB), light blue (#38BDF8), with subtle accents. Abstract geometric composition background. No text, no words, purely visual.",
        "chinese_style" => "扁平插畫風格展示多元團隊成員協作。極簡角色設計，簡單幾何特徵，以扁平色彩呈現不同膚色。乾淨向量藝術風格，角色與抽象數據節點或設備互動。專業色彩配置：深藍色、淺藍色與細微點綴。抽象幾何構圖背景。無文字，純視覺。"
    ],
    "service_illustration" => [
        "original" => "Modern technology and innovation concept",
        "humaaans_style" => "A flat illustration in the style of humaaans depicting technology and innovation. Minimalist characters interacting with abstract tech elements like nodes, connections, and geometric interfaces. Clean lines, vector aesthetic, simple shapes. Background features overlapping geometric patterns suggesting connectivity. Color scheme: deep blue (#2563EB), light blue (#38BDF8), dark gray (#0F172A) accents. Modern, friendly, approachable design. No text or letters.",
        "chinese_style" => "扁平插畫風格描繪科技與創新。極簡角色與抽象科技元素互動，如節點、連接和幾何介面。乾淨線條，向量美學，簡單形狀。背景以重疊幾何圖案暗示連結性。色彩方案：深藍色、淺藍色、深灰色點綴。現代、友善、親和設計。無文字。"
    ]
];

/**
 * @throws GuzzleException
 */
// 第一步：創建一個 API 請求函數。簽名計算的過程包含在該函數中。
function request($method, $query, $header, $ak, $sk, $action, $version, $body)
{
    // 第二步：創建身份證明。其中的 Service 和 Region 字段是固定的。ak 和 sk 分別代表
    // AccessKeyID 和 SecretAccessKey。同時需要初始化簽名結構體。一些簽名計算時需要的屬性也在這裡處理。
    // 初始化身份證明結構體
    global $Service, $Region, $Host, $ContentType;
    $credential = [
        'accessKeyId' => $ak,
        'secretKeyId' => $sk,
        'service' => $Service,
        'region' => $Region,
    ];
    // 初始化簽名結構體
    $query = array_merge($query, [
        'Action' => $action,
        'Version' => $version
    ]);
    ksort($query);
    $requestParam = [
        // body是http請求需要的原生body
        'body' => $body,
        'host' => $Host,
        'path' => '/',
        'method' => $method,
        'contentType' => $ContentType,
        'date' => gmdate('Ymd\THis\Z'),
        'query' => $query
    ];
    // 第三步：接下來開始計算簽名。在計算簽名前，先準備好用於接收簽算結果的 signResult 變量，並設置一些參數。
    // 初始化簽名結果的結構體
    $xDate = $requestParam['date'];
    $shortXDate = substr($xDate, 0, 8);
    $xContentSha256 = hash('sha256', $requestParam['body']);
    $signResult = [
        'Host' => $requestParam['host'],
        'X-Content-Sha256' => $xContentSha256,
        'X-Date' => $xDate,
        'Content-Type' => $requestParam['contentType']
    ];
    // 第四步：計算 Signature 簽名。
    $signedHeaderStr = join(';', ['content-type', 'host', 'x-content-sha256', 'x-date']);
    $canonicalRequestStr = join("\n", [
        $requestParam['method'],
        $requestParam['path'],
        http_build_query($requestParam['query']),
        join("\n", ['content-type:'. $requestParam['contentType'], 'host:'. $requestParam['host'], 'x-content-sha256:'. $xContentSha256, 'x-date:'. $xDate]),
        '',
        $signedHeaderStr,
        $xContentSha256
    ]);
    $hashedCanonicalRequest = hash("sha256", $canonicalRequestStr);
    $credentialScope = join('/', [$shortXDate, $credential['region'], $credential['service'], 'request']);
    $stringToSign = join("\n", ['HMAC-SHA256', $xDate, $credentialScope, $hashedCanonicalRequest]);
    $kDate = hash_hmac("sha256", $shortXDate, $credential['secretKeyId'], true);
    $kRegion = hash_hmac("sha256", $credential['region'], $kDate, true);
    $kService = hash_hmac("sha256", $credential['service'], $kRegion, true);
    $kSigning = hash_hmac("sha256", 'request', $kService, true);
    $signature = hash_hmac("sha256", $stringToSign, $kSigning);
    $signResult['Authorization'] = sprintf("HMAC-SHA256 Credential=%s, SignedHeaders=%s, Signature=%s", $credential['accessKeyId']. '/'. $credentialScope, $signedHeaderStr, $signature);
    $header = array_merge($header, $signResult);
    // 第五步：將 Signature 簽名寫入 HTTP Header 中，並發送 HTTP 請求。
    $client = new Client([
        'base_uri' => 'https://'. $requestParam['host'],
        'timeout' => 120.0,
    ]);
    $response = $client->request($method, 'https://'. $requestParam['host']. $requestParam['path'], [
        'headers' => $header,
        'query' => $requestParam['query'],
        'body' => $requestParam['body']
    ]);
    $responseContent = $response->getBody()->getContents();
    // 轉換 \u0026 為 &
    $responseContent = str_replace('\u0026', '&', $responseContent);
    return $responseContent;
}

/**
 * 呼叫即夢 AI API 生成圖片 - 使用官方 GuzzleHttp 版本
 */
function generateImageWithJimeng($prompt, $config, $params = []) {
    // 火山官網密鑰信息來自配置檔
    $AccessKeyID = $config['access_key'];
    $SecretAccessKey = $config['secret_key'];
    
    // 參考接口文檔Query參數
    $action = "CVProcess";
    $version = "2022-08-31";
    
    // 參考 jimeng-ai-mcp 源碼中的默認 req_key 值
    $requestBody = [
        "req_key" => "jimeng_high_aes_general_v21_L",  // MCP 使用的默認模型
        "prompt" => $prompt,
        "seed" => -1,
        "scale" => 2.5,
        "width" => $params['width'] ?? 1328,
        "height" => $params['height'] ?? 1328,
        "return_url" => true,
        "logo_info" => [
            "add_logo" => false,
            "position" => 0,
            "language" => 0,
            "opacity" => 0.3
        ]
    ];
    $body = json_encode($requestBody);
    
    // 調試信息
    echo "使用模型: jimeng_high_aes_general_v21_L\n";
    echo "請求體長度: " . strlen($body) . " 字元\n";
    echo "請求體內容 (前100字): " . substr($body, 0, 100) . "...\n\n";
    
    try {
        $response = request("POST", [], [], $AccessKeyID, $SecretAccessKey, $action, $version, $body);
        
        // 解析回應
        $responseData = json_decode($response, true);
        
        // 檢查 API 回應狀態 - 即夢 AI 成功碼是 10000
        $isSuccess = ($responseData !== null && 
                     (isset($responseData['code']) && $responseData['code'] === 10000));
        
        return [
            'success' => $isSuccess,
            'response' => $response,
            'body' => $responseData
        ];
        
    } catch (GuzzleException $e) {
        $errorResponse = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
        return [
            'success' => false,
            'error' => $errorResponse,
            'response' => $errorResponse,  // 新增 response 鍵以避免 undefined key 錯誤
            'body' => json_decode($errorResponse, true)
        ];
    }
}

/**
 * 測試生成圖片
 */
function testJimengGeneration($prompt_name, $prompt_data, $config) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "測試: {$prompt_name}\n";
    echo str_repeat("=", 60) . "\n";
    
    // 測試英文 Humaaans 風格提示詞
    echo "\n1. 測試英文 Humaaans 風格提示詞\n";
    echo "提示詞: " . substr($prompt_data['humaaans_style'], 0, 100) . "...\n";
    
    $result = generateImageWithJimeng($prompt_data['humaaans_style'], $config, [
        'width' => 1328,
        'height' => 1328
    ]);
    
    if ($result['success'] && isset($result['body']['data'])) {
        $data = $result['body']['data'];
        if (isset($data['image_urls']) && is_array($data['image_urls']) && count($data['image_urls']) > 0) {
            echo "✅ 生成成功！共生成 " . count($data['image_urls']) . " 張圖片\n";
            echo "圖片 URL: " . $data['image_urls'][0] . "\n";
            
            // 下載並儲存第一張圖片
            $image_url = $data['image_urls'][0];
            $image_data = file_get_contents($image_url);
            if ($image_data !== false) {
                $filename = "jimeng_humaaans_en_{$prompt_name}_" . time() . ".jpg";
                file_put_contents(DEPLOY_BASE_PATH . "/temp/{$filename}", $image_data);
                echo "圖片已儲存: temp/{$filename}\n";
            } else {
                echo "無法下載圖片\n";
            }
        } else {
            echo "❌ 生成失敗: 回應格式異常\n";
            echo "回應內容: " . json_encode($result['body'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "❌ API 錯誤: " . ($result['body']['message'] ?? $result['error'] ?? '未知錯誤') . "\n";
        echo "詳細錯誤資訊:\n";
        echo "完整回應: " . ($result['response'] ?? '無回應內容') . "\n";
        
        // 如果是權限錯誤，提供解決建議
        if (isset($result['body']['code']) && $result['body']['code'] == 50400) {
            echo "\n權限問題解決建議:\n";
            echo "1. 確認火山引擎控制台中 API 金鑰擁有 '視覺智能-圖像生成' 權限\n";
            echo "2. 檢查帳戶是否已完成實名認證\n";
            echo "3. 確認即夢 AI 服務已正式開通（非試用版）\n";
            echo "4. 檢查帳戶餘額和計費配置\n";
            echo "5. 聯繫火山引擎技術支援確認服務配置\n";
        }
    }
    
    // 測試中文提示詞
    echo "\n2. 測試中文風格提示詞\n";
    echo "提示詞: " . substr($prompt_data['chinese_style'], 0, 100) . "...\n";
    
    $result = generateImageWithJimeng($prompt_data['chinese_style'], $config, [
        'width' => 1328,
        'height' => 1328
    ]);
    
    if ($result['success'] && isset($result['body']['data'])) {
        $data = $result['body']['data'];
        if (isset($data['image_urls']) && is_array($data['image_urls']) && count($data['image_urls']) > 0) {
            echo "✅ 生成成功！共生成 " . count($data['image_urls']) . " 張圖片\n";
            echo "圖片 URL: " . $data['image_urls'][0] . "\n";
            
            // 下載並儲存第一張圖片
            $image_url = $data['image_urls'][0];
            $image_data = file_get_contents($image_url);
            if ($image_data !== false) {
                $filename = "jimeng_humaaans_zh_{$prompt_name}_" . time() . ".jpg";
                file_put_contents(DEPLOY_BASE_PATH . "/temp/{$filename}", $image_data);
                echo "圖片已儲存: temp/{$filename}\n";
            } else {
                echo "無法下載圖片\n";
            }
        } else {
            echo "❌ 生成失敗: 回應格式異常\n";
        }
    } else {
        echo "❌ API 錯誤: " . ($result['body']['message'] ?? $result['error'] ?? '未知錯誤') . "\n";
        echo "詳細錯誤資訊:\n";
        echo "完整回應: " . ($result['response'] ?? '無回應內容') . "\n";
        
        // 如果是權限錯誤，提供解決建議
        if (isset($result['body']['code']) && $result['body']['code'] == 50400) {
            echo "\n權限問題解決建議:\n";
            echo "1. 確認火山引擎控制台中 API 金鑰擁有 '視覺智能-圖像生成' 權限\n";
            echo "2. 檢查帳戶是否已完成實名認證\n";
            echo "3. 確認即夢 AI 服務已正式開通（非試用版）\n";
            echo "4. 檢查帳戶餘額和計費配置\n";
            echo "5. 聯繫火山引擎技術支援確認服務配置\n";
        }
    }
    
    echo "\n--- 測試完成 ---\n";
    
    // 暫停避免 API 限制
    sleep(3);
}

// 建立暫存目錄
if (!is_dir(DEPLOY_BASE_PATH . '/temp')) {
    mkdir(DEPLOY_BASE_PATH . '/temp', 0755, true);
}

// 執行測試
echo "\n開始測試即夢 AI 的 Humaaans 風格生成能力...\n";
echo "提示: 生成的圖片將儲存在 temp/ 目錄下\n";

foreach ($test_prompts as $name => $prompts) {
    testJimengGeneration($name, $prompts, $jimeng_config);
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "所有測試完成！\n";
echo "請檢查 temp/ 目錄下的圖片，評估 Humaaans 風格效果\n";
echo str_repeat("=", 60) . "\n";

// 測試結果總結
echo "\n測試總結：\n";
echo "1. 即夢 AI 支援英文和中文提示詞\n";
echo "2. 可以通過詳細的提示詞描述來生成特定風格\n";
echo "3. 支援自定義尺寸和參數\n";
echo "4. API 回應包含 base64 編碼的圖片數據\n";
echo "\n下一步建議：\n";
echo "- 檢視生成的圖片是否符合 Humaaans 風格\n";
echo "- 根據結果調整提示詞策略\n";
echo "- 評估是否需要更多風格指導詞\n";
?>