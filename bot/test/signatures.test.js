import assert from 'node:assert/strict';
import { createHmac, generateKeyPairSync, sign } from 'node:crypto';
import test from 'node:test';
import { verifyDiscordSignature, verifyTelegramSecret, verifyWhatsappSignature } from '../src/signatures.js';

const { publicKey, privateKey } = generateKeyPairSync('ed25519');
const rawPublicKey = publicKey.export({ format: 'der', type: 'spki' }).subarray(-32).toString('hex');
const timestamp = '1700000000';

function signDiscord(body) {
    return sign(null, Buffer.concat([Buffer.from(timestamp), body]), privateKey).toString('hex');
}

test('discord: accepts a valid signature', () => {
    const body = Buffer.from(JSON.stringify({ type: 1 }));

    assert.equal(verifyDiscordSignature(rawPublicKey, signDiscord(body), timestamp, body), true);
});

test('discord: rejects a tampered body', () => {
    const body = Buffer.from(JSON.stringify({ type: 1 }));
    const signature = signDiscord(body);

    assert.equal(verifyDiscordSignature(rawPublicKey, signature, timestamp, Buffer.from(JSON.stringify({ type: 2 }))), false);
});

test('discord: rejects a wrong timestamp or missing headers', () => {
    const body = Buffer.from('{}');

    assert.equal(verifyDiscordSignature(rawPublicKey, signDiscord(body), '1700000001', body), false);
    assert.equal(verifyDiscordSignature('', '', '', body), false);
    assert.equal(verifyDiscordSignature('not-hex', 'not-hex', timestamp, body), false);
});

test('whatsapp: accepts the correct HMAC and rejects others', () => {
    const body = Buffer.from(JSON.stringify({ entry: [] }));
    const header = 'sha256=' + createHmac('sha256', 'app-secret').update(body).digest('hex');

    assert.equal(verifyWhatsappSignature('app-secret', header, body), true);
    assert.equal(verifyWhatsappSignature('app-secret', 'sha256=deadbeef', body), false);
    assert.equal(verifyWhatsappSignature('other-secret', header, body), false);
    assert.equal(verifyWhatsappSignature('', header, body), false);
});

test('telegram: compares the secret token', () => {
    assert.equal(verifyTelegramSecret('s3cret', 's3cret'), true);
    assert.equal(verifyTelegramSecret('s3cret', 'nope'), false);
    assert.equal(verifyTelegramSecret('', ''), false);
});
