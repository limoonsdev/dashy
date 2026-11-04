import { loadConfig, resolveApi } from '../lib/config.js';
import { ApiError } from '../lib/api.js';
import { printError } from '../lib/plain.js';
export function authenticated(options) {
    const config = loadConfig();
    const api = resolveApi(options.api);
    if (!config.token || api === '') {
        printError(config.token
            ? 'No instance configured. Pass --api <url>.'
            : 'Not connected. Run `dashy connect` and paste a code from Settings → CLI.');
        return null;
    }
    return { api, token: config.token };
}
export const fail = (error) => {
    printError(error instanceof ApiError ? error.message : error instanceof Error ? error.message : String(error));
    return 1;
};
