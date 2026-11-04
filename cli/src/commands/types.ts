import { loadConfig, resolveApi } from '../lib/config.js';
import { ApiError } from '../lib/api.js';
import { printError } from '../lib/plain.js';

export type Options = {
    api?: string;
    json: boolean;
    demo: boolean;
    yes: boolean;
    positionals: string[];
};

export function authenticated(options: Options): { api: string; token: string } | null {
    const config = loadConfig();
    const api = resolveApi(options.api);

    if (!config.token || api === '') {
        printError(
            config.token
                ? 'No instance configured. Pass --api <url>.'
                : 'Not connected. Run `dashy connect` and paste a code from Settings → CLI.',
        );

        return null;
    }

    return { api, token: config.token };
}

export const fail = (error: unknown): number => {
    printError(error instanceof ApiError ? error.message : error instanceof Error ? error.message : String(error));
    return 1;
};
