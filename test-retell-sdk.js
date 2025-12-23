/**
 * Retell SDK Load Test Script
 *
 * Run this in browser console at https://boltelectrical.uk/ to test if the new CDN URL works
 * Copy and paste the entire script into DevTools console
 */

(function testRetellSDK() {
    console.clear();
    console.log('=== RETELL SDK TEST SCRIPT ===\n');

    // Test 1: Check current SDK status
    console.log('1️⃣ CURRENT SDK STATUS:');
    console.log('   window.retellClientJsSdk:', typeof window.retellClientJsSdk);
    console.log('   window.RetellWebClient:', typeof window.RetellWebClient);
    if (window.retellClientJsSdk) {
        console.log('   window.retellClientJsSdk.RetellWebClient:', typeof window.retellClientJsSdk.RetellWebClient);
    }
    console.log('');

    // Test 2: Load the new SDK from correct CDN
    console.log('2️⃣ LOADING RETELL SDK v2.0.7 FROM NEW CDN...');
    const sdkScript = document.createElement('script');
    sdkScript.src = 'https://cdn.jsdelivr.net/npm/retell-client-js-sdk@2.0.7/dist/index.umd.js';
    sdkScript.onload = function() {
        console.log('✅ SDK script loaded successfully');

        // Test 3: Verify SDK is available
        console.log('\n3️⃣ VERIFYING SDK AVAILABILITY:');
        setTimeout(() => {
            console.log('   window.retellClientJsSdk:', typeof window.retellClientJsSdk);
            if (window.retellClientJsSdk) {
                console.log('   ✅ SDK namespace found');
                console.log('   window.retellClientJsSdk.RetellWebClient:', typeof window.retellClientJsSdk.RetellWebClient);

                if (typeof window.retellClientJsSdk.RetellWebClient === 'function') {
                    console.log('   ✅ RetellWebClient is a function (constructor)');

                    // Test 4: Try instantiating
                    console.log('\n4️⃣ TESTING INSTANTIATION:');
                    try {
                        const client = new window.retellClientJsSdk.RetellWebClient();
                        console.log('   ✅ Successfully created RetellWebClient instance');
                        console.log('   Instance:', client);
                    } catch (e) {
                        console.error('   ❌ Failed to instantiate:', e.message);
                    }
                } else {
                    console.error('   ❌ RetellWebClient is not a function');
                }
            } else {
                console.error('   ❌ SDK namespace not found on window');
            }

            // Summary
            console.log('\n' + '='.repeat(50));
            console.log('📋 SUMMARY:');
            console.log('='.repeat(50));
            if (window.retellClientJsSdk && typeof window.retellClientJsSdk.RetellWebClient === 'function') {
                console.log('✅ FIX VERIFIED - SDK loads correctly!');
                console.log('\nDeploy these changes to your server:');
                console.log('1. antek-chat-connector/includes/class-widget-renderer.php (line 296)');
                console.log('2. antek-chat-connector/public/js/providers/retell-provider.js (lines 51, 55, 77)');
            } else {
                console.log('❌ SDK still not loading correctly');
            }
        }, 500);
    };

    sdkScript.onerror = function() {
        console.error('❌ Failed to load SDK from CDN');
        console.log('   URL: https://cdn.jsdelivr.net/npm/retell-client-js-sdk@2.0.7/dist/index.umd.js');
    };

    document.head.appendChild(sdkScript);
    console.log('   Loading from: https://cdn.jsdelivr.net/npm/retell-client-js-sdk@2.0.7/dist/index.umd.js');
    console.log('   Waiting for load...\n');
})();
