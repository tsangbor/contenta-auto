const { chromium } = require('playwright');

async function getBtpanelCredentials() {
    console.log('🔐 開始登入 BT Panel...');
    
    const browser = await chromium.launch({
        headless: true, // 使用 headless 模式加快速度
        ignoreHTTPSErrors: true // 忽略 SSL 憑證錯誤
    });
    
    const context = await browser.newContext();
    const page = await context.newPage();
    
    try {
        // 前往登入頁面
        console.log('📍 前往登入頁面...');
        await page.goto('https://jp3.contenta.tw:8888/btpanel');
        
        // 等待登入表單載入
        await page.waitForLoadState('networkidle');
        
        // 填寫登入資訊
        console.log('✏️ 填寫登入資訊...');
        await page.fill('input[name="username"]', 'tsangbor');
        await page.fill('input[name="password"]', 'XSW2cde3');
        
        // 點擊登入按鈕
        console.log('🔑 點擊登入...');
        await page.click('button[type="submit"]');
        
        // 等待登入完成
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000); // 額外等待 2 秒
        
        // 取得 cookies
        const cookies = await context.cookies();
        console.log('\n🍪 Cookies:');
        cookies.forEach(cookie => {
            console.log(`${cookie.name}: ${cookie.value}`);
        });
        
        // 尋找 session cookie
        const sessionCookie = cookies.find(c => c.name.includes('session') || c.name.includes('ssl'));
        if (sessionCookie) {
            console.log(`\n✅ Session Cookie: ${sessionCookie.name}=${sessionCookie.value}`);
        }
        
        // 取得 X-HTTP-Token (通常在頁面的 meta 標籤或 JavaScript 變數中)
        const token = await page.evaluate(() => {
            // 嘗試從 meta 標籤取得
            const metaToken = document.querySelector('meta[name="csrf-token"]');
            if (metaToken) return metaToken.content;
            
            // 嘗試從全域變數取得
            if (window.http_token) return window.http_token;
            if (window.csrf_token) return window.csrf_token;
            
            // 嘗試從 localStorage 取得
            const localToken = localStorage.getItem('http_token') || localStorage.getItem('csrf_token');
            if (localToken) return localToken;
            
            return null;
        });
        
        if (token) {
            console.log(`\n✅ X-HTTP-Token: ${token}`);
        } else {
            console.log('\n⚠️ 無法找到 X-HTTP-Token，請手動從瀏覽器開發者工具中查找');
        }
        
        // 保存認證資訊到檔案
        const credentials = {
            cookies: cookies.map(c => `${c.name}=${c.value}`).join('; '),
            session_cookie: sessionCookie ? `${sessionCookie.name}=${sessionCookie.value}` : '',
            http_token: token || '請從瀏覽器開發者工具手動取得',
            timestamp: new Date().toISOString()
        };
        
        const fs = require('fs');
        fs.writeFileSync('btpanel-credentials.json', JSON.stringify(credentials, null, 2));
        console.log('\n💾 認證資訊已保存到 btpanel-credentials.json');
        
        // 在 headless 模式下不需要等待
        console.log('\n✅ 完成！');
        
    } catch (error) {
        console.error('❌ 錯誤:', error.message);
    } finally {
        await browser.close();
    }
}

// 執行
getBtpanelCredentials();