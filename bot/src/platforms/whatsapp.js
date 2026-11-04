import { limit } from '../format.js';
import { verifyWhatsappSignature } from '../signatures.js';

export function whatsappChallenge(query, config) {
    if (query.get('hub.mode') !== 'subscribe') {
        return { status: 400, body: 'invalid mode' };
    }

    if (!config.whatsapp.verifyToken || query.get('hub.verify_token') !== config.whatsapp.verifyToken) {
        return { status: 403, body: 'invalid verify token' };
    }

    return { status: 200, body: query.get('hub.challenge') ?? '' };
}

async function sendMessage(config, to, text, fetchImpl = fetch) {
    await fetchImpl(`https://graph.facebook.com/v20.0/${config.whatsapp.phoneNumberId}/messages`, {
        method: 'POST',
        headers: {
            'content-type': 'application/json',
            authorization: `Bearer ${config.whatsapp.token}`,
        },
        body: JSON.stringify({
            messaging_product: 'whatsapp',
            to,
            type: 'text',
            text: { body: limit(text, 4000) },
        }),
        signal: AbortSignal.timeout(10000),
    });
}

export async function handleWhatsapp({ headers, rawBody, config, router, fetchImpl = fetch }) {
    if (!config.whatsapp.appSecret) {
        return { status: 503, body: { error: 'whatsapp is not configured' } };
    }

    if (!verifyWhatsappSignature(config.whatsapp.appSecret, headers['x-hub-signature-256'], rawBody)) {
        return { status: 401, body: { error: 'invalid signature' } };
    }

    let payload;

    try {
        payload = JSON.parse(rawBody.toString('utf8'));
    } catch {
        return { status: 400, body: { error: 'invalid json' } };
    }

    for (const entry of payload.entry ?? []) {
        for (const change of entry.changes ?? []) {
            const message = change.value?.messages?.[0];

            if (!message?.text?.body || message.type !== 'text') {
                continue;
            }

            const userId = String(message.from ?? 'unknown');
            const reply = await router({ platform: 'whatsapp', userId, text: message.text.body });

            if (config.whatsapp.phoneNumberId && config.whatsapp.token) {
                await sendMessage(config, message.from, reply, fetchImpl);
            }
        }
    }

    return { status: 200, body: { ok: true } };
}
