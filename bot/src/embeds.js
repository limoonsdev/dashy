/**
 * Discord embed helpers. Replies stay plain text for every other platform, so
 * Discord turns the same text into a richer card without changing the router.
 */

export const DEFAULT_COLOR = 0x6b16ed;

const TITLES = {
    help: 'Commands',
    ping: 'Pong',
    version: 'Version',
    status: 'Fleet status',
    servers: 'Servers',
    apps: 'Applications',
    databases: 'Databases',
    projects: 'Projects',
    deployments: 'Deployments',
    deploy: 'Deployment',
    domain: 'Domain',
    dns: 'DNS records',
    whois: 'WHOIS',
    ip: 'Addresses',
    fleet: 'Fleet',
    search: 'Search',
    app: 'Application',
    db: 'Database',
    id: 'Identity',
};

export function embedTitle(name) {
    return TITLES[String(name ?? '').toLowerCase()] ?? 'Dashy';
}

export function commandEmbed(name, content, { color = DEFAULT_COLOR } = {}) {
    const text = String(content ?? '').trim();
    const failed = /^(unknown command|command failed|too many commands|this command requires|the assistant|usage:)/i.test(text);

    return {
        title: embedTitle(name),
        description: text.length > 3900 ? `${text.slice(0, 3900)}…` : text || 'No output.',
        color: failed ? 0xef4444 : color,
        timestamp: new Date().toISOString(),
        footer: { text: 'Dashy' },
    };
}

export function statusEmbed({ servers = 0, applications = 0, databases = 0, unreachable = 0, version = '' } = {}, { color = DEFAULT_COLOR } = {}) {
    return {
        title: 'Fleet status',
        color: unreachable > 0 ? 0xf59e0b : color,
        timestamp: new Date().toISOString(),
        fields: [
            { name: 'Servers', value: String(servers), inline: true },
            { name: 'Applications', value: String(applications), inline: true },
            { name: 'Databases', value: String(databases), inline: true },
            { name: 'Unreachable', value: String(unreachable), inline: true },
            ...(version ? [{ name: 'Version', value: version, inline: true }] : []),
        ],
        footer: { text: 'Dashy' },
    };
}
