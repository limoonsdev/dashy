export class DashyApiError extends Error {
    constructor(message, status = 0) {
        super(message);
        this.name = 'DashyApiError';
        this.status = status;
    }
}

function unwrap(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    if (payload?.data && typeof payload.data === 'object') {
        return payload.data;
    }

    return payload ?? null;
}

export function createDashyClient({ baseUrl, token, fetchImpl = fetch, timeoutMs = 10000 }) {
    const base = String(baseUrl ?? '').replace(/\/+$/, '');

    if (!base || !token) {
        return null;
    }

    async function request(path, options = {}) {
        const response = await fetchImpl(`${base}/api/v1${path}`, {
            ...options,
            headers: {
                accept: 'application/json',
                authorization: `Bearer ${token}`,
                'content-type': 'application/json',
                ...(options.headers ?? {}),
            },
            signal: AbortSignal.timeout(timeoutMs),
        });

        const text = await response.text();
        let body = null;

        if (text !== '') {
            try {
                body = JSON.parse(text);
            } catch {
                body = { message: text.slice(0, 200) };
            }
        }

        if (!response.ok) {
            const message = body?.message ?? body?.error ?? `Dashy returned HTTP ${response.status}.`;

            throw new DashyApiError(String(message), response.status);
        }

        return unwrap(body);
    }

    return {
        request,
        version: () => request('/version'),
        servers: () => request('/servers'),
        applications: () => request('/applications'),
        databases: () => request('/databases'),
        projects: () => request('/projects'),
        deployments: (uuid) => request(uuid ? `/deployments/${encodeURIComponent(uuid)}` : '/deployments'),
        deploy: (uuid) => request('/deploy', {
            method: 'POST',
            body: JSON.stringify({ uuid }),
        }),
    };
}
