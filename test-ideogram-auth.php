<?php
/**
 * 測試 Ideogram API 認證
 */

// 載入配置
$config_file = __DIR__ . '/config/deploy-config.json';
$config = json_decode(file_get_contents($config_file), true);
$api_key = $config['api_credentials']['ideogram']['api_key'] ?? '';

if (empty($api_key)) {
    die("錯誤: Ideogram API 金鑰未設定\n");
}

echo "API 金鑰長度: " . strlen($api_key) . "\n";
echo "API 金鑰前綴: " . substr($api_key, 0, 20) . "...\n";
echo "API 金鑰後綴: ..." . substr($api_key, -10) . "\n\n";

// 測試 API 請求
$url = 'https://api.ideogram.ai/v1/ideogram-v3/generate';
$prompt = 'A simple test image';

// 準備 multipart form data
$boundary = uniqid();
$delimiter = '-------------' . $boundary;

$post_data = '';

// Add prompt
$post_data .= "--" . $delimiter . "\r\n";
$post_data .= 'Content-Disposition: form-data; name="prompt"' . "\r\n\r\n";
$post_data .= $prompt . "\r\n";

// Add aspect_ratio
$post_data .= "--" . $delimiter . "\r\n";
$post_data .= 'Content-Disposition: form-data; name="aspect_ratio"' . "\r\n\r\n";
$post_data .= '1x1' . "\r\n";

// Close the boundary
$post_data .= "--" . $delimiter . "--\r\n";

echo "發送 API 請求到: $url\n";
echo "請求大小: " . strlen($post_data) . " bytes\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Api-Key: ' . $api_key,
    'Content-Type: multipart/form-data; boundary=' . $delimiter,
    'Content-Length: ' . strlen($post_data)
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP 狀態碼: $http_code\n";

if ($curl_error) {
    echo "CURL 錯誤: $curl_error\n";
}

echo "API 回應: " . substr($response, 0, 500) . "\n";

if ($http_code === 200) {
    echo "\n✅ API 認證成功!\n";
} else {
    echo "\n❌ API 認證失敗!\n";
    
    // 分析錯誤
    $response_data = json_decode($response, true);
    if ($response_data && isset($response_data['detail'])) {
        echo "錯誤詳情: " . $response_data['detail'] . "\n";
    }
}