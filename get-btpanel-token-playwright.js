const { chromium } = require('playwright');

async function getBtpanelCredentials() {
    console.log('🔐 開始使用 Playwright 登入 BT Panel...');
    
    const browser = await chromium.launch({
        headless: false, // 設為 false 可以看到操作過程
        ignoreHTTPSErrors: true
    });
    
    const context = await browser.newContext({
        // 模擬真實瀏覽器
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    
    const page = await context.newPage();
    
    try {
        // 前往登入頁面
        console.log('📍 前往登入頁面...');
        await page.goto('https://jp3.contenta.tw:8888/btpanel', {
            waitUntil: 'networkidle',
            timeout: 30000
        });
        
        // 截圖登入頁面
        await page.screenshot({ path: 'login-page.png' });
        console.log('📸 已截圖登入頁面: login-page.png');
        
        // 等待並填寫表單 - 嘗試多種可能的選擇器
        console.log('✏️ 填寫登入資訊...');
        
        // 嘗試不同的使用者名稱選擇器
        const usernameSelectors = [
            'input[name="username"]',
            'input[type="text"]',
            '#username',
            'input[placeholder*="用户名"]',
            'input[placeholder*="Username"]'
        ];
        
        let usernameField = null;
        for (const selector of usernameSelectors) {
            try {
                usernameField = await page.waitForSelector(selector, { timeout: 5000 });
                if (usernameField) {
                    console.log(`✅ 找到使用者名稱欄位: ${selector}`);
                    break;
                }
            } catch (e) {
                continue;
            }
        }
        
        if (usernameField) {
            await usernameField.fill('tsangbor');
        } else {
            throw new Error('無法找到使用者名稱輸入欄位');
        }
        
        // 嘗試不同的密碼選擇器
        const passwordSelectors = [
            'input[name="password"]',
            'input[type="password"]',
            '#password'
        ];
        
        let passwordField = null;
        for (const selector of passwordSelectors) {
            try {
                passwordField = await page.waitForSelector(selector, { timeout: 5000 });
                if (passwordField) {
                    console.log(`✅ 找到密碼欄位: ${selector}`);
                    break;
                }
            } catch (e) {
                continue;
            }
        }
        
        if (passwordField) {
            await passwordField.fill('XSW2cde3');
        } else {
            throw new Error('無法找到密碼輸入欄位');
        }
        
        // 截圖填寫後的表單
        await page.screenshot({ path: 'form-filled.png' });
        console.log('📸 已截圖填寫後的表單: form-filled.png');
        
        // 嘗試不同的登入按鈕選擇器
        console.log('🔑 點擊登入...');
        const submitSelectors = [
            'button[type="submit"]',
            'input[type="submit"]',
            'button:has-text("登录")',
            'button:has-text("Login")',
            '.login-button',
            '#login-button'
        ];
        
        let submitted = false;
        for (const selector of submitSelectors) {
            try {
                await page.click(selector, { timeout: 5000 });
                console.log(`✅ 點擊了登入按鈕: ${selector}`);
                submitted = true;
                break;
            } catch (e) {
                continue;
            }
        }
        
        if (!submitted) {
            // 如果找不到按鈕，嘗試按 Enter
            console.log('⌨️ 嘗試按 Enter 鍵登入...');
            await page.keyboard.press('Enter');
        }
        
        // 等待導航完成
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);
        
        // 截圖登入後的頁面
        await page.screenshot({ path: 'after-login.png' });
        console.log('📸 已截圖登入後頁面: after-login.png');
        
        // 取得當前 URL
        const currentUrl = page.url();
        console.log(`📍 當前 URL: ${currentUrl}`);
        
        // 取得所有 cookies
        const cookies = await context.cookies();
        console.log('\n🍪 找到的 Cookies:');
        const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');
        console.log(cookieString);
        
        // 在頁面中執行 JavaScript 來找 token
        const tokenInfo = await page.evaluate(() => {
            const results = {
                fromMeta: null,
                fromWindow: null,
                fromLocalStorage: null,
                fromSessionStorage: null,
                fromAjaxSetup: null
            };
            
            // 從 meta 標籤
            const metaToken = document.querySelector('meta[name="csrf-token"]') || 
                             document.querySelector('meta[name="csrf_token"]');
            if (metaToken) results.fromMeta = metaToken.content;
            
            // 從 window 物件
            if (window.http_token) results.fromWindow = window.http_token;
            if (window.csrf_token) results.fromWindow = window.csrf_token;
            if (window.request_token) results.fromWindow = window.request_token;
            
            // 從 localStorage
            try {
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key.toLowerCase().includes('token')) {
                        results.fromLocalStorage = localStorage.getItem(key);
                        break;
                    }
                }
            } catch (e) {}
            
            // 從 sessionStorage
            try {
                for (let i = 0; i < sessionStorage.length; i++) {
                    const key = sessionStorage.key(i);
                    if (key.toLowerCase().includes('token')) {
                        results.fromSessionStorage = sessionStorage.getItem(key);
                        break;
                    }
                }
            } catch (e) {}
            
            // 從 jQuery ajaxSetup (如果有)
            if (typeof $ !== 'undefined' && $.ajaxSetup) {
                const settings = $.ajaxSetup();
                if (settings.headers) {
                    results.fromAjaxSetup = settings.headers['X-HTTP-Token'] || 
                                          settings.headers['X-CSRF-Token'];
                }
            }
            
            return results;
        });
        
        console.log('\n🔍 Token 搜尋結果:');
        console.log(JSON.stringify(tokenInfo, null, 2));
        
        // 攔截網路請求來找 token
        console.log('\n🌐 監聽網路請求來找 token...');
        const tokenFromRequest = await new Promise((resolve) => {
            let found = false;
            page.on('request', request => {
                const headers = request.headers();
                for (const [key, value] of Object.entries(headers)) {
                    if (key.toLowerCase().includes('token') || key.toLowerCase() === 'x-http-token') {
                        if (!found) {
                            console.log(`✅ 從請求中找到 token: ${key}: ${value}`);
                            found = true;
                            resolve({ key, value });
                        }
                    }
                }
            });
            
            // 觸發一些 AJAX 請求
            page.evaluate(() => {
                // 嘗試點擊一些連結或按鈕來觸發 AJAX
                const links = document.querySelectorAll('a[href*="ajax"], button');
                if (links.length > 0) links[0].click();
            }).catch(() => {});
            
            // 5 秒後如果還沒找到就返回 null
            setTimeout(() => resolve(null), 5000);
        });
        
        // 保存結果
        const credentials = {
            url: currentUrl,
            cookies: cookieString,
            tokens: tokenInfo,
            tokenFromRequest: tokenFromRequest,
            timestamp: new Date().toISOString()
        };
        
        const fs = require('fs');
        fs.writeFileSync('btpanel-credentials.json', JSON.stringify(credentials, null, 2));
        console.log('\n💾 認證資訊已保存到 btpanel-credentials.json');
        
        console.log('\n⏳ 瀏覽器將保持開啟 30 秒，你可以：');
        console.log('1. 手動檢查開發者工具的 Network 標籤');
        console.log('2. 點擊一些功能來觸發 AJAX 請求');
        console.log('3. 在 Console 執行: console.log(document.cookie)');
        
        await page.waitForTimeout(30000);
        
    } catch (error) {
        console.error('❌ 錯誤:', error.message);
        
        // 錯誤時也截圖
        await page.screenshot({ path: 'error-screenshot.png' });
        console.log('📸 錯誤截圖已保存: error-screenshot.png');
    } finally {
        await browser.close();
    }
}

// 執行
getBtpanelCredentials();