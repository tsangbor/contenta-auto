const { chromium } = require('playwright');

async function getHttpToken() {
    console.log('🔐 取得 X-HTTP-Token...');
    
    const browser = await chromium.launch({
        headless: false,
        ignoreHTTPSErrors: true
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    // 設定 cookie
    await context.addCookies([{
        name: 'cbffa9cfef96703cf3e4e7c627e7c1e5_ssl',
        value: '2cd23b77-d9b7-4421-b04c-25168a43ed2a.cl7r_9PKmnStRFMb2Tws57_6w74',
        domain: 'jp3.contenta.tw',
        path: '/'
    }]);
    
    let foundToken = null;
    
    // 監聽網路請求
    page.on('request', request => {
        const headers = request.headers();
        console.log('📡 請求 URL:', request.url());
        
        for (const [key, value] of Object.entries(headers)) {
            if (key.toLowerCase().includes('token') || 
                key.toLowerCase() === 'x-http-token' ||
                key.toLowerCase() === 'x-csrf-token') {
                console.log(`✅ 找到 token: ${key}: ${value}`);
                foundToken = { key, value };
            }
        }
    });
    
    try {
        console.log('📍 前往主頁面...');
        await page.goto('https://jp3.contenta.tw:8888/home');
        await page.waitForTimeout(3000);
        
        console.log('🔄 嘗試觸發 AJAX 請求...');
        
        // 點擊不同的功能來觸發 AJAX
        const clickTargets = [
            'a[href*="site"]',
            'a[href*="file"]',
            'a[href*="database"]',
            '.nav-link',
            '.menu-item',
            'button',
            'a'
        ];
        
        for (const selector of clickTargets) {
            try {
                const elements = await page.$$(selector);
                if (elements.length > 0) {
                    console.log(`🖱️  點擊 ${selector}...`);
                    await elements[0].click();
                    await page.waitForTimeout(2000);
                    
                    if (foundToken) {
                        console.log(`✅ 在點擊 ${selector} 後找到 token！`);
                        break;
                    }
                }
            } catch (e) {
                console.log(`⚠️  無法點擊 ${selector}`);
            }
        }
        
        // 執行 JavaScript 來搜尋 token
        const pageTokens = await page.evaluate(() => {
            const results = [];
            
            // 搜尋所有可能的 token 變數
            const tokenKeys = ['http_token', 'csrf_token', 'request_token', 'bt_token', 'panel_token'];
            
            for (const key of tokenKeys) {
                if (window[key]) {
                    results.push({ source: 'window', key, value: window[key] });
                }
            }
            
            // 搜尋 meta 標籤
            const metaTags = document.querySelectorAll('meta[name*="token"], meta[name*="csrf"]');
            metaTags.forEach(meta => {
                results.push({
                    source: 'meta',
                    key: meta.getAttribute('name'),
                    value: meta.getAttribute('content')
                });
            });
            
            // 搜尋 script 標籤中的 token
            const scripts = document.querySelectorAll('script');
            scripts.forEach((script, index) => {
                const content = script.textContent || script.innerHTML;
                const matches = content.match(/(?:http_token|csrf_token|request_token|token)['"\s]*[:=]['"\s]*([a-zA-Z0-9\-_]+)/gi);
                if (matches) {
                    matches.forEach(match => {
                        results.push({
                            source: `script_${index}`,
                            key: 'found_pattern',
                            value: match
                        });
                    });
                }
            });
            
            return results;
        });
        
        console.log('\n🔍 從頁面找到的 tokens:');
        pageTokens.forEach(token => {
            console.log(`  ${token.source}: ${token.key} = ${token.value}`);
        });
        
        // 保存結果
        const result = {
            tokenFromRequest: foundToken,
            tokensFromPage: pageTokens,
            sessionCookie: 'cbffa9cfef96703cf3e4e7c627e7c1e5_ssl=2cd23b77-d9b7-4421-b04c-25168a43ed2a.cl7r_9PKmnStRFMb2Tws57_6w74',
            timestamp: new Date().toISOString()
        };
        
        require('fs').writeFileSync('http-token-result.json', JSON.stringify(result, null, 2));
        console.log('\n💾 結果已保存到 http-token-result.json');
        
        // 更新設定檔
        if (foundToken || pageTokens.length > 0) {
            console.log('\n🔄 更新設定檔...');
            const configPath = 'config/deploy-config.json';
            
            try {
                const config = JSON.parse(require('fs').readFileSync(configPath, 'utf8'));
                
                config.api_credentials.btcn.session_cookie = 'cbffa9cfef96703cf3e4e7c627e7c1e5_ssl=2cd23b77-d9b7-4421-b04c-25168a43ed2a.cl7r_9PKmnStRFMb2Tws57_6w74';
                
                if (foundToken) {
                    config.api_credentials.btcn.http_token = foundToken.value;
                } else if (pageTokens.length > 0) {
                    config.api_credentials.btcn.http_token = pageTokens[0].value;
                }
                
                require('fs').writeFileSync(configPath, JSON.stringify(config, null, 2));
                console.log('✅ 設定檔已更新！');
            } catch (e) {
                console.log('⚠️  無法更新設定檔:', e.message);
            }
        }
        
        console.log('\n⏳ 瀏覽器將保持開啟 15 秒供檢查...');
        await page.waitForTimeout(15000);
        
    } catch (error) {
        console.error('❌ 錯誤:', error);
    } finally {
        await browser.close();
    }
}

getHttpToken();