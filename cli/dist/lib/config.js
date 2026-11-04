import { chmodSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';
export const configDirectory = () => process.env.DASHY_HOME || join(homedir(), '.dashy');
export const configFile = () => join(configDirectory(), 'config.json');
export function loadConfig() {
    try {
        return JSON.parse(readFileSync(configFile(), 'utf8'));
    }
    catch {
        return {};
    }
}
export function saveConfig(config) {
    mkdirSync(configDirectory(), { recursive: true, mode: 0o700 });
    writeFileSync(configFile(), JSON.stringify(config, null, 2) + '\n', { mode: 0o600 });
    if (process.platform !== 'win32') {
        try {
            chmodSync(configFile(), 0o600);
        }
        catch {
            // The file was created with 0600; a failed chmod is not fatal.
        }
    }
}
export function clearConfig() {
    try {
        unlinkSync(configFile());
    }
    catch {
        // Already signed out.
    }
}
export function normalizeApi(value) {
    let api = String(value ?? '').trim().replace(/\/+$/, '');
    if (api === '') {
        return '';
    }
    if (!/^https?:\/\//i.test(api)) {
        api = (api.startsWith('localhost') || api.startsWith('127.') ? 'http://' : 'https://') + api;
    }
    return api.replace(/\/api(\/v1)?$/i, '');
}
export function resolveApi(override) {
    return normalizeApi(override || process.env.DASHY_API || loadConfig().api || '');
}
