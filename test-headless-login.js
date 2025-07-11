const { chromium } = require('playwright');

async function testHeadlessLogin() {
    console.log('🔐 測試無頭模式登入 BT Panel...');
    
    const browser = await chromium.launch({
        headless: true, // 無頭模式
        ignoreHTTPSErrors: true,
        args: ['--disable-web-security']
    });
    
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    
    const page = await context.newPage();
    
    let foundToken = null;
    
    // 監聽網路請求
    page.on('request', request => {
        const headers = request.headers();
        for (const [key, value] of Object.entries(headers)) {
            if (key.toLowerCase() === 'x-http-token') {
                console.log(`✅ 找到 token: ${value}`);
                foundToken = value;
            }
        }
    });
    
    try {
        console.log('📍 前往登入頁面...');
        await page.goto('https://jp3.contenta.tw:8888/btpanel');
        await page.waitForTimeout(3000);
        
        console.log('✏️ 填寫登入資訊...');
        await page.locator('input').first().fill('tsangbor');
        await page.locator('input[type="password"]').fill('XSW2cde3');
        
        console.log('🔑 點擊登入...');
        await page.locator('button').first().click();
        
        // 等待登入完成
        await page.waitForTimeout(8000);
        
        const currentUrl = page.url();
        console.log(`📍 當前 URL: ${currentUrl}`);
        
        // 檢查是否登入成功
        const loginSuccess = currentUrl.includes('home') || (currentUrl.includes('btpanel') && !currentUrl.includes('login'));
        
        if (loginSuccess) {
            console.log('✅ 無頭模式登入成功！');
            
            // 取得 cookies
            const cookies = await context.cookies();
            const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');
            
            console.log('\n🍪 Cookies:');
            console.log(cookieString);
            
            // 觸發 AJAX 請求來取得 token
            console.log('\n🔄 點擊網站管理來觸發 token...');
            try {
                await page.click('a[href*="site"]');
                await page.waitForTimeout(3000);
            } catch (e) {
                console.log('嘗試其他方法觸發 token...');
            }
            
            // 搜尋頁面中的 token
            const pageToken = await page.evaluate(() => {
                const scripts = document.querySelectorAll('script');
                for (const script of scripts) {
                    const content = script.textContent || script.innerHTML;
                    const match = content.match(/request_token\s*=\s*['"]([^'"]+)['"]/);
                    if (match) return match[1];
                }
                return null;
            });
            
            console.log('\n🔍 結果:');
            console.log('Login Success:', loginSuccess);
            console.log('Cookie:', cookieString);
            console.log('Token from Request:', foundToken);
            console.log('Token from Page:', pageToken);
            
            // 保存結果
            const result = {
                headlessMode: true,
                loginSuccess: loginSuccess,
                url: currentUrl,
                cookies: cookieString,
                tokenFromRequest: foundToken,
                tokenFromPage: pageToken,
                timestamp: new Date().toISOString()
            };
            
            require('fs').writeFileSync('headless-test-result.json', JSON.stringify(result, null, 2));
            console.log('\n💾 結果已保存到 headless-test-result.json');
            
        } else {
            console.log('❌ 無頭模式登入失敗');
            console.log('當前 URL:', currentUrl);
        }
        
    } catch (error) {
        console.error('❌ 錯誤:', error.message);
    } finally {
        await browser.close();
    }
}

testHeadlessLogin();