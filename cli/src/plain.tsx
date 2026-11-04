import { h, type Element } from './ui/jsx.js';
import { render } from './ui/render.js';
import { Box, Text } from './ui/index.js';
import { dim, dot, painted, palette } from './theme.js';
import type { Me } from './api.js';

const PANEL_WIDTH = 88;

function panel(title: string, lines: Element[], borderColor: string = palette.line): void {
    const element = <Box border title={title} borderColor={borderColor} paddingX={1}>{lines}</Box>;

    process.stdout.write(render(element, PANEL_WIDTH).join('\n') + '\n');
}

export function printMe(me: Me): void {
    const line = (label: string, value: string): Element => (
        <Text>{'  ' + dim(label.padEnd(16)) + value}</Text>
    );

    panel(me.instance.name + ' · ' + me.instance.version, [
        line('URL', me.instance.url),
        line('User', (me.user?.email ?? '—') + (me.team ? '  ·  ' + me.team.name : '')),
        line('Servers', me.counts.servers + ' total, ' + me.servers.filter((server) => server.reachable).length + ' reachable'),
        line('Applications', String(me.counts.applications)),
        line('Databases', String(me.counts.databases)),
        line('Services', String(me.counts.services)),
        line('Update', me.update.available
            ? painted('▲ ' + me.update.current + ' → ' + me.update.latest, palette.amber, true)
            : painted('● up to date (' + me.update.current + ')', palette.green, true)),
    ]);
}

export function printServers(me: Me): void {
    const header = (
        <Text color={palette.faint}>
            {dim('  ' + 'NAME'.padEnd(22) + 'ADDRESS'.padEnd(20) + 'STATUS')}
        </Text>
    );

    const rows = me.servers.map((server) => (
        <Text>
            {'  ' + painted(server.name.padEnd(22).slice(0, 22), palette.text) + (server.ip ?? '—').padEnd(20) + dot(server.reachable) + ' ' + (server.reachable ? painted('online', palette.green) : painted('unreachable', palette.red))}
        </Text>
    ));

    panel('Servers · ' + me.servers.length, [header, ...rows]);
}

export function printPair(result: { user: { email: string }; team: { name: string } | null; instance: { name: string; url: string }; expires_at: string }): void {
    panel('Connected', [
        <Text>{'  ' + painted('● ', palette.green) + 'Paired as ' + painted(result.user.email, palette.text, true)}</Text>,
        <Text>{'  ' + dim('Instance  ') + result.instance.name + '  ' + dim(result.instance.url)}</Text>,
        <Text>{'  ' + dim('Team      ') + (result.team?.name ?? '—')}</Text>,
        <Text>{'  ' + dim('Expires   ') + new Date(result.expires_at).toLocaleString()}</Text>,
        <Text>{' '}</Text>,
        <Text>{'  ' + dim('Run `dashy` for the full-screen console.')}</Text>,
    ], palette.green);
}

export function printError(message: string): void {
    process.stderr.write(painted('✖ ', palette.red) + message + '\n');
}
