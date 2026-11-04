import { normalizeApi } from './config.js';

export type ServerSummary = {
    uuid: string;
    name: string;
    ip: string | null;
    reachable: boolean;
};

export type Me = {
    instance: { name: string; url: string; version: string; timezone: string };
    user: { id: number; name: string; email: string } | null;
    team: { id: number; name: string } | null;
    servers: ServerSummary[];
    counts: { servers: number; applications: number; databases: number; services: number };
    update: {
        available: boolean;
        current: string;
        latest: string;
        checked_at: string | null;
        error: string | null;
        notes: string | null;
        url: string | null;
    };
    abilities: string[];
};

export type PairResult = {
    token: string;
    abilities: string[];
    expires_at: string;
    user: { id: number; name: string; email: string };
    team: { id: number; name: string } | null;
    instance: { name: string; url: string; version: string; timezone: string };
};

export class ApiError extends Error {
    constructor(message: string, readonly status?: number) {
        super(message);
        this.name = 'ApiError';
    }
}

type RequestOptions = {
    token?: string;
    body?: unknown;
    timeout?: number;
};

export async function request<T>(
    method: string,
    url: string,
    { token, body, timeout = 20000 }: RequestOptions = {},
): Promise<T> {
    const headers: Record<string, string> = { Accept: 'application/json' };

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    let response: Response;

    try {
        response = await fetch(url, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: AbortSignal.timeout(timeout),
        });
    } catch (error) {
        throw new ApiError(
            error instanceof Error && error.name === 'TimeoutError'
                ? 'The instance did not answer in time.'
                : 'Could not reach the instance: ' + (error instanceof Error ? error.message : String(error)),
        );
    }

    const text = await response.text();
    let data: unknown = null;

    try {
        data = text === '' ? null : JSON.parse(text);
    } catch {
        data = { raw: text };
    }

    if (!response.ok) {
        const payload = (data ?? {}) as Record<string, unknown>;
        const message = String(payload.message ?? payload.error ?? `HTTP ${response.status}`);

        throw new ApiError(message, response.status);
    }

    return data as T;
}

export const pairWithCode = (api: string, code: string, name: string): Promise<PairResult> =>
    request<PairResult>('POST', `${normalizeApi(api)}/api/cli/pair`, {
        body: { code, name },
    });

export const fetchMe = (api: string, token: string): Promise<Me> => request<Me>('GET', `${api}/api/cli/me`, { token });

export const triggerUpgrade = (api: string, token: string): Promise<{ message?: string }> =>
    request<{ message?: string }>('POST', `${api}/api/cli/upgrade`, { token });

export const deployApplication = (api: string, token: string, uuid: string): Promise<Record<string, unknown>> =>
    request('POST', `${api}/api/v1/deploy`, { token, body: { tag: uuid } });

export const applicationLogs = (api: string, token: string, uuid: string, lines = 100): Promise<unknown> =>
    request('GET', `${api}/api/v1/applications/${uuid}/logs?lines=${lines}`, { token });
