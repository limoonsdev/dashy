import { bullets, oneLine, statusLabel } from './format.js';

export const COMMANDS = [
    { name: 'help', description: 'List every command.', usage: 'help' },
    { name: 'ping', description: 'Check that the bot is alive.', usage: 'ping' },
    { name: 'version', description: 'Show the Dashy version.', usage: 'version' },
    { name: 'status', description: 'Count servers, applications and databases.', usage: 'status' },
    { name: 'servers', description: 'List servers and whether they are reachable.', usage: 'servers' },
    { name: 'apps', description: 'List applications with status and domain.', usage: 'apps' },
    { name: 'databases', description: 'List databases and their engine.', usage: 'databases' },
    { name: 'projects', description: 'List projects.', usage: 'projects' },
    { name: 'deployments', description: 'Show the latest deployments.', usage: 'deployments [app-uuid]' },
    { name: 'deploy', description: 'Deploy an application by uuid.', usage: 'deploy <app-uuid>', admin: true },
    { name: 'domain', description: 'Check domain registration over RDAP.', usage: 'domain example.com' },
    { name: 'dns', description: 'Show A/AAAA/CNAME/MX/NS/TXT records.', usage: 'dns example.com' },
    { name: 'whois', description: 'Show the raw WHOIS record.', usage: 'whois example.com' },
    { name: 'ip', description: 'Resolve a hostname to its addresses.', usage: 'ip example.com' },
    { name: 'id', description: 'Show your platform user id.', usage: 'id' },
    { name: 'fleet', description: 'Summarise servers, applications and unreachable hosts.', usage: 'fleet' },
    { name: 'search', description: 'Find a server, application or database by name.', usage: 'search <term>' },
    { name: 'app', description: 'Show one application by uuid or name.', usage: 'app <uuid|name>' },
    { name: 'db', description: 'Show one database by uuid or name.', usage: 'db <uuid|name>' },
];

function requireApi(dashy) {
    if (!dashy) {
        throw new Error('DASHY_URL and DASHY_API_TOKEN are not configured.');
    }

    return dashy;
}

export function helpText() {
    const lines = COMMANDS.map((command) => `/${command.name} — ${command.description}`);

    return ['Dashy bot commands:', ...lines].join('\n');
}

export function createCommands({ dashy, lookup, config, startedAt = Date.now() }) {
    const api = () => requireApi(dashy);

    return [
        {
            name: 'help',
            run: async () => helpText(),
        },
        {
            name: 'ping',
            run: async () => `pong · uptime ${Math.round((Date.now() - startedAt) / 1000)}s`,
        },
        {
            name: 'id',
            run: async (ctx) => `platform: ${ctx.platform}\nuser: ${ctx.userId}`,
        },
        {
            name: 'version',
            run: async () => {
                const data = await api().version();

                return data ? `Dashy ${data.version ?? ''} (${data.name ?? 'instance'})`.replace(/\s+/g, ' ').trim() : 'No version returned.';
            },
        },
        {
            name: 'status',
            run: async () => {
                const client = api();
                const [servers, applications, databases] = await Promise.all([
                    client.servers(),
                    client.applications(),
                    client.databases(),
                ]);

                return [
                    'Dashy status',
                    `servers: ${servers?.length ?? 0}`,
                    `applications: ${applications?.length ?? 0}`,
                    `databases: ${databases?.length ?? 0}`,
                ].join('\n');
            },
        },
        {
            name: 'servers',
            run: async () => {
                const servers = await api().servers();
                const rows = (servers ?? []).slice(0, 20).map((server) => {
                    const reachable = server.is_reachable ?? server.settings?.is_reachable;
                    const state = reachable === false ? 'unreachable' : 'ok';

                    return `${oneLine(server.name ?? server.uuid, 40)} — ${state}`;
                });

                return bullets(rows, 'No servers.');
            },
        },
        {
            name: 'apps',
            run: async () => {
                const applications = await api().applications();
                const rows = (applications ?? []).slice(0, 15).map((application) => {
                    const domain = String(application.fqdn ?? '').split(',')[0] || 'no domain';

                    return `${oneLine(application.name ?? application.uuid, 32)} — ${statusLabel(application.status)} — ${oneLine(domain, 40)}`;
                });

                return bullets(rows, 'No applications.');
            },
        },
        {
            name: 'databases',
            run: async () => {
                const databases = await api().databases();
                const rows = (databases ?? []).slice(0, 15).map((database) => (
                    `${oneLine(database.name ?? database.uuid, 32)} — ${database.database_type ?? database.type ?? 'database'} — ${statusLabel(database.status)}`
                ));

                return bullets(rows, 'No databases.');
            },
        },
        {
            name: 'projects',
            run: async () => {
                const projects = await api().projects();
                const rows = (projects ?? []).slice(0, 20).map((project) => oneLine(project.name ?? project.uuid, 48));

                return bullets(rows, 'No projects.');
            },
        },
        {
            name: 'deployments',
            run: async (ctx) => {
                const deployments = await api().deployments(ctx.args[0]);
                const rows = (deployments ?? []).slice(0, 10).map((deployment) => (
                    `${oneLine(deployment.deployment_uuid ?? deployment.uuid ?? 'deployment', 40)} — ${statusLabel(deployment.status)}`
                ));

                return bullets(rows, 'No deployments.');
            },
        },
        {
            name: 'deploy',
            admin: true,
            run: async (ctx) => {
                const uuid = ctx.args[0];

                if (!uuid) {
                    return 'Usage: /deploy <application-uuid>';
                }

                const result = await api().deploy(uuid);
                const message = result?.message ?? 'Deployment queued.';

                return `${message}\n${uuid}`;
            },
        },
        {
            name: 'domain',
            run: async (ctx) => {
                const result = await lookup.rdap(ctx.args[0]);

                if (result.status === 'invalid' || result.status === 'unknown') {
                    return result.error ?? 'Lookup failed.';
                }

                if (result.status === 'available') {
                    return `${result.domain} looks available.`;
                }

                return [
                    `${result.domain} is registered.`,
                    `registrar: ${result.registrar ?? 'unknown'}`,
                    `created: ${result.created ?? 'unknown'}`,
                    `expires: ${result.expires ?? 'unknown'}`,
                    `nameservers: ${(result.nameservers ?? []).join(', ') || 'unknown'}`,
                ].join('\n');
            },
        },
        {
            name: 'dns',
            run: async (ctx) => {
                const result = await lookup.dnsRecords(ctx.args[0]);

                if (result.status !== 'ok') {
                    return result.error ?? 'Lookup failed.';
                }

                if (result.records.length === 0) {
                    return `No records found for ${result.domain}.`;
                }

                return [
                    `${result.domain}`,
                    ...result.records.slice(0, 25).map((record) => `${record.type} ${record.value}`),
                ].join('\n');
            },
        },
        {
            name: 'whois',
            run: async (ctx) => {
                const result = await lookup.whois(ctx.args[0]);

                if (!result.raw) {
                    return result.error ?? 'Lookup failed.';
                }

                return `${result.domain} (${result.status})\n${result.raw}`;
            },
        },
        {
            name: 'ip',
            run: async (ctx) => {
                const result = await lookup.findIp(ctx.args[0]);

                if (result.status !== 'ok') {
                    return result.error ?? 'Lookup failed.';
                }

                return `${result.domain}\n${result.addresses.join('\n')}`;
            },
        },
        {
            name: 'fleet',
            run: async () => {
                const client = api();
                const [servers, applications, databases] = await Promise.all([
                    client.servers(),
                    client.applications(),
                    client.databases(),
                ]);
                const all = servers ?? [];
                const unreachable = all.filter((server) => (server.is_reachable ?? server.settings?.is_reachable) === false);

                return [
                    `servers: ${all.length} (${unreachable.length} unreachable)`,
                    `applications: ${applications?.length ?? 0}`,
                    `databases: ${databases?.length ?? 0}`,
                    unreachable.length ? `check: ${unreachable.slice(0, 8).map((server) => oneLine(server.name ?? server.uuid, 32)).join(', ')}` : 'everything looks reachable',
                ].join('\n');
            },
        },
        {
            name: 'search',
            run: async (ctx) => {
                const term = String(ctx.args.join(' ') ?? '').trim().toLowerCase();

                if (term === '') {
                    return 'Usage: /search <term>';
                }

                const client = api();
                const [servers, applications, databases] = await Promise.all([
                    client.servers(),
                    client.applications(),
                    client.databases(),
                ]);
                const matches = [
                    ...(servers ?? []).filter((item) => String(item.name ?? '').toLowerCase().includes(term)).map((item) => `server: ${oneLine(item.name, 40)} — ${item.uuid}`),
                    ...(applications ?? []).filter((item) => String(item.name ?? '').toLowerCase().includes(term)).map((item) => `app: ${oneLine(item.name, 40)} — ${item.uuid}`),
                    ...(databases ?? []).filter((item) => String(item.name ?? '').toLowerCase().includes(term)).map((item) => `database: ${oneLine(item.name, 40)} — ${item.uuid}`),
                ];

                return bullets(matches.slice(0, 20), `Nothing matches “${term}”.`);
            },
        },
        {
            name: 'app',
            run: async (ctx) => {
                const needle = String(ctx.args.join(' ') ?? '').trim().toLowerCase();

                if (needle === '') {
                    return 'Usage: /app <uuid|name>';
                }

                const applications = await api().applications();
                const match = (applications ?? []).find((application) => String(application.uuid ?? '').toLowerCase() === needle
                    || String(application.name ?? '').toLowerCase() === needle);

                if (!match) {
                    return `No application matches “${needle}”.`;
                }

                return [
                    oneLine(match.name ?? match.uuid, 60),
                    `status: ${statusLabel(match.status)}`,
                    `domain: ${String(match.fqdn ?? '').split(',')[0] || 'none'}`,
                    `uuid: ${match.uuid}`,
                ].join('\n');
            },
        },
        {
            name: 'db',
            run: async (ctx) => {
                const needle = String(ctx.args.join(' ') ?? '').trim().toLowerCase();

                if (needle === '') {
                    return 'Usage: /db <uuid|name>';
                }

                const databases = await api().databases();
                const match = (databases ?? []).find((database) => String(database.uuid ?? '').toLowerCase() === needle
                    || String(database.name ?? '').toLowerCase() === needle);

                if (!match) {
                    return `No database matches “${needle}”.`;
                }

                return [
                    oneLine(match.name ?? match.uuid, 60),
                    `engine: ${match.database_type ?? match.type ?? 'database'}`,
                    `status: ${statusLabel(match.status)}`,
                    `uuid: ${match.uuid}`,
                ].join('\n');
            },
        },
    ];
}
