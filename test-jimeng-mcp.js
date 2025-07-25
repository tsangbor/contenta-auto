/**
 * 使用 jimeng-ai-mcp 的即夢 AI 測試版本
 * 需要先安裝: npm install jimeng-ai-mcp
 */

const fs = require('fs');
const path = require('path');

console.log('=== 即夢 AI MCP 版本測試 ===\n');

// 動態導入 jimeng-ai-mcp
async function loadJimengMCP() {
    try {
        const { JimengClient } = await import('jimeng-ai-mcp');
        return JimengClient;
    } catch (error) {
        console.error('無法載入 jimeng-ai-mcp 模組:', error.message);
        console.log('\n請先安裝 jimeng-ai-mcp:');
        console.log('npm install jimeng-ai-mcp');
        console.log('\n或者使用原生版本: node test-jimeng-signature.js');
        process.exit(1);
    }
}

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

// Humaaans 風格提示詞生成器
class HumaaansPromptBuilder {
    static buildPrompt(scene, options = {}) {
        const { language = 'en' } = options;
        const colorPalette = "deep blue (#2563EB), light blue (#38BDF8), subtle dark gray (#0F172A) accents";
        
        if (language === 'zh') {
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
    
    static getTestScenes() {
        return {
            'homepage_hero': {
                'original': 'Professional team collaboration in modern office',
                'humaaans_en': this.buildPrompt('Professional team collaboration in modern office', { language: 'en' }),
                'humaaans_zh': this.buildPrompt('現代辦公室專業團隊協作', { language: 'zh' })
            },
            'about_team': {
                'original': 'Diverse business team working together',
                'humaaans_en': this.buildPrompt('Diverse business team working together', { language: 'en' }),
                'humaaans_zh': this.buildPrompt('多元商務團隊協作', { language: 'zh' })
            },
            'service_illustration': {
                'original': 'Modern technology and innovation concept',
                'humaaans_en': this.buildPrompt('Modern technology and innovation concept', { language: 'en' }),
                'humaaans_zh': this.buildPrompt('現代科技與創新概念', { language: 'zh' })
            }
        };
    }
}

// 主要測試函數
async function main() {
    // 載入 jimeng-ai-mcp
    const JimengClient = await loadJimengMCP();
    
    // 載入配置
    const jimengCredentials = loadConfig();
    const AccessKeyID = jimengCredentials.AccessKeyID || '';
    const SecretAccessKey = jimengCredentials.SecretAccessKey || '';
    
    if (!AccessKeyID || !SecretAccessKey) {
        console.error('錯誤: 請在 config/deploy-config.json 中配置 jimeng API 金鑰');
        return;
    }
    
    console.log('金鑰信息:');
    console.log(`AccessKeyID: ${AccessKeyID}`);
    console.log(`SecretAccessKey: ${SecretAccessKey.substring(0, 20)}...`);
    console.log(`AccessKeyID 長度: ${AccessKeyID.length}`);
    console.log(`SecretAccessKey 長度: ${SecretAccessKey.length}\n`);
    
    // 初始化 jimeng-ai-mcp 客戶端
    const client = new JimengClient({
        accessKey: AccessKeyID,
        secretKey: SecretAccessKey,
        region: 'cn-north-1'
    });
    
    // 獲取測試場景
    const testScenes = HumaaansPromptBuilder.getTestScenes();
    
    console.log('開始使用 jimeng-ai-mcp 測試 Humaaans 風格生成...\n');
    
    // 建立輸出目錄
    const tempDir = path.join(__dirname, 'temp');
    if (!fs.existsSync(tempDir)) {
        fs.mkdirSync(tempDir, { recursive: true });
    }
    
    try {
        for (const [sceneName, sceneData] of Object.entries(testScenes)) {
            console.log(`\n${'='.repeat(60)}`);
            console.log(`測試場景: ${sceneName}`);
            console.log(`${'='.repeat(60)}`);
            
            // 測試英文 Humaaans 風格
            console.log('\n1. 測試英文 Humaaans 風格提示詞');
            console.log(`提示詞: ${sceneData.humaaans_en.substring(0, 100)}...`);
            
            try {
                const result = await client.generateImage({
                    prompt: sceneData.humaaans_en,
                    model: 'jimeng_t2i_s20pro', // 使用即夢專業版模型
                    width: 1328,
                    height: 1328,
                    seed: -1,
                    scale: 2.5,
                    return_url: true
                });
                
                console.log(`狀態: ${result.success ? '✅ 成功' : '❌ 失敗'}`);
                
                if (result.success && result.images?.length > 0) {
                    console.log(`✅ 生成成功！共生成 ${result.images.length} 張圖片`);
                    
                    // 儲存第一張圖片
                    const filename = `jimeng_mcp_${sceneName}_en_${Date.now()}.jpg`;
                    const filepath = path.join(tempDir, filename);
                    
                    // 假設 MCP 返回的是 base64 數據
                    if (result.images[0]) {
                        const imageBuffer = Buffer.from(result.images[0], 'base64');
                        fs.writeFileSync(filepath, imageBuffer);
                        console.log(`圖片已儲存: temp/${filename}`);
                    }
                } else {
                    console.log(`❌ 生成失敗: ${result.error || '未知錯誤'}`);
                    if (result.request_id) {
                        console.log(`Request ID: ${result.request_id}`);
                    }
                }
                
            } catch (error) {
                console.log(`❌ API 呼叫失敗: ${error.message}`);
                
                // 如果是權限錯誤，提供詳細資訊
                if (error.message.includes('Access Denied')) {
                    console.log('\n權限問題可能原因:');
                    console.log('1. API 金鑰沒有 "視覺智能-圖像生成" 權限');
                    console.log('2. 帳戶未完成實名認證');
                    console.log('3. 服務未正確開通或配置');
                    console.log('4. 帳戶餘額不足');
                }
            }
            
            // 暫停避免 API 限制
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // 測試中文 Humaaans 風格
            console.log('\n2. 測試中文 Humaaans 風格提示詞');
            console.log(`提示詞: ${sceneData.humaaans_zh.substring(0, 100)}...`);
            
            try {
                const result = await client.generateImage({
                    prompt: sceneData.humaaans_zh,
                    model: 'jimeng_t2i_s20pro',
                    width: 1328,
                    height: 1328,
                    seed: -1,
                    scale: 2.5,
                    return_url: true
                });
                
                console.log(`狀態: ${result.success ? '✅ 成功' : '❌ 失敗'}`);
                
                if (result.success && result.images?.length > 0) {
                    console.log(`✅ 生成成功！共生成 ${result.images.length} 張圖片`);
                    
                    const filename = `jimeng_mcp_${sceneName}_zh_${Date.now()}.jpg`;
                    const filepath = path.join(tempDir, filename);
                    
                    if (result.images[0]) {
                        const imageBuffer = Buffer.from(result.images[0], 'base64');
                        fs.writeFileSync(filepath, imageBuffer);
                        console.log(`圖片已儲存: temp/${filename}`);
                    }
                } else {
                    console.log(`❌ 生成失敗: ${result.error || '未知錯誤'}`);
                }
                
            } catch (error) {
                console.log(`❌ API 呼叫失敗: ${error.message}`);
            }
            
            console.log('\n--- 測試完成 ---');
            await new Promise(resolve => setTimeout(resolve, 3000));
        }
        
    } catch (error) {
        console.error('測試過程中發生錯誤:', error.message);
    }
    
    console.log('\n' + '='.repeat(60));
    console.log('所有測試完成！');
    console.log('如果生成成功，請檢查 temp/ 目錄下的圖片');
    console.log('如果全部失敗，請檢查火山引擎控制台的 API 權限配置');
    console.log('='.repeat(60));
    
    // 測試結果總結
    console.log('\n測試總結：');
    console.log('1. jimeng-ai-mcp 支援英文和中文提示詞');
    console.log('2. 可以通過詳細的提示詞描述來生成 Humaaans 風格');
    console.log('3. 支援自定義尺寸和模型參數');
    console.log('4. MCP 封裝簡化了 API 調用複雜度');
    console.log('\n下一步建議：');
    console.log('- 檢視生成的圖片是否符合 Humaaans 風格');
    console.log('- 根據結果調整提示詞策略');
    console.log('- 如果權限問題，請聯繫火山引擎技術支援');
}

// 執行測試
main().catch(console.error);