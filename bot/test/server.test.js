import assert from 'node:assert/strict';
import { generateKeyPairSync, sign } from 'node:crypto';
import test from 'node:test';
import { createBotServer } from '../src/server.js';
import { createRouter } from '../src/router.js';
import { loadConfig } from '../src/config.js';

const { publicKey, privateKey } = generateKeyPairSync('ed25519');
const rawPublicKey = publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('hex');

function startServer() {
    const config = loadConfig({
        DISCORD_PUBLIC_KEY: rawPublicKey,
        TELEGRAM_BOT_TOKEN: 'tg-token',
        TELEGRAM_WEBHOOK_SECRET: 'tg-secret',
        WHATSAPP_APP_SECRET: 'wa-secret',
        WHATSAPP_VERIFY_TOKEN: 'verify-me',
        BOT_ADMIN_USERS: 'admin',
        BOT_RATE_MAX: '100',
    });

    const commands = [{ name: 'ping', run: async () => 'pong' }];
    const router = createRouter({ commands, config });
    const server = createBotServer({ config, router });

    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => {
            resolve({ server, base: `http://127.0.0.1:${server.address().port}` });
        });
    });
}

function stop(server) {
    server.closeAllConnections?.();
    server.close();
}

function discordHeaders(body) {
    const timestamp = '1700000000';
    const signature = sign(null, Buffer.concat([Buffer.from(timestamp), body]), privateKey).toString('hex');

    return {
        'content-type': 'application/json',
        'x-signature-ed25519': signature,
        'x-signature-timestamp': timestamp,
    };
}

test('health endpoint reports configured platforms', async (t) => {
    const { server, base } = await startServer();
    t.after(() => stop(server));

    const response = await fetch(`${base}/health`);
    const body = await response.json();

    assert.equal(response.status, 200);
    assert.equal(body.ok, true);
    assert.equal(body.platforms.discord, true);
    assert.equal(body.platforms.telegram, true);
    assert.equal(body.platforms.whatsapp, true);
});

test('unknown route is a 404', async (t) => {
    const { server, base } = await startServer();
    t.after(() => stop(server));

    assert.equal((await fetch(`${base}/nope`)).status, 404);
});

test('discord PING handshake', async (t) => {
    const { server, base } = await startServer();
    t.after(() => stop(server));

    const body = Buffer.from(JSON.stringify({ type: 1 }));
    const response = await fetch(`${base}/discord`, {
        method: 'POST',
        headers: discordHeaders(body),
        body,
    });

    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), { type: 1 });
});

test('discord rejects a bad signature', async (t) => {
    const { server, base } = await startServer();
    t.after(() => stop(server));

    const response = await fetch(`${base}/discord`, {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
            'x-signature-ed25519': '00'.repeat(64),
            'x-signature-timestamp': '1700000000',
        },
        body: JSON.stringify({ type: 1 }),
    });

    assert.equal(response.status, 401);
});

test('whatsapp verification challenge', async (t) => {
    const { server, base } = await startServer();
    t.after(() => stop(server));

    const ok = await fetch(`${base}/whatsapp?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=123`);
    assert.equal(ok.status, 200);
    assert.equal(await ok.text(), '123');

    const denied = await fetch(`${base}/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=123`);
    assert.equal(denied.status, 403);
});

test('telegram rejects a wrong secret', async (t) => {
    const { server, base } = await startServer();
    t.after(() => stop(server));

    const response = await fetch(`${base}/telegram`, {
        method: 'POST',
        headers: { 'content-type': 'application/json', 'x-telegram-bot-api-secret-token': 'wrong' },
        body: JSON.stringify({ message: { text: '/ping', chat: { id: 1 }, from: { id: 1 } } }),
    });

    assert.equal(response.status, 401);
});
