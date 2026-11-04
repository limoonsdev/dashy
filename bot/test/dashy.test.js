import assert from 'node:assert/strict';
import test from 'node:test';
import { createDashyClient, DashyApiError } from '../src/dashy.js';

function fakeFetch(payload, { status = 200, ok = true } = {}) {
    const calls = [];

    const impl = async (url, options) => {
        calls.push({ url, options });

        return {
            ok,
            status,
            text: async () => (typeof payload === 'string' ? payload : JSON.stringify(payload)),
        };
    };

    impl.calls = calls;

    return impl;
}

test('client is disabled without url or token', () => {
    assert.equal(createDashyClient({ baseUrl: '', token: 'x' }), null);
    assert.equal(createDashyClient({ baseUrl: 'https://dashy.test', token: '' }), null);
});

test('sends a bearer token and unwraps array payloads', async () => {
    const fetchImpl = fakeFetch([{ uuid: 'a' }]);
    const client = createDashyClient({ baseUrl: 'https://dashy.test/', token: 'secret', fetchImpl });

    const servers = await client.servers();

    assert.deepEqual(servers, [{ uuid: 'a' }]);
    assert.equal(fetchImpl.calls[0].url, 'https://dashy.test/api/v1/servers');
    assert.equal(fetchImpl.calls[0].options.headers.authorization, 'Bearer secret');
});

test('unwraps data envelopes', async () => {
    const client = createDashyClient({
        baseUrl: 'https://dashy.test',
        token: 'secret',
        fetchImpl: fakeFetch({ data: [{ uuid: 'b' }] }),
    });

    assert.deepEqual(await client.applications(), [{ uuid: 'b' }]);
});

test('deploy posts the uuid', async () => {
    const fetchImpl = fakeFetch({ message: 'queued' });
    const client = createDashyClient({ baseUrl: 'https://dashy.test', token: 'secret', fetchImpl });

    assert.deepEqual(await client.deploy('abc'), { message: 'queued' });
    assert.equal(fetchImpl.calls[0].options.method, 'POST');
    assert.equal(fetchImpl.calls[0].options.body, JSON.stringify({ uuid: 'abc' }));
});

test('throws a typed error with the API message', async () => {
    const client = createDashyClient({
        baseUrl: 'https://dashy.test',
        token: 'secret',
        fetchImpl: fakeFetch({ message: 'Unauthenticated.' }, { status: 401, ok: false }),
    });

    await assert.rejects(() => client.servers(), (error) => {
        assert.ok(error instanceof DashyApiError);
        assert.equal(error.status, 401);
        assert.equal(error.message, 'Unauthenticated.');

        return true;
    });
});
