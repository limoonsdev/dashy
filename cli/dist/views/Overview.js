import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, dot, painted, palette } from '../ui/theme.js';
function Tile({ label, value, hint, accent }) {
    return (h(Box, { border: true, paddingX: 1, title: label, borderColor: palette.line },
        h(Text, null, painted(String(value), accent, true)),
        h(Text, { color: palette.faint }, dim(hint))));
}
const when = (value) => (value ? new Date(value).toLocaleTimeString() : 'never');
export function Overview({ state }) {
    const me = state.me;
    const update = me.update;
    return (h(Box, { border: false, direction: "column", gap: 1 },
        h(Box, { direction: "row", gap: 1, border: false },
            h(Tile, { label: "Applications", value: me.counts.applications, hint: "deployed", accent: palette.accent }),
            h(Tile, { label: "Databases", value: me.counts.databases, hint: "managed", accent: palette.brandSoft }),
            h(Tile, { label: "Services", value: me.counts.services, hint: "compose", accent: palette.pink }),
            h(Tile, { label: "Servers", value: me.servers.length, hint: `${me.servers.filter((server) => server.reachable).length} reachable`, accent: palette.green })),
        h(Box, { border: true, title: "Release", borderColor: update.available ? palette.amber : palette.line, paddingX: 1 }, update.available ? (h(Text, null, painted('▲ ', palette.amber, true) +
            painted('Update available', palette.amber, true) +
            '  ' +
            update.current +
            ' → ' +
            update.latest +
            dim('   press u for the notes'))) : (h(Text, null, painted('● ', palette.green) +
            painted('Up to date', palette.green, true) +
            dim(`  ${update.current} · checked ${when(update.checked_at)}`)))),
        h(Box, { direction: "row", gap: 1, border: false },
            h(Box, { border: true, title: "Servers", borderColor: palette.line, paddingX: 1 },
                me.servers.length === 0 ? h(Text, { color: palette.faint }, dim('No servers yet.')) : null,
                me.servers.slice(0, 6).map((server) => (h(Text, null, dot(server.reachable) + ' ' + painted(server.name.padEnd(20).slice(0, 20), palette.text) + ' ' + dim(server.ip ?? '—'))))),
            h(Box, { border: true, title: "Session", borderColor: palette.line, paddingX: 1 },
                h(Text, null, painted(me.user?.name ?? 'user', palette.text, true) + dim('  <' + (me.user?.email ?? '') + '>')),
                h(Text, null, dim('team       ') + (me.team?.name ?? '—')),
                h(Text, null, dim('abilities  ') + me.abilities.join(', ')),
                h(Text, null, dim('timezone   ') + me.instance.timezone)))));
}
