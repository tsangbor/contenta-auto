const { chromium } = require('playwright');

async function getBtpanelCredentials() {
    console.log('🔐 開始使用 Playwright 登入 BT Panel...');
    
    const browser = await chromium.launch({
        headless: false, // 設為 false 可以看到操作過程
        ignoreHTTPSErrors: true
    });
    
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    
    const page = await context.newPage();
    
    try {
        // 前往登入頁面
        console.log('📍 前往登入頁面...');
        await page.goto('https://jp3.contenta.tw:8888/btpanel', {
            waitUntil: 'domcontentloaded',
            timeout: 30000
        });
        
        // 等待頁面完全載入
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
        
        // 截圖登入頁面
        await page.screenshot({ path: 'login-page-success.png' });
        console.log('📸 已截圖登入頁面: login-page-success.png');
        
        // 填寫使用者名稱 - 使用 placeholder 定位
        console.log('✏️ 填寫帳號...');
        await page.fill('input[placeholder="帳號"]', 'tsangbor');
        
        // 填寫密碼
        console.log('✏️ 填寫密碼...');
        await page.fill('input[placeholder="密碼"]', 'XSW2cde3');
        
        // 截圖填寫後的表單
        await page.screenshot({ path: 'form-filled-success.png' });
        console.log('📸 已截圖填寫後的表單: form-filled-success.png');
        
        // 點擊登入按鈕
        console.log('🔑 點擊登入按鈕...');
        await page.click('button:has-text("登入")');
        
        // 等待登入完成 - 可能會跳轉到儀表板
        console.log('⏳ 等待登入完成...');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(5000); // 等待 5 秒讓頁面完全載入
        
        // 截圖登入後的頁面
        await page.screenshot({ path: 'after-login-success.png' });
        console.log('📸 已截圖登入後頁面: after-login-success.png');
        
        // 取得當前 URL
        const currentUrl = page.url();
        console.log(`📍 當前 URL: ${currentUrl}`);
        
        // 檢查是否登入成功（URL 應該會改變）
        if (currentUrl.includes('btpanel') && !currentUrl.includes('login')) {
            console.log('✅ 登入成功！');
        } else {
            console.log('⚠️  可能登入失敗，請檢查憑證');
        }
        
        // 取得所有 cookies
        const cookies = await context.cookies();
        console.log('\n🍪 取得的 Cookies:');
        const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');
        console.log(cookieString);
        
        // 尋找重要的 cookies
        const sessionCookies = cookies.filter(c => 
            c.name.toLowerCase().includes('session') || 
            c.name.toLowerCase().includes('ssl') ||
            c.name.toLowerCase().includes('bt') ||
            c.name.toLowerCase().includes('token')
        );
        
        console.log('\n🔑 重要的 Session Cookies:');
        sessionCookies.forEach(cookie => {
            console.log(`${cookie.name}: ${cookie.value}`);
        });
        
        // 監聽網路請求來找 X-HTTP-Token
        console.log('\n🌐 監聽網路請求找 token...');
        
        let foundToken = null;
        
        // 設定請求攔截器
        page.on('request', request => {
            const headers = request.headers();
            for (const [key, value] of Object.entries(headers)) {
                if (key.toLowerCase().includes('token') || 
                    key.toLowerCase() === 'x-http-token' ||
                    key.toLowerCase() === 'x-csrf-token') {
                    console.log(`✅ 找到 token header: ${key}: ${value}`);
                    foundToken = { key, value };
                }
            }
        });
        
        // 嘗試點擊一些連結來觸發 AJAX 請求
        console.log('🔄 嘗試觸發 AJAX 請求...');
        try {
            // 尋找並點擊一些常見的連結
            const navigationLinks = await page.$$('a[href*="site"], a[href*="file"], a[href*="database"], .nav-link, .menu-item');
            if (navigationLinks.length > 0) {
                await navigationLinks[0].click();
                await page.waitForTimeout(2000);
            }
        } catch (e) {
            console.log('無法點擊導航連結，繼續其他方法...');
        }
        
        // 在頁面中搜尋 token
        const tokenFromPage = await page.evaluate(() => {
            const results = {
                metaTokens: [],
                windowTokens: [],
                localStorageTokens: [],
                sessionStorageTokens: [],
                scriptTokens: []
            };
            
            // 從 meta 標籤
            const metaTags = document.querySelectorAll('meta[name*="token"], meta[name*="csrf"]');
            metaTags.forEach(meta => {
                results.metaTokens.push({
                    name: meta.getAttribute('name'),
                    content: meta.getAttribute('content')
                });
            });
            
            // 從 window 物件
            for (const key in window) {
                if (key.toLowerCase().includes('token')) {
                    results.windowTokens.push({
                        key: key,
                        value: window[key]
                    });
                }
            }
            
            // 從 localStorage
            try {
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.toLowerCase().includes('token')) {
                        results.localStorageTokens.push({
                            key: key,
                            value: localStorage.getItem(key)
                        });
                    }
                }
            } catch (e) {}
            
            // 從 sessionStorage
            try {
                for (let i = 0; i < sessionStorage.length; i++) {
                    const key = sessionStorage.key(i);
                    if (key && key.toLowerCase().includes('token')) {
                        results.sessionStorageTokens.push({
                            key: key,
                            value: sessionStorage.getItem(key)
                        });
                    }
                }
            } catch (e) {}
            
            // 從頁面 script 標籤中搜尋 token
            const scripts = document.querySelectorAll('script');
            scripts.forEach(script => {
                const content = script.textContent || script.innerHTML;
                const tokenMatches = content.match(/['"](http_token|csrf_token|request_token)['"]\s*:\s*['"]([^'"]+)['"]/gi);
                if (tokenMatches) {
                    tokenMatches.forEach(match => {
                        results.scriptTokens.push(match);
                    });
                }
            });
            
            return results;
        });
        
        console.log('\n🔍 從頁面找到的 Token 資訊:');
        console.log(JSON.stringify(tokenFromPage, null, 2));
        
        // 保存結果
        const credentials = {
            loginSuccess: currentUrl.includes('btpanel') && !currentUrl.includes('login'),
            currentUrl: currentUrl,
            cookies: cookieString,
            sessionCookies: sessionCookies,
            tokenFromRequest: foundToken,
            tokenFromPage: tokenFromPage,
            timestamp: new Date().toISOString()
        };
        
        const fs = require('fs');
        fs.writeFileSync('btpanel-credentials-success.json', JSON.stringify(credentials, null, 2));
        console.log('\n💾 認證資訊已保存到 btpanel-credentials-success.json');
        
        // 輸出使用說明
        console.log('\n📋 使用說明:');
        console.log('1. 檢查 btpanel-credentials-success.json 檔案');
        console.log('2. 複製 cookies 字串到你的設定檔');
        console.log('3. 如果找到 token，也一併複製');
        console.log('4. 瀏覽器將保持開啟 30 秒供你手動檢查');
        
        // 保持瀏覽器開啟 30 秒
        await page.waitForTimeout(30000);
        
    } catch (error) {
        console.error('❌ 錯誤:', error.message);
        
        // 錯誤時也截圖
        await page.screenshot({ path: 'error-final.png' });
        console.log('📸 錯誤截圖已保存: error-final.png');
    } finally {
        await browser.close();
    }
}

// 執行
getBtpanelCredentials();