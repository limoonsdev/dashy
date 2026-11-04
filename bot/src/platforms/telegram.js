import { limit } from '../format.js';
import { verifyTelegramSecret } from '../signatures.js';

async function sendMessage(token, chatId, text, fetchImpl = fetch) {
    await fetchImpl(`https://api.telegram.org/bot${token}/sendMessage`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ chat_id: chatId, text, disable_web_page_preview: true }),
        signal: AbortSignal.timeout(10000),
    });
}

export async function handleTelegram({ headers, rawBody, config, router, fetchImpl = fetch }) {
    if (!config.telegram.secret) {
        return { status: 503, body: { error: 'telegram is not configured' } };
    }

    if (!verifyTelegramSecret(config.telegram.secret, headers['x-telegram-bot-api-secret-token'])) {
        return { status: 401, body: { error: 'invalid secret token' } };
    }

    let update;

    try {
        update = JSON.parse(rawBody.toString('utf8'));
    } catch {
        return { status: 400, body: { error: 'invalid json' } };
    }

    const message = update.message ?? update.edited_message;

    if (!message?.text || !message.chat?.id) {
        return { status: 200, body: { ok: true } };
    }

    const userId = String(message.from?.id ?? message.chat.id);
    const reply = await router({ platform: 'telegram', userId, text: message.text });

    await sendMessage(config.telegram.token, message.chat.id, limit(reply, 4000), fetchImpl);

    return { status: 200, body: { ok: true } };
}
