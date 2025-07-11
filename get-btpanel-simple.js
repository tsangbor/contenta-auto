const { chromium } = require('playwright');

async function getBtpanelCredentials() {
    console.log('🔐 簡化版 BT Panel 登入...');
    
    const browser = await chromium.launch({
        headless: false,
        ignoreHTTPSErrors: true,
        args: ['--disable-web-security', '--disable-features=VizDisplayCompositor']
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        console.log('📍 前往登入頁面...');
        await page.goto('https://jp3.contenta.tw:8888/btpanel');
        
        // 等待 5 秒讓頁面載入
        await page.waitForTimeout(5000);
        
        console.log('📸 截圖當前頁面...');
        await page.screenshot({ path: 'current-page.png' });
        
        console.log('✏️ 填寫登入資訊...');
        // 使用更通用的選擇器
        await page.locator('input').first().fill('tsangbor');
        await page.locator('input[type="password"]').fill('XSW2cde3');
        
        await page.screenshot({ path: 'filled-form.png' });
        
        console.log('🔑 點擊登入...');
        await page.locator('button').first().click();
        
        // 等待 10 秒
        await page.waitForTimeout(10000);
        
        await page.screenshot({ path: 'after-login.png' });
        
        // 取得 cookies
        const cookies = await context.cookies();
        const cookieString = cookies.map(c => `${c.name}=${c.value}`).join('; ');
        
        console.log('\n🍪 Cookies:');
        console.log(cookieString);
        
        // 保存結果
        const result = {
            url: page.url(),
            cookies: cookieString,
            individual_cookies: cookies,
            timestamp: new Date().toISOString()
        };
        
        require('fs').writeFileSync('simple-result.json', JSON.stringify(result, null, 2));
        
        console.log('\n✅ 結果已保存到 simple-result.json');
        console.log('瀏覽器將保持開啟 20 秒...');
        
        await page.waitForTimeout(20000);
        
    } catch (error) {
        console.error('❌ 錯誤:', error);
        await page.screenshot({ path: 'simple-error.png' });
    } finally {
        await browser.close();
    }
}

getBtpanelCredentials();