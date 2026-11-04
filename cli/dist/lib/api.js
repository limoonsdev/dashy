import { normalizeApi } from './config.js';
export class ApiError extends Error {
    status;
    constructor(message, status) {
        super(message);
        this.status = status;
        this.name = 'ApiError';
    }
}
export async function request(method, url, { token, body, timeout = 20000 } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }
    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            signal: AbortSignal.timeout(timeout),
        });
    }
    catch (error) {
        throw new ApiError(error instanceof Error && error.name === 'TimeoutError'
            ? 'The instance did not answer in time.'
            : 'Could not reach the instance: ' + (error instanceof Error ? error.message : String(error)));
    }
    const text = await response.text();
    let data = null;
    try {
        data = text === '' ? null : JSON.parse(text);
    }
    catch {
        data = { raw: text };
    }
    if (!response.ok) {
        const payload = (data ?? {});
        const message = String(payload.message ?? payload.error ?? `HTTP ${response.status}`);
        throw new ApiError(message, response.status);
    }
    return data;
}
export const pairWithCode = (api, code, name) => request('POST', `${normalizeApi(api)}/api/cli/pair`, {
    body: { code, name },
});
export const fetchMe = (api, token) => request('GET', `${api}/api/cli/me`, { token });
export const triggerUpgrade = (api, token) => request('POST', `${api}/api/cli/upgrade`, { token });
export const deployApplication = (api, token, uuid) => request('POST', `${api}/api/v1/deploy`, { token, body: { tag: uuid } });
export const applicationLogs = (api, token, uuid, lines = 100) => request('GET', `${api}/api/v1/applications/${uuid}/logs?lines=${lines}`, { token });
