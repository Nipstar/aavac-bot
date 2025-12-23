/**
 * SDK Dependencies Shim
 *
 * Provides proper global variable aliases for Retell SDK compatibility
 * The retell-sdk.js UMD bundle expects lowercase property names
 * but eventemitter3 exports capitalized names
 *
 * @since 1.2.16
 */

(function(window) {
    'use strict';

    // Alias EventEmitter3 to lowercase eventemitter3
    // Retell SDK expects window.eventemitter3, not window.EventEmitter3
    if (typeof window.EventEmitter3 !== 'undefined') {
        window.eventemitter3 = window.EventEmitter3;
    }

    // Provide WebSocket as isomorphicWs
    // Retell SDK uses isomorphic-ws which provides WebSocket
    if (typeof window.WebSocket !== 'undefined') {
        window.isomorphicWs = window.WebSocket;
    }

    // Alias Retell SDK exports for compatibility
    // The bundled SDK v1.3.3 exports to window.retellClientJsSdk
    // But our code expects window.RetellWebClient
    // Wait for SDK to fully load before creating alias
    if (typeof window.retellClientJsSdk !== 'undefined' && typeof window.retellClientJsSdk.RetellWebClient !== 'undefined') {
        window.RetellWebClient = window.retellClientJsSdk.RetellWebClient;
    } else {
        // If SDK not loaded yet, poll for it
        var attempts = 0;
        var checkSDK = setInterval(function() {
            if (typeof window.retellClientJsSdk !== 'undefined' && typeof window.retellClientJsSdk.RetellWebClient !== 'undefined') {
                window.RetellWebClient = window.retellClientJsSdk.RetellWebClient;
                clearInterval(checkSDK);
            }
            attempts++;
            if (attempts > 100) {
                clearInterval(checkSDK); // Stop checking after 5 seconds
            }
        }, 50);
    }

})(window);
