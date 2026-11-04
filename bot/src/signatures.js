import { createHmac, createPublicKey, timingSafeEqual, verify } from 'node:crypto';

// Ed25519 SubjectPublicKeyInfo header. Discord sends the raw 32-byte key, so it
// has to be wrapped before node:crypto will accept it.
const ED25519_SPKI_PREFIX = Buffer.from('302a300506032b6570032100', 'hex');

function safeEqual(left, right) {
    const a = Buffer.from(String(left ?? ''), 'utf8');
    const b = Buffer.from(String(right ?? ''), 'utf8');

    if (a.length !== b.length) {
        return false;
    }

    return timingSafeEqual(a, b);
}

export function verifyDiscordSignature(publicKeyHex, signatureHex, timestamp, rawBody) {
    if (!publicKeyHex || !signatureHex || !timestamp) {
        return false;
    }

    try {
        const rawKey = Buffer.from(publicKeyHex, 'hex');

        if (rawKey.length !== 32) {
            return false;
        }

        const key = createPublicKey({
            key: Buffer.concat([ED25519_SPKI_PREFIX, rawKey]),
            format: 'der',
            type: 'spki',
        });

        const message = Buffer.concat([
            Buffer.from(String(timestamp), 'utf8'),
            Buffer.isBuffer(rawBody) ? rawBody : Buffer.from(rawBody ?? ''),
        ]);

        return verify(null, message, key, Buffer.from(signatureHex, 'hex'));
    } catch {
        return false;
    }
}

export function verifyWhatsappSignature(appSecret, header, rawBody) {
    if (!appSecret || !header) {
        return false;
    }

    const expected = 'sha256=' + createHmac('sha256', appSecret)
        .update(Buffer.isBuffer(rawBody) ? rawBody : Buffer.from(rawBody ?? ''))
        .digest('hex');

    return safeEqual(expected, header);
}

export function verifyTelegramSecret(secret, header) {
    if (!secret) {
        return false;
    }

    return safeEqual(secret, header);
}
