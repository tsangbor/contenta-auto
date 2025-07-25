/**
 * 即夢 AI 簽名驗證 - JavaScript/Node.js 版本
 * 基於火山引擎官方簽名算法
 */

const crypto = require('crypto');
const https = require('https');
const fs = require('fs');
const path = require('path');

console.log('=== 即夢 AI 簽名驗證 (JavaScript版本) ===\n');

// 載入配置
function loadConfig() {
    try {
        const configPath = path.join(__dirname, 'config', 'deploy-config.json');
        const configData = fs.readFileSync(configPath, 'utf8');
        const config = JSON.parse(configData);
        return config.api_credentials?.jimeng || {};
    } catch (error) {
        console.error('無法載入配置檔案:', error.message);
        return {};
    }
}

const jimengCredentials = loadConfig();
const AccessKeyID = jimengCredentials.AccessKeyID || '';
const SecretAccessKey = jimengCredentials.SecretAccessKey || '';

console.log('金鑰信息:');
console.log(`AccessKeyID: ${AccessKeyID}`);
console.log(`SecretAccessKey: ${SecretAccessKey}`);
console.log(`AccessKeyID 長度: ${AccessKeyID.length}`);
console.log(`SecretAccessKey 長度: ${SecretAccessKey.length}\n`);

/**
 * 火山引擎官方簽名函數 - JavaScript 版本
 */
function requestWithDebug(method, query, headers, ak, sk, action, version, body) {
    console.log('=== 開始簽名生成過程 ===');
    
    // 基礎參數
    const Service = "cv";
    const Region = "cn-north-1";
    const Host = "visual.volcengineapi.com";
    const ContentType = "application/json";
    
    console.log('基礎參數:');
    console.log(`  Service: ${Service}`);
    console.log(`  Region: ${Region}`);
    console.log(`  Host: ${Host}`);
    console.log(`  ContentType: ${ContentType}\n`);
    
    const credential = {
        accessKeyId: ak,
        secretKeyId: sk,
        service: Service,
        region: Region
    };
    
    // 查詢參數處理
    query = Object.assign(query || {}, {
        Action: action,
        Version: version
    });
    
    // 對查詢參數排序
    const sortedQuery = Object.keys(query).sort().reduce((result, key) => {
        result[key] = query[key];
        return result;
    }, {});
    
    console.log('查詢參數 (排序後):');
    Object.entries(sortedQuery).forEach(([k, v]) => {
        console.log(`  ${k}: ${v}`);
    });
    console.log('');
    
    // 請求參數
    const now = new Date();
    const xDate = now.toISOString().replace(/[:\-]|\.\d{3}/g, '');
    const shortXDate = xDate.substr(0, 8);
    
    const requestParam = {
        body: body,
        host: Host,
        path: '/',
        method: method,
        contentType: ContentType,
        date: xDate,
        query: sortedQuery
    };
    
    console.log('請求參數:');
    console.log(`  method: ${requestParam.method}`);
    console.log(`  path: ${requestParam.path}`);
    console.log(`  host: ${requestParam.host}`);
    console.log(`  contentType: ${requestParam.contentType}`);
    console.log(`  date: ${requestParam.date}`);
    console.log(`  body 長度: ${requestParam.body.length}\n`);
    
    // 簽名基礎數據
    const xContentSha256 = crypto.createHash('sha256').update(requestParam.body).digest('hex');
    
    console.log('簽名基礎數據:');
    console.log(`  xDate: ${xDate}`);
    console.log(`  shortXDate: ${shortXDate}`);
    console.log(`  xContentSha256: ${xContentSha256}\n`);
    
    const signResult = {
        'Host': requestParam.host,
        'X-Content-Sha256': xContentSha256,
        'X-Date': xDate,
        'Content-Type': requestParam.contentType
    };
    
    // 構建 Canonical Request
    const signedHeaderStr = 'content-type;host;x-content-sha256;x-date';
    const queryString = new URLSearchParams(requestParam.query).toString();
    
    console.log('Canonical Request 組件:');
    console.log(`  signedHeaderStr: ${signedHeaderStr}`);
    console.log(`  queryString: ${queryString}\n`);
    
    const canonicalHeaders = [
        `content-type:${requestParam.contentType}`,
        `host:${requestParam.host}`,
        `x-content-sha256:${xContentSha256}`,
        `x-date:${xDate}`
    ].join('\n');
    
    console.log('Canonical Headers:');
    console.log(`${canonicalHeaders}\n`);
    
    const canonicalRequestStr = [
        requestParam.method,
        requestParam.path,
        queryString,
        canonicalHeaders,
        '',
        signedHeaderStr,
        xContentSha256
    ].join('\n');
    
    console.log('完整 Canonical Request:');
    console.log('---');
    console.log(canonicalRequestStr);
    console.log('---\n');
    
    const hashedCanonicalRequest = crypto.createHash('sha256').update(canonicalRequestStr).digest('hex');
    console.log(`Hashed Canonical Request: ${hashedCanonicalRequest}\n`);
    
    const credentialScope = [shortXDate, credential.region, credential.service, 'request'].join('/');
    console.log(`Credential Scope: ${credentialScope}\n`);
    
    const stringToSign = ['HMAC-SHA256', xDate, credentialScope, hashedCanonicalRequest].join('\n');
    console.log('String to Sign:');
    console.log('---');
    console.log(stringToSign);
    console.log('---\n');
    
    // 簽名計算步驟
    console.log('簽名計算步驟:');
    console.log(`  使用 secretKeyId: ${credential.secretKeyId.substr(0, 10)}...`);
    
    const kDate = crypto.createHmac('sha256', credential.secretKeyId).update(shortXDate).digest();
    console.log(`  kDate (hex): ${kDate.toString('hex')}`);
    
    const kRegion = crypto.createHmac('sha256', kDate).update(credential.region).digest();
    console.log(`  kRegion (hex): ${kRegion.toString('hex')}`);
    
    const kService = crypto.createHmac('sha256', kRegion).update(credential.service).digest();
    console.log(`  kService (hex): ${kService.toString('hex')}`);
    
    const kSigning = crypto.createHmac('sha256', kService).update('request').digest();
    console.log(`  kSigning (hex): ${kSigning.toString('hex')}`);
    
    const signature = crypto.createHmac('sha256', kSigning).update(stringToSign).digest('hex');
    console.log(`  最終簽名: ${signature}\n`);
    
    signResult['Authorization'] = `HMAC-SHA256 Credential=${credential.accessKeyId}/${credentialScope}, SignedHeaders=${signedHeaderStr}, Signature=${signature}`;
    
    console.log('Authorization Header:');
    console.log(`${signResult['Authorization']}\n`);
    
    const allHeaders = Object.assign({}, headers || {}, signResult);
    
    // 發送請求
    const fullUrl = `https://${requestParam.host}${requestParam.path}?${queryString}`;
    console.log(`請求 URL: ${fullUrl}`);
    
    console.log('Headers:');
    Object.entries(allHeaders).forEach(([key, value]) => {
        console.log(`Header: ${key}: ${value}`);
    });
    console.log('');
    
    return new Promise((resolve, reject) => {
        const options = {
            hostname: requestParam.host,
            path: `${requestParam.path}?${queryString}`,
            method: requestParam.method,
            headers: allHeaders,
            timeout: 10000
        };
        
        const req = https.request(options, (res) => {
            let data = '';
            
            res.on('data', (chunk) => {
                data += chunk;
            });
            
            res.on('end', () => {
                console.log('回應結果:');
                console.log(`HTTP Code: ${res.statusCode}`);
                console.log(`Response: ${data.substr(0, 500)}`);
                
                resolve({
                    http_code: res.statusCode,
                    response: data
                });
            });
        });
        
        req.on('error', (error) => {
            console.error('請求錯誤:', error.message);
            reject(error);
        });
        
        req.on('timeout', () => {
            console.error('請求超時');
            req.destroy();
            reject(new Error('Request timeout'));
        });
        
        // 發送請求體
        req.write(requestParam.body);
        req.end();
    });
}

// 即夢 AI 客戶端類
class JimengAIClient {
    constructor(accessKey, secretKey) {
        this.accessKey = accessKey;
        this.secretKey = secretKey;
        this.baseUrl = 'https://visual.volcengineapi.com';
        this.service = 'cv';
        this.region = 'cn-north-1';
        this.version = '2022-08-31';
    }
    
    /**
     * 生成 Humaaans 風格圖片
     */
    async generateHumaaans(scene, options = {}) {
        const defaultOptions = {
            width: 1328,
            height: 1328,
            style: 'humaaans',
            language: 'en'
        };
        
        const opts = Object.assign(defaultOptions, options);
        
        // 構建 Humaaans 風格提示詞
        const prompt = this.buildHumaansPrompt(scene, opts);
        
        return await this.generateImage(prompt, opts.width, opts.height);
    }
    
    /**
     * 構建 Humaaans 風格提示詞
     */
    buildHumaansPrompt(scene, options) {
        const colorPalette = "deep blue (#2563EB), light blue (#38BDF8), subtle dark gray (#0F172A) accents";
        
        if (options.language === 'zh') {
            const basePrompt = "扁平插畫風格，極簡人物角色";
            const styleElements = "簡單幾何形狀，乾淨線條，向量藝術美學";
            const background = "抽象幾何背景與重疊形狀";
            const colors = "深藍色(#2563EB)、淺藍色(#38BDF8)、深灰色(#0F172A)點綴";
            const ending = "充足留白空間。純視覺圖像，無文字";
            
            return `${basePrompt}在${scene}。${styleElements}。友善的幾何特徵角色。${background}。色彩配置：${colors}。${ending}。`;
        } else {
            const basePrompt = "A flat illustration in the style of humaaans";
            const styleElements = "Minimalist character design with simple geometric features, clean lines, vector art aesthetic";
            const background = "Abstract geometric composition background with overlapping shapes";
            const ending = "Plenty of negative space for text overlay. Purely visual imagery, no text, no words, no letters";
            
            return `${basePrompt} depicting ${scene}. ${styleElements}. Characters with friendly geometric features. ${background}. Color palette: ${colorPalette}. ${ending}.`;
        }
    }
    
    /**
     * 生成圖片
     */
    async generateImage(prompt, width = 1328, height = 1328, reqKey = 'high_aes_general_v30l_zt2i') {
        const requestBody = {
            req_key: reqKey,
            prompt: prompt,
            seed: -1,
            scale: 2.5,
            width: width,
            height: height,
            return_url: true,
            logo_info: {
                add_logo: false,
                position: 0,
                language: 0,
                opacity: 0.3
            }
        };
        
        const body = JSON.stringify(requestBody);
        
        console.log(`開始生成圖片...`);
        console.log(`提示詞: ${prompt.substr(0, 100)}...`);
        console.log(`請求體長度: ${body.length} 字元\n`);
        
        const result = await requestWithDebug(
            'POST',
            {},
            {},
            this.accessKey,
            this.secretKey,
            'CVProcess',
            this.version,
            body
        );
        
        const responseData = JSON.parse(result.response);
        
        return {
            success: result.http_code === 200,
            http_code: result.http_code,
            data: responseData.data || null,
            error: responseData.message || null,
            images: responseData.data?.binary_data_base64 || [],
            request_id: responseData.request_id || null
        };
    }
    
    /**
     * 儲存圖片
     */
    saveImage(base64Data, filename) {
        try {
            const imageBuffer = Buffer.from(base64Data, 'base64');
            fs.writeFileSync(filename, imageBuffer);
            return true;
        } catch (error) {
            console.error('儲存圖片失敗:', error.message);
            return false;
        }
    }
}

// 主要測試函數
async function main() {
    if (!AccessKeyID || !SecretAccessKey) {
        console.error('錯誤: 請在 config/deploy-config.json 中配置 jimeng API 金鑰');
        return;
    }
    
    const client = new JimengAIClient(AccessKeyID, SecretAccessKey);
    
    // 測試場景
    const testScenes = {
        'homepage_hero': 'Professional team collaboration in modern office',
        'about_team': 'Diverse business team working together',
        'service_illustration': 'Modern technology and innovation concept'
    };
    
    console.log('\n開始測試即夢 AI 的 Humaaans 風格生成...\n');
    
    try {
        for (const [sceneName, sceneDesc] of Object.entries(testScenes)) {
            console.log(`\n${'='.repeat(60)}`);
            console.log(`測試場景: ${sceneName}`);
            console.log(`${'='.repeat(60)}`);
            
            // 測試英文風格
            console.log('\n1. 測試英文 Humaaans 風格提示詞');
            const result = await client.generateHumaaans(sceneDesc, {
                language: 'en',
                width: 1328,
                height: 1328
            });
            
            if (result.success && result.images.length > 0) {
                console.log(`✅ 生成成功！共生成 ${result.images.length} 張圖片`);
                
                // 建立目錄
                const tempDir = path.join(__dirname, 'temp');
                if (!fs.existsSync(tempDir)) {
                    fs.mkdirSync(tempDir, { recursive: true });
                }
                
                // 儲存第一張圖片
                const filename = `jimeng_humaaans_js_${sceneName}_${Date.now()}.jpg`;
                const filepath = path.join(tempDir, filename);
                
                if (client.saveImage(result.images[0], filepath)) {
                    console.log(`圖片已儲存: temp/${filename}`);
                }
            } else {
                console.log(`❌ 生成失敗: ${result.error || '未知錯誤'}`);
                console.log(`HTTP 狀態: ${result.http_code}`);
            }
            
            // 暫停避免 API 限制
            await new Promise(resolve => setTimeout(resolve, 3000));
        }
        
    } catch (error) {
        console.error('測試過程中發生錯誤:', error.message);
    }
    
    console.log('\n' + '='.repeat(60));
    console.log('所有測試完成！');
    console.log('如果生成成功，請檢查 temp/ 目錄下的圖片');
    console.log('='.repeat(60));
}

// 執行測試
main().catch(console.error);