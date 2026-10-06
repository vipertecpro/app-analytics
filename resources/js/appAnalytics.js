/**
 * App Analytics & Remote Config for NativePHP Mobile — JavaScript bridge (legacy web-view apps).
 *
 * NOTE: the primary consumer in v4 is the PHP facades (`Analytics::logEvent()`,
 * `RemoteConfig::bool()`), which also validate input and serve defaults. This
 * wrapper exposes the native calls for web views; it does not validate.
 *
 * @example
 *   import { appAnalytics } from '@vipertecpro/app-analytics';
 *
 *   await appAnalytics.logScreenView('Pricing');
 *   await appAnalytics.logEvent('plan_selected', { plan: 'pro' });
 */

const baseUrl = '/_native/api/call';

/**
 * Internal bridge call function.
 * @private
 */
async function bridgeCall(method, params = {}) {
    const response = await fetch(baseUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({ method, params })
    });

    const result = await response.json();

    if (result.status === 'error') {
        throw new Error(result.message || 'Native call failed');
    }

    return result.data ?? result;
}

/**
 * Log an analytics event.
 * @param {string} name
 * @param {Object<string, string|number|boolean>} [parameters]
 */
export async function logEvent(name, parameters = {}) {
    const clean = {};
    for (const [key, value] of Object.entries(parameters)) {
        clean[key] = typeof value === 'boolean' ? (value ? 1 : 0) : value;
    }
    await bridgeCall('AppAnalytics.LogEvent', { name, parameters: clean });
}

/**
 * Log a screen view.
 * @param {string} screenName
 * @param {string} [screenClass]
 */
export async function logScreenView(screenName, screenClass) {
    await logEvent('screen_view', { screen_name: screenName, screen_class: screenClass || screenName });
}

/** Whether Firebase is configured, and why not. */
export async function status() {
    return bridgeCall('AppAnalytics.Status');
}

/**
 * Fetch and activate Remote Config; the result arrives as a native event.
 * @param {number} [minimumFetchInterval=3600]
 */
export async function fetchAndActivate(minimumFetchInterval = 3600) {
    await bridgeCall('AppAnalytics.FetchAndActivate', { minimumFetchInterval });
}

export const appAnalytics = { logEvent, logScreenView, status, fetchAndActivate };

export default appAnalytics;
