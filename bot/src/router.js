import { isListed } from './config.js';
import { helpText } from './commands.js';

export function parseCommand(text) {
    let value = String(text ?? '').trim();

    if (value === '') {
        return { name: '', args: [] };
    }

    value = value.replace(/^[!/]/, '');
    // Telegram sends "/cmd@BotName arg" in groups.
    value = value.replace(/^([a-z0-9_-]+)@\S+/i, '$1');

    const [name, ...args] = value.split(/\s+/);

    return { name: name.toLowerCase(), args };
}

export function createRouter({ commands, config, now = () => Date.now() }) {
    const byName = new Map(commands.map((command) => [command.name, command]));
    const buckets = new Map();

    function rateLimited(key) {
        const windowMs = config.rateLimit?.windowMs ?? 60000;
        const max = config.rateLimit?.max ?? 20;
        const current = now();
        const bucket = buckets.get(key);

        if (!bucket || current >= bucket.resetAt) {
            buckets.set(key, { count: 1, resetAt: current + windowMs });

            if (buckets.size > 5000) {
                for (const [entryKey, entry] of buckets) {
                    if (current >= entry.resetAt) {
                        buckets.delete(entryKey);
                    }
                }
            }

            return false;
        }

        bucket.count++;

        return bucket.count > max;
    }

    return async function handle({ platform, userId, text, isAdmin }) {
        const key = `${platform}:${userId}`;

        if (isListed(config.allowed, platform, userId) === false && config.allowed?.size > 0) {
            return 'You are not allowed to use this bot.';
        }

        if (rateLimited(key)) {
            return 'Too many commands. Wait a minute and try again.';
        }

        const { name, args } = parseCommand(text);

        if (name === '') {
            return helpText();
        }

        const command = byName.get(name);

        if (!command) {
            return `Unknown command: ${name}\n\n${helpText()}`;
        }

        const admin = isAdmin ?? isListed(config.admins, platform, userId);

        if (command.admin && admin !== true) {
            return 'This command requires an admin user.';
        }

        try {
            return await command.run({ platform, userId, args, isAdmin: admin === true });
        } catch (error) {
            return `Command failed: ${error?.message ?? 'unknown error'}`;
        }
    };
}
