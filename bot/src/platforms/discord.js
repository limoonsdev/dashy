import { commandEmbed } from '../embeds.js';
import { verifyDiscordSignature } from '../signatures.js';

export async function handleDiscord({ headers, rawBody, config, router }) {
    const signature = headers['x-signature-ed25519'];
    const timestamp = headers['x-signature-timestamp'];

    if (!verifyDiscordSignature(config.discord.publicKey, signature, timestamp, rawBody)) {
        return { status: 401, body: { error: 'invalid request signature' } };
    }

    let payload;

    try {
        payload = JSON.parse(rawBody.toString('utf8'));
    } catch {
        return { status: 400, body: { error: 'invalid json' } };
    }

    if (payload.type === 1) {
        return { status: 200, body: { type: 1 } };
    }

    if (payload.type !== 2) {
        return { status: 200, body: { type: 4, data: { content: 'Unsupported interaction.', flags: 64 } } };
    }

    const name = String(payload.data?.name ?? '');
    const args = (payload.data?.options ?? [])
        .map((option) => String(option.value ?? ''))
        .join(' ');
    const userId = String(payload.member?.user?.id ?? payload.user?.id ?? 'unknown');
    const text = `/${name}${args ? ` ${args}` : ''}`;

    const reply = await router({ platform: 'discord', userId, text });

    // Discord gets a card; the router still returns plain text for the other platforms.
    return {
        status: 200,
        body: {
            type: 4,
            data: { embeds: [commandEmbed(name, reply)], flags: 64 },
        },
    };
}

export function discordCommandDefinitions() {
    return [
        { name: 'help', description: 'List every command.' },
        { name: 'ping', description: 'Check that the bot is alive.' },
        { name: 'version', description: 'Show the Dashy version.' },
        { name: 'status', description: 'Count servers, applications and databases.' },
        { name: 'servers', description: 'List servers and whether they are reachable.' },
        { name: 'apps', description: 'List applications with status and domain.' },
        { name: 'databases', description: 'List databases and their engine.' },
        { name: 'projects', description: 'List projects.' },
        {
            name: 'deployments',
            description: 'Show the latest deployments.',
            options: [{ type: 3, name: 'uuid', description: 'Application uuid', required: false }],
        },
        {
            name: 'deploy',
            description: 'Deploy an application by uuid.',
            options: [{ type: 3, name: 'uuid', description: 'Application uuid', required: true }],
        },
        {
            name: 'domain',
            description: 'Check domain registration over RDAP.',
            options: [{ type: 3, name: 'name', description: 'Domain, such as example.com', required: true }],
        },
        {
            name: 'dns',
            description: 'Show A/AAAA/CNAME/MX/NS/TXT records.',
            options: [{ type: 3, name: 'host', description: 'Hostname to inspect', required: true }],
        },
        {
            name: 'whois',
            description: 'Show the raw WHOIS record.',
            options: [{ type: 3, name: 'name', description: 'Domain, such as example.com', required: true }],
        },
        {
            name: 'ip',
            description: 'Resolve a hostname to its addresses.',
            options: [{ type: 3, name: 'host', description: 'Hostname to resolve', required: true }],
        },
        { name: 'id', description: 'Show your platform user id.' },
        { name: 'fleet', description: 'Summarise servers, applications and unreachable hosts.' },
        {
            name: 'search',
            description: 'Find a server, application or database by name.',
            options: [{ type: 3, name: 'term', description: 'Name to search for', required: true }],
        },
        {
            name: 'app',
            description: 'Show one application by uuid or name.',
            options: [{ type: 3, name: 'name', description: 'Application uuid or name', required: true }],
        },
        {
            name: 'db',
            description: 'Show one database by uuid or name.',
            options: [{ type: 3, name: 'name', description: 'Database uuid or name', required: true }],
        },
    ];
}
