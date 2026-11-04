import type { Me } from './api.js';

export const TABS = ['Overview', 'Servers', 'Updates', 'Help'] as const;

export type TabIndex = 0 | 1 | 2 | 3;

export type Flash = { text: string; tone: 'ok' | 'warn' | 'err' };

export type PairState = {
    api: string;
    code: string;
    field: 'api' | 'code';
    error: string | null;
    busy: boolean;
};

export type AppState = {
    phase: 'boot' | 'pair' | 'main';
    bootFrame: number;
    tab: TabIndex;
    me: Me | null;
    error: string | null;
    loading: boolean;
    flash: Flash | null;
    confirmUpgrade: boolean;
    pair: PairState;
    clock: Date;
};

export function demoState(): AppState {
    return {
        phase: 'main',
        bootFrame: 12,
        tab: 0,
        me: {
            instance: {
                name: 'Dashy',
                url: 'https://dashy.example.com',
                version: '4.7.0',
                timezone: 'Europe/Paris',
            },
            user: { id: 0, name: 'Alex', email: 'alex@example.com' },
            team: { id: 0, name: 'Root Team' },
            servers: [
                { uuid: 'a', name: 'prod-eu-1', ip: '51.15.20.4', reachable: true },
                { uuid: 'b', name: 'prod-eu-2', ip: '51.15.20.5', reachable: true },
                { uuid: 'c', name: 'staging', ip: '10.0.0.12', reachable: false },
            ],
            counts: { servers: 3, applications: 12, databases: 5, services: 4 },
            update: {
                available: true,
                current: '4.7.0',
                latest: '4.7.1',
                checked_at: new Date().toISOString(),
                error: null,
            },
            abilities: ['read', 'write', 'deploy'],
        },
        error: null,
        loading: false,
        flash: null,
        confirmUpgrade: false,
        pair: { api: '', code: '', field: 'api', error: null, busy: false },
        clock: new Date(),
    };
}
