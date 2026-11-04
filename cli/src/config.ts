import { chmodSync, mkdirSync, readFileSync, unlinkSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

export type DashyConfig = {
    api?: string;
    token?: string;
    abilities?: string[];
    instance?: { name?: string; url?: string; version?: string } | null;
    user?: { name?: string; email?: string } | null;
    team?: { id?: number; name?: string } | null;
    paired_at?: string;
    expires_at?: string | null;
};

export const configDirectory = (): string => process.env.DASHY_HOME || join(homedir(), '.dashy');

export const configFile = (): string => join(configDirectory(), 'config.json');

export function loadConfig(): DashyConfig {
    try {
        return JSON.parse(readFileSync(configFile(), 'utf8')) as DashyConfig;
    } catch {
        return {};
    }
}

export function saveConfig(config: DashyConfig): void {
    mkdirSync(configDirectory(), { recursive: true, mode: 0o700 });
    writeFileSync(configFile(), JSON.stringify(config, null, 2) + '\n', { mode: 0o600 });

    if (process.platform !== 'win32') {
        try {
            chmodSync(configFile(), 0o600);
        } catch {
            // The file was created with 0600; a failed chmod is not fatal.
        }
    }
}

export function clearConfig(): void {
    try {
        unlinkSync(configFile());
    } catch {
        // Already signed out.
    }
}

export function normalizeApi(value: string | undefined): string {
    let api = String(value ?? '').trim().replace(/\/+$/, '');

    if (api === '') {
        return '';
    }

    if (!/^https?:\/\//i.test(api)) {
        api = (api.startsWith('localhost') || api.startsWith('127.') ? 'http://' : 'https://') + api;
    }

    return api.replace(/\/api(\/v1)?$/i, '');
}

export function resolveApi(override?: string): string {
    return normalizeApi(override || process.env.DASHY_API || loadConfig().api || '');
}
