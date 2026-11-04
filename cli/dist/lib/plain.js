import { h } from '../ui/jsx.js';
import { render } from '../ui/render.js';
import { Box, Text } from '../ui/index.js';
import { dim, dot, painted, palette } from '../ui/theme.js';
const PANEL_WIDTH = 88;
function panel(title, lines, borderColor = palette.line) {
    const element = h(Box, { border: true, title: title, borderColor: borderColor, paddingX: 1 }, lines);
    process.stdout.write(render(element, PANEL_WIDTH).join('\n') + '\n');
}
export function printMe(me) {
    const line = (label, value) => (h(Text, null, '  ' + dim(label.padEnd(16)) + value));
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
export function printServers(me) {
    const header = (h(Text, { color: palette.faint }, dim('  ' + 'NAME'.padEnd(22) + 'ADDRESS'.padEnd(20) + 'STATUS')));
    const rows = me.servers.map((server) => (h(Text, null, '  ' + painted(server.name.padEnd(22).slice(0, 22), palette.text) + (server.ip ?? '—').padEnd(20) + dot(server.reachable) + ' ' + (server.reachable ? painted('online', palette.green) : painted('unreachable', palette.red)))));
    panel('Servers · ' + me.servers.length, [header, ...rows]);
}
export function printPair(result) {
    panel('Connected', [
        h(Text, null, '  ' + painted('● ', palette.green) + 'Paired as ' + painted(result.user.email, palette.text, true)),
        h(Text, null, '  ' + dim('Instance  ') + result.instance.name + '  ' + dim(result.instance.url)),
        h(Text, null, '  ' + dim('Team      ') + (result.team?.name ?? '—')),
        h(Text, null, '  ' + dim('Expires   ') + new Date(result.expires_at).toLocaleString()),
        h(Text, null, ' '),
        h(Text, null, '  ' + dim('Run `dashy` for the full-screen console.')),
    ], palette.green);
}
export function printError(message) {
    process.stderr.write(painted('✖ ', palette.red) + message + '\n');
}
