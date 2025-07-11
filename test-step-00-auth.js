#!/usr/bin/env node

/**
 * 測試步驟 00 的認證更新邏輯
 */

const fs = require('fs');

console.log('🧪 測試步驟 00 認證更新邏輯');
console.log('============================\n');

// 模擬 PHP 中的 AuthManager 類別邏輯
class TestAuthManager {
    constructor(configPath) {
        this.configPath = configPath;
    }
    
    needsUpdate() {
        if (!fs.existsSync(this.configPath)) {
            console.log('⚠️ 設定檔不存在，需要更新');
            return true;
        }
        
        try {
            const config = JSON.parse(fs.readFileSync(this.configPath, 'utf8'));
            const lastUpdated = config.api_credentials?.btcn?._last_updated?.cookie;
            
            if (!lastUpdated) {
                console.log('📋 從未更新過認證，需要更新');
                return true;
            }
            
            const lastUpdateTime = new Date(lastUpdated);
            const now = new Date();
            const hoursSince = (now - lastUpdateTime) / (1000 * 60 * 60);
            
            // 如果超過 6 小時就需要更新
            if (hoursSince > 6) {
                console.log(`⏰ 認證已過期 ${hoursSince.toFixed(1)} 小時，需要更新`);
                return true;
            }
            
            console.log(`✅ 認證仍有效，${(6 - hoursSince).toFixed(1)} 小時後過期`);
            return false;
            
        } catch (error) {
            console.log('❌ 檢查認證狀態時發生錯誤:', error.message);
            return true;
        }
    }
    
    getCredentials() {
        if (!fs.existsSync(this.configPath)) {
            return null;
        }
        
        try {
            const config = JSON.parse(fs.readFileSync(this.configPath, 'utf8'));
            const btcnConfig = config.api_credentials?.btcn;
            
            return {
                cookie: btcnConfig?.session_cookie || null,
                token: btcnConfig?.http_token || null,
                last_updated: btcnConfig?._last_updated || null
            };
        } catch (error) {
            console.log('❌ 讀取認證資訊時發生錯誤:', error.message);
            return null;
        }
    }
    
    getLoginCredentials() {
        try {
            const config = JSON.parse(fs.readFileSync(this.configPath, 'utf8'));
            const btcnConfig = config.api_credentials?.btcn || {};
            
            return {
                username: btcnConfig.panel_login || process.env.BTPANEL_USERNAME || 'tsangbor',
                password: btcnConfig.panel_password || process.env.BTPANEL_PASSWORD || 'XSW2cde',
                login_url: btcnConfig.panel_auth || 'https://jp3.contenta.tw:8888/btpanel'
            };
        } catch (error) {
            console.log('❌ 讀取登入認證時發生錯誤:', error.message);
            return {
                username: 'tsangbor',
                password: 'XSW2cde',
                login_url: 'https://jp3.contenta.tw:8888/btpanel'
            };
        }
    }
}

// 模擬步驟 00 的認證檢查邏輯
function simulateStep00Auth() {
    console.log('🔐 階段 1: 更新 BT Panel 認證資訊');
    
    const authManager = new TestAuthManager('config/deploy-config.json');
    
    // 檢查認證是否需要更新
    const needsUpdate = authManager.needsUpdate();
    console.log(`認證狀態檢查: ${needsUpdate ? "需要更新" : "仍然有效"}`);
    
    if (needsUpdate) {
        console.log('開始更新認證...');
        
        const loginCreds = authManager.getLoginCredentials();
        console.log('登入認證資訊:');
        console.log(`  使用者名稱: ${loginCreds.username}`);
        console.log(`  密碼: ${loginCreds.password.substring(0, 3)}***`);
        console.log(`  登入網址: ${loginCreds.login_url}`);
        
        console.log('\n⚠️ 實際的認證更新需要透過 Playwright 執行');
        console.log('可以執行: node auth-updater.js --username tsangbor --password XSW2cde');
        
        return false; // 模擬更新失敗，避免實際執行
    } else {
        console.log('認證仍然有效，跳過更新');
        
        // 驗證當前認證是否真的可用
        const credentials = authManager.getCredentials();
        if (!credentials || !credentials.cookie || !credentials.token) {
            console.log('⚠️ 認證資訊不完整，需要強制更新...');
            
            const loginCreds = authManager.getLoginCredentials();
            console.log('登入認證資訊:');
            console.log(`  使用者名稱: ${loginCreds.username}`);
            console.log(`  密碼: ${loginCreds.password.substring(0, 3)}***`);
            console.log(`  登入網址: ${loginCreds.login_url}`);
            
            return false; // 模擬更新失敗
        } else {
            console.log('✅ 認證資訊完整且有效');
            console.log(`  Cookie: ${credentials.cookie.substring(0, 50)}...`);
            console.log(`  Token: ${credentials.token.substring(0, 20)}...`);
            
            if (credentials.last_updated && credentials.last_updated.cookie) {
                console.log(`  最後更新: ${credentials.last_updated.cookie}`);
            }
            
            return true; // 認證有效
        }
    }
}

// 執行模擬
console.log('開始模擬步驟 00 認證檢查...\n');

const authResult = simulateStep00Auth();

console.log('\n🎯 模擬結果:');
if (authResult) {
    console.log('✅ 認證驗證成功，可以繼續執行後續步驟');
    console.log('📋 建議: 直接執行步驟 00，應該會跳過認證更新');
} else {
    console.log('⚠️ 需要更新認證或認證不完整');
    console.log('📋 建議: 手動執行 node auth-updater.js 來更新認證');
}

console.log('\n💡 如果要強制更新認證，可以執行:');
console.log('node auth-updater.js --username tsangbor --password XSW2cde');

console.log('\n🔧 如果要測試認證是否有效，可以執行:');
console.log('node test-bt-simple.js');