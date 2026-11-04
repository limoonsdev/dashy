import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, dot, painted, palette } from '../ui/theme.js';
export function Servers({ state }) {
    const me = state.me;
    return (h(Box, { border: true, title: `Servers · ${me.servers.length}`, borderColor: palette.line, paddingX: 1 },
        h(Text, { color: palette.faint }, dim('  NAME                ADDRESS             STATUS')),
        me.servers.length === 0 ? (h(Text, { color: palette.faint }, dim('  No servers in this team yet.'))) : null,
        me.servers.map((server) => (h(Text, null, '  ' +
            painted(server.name.padEnd(20).slice(0, 20), palette.text, true) +
            ' ' +
            (server.ip ?? '—').padEnd(19) +
            ' ' +
            dot(server.reachable) +
            ' ' +
            (server.reachable ? painted('online', palette.green) : painted('unreachable', palette.red))))),
        h(Text, null, ' '),
        h(Text, { color: palette.faint }, dim('  Same permissions as the web UI. Deploys and updates use the token abilities.'))));
}
