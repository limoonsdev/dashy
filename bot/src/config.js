function idSet(value) {
    return new Set(
        String(value ?? '')
            .split(/[,\s]+/)
            .map((entry) => entry.trim().toLowerCase())
            .filter(Boolean),
    );
}

function trimUrl(value) {
    return String(value ?? '').trim().replace(/\/+$/, '');
}

export function loadConfig(env = process.env) {
    return {
        port: Number(env.BOT_PORT || 8787),
        host: env.BOT_HOST || '0.0.0.0',
        dashy: {
            baseUrl: trimUrl(env.DASHY_URL),
            token: String(env.DASHY_API_TOKEN ?? '').trim(),
        },
        discord: {
            applicationId: String(env.DISCORD_APPLICATION_ID ?? '').trim(),
            botToken: String(env.DISCORD_BOT_TOKEN ?? '').trim(),
            publicKey: String(env.DISCORD_PUBLIC_KEY ?? '').trim(),
        },
        telegram: {
            token: String(env.TELEGRAM_BOT_TOKEN ?? '').trim(),
            secret: String(env.TELEGRAM_WEBHOOK_SECRET ?? '').trim(),
        },
        whatsapp: {
            token: String(env.WHATSAPP_TOKEN ?? '').trim(),
            phoneNumberId: String(env.WHATSAPP_PHONE_NUMBER_ID ?? '').trim(),
            verifyToken: String(env.WHATSAPP_VERIFY_TOKEN ?? '').trim(),
            appSecret: String(env.WHATSAPP_APP_SECRET ?? '').trim(),
        },
        allowed: idSet(env.BOT_ALLOWED_USERS),
        admins: idSet(env.BOT_ADMIN_USERS),
        rateLimit: {
            windowMs: Number(env.BOT_RATE_WINDOW_MS || 60000),
            max: Number(env.BOT_RATE_MAX || 20),
        },
    };
}

export function isListed(set, platform, userId) {
    if (!set || set.size === 0) {
        return false;
    }

    const id = String(userId).toLowerCase();

    return set.has(id) || set.has(`${platform}:${id}`);
}
