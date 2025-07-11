#!/usr/bin/env node

/**
 * BT Panel API 測試工具 (Node.js 版本)
 * 測試 POST https://jp3.contenta.tw:8888/panel/public/get_public_config
 */

const fs = require('fs');
const https = require('https');
const { URL } = require('url');

console.log('🧪 BT Panel API 測試工具');
console.log('========================\n');

// 讀取配置
const configPath = 'config/deploy-config.json';
if (!fs.existsSync(configPath)) {
    console.log('❌ 設定檔不存在:', configPath);
    process.exit(1);
}

let config;
try {
    config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
} catch (error) {
    console.log('❌ 設定檔格式錯誤:', error.message);
    process.exit(1);
}

const btcnConfig = config.api_credentials?.btcn;
if (!btcnConfig) {
    console.log('❌ BT Panel 設定不存在');
    process.exit(1);
}

// 取得認證資訊
const panelUrl = btcnConfig.panel_url || '';
const sessionCookie = btcnConfig.session_cookie || '';
const httpToken = btcnConfig.http_token || '';

console.log('📋 配置資訊:');
console.log('Panel URL:', panelUrl);
console.log('Cookie:', sessionCookie ? `已設定 (${sessionCookie.substring(0, 50)}...)` : '未設定');
console.log('Token:', httpToken ? `已設定 (${httpToken.substring(0, 20)}...)` : '未設定');
console.log();

if (!sessionCookie || !httpToken) {
    console.log('❌ 認證資訊不完整，請先執行認證更新');
    console.log('建議執行: node auth-updater.js');
    process.exit(1);
}

/**
 * 執行 BT Panel API 請求
 */
function testBTAPI(url, cookie, token, data = {}, method = 'POST') {
    return new Promise((resolve, reject) => {
        console.log(`🌐 測試 API: ${url}`);
        console.log(`方法: ${method}`);
        
        const urlObj = new URL(url);
        const postData = method === 'POST' ? new URLSearchParams(data).toString() : '';
        
        const options = {
            hostname: urlObj.hostname,
            port: urlObj.port || 443,
            path: urlObj.pathname + urlObj.search,
            method: method,
            headers: {
                'Cookie': cookie,
                'X-HTTP-Token': token,
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept': 'application/json, text/javascript, */*; q=0.01',
                'Accept-Language': 'zh-TW,zh;q=0.9,en;q=0.8',
                'Accept-Encoding': 'gzip, deflate, br',
                'Connection': 'keep-alive',
                'Sec-Fetch-Dest': 'empty',
                'Sec-Fetch-Mode': 'cors',
                'Sec-Fetch-Site': 'same-origin'
            },
            rejectUnauthorized: false // 忽略 SSL 憑證錯誤
        };
        
        if (method === 'POST' && postData) {
            options.headers['Content-Length'] = Buffer.byteLength(postData);
            console.log('POST 資料:', postData);
        }
        
        console.log('請求標頭:');
        Object.entries(options.headers).forEach(([key, value]) => {
            if (key === 'Cookie') {
                console.log(`  ${key}: ${value.substring(0, 50)}...`);
            } else if (key === 'X-HTTP-Token') {
                console.log(`  ${key}: ${value.substring(0, 20)}...`);
            } else {
                console.log(`  ${key}: ${value}`);
            }
        });
        
        const req = https.request(options, (res) => {
            console.log(`HTTP 狀態碼: ${res.statusCode}`);
            console.log(`Content-Type: ${res.headers['content-type']}`);
            
            let data = '';
            
            // 處理 gzip 編碼
            let stream = res;
            if (res.headers['content-encoding'] === 'gzip') {
                const zlib = require('zlib');
                stream = zlib.createGunzip();
                res.pipe(stream);
            }
            
            stream.on('data', (chunk) => {
                data += chunk;
            });
            
            stream.on('end', () => {
                resolve({
                    statusCode: res.statusCode,
                    headers: res.headers,
                    data: data
                });
            });
        });
        
        req.on('error', (error) => {
            console.log('❌ 請求錯誤:', error.message);
            reject(error);
        });
        
        req.on('timeout', () => {
            console.log('❌ 請求超時');
            req.destroy();
            reject(new Error('Request timeout'));
        });
        
        req.setTimeout(30000); // 30 秒超時
        
        if (method === 'POST' && postData) {
            req.write(postData);
        }
        
        req.end();
    });
}

/**
 * 分析 JSON 回應
 */
function analyzeResponse(response) {
    console.log('\n📊 測試結果:');
    console.log('HTTP 狀態碼:', response.statusCode);
    console.log('Content-Type:', response.headers['content-type']);
    console.log('回應內容:');
    
    try {
        const jsonData = JSON.parse(response.data);
        console.log('✅ JSON 格式正確');
        console.log(JSON.stringify(jsonData, null, 2));
        
        console.log('\n🔍 回應分析:');
        console.log('資料類型:', typeof jsonData);
        
        if (typeof jsonData === 'object' && jsonData !== null) {
            console.log('欄位數量:', Object.keys(jsonData).length);
            console.log('主要欄位:', Object.keys(jsonData).join(', '));
            
            // 檢查常見的狀態欄位
            if ('status' in jsonData) {
                console.log('狀態:', jsonData.status ? '成功' : '失敗');
            }
            if ('msg' in jsonData) {
                console.log('訊息:', jsonData.msg);
            }
            if ('data' in jsonData) {
                console.log('資料:', Array.isArray(jsonData.data) ? `${jsonData.data.length} 項目` : typeof jsonData.data);
            }
        }
        
        return jsonData;
    } catch (error) {
        console.log('⚠️ 非 JSON 格式或格式錯誤');
        console.log('原始回應:');
        console.log(response.data);
        return null;
    }
}

/**
 * 執行測試
 */
async function runTests() {
    console.log('🚀 開始 API 測試');
    console.log('================\n');
    
    try {
        // 測試 1: get_public_config
        console.log('測試 1: panel/public/get_public_config');
        console.log('--------------------------------------');
        
        const testUrl = `${panelUrl}/panel/public/get_public_config`;
        const result = await testBTAPI(testUrl, sessionCookie, httpToken);
        
        if (result.statusCode === 200) {
            const jsonData = analyzeResponse(result);
            
            if (jsonData) {
                console.log('\n✅ API 測試成功');
            }
        } else {
            console.log(`❌ API 測試失敗 - HTTP ${result.statusCode}`);
        }
        
        console.log('\n');
        
        // 測試 2: 其他 API 端點
        console.log('測試 2: 其他 API 端點');
        console.log('--------------------');
        
        const additionalTests = [
            {
                name: '系統資訊',
                endpoint: '/system?action=GetNetWork',
                data: {}
            },
            {
                name: '面板狀態',
                endpoint: '/ajax?action=get_load_average',
                data: {}
            }
        ];
        
        for (const test of additionalTests) {
            console.log(`\n測試: ${test.name}`);
            const testUrl = `${panelUrl}${test.endpoint}`;
            
            try {
                const result = await testBTAPI(testUrl, sessionCookie, httpToken, test.data);
                
                if (result.statusCode === 200) {
                    console.log(`✅ ${test.name} - HTTP ${result.statusCode}`);
                    
                    try {
                        const jsonData = JSON.parse(result.data);
                        const preview = JSON.stringify(jsonData).substring(0, 200);
                        console.log(`📄 回應預覽: ${preview}...`);
                    } catch (e) {
                        console.log('📄 回應:', result.data.substring(0, 200) + '...');
                    }
                } else {
                    console.log(`❌ ${test.name} - HTTP ${result.statusCode}`);
                }
            } catch (error) {
                console.log(`❌ ${test.name} - 錯誤: ${error.message}`);
            }
        }
        
    } catch (error) {
        console.log('❌ 測試過程中發生錯誤:', error.message);
    }
    
    console.log('\n🎯 測試總結');
    console.log('==========');
    console.log('✅ BT Panel API 測試完成');
    console.log('📋 認證狀態:', (!sessionCookie || !httpToken) ? '需要更新' : '正常');
    console.log('🌐 面板地址:', panelUrl);
    console.log('⏰ 測試時間:', new Date().toISOString());
    console.log('\n💡 建議:');
    console.log('1. 如果 API 返回認證錯誤，請執行: node auth-updater.js');
    console.log('2. 檢查防火牆是否允許連接到 BT Panel');
    console.log('3. 確認 BT Panel 服務正常運行');
}

// 執行測試
runTests().catch(console.error);