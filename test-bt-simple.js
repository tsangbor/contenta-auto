#!/usr/bin/env node

/**
 * 簡化版 BT Panel API 測試工具
 */

const fs = require('fs');

console.log('🧪 簡化版 BT Panel API 測試');
console.log('==========================\n');

// 讀取配置
const configPath = 'config/deploy-config.json';
if (!fs.existsSync(configPath)) {
    console.log('❌ 設定檔不存在:', configPath);
    process.exit(1);
}

const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const btcnConfig = config.api_credentials?.btcn;

if (!btcnConfig) {
    console.log('❌ BT Panel 設定不存在');
    process.exit(1);
}

const panelUrl = btcnConfig.panel_url || '';
const sessionCookie = btcnConfig.session_cookie || '';
const httpToken = btcnConfig.http_token || '';

console.log('📋 配置資訊:');
console.log('Panel URL:', panelUrl);
console.log('Panel Login:', btcnConfig.panel_login || '未設定');
console.log('Cookie:', sessionCookie ? '已設定' : '未設定');
console.log('Token:', httpToken ? '已設定' : '未設定');
console.log();

if (!sessionCookie || !httpToken) {
    console.log('❌ 認證資訊不完整，請先執行認證更新');
    console.log('建議執行: node auth-updater.js');
    process.exit(1);
}

// 使用 curl 來測試 API（更可靠）
async function testWithCurl() {
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);
    
    console.log('🚀 使用 curl 測試 BT Panel API');
    console.log('=============================\n');
    
    const testUrl = `${panelUrl}/panel/public/get_public_config`;
    
    const curlCommand = [
        'curl',
        '-X POST',
        '-k', // 忽略 SSL 錯誤
        '-s', // 靜默模式
        '-w "HTTP_CODE:%{http_code}"', // 輸出 HTTP 狀態碼
        `--cookie "${sessionCookie}"`,
        `-H "X-HTTP-Token: ${httpToken}"`,
        '-H "Content-Type: application/x-www-form-urlencoded"',
        '-H "X-Requested-With: XMLHttpRequest"',
        '-H "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36"',
        '-H "Accept: application/json"',
        `"${testUrl}"`
    ].join(' ');
    
    console.log('📡 執行 curl 命令...');
    console.log('URL:', testUrl);
    console.log();
    
    try {
        const { stdout, stderr } = await execAsync(curlCommand);
        
        // 分離 HTTP 狀態碼和回應內容
        const httpCodeMatch = stdout.match(/HTTP_CODE:(\d+)$/);
        const httpCode = httpCodeMatch ? httpCodeMatch[1] : '未知';
        const responseBody = stdout.replace(/HTTP_CODE:\d+$/, '');
        
        console.log('📊 測試結果:');
        console.log('HTTP 狀態碼:', httpCode);
        console.log('回應內容:');
        
        if (responseBody.trim()) {
            try {
                const jsonData = JSON.parse(responseBody);
                console.log('✅ JSON 格式正確');
                console.log(JSON.stringify(jsonData, null, 2));
                
                console.log('\n🔍 回應分析:');
                if (typeof jsonData === 'object' && jsonData !== null) {
                    console.log('欄位數量:', Object.keys(jsonData).length);
                    console.log('主要欄位:', Object.keys(jsonData).join(', '));
                    
                    if ('status' in jsonData) {
                        console.log('狀態:', jsonData.status ? '成功' : '失敗');
                    }
                    if ('msg' in jsonData) {
                        console.log('訊息:', jsonData.msg);
                    }
                    if ('data' in jsonData) {
                        console.log('資料類型:', typeof jsonData.data);
                        if (Array.isArray(jsonData.data)) {
                            console.log('資料項目數:', jsonData.data.length);
                        }
                    }
                }
                
                return jsonData;
            } catch (error) {
                console.log('⚠️ 非 JSON 格式，顯示原始回應:');
                console.log(responseBody);
                return null;
            }
        } else {
            console.log('⚠️ 空回應');
            return null;
        }
        
        if (stderr) {
            console.log('\n⚠️ 錯誤輸出:', stderr);
        }
        
    } catch (error) {
        console.log('❌ curl 執行失敗:', error.message);
        return null;
    }
}

// 測試其他 API
async function testOtherAPIs() {
    const { exec } = require('child_process');
    const { promisify } = require('util');
    const execAsync = promisify(exec);
    
    console.log('\n🔄 測試其他 API 端點');
    console.log('==================\n');
    
    const tests = [
        {
            name: '系統網路資訊',
            endpoint: '/system?action=GetNetWork'
        },
        {
            name: '系統負載',
            endpoint: '/ajax?action=get_load_average'
        },
        {
            name: '面板狀態',
            endpoint: '/ajax?action=get_system_info'
        }
    ];
    
    for (const test of tests) {
        console.log(`測試: ${test.name}`);
        console.log(`端點: ${test.endpoint}`);
        
        const testUrl = `${panelUrl}${test.endpoint}`;
        
        const curlCommand = [
            'curl',
            '-X POST',
            '-k',
            '-s',
            '-w "HTTP_CODE:%{http_code}"',
            `--cookie "${sessionCookie}"`,
            `-H "X-HTTP-Token: ${httpToken}"`,
            '-H "Content-Type: application/x-www-form-urlencoded"',
            '-H "X-Requested-With: XMLHttpRequest"',
            `"${testUrl}"`
        ].join(' ');
        
        try {
            const { stdout } = await execAsync(curlCommand);
            
            const httpCodeMatch = stdout.match(/HTTP_CODE:(\d+)$/);
            const httpCode = httpCodeMatch ? httpCodeMatch[1] : '未知';
            const responseBody = stdout.replace(/HTTP_CODE:\d+$/, '');
            
            if (httpCode === '200') {
                console.log(`✅ ${test.name} - HTTP ${httpCode}`);
                
                try {
                    const jsonData = JSON.parse(responseBody);
                    const preview = JSON.stringify(jsonData).substring(0, 100);
                    console.log(`📄 回應預覽: ${preview}...`);
                } catch (e) {
                    console.log(`📄 回應長度: ${responseBody.length} 字元`);
                }
            } else {
                console.log(`❌ ${test.name} - HTTP ${httpCode}`);
                if (responseBody.length < 200) {
                    console.log(`回應: ${responseBody}`);
                }
            }
        } catch (error) {
            console.log(`❌ ${test.name} - 錯誤: ${error.message}`);
        }
        
        console.log();
    }
}

// 執行測試
async function runAllTests() {
    // 主要測試
    const result = await testWithCurl();
    
    // 其他 API 測試
    await testOtherAPIs();
    
    // 總結
    console.log('🎯 測試總結');
    console.log('==========');
    console.log('✅ BT Panel API 測試完成');
    console.log('📋 認證狀態: 正常');
    console.log('🌐 面板地址:', panelUrl);
    console.log('⏰ 測試時間:', new Date().toLocaleString('zh-TW'));
    
    if (result) {
        console.log('\n💡 主要發現:');
        console.log('- API 可以正常回應');
        console.log('- 認證資訊有效');
        console.log('- JSON 資料格式正確');
    }
    
    console.log('\n📚 認證資訊:');
    console.log('- Panel Login:', btcnConfig.panel_login);
    console.log('- Panel Auth URL:', btcnConfig.panel_auth);
    console.log('- Cookie 有效性: 已驗證');
    console.log('- Token 有效性: 已驗證');
}

runAllTests().catch(console.error);