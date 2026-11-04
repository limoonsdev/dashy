import { h } from './ui/jsx.js';
import { Box, Text } from './ui/index.js';
import { bar, dim, dot, gradient, logoMarkup, painted, palette } from './theme.js';
const rule = (width) => painted('─'.repeat(Math.max(0, width)), palette.line);
export function Boot(props) {
    const { frame } = props;
    const progress = Math.min(1, frame / 14);
    const spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'][frame % 10];
    return (h(Box, { border: false, direction: "column", gap: 1, align: "center" },
        h(Text, null, ' '),
        logoMarkup(frame / 8, 0).map((line) => (h(Text, null, line))),
        h(Text, null, ' '),
        h(Text, { color: palette.muted }, painted('◆ ', palette.accent) + dim('self-hosted control plane')),
        h(Text, null, ' '),
        h(Text, null, bar(progress, Math.min(40, Math.max(20, props.width - 20)))),
        h(Text, { color: palette.faint }, painted(spinner + ' ', palette.brand) + 'loading workspace · ' + Math.round(progress * 100) + '%')));
}
function Header(props) {
    const { state } = props;
    const name = state.me?.instance.name ?? 'Dashy';
    const url = state.me?.instance.url ?? state.pair.api ?? 'not connected';
    const version = state.me?.instance.version ?? '—';
    const clock = state.clock.toTimeString().slice(0, 8);
    const status = state.loading
        ? painted('● syncing', palette.amber)
        : painted('● live', palette.green);
    return (h(Box, { border: true, borderColor: palette.line, paddingX: 1 },
        h(Box, { direction: "row", border: false },
            h(Text, null, painted('◆ ', palette.brand, true) + painted(name, palette.text, true)),
            h(Text, { align: "end", color: palette.faint }, version + '   ' + clock + '   ' + status)),
        h(Text, { color: palette.faint }, dim(url))));
}
function Nav(props) {
    const { state } = props;
    const labels = ['Overview', 'Servers', 'Updates', 'Help'];
    const parts = labels.map((label, index) => {
        const active = index === state.tab;
        const hint = active
            ? painted('▎', palette.accent) + ' ' + painted(String(index + 1) + ' ' + label, palette.text, true)
            : '  ' + dim(String(index + 1) + ' ' + label);
        return hint;
    });
    return (h(Box, { border: false, paddingX: 1 },
        h(Text, null, parts.join('    '))));
}
function Tile(props) {
    return (h(Box, { border: true, paddingX: 1, title: props.label, borderColor: palette.line },
        h(Text, null, painted(String(props.value), props.accent, true)),
        h(Text, { color: palette.faint }, dim(props.hint))));
}
function Overview(props) {
    const me = props.state.me;
    const available = me.update.available;
    const servers = me.servers;
    return (h(Box, { border: false, direction: "column", gap: 1 },
        h(Box, { direction: "row", gap: 1, border: false },
            h(Tile, { label: "Applications", value: me.counts.applications, hint: "deployed", accent: palette.accent }),
            h(Tile, { label: "Databases", value: me.counts.databases, hint: "managed", accent: palette.brandSoft }),
            h(Tile, { label: "Services", value: me.counts.services, hint: "compose", accent: palette.pink }),
            h(Tile, { label: "Servers", value: servers.length, hint: servers.filter((server) => server.reachable).length + ' reachable', accent: palette.green })),
        h(Box, { border: true, title: "Update", borderColor: available ? palette.amber : palette.line, paddingX: 1 },
            available ? (h(Text, { color: palette.amber },
                '▲ ',
                painted('Update available', palette.amber, true) +
                    '  ' +
                    me.update.current +
                    ' → ' +
                    me.update.latest +
                    dim('   press u to install'))) : (h(Text, { color: palette.green }, '● ' + painted('Up to date', palette.green, true) + dim('  running ' + me.update.current + ' · checked ' + (me.update.checked_at ? new Date(me.update.checked_at).toLocaleTimeString() : '—')))),
            me.update.error ? h(Text, { color: palette.faint }, dim('note: ' + me.update.error)) : null),
        h(Box, { direction: "row", gap: 1, border: false },
            h(Box, { border: true, title: "Servers", borderColor: palette.line, paddingX: 1 },
                servers.length === 0 ? h(Text, { color: palette.faint }, dim('No servers yet.')) : null,
                servers.slice(0, 6).map((server) => (h(Text, null, dot(server.reachable) +
                    ' ' +
                    painted(server.name.padEnd(20).slice(0, 20), palette.text) +
                    ' ' +
                    dim(server.ip ?? '—'))))),
            h(Box, { border: true, title: "Session", borderColor: palette.line, paddingX: 1 },
                h(Text, null, painted(me.user?.name ?? 'user', palette.text, true) + dim('  <' + (me.user?.email ?? '') + '>')),
                h(Text, null, dim('team     ') + (me.team?.name ?? '—')),
                h(Text, null, dim('abilities ') + me.abilities.join(', ')),
                h(Text, null, dim('timezone  ') + me.instance.timezone)))));
}
function Servers(props) {
    const me = props.state.me;
    return (h(Box, { border: true, title: 'Servers · ' + me.servers.length, borderColor: palette.line, paddingX: 1 },
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
        h(Text, { color: palette.faint }, dim('  Each server is managed through the same permissions as the web UI.'))));
}
function Updates(props) {
    const me = props.state.me;
    const row = (label, value) => (h(Text, null, '  ' + dim(label.padEnd(16)) + value));
    return (h(Box, { border: false, direction: "column", gap: 1 },
        h(Box, { border: true, title: "Update state", borderColor: me.update.available ? palette.amber : palette.line, paddingX: 1 },
            row('Running', painted(me.update.current, palette.text, true)),
            row('Latest', painted(me.update.latest, me.update.available ? palette.amber : palette.green, true)),
            row('Result', me.update.available ? painted('▲ update available', palette.amber, true) : painted('● up to date', palette.green, true)),
            row('Checked', me.update.checked_at ? new Date(me.update.checked_at).toLocaleString() : 'never'),
            row('Source', 'github.com/limoonsdev/dashy'),
            me.update.error ? row('Error', painted(me.update.error, palette.red)) : null),
        h(Box, { border: true, title: "Actions", borderColor: palette.line, paddingX: 1 },
            h(Text, null, painted('  c', palette.accent, true) + dim('  check GitHub for a new release')),
            h(Text, null, painted('  u', palette.accent, true) + dim('  install the update (asks first)')),
            h(Text, null, painted('  r', palette.accent, true) + dim('  refresh this screen')),
            props.state.confirmUpgrade ? (h(Text, { color: palette.amber }, '  ' + painted('Install the update and restart the instance?  y / n', palette.amber, true))) : null)));
}
function Help() {
    const keys = [
        ['↑ ↓ / k j', 'move between screens'],
        ['1 – 4', 'jump to a screen'],
        ['r', 'refresh instance data'],
        ['c', 'check for an update'],
        ['u', 'install the update'],
        ['q', 'quit the console'],
    ];
    const commands = [
        ['dashy', 'full-screen console'],
        ['dashy connect <code>', 'pair this terminal'],
        ['dashy status', 'instance summary'],
        ['dashy servers', 'servers and reachability'],
        ['dashy check', 'check for an update'],
        ['dashy upgrade', 'install the update'],
        ['dashy logs <uuid>', 'read application logs'],
        ['dashy doctor', 'configuration check'],
    ];
    return (h(Box, { direction: "row", gap: 1, border: false },
        h(Box, { border: true, title: "Keys", borderColor: palette.line, paddingX: 1 }, keys.map(([key, label]) => (h(Text, null, '  ' + painted(key.padEnd(12), palette.accent, true) + dim(label))))),
        h(Box, { border: true, title: "Commands", borderColor: palette.line, paddingX: 1 }, commands.map(([command, label]) => (h(Text, null, '  ' + painted(command.padEnd(24).slice(0, 24), palette.text) + dim(label)))))));
}
function Pair(props) {
    const pair = props.state.pair;
    const cursor = painted('▌', palette.accent);
    const field = (name, label, placeholder, value) => {
        const active = pair.field === name;
        return (h(Text, null, '  ' + (active ? painted('▎ ', palette.accent) : '  ') + dim(label.padEnd(10)) + (value === '' ? dim(placeholder) : painted(value, palette.text)) + (active ? cursor : '')));
    };
    return (h(Box, { border: false, direction: "column", gap: 1, align: "center" },
        h(Text, null, ' '),
        logoMarkup(0.55, 0).map((line) => (h(Text, null, line))),
        h(Text, null, ' '),
        h(Box, { border: true, title: "Connect this terminal", borderColor: palette.brand, paddingX: 2, width: 72 },
            h(Text, { color: palette.faint }, dim('Settings → CLI → Generate a sign-in code')),
            h(Text, null, ' '),
            field('api', 'Instance', 'https://dashy.example.com', pair.api),
            field('code', 'Code', 'DASHY-XXXX-XXXX', pair.code),
            h(Text, null, ' '),
            pair.error ? h(Text, { color: palette.red }, '  ' + pair.error) : null,
            h(Text, { color: palette.faint }, '  ' + dim('Tab switch · Enter connect · Esc quit') + (pair.busy ? painted('  ⠋ pairing…', palette.amber) : '')))));
}
export function Footer(props) {
    const flash = props.state.flash;
    if (flash) {
        const color = flash.tone === 'ok' ? palette.green : flash.tone === 'warn' ? palette.amber : palette.red;
        return (h(Box, { border: false, paddingX: 1 },
            h(Text, { color: color }, flash.text)));
    }
    return (h(Box, { border: false, paddingX: 1 },
        h(Text, { color: palette.faint }, dim('↑↓ navigate  ·  r refresh  ·  c check  ·  u update  ·  ? help  ·  q quit'))));
}
export function Screen(props) {
    const { state, width } = props;
    if (state.phase === 'boot') {
        return h(Boot, { frame: state.bootFrame, width: width });
    }
    if (state.phase === 'pair') {
        return h(Pair, { state: state });
    }
    const body = state.error
        ? (h(Box, { border: true, title: "Error", borderColor: palette.red, paddingX: 1 },
            h(Text, { color: palette.red }, state.error),
            h(Text, { color: palette.faint }, dim('Press r to retry, or run `dashy doctor`.'))))
        : state.tab === 0
            ? h(Overview, { state: state })
            : state.tab === 1
                ? h(Servers, { state: state })
                : state.tab === 2
                    ? h(Updates, { state: state })
                    : h(Help, null);
    return (h(Box, { direction: "column", gap: 1, border: false },
        h(Header, { state: state }),
        h(Nav, { state: state }),
        body,
        h(Footer, { state: state })));
}
export function MiniLogo(phase = 0) {
    return (h(Box, { border: false, direction: "column", gap: 0 }, logoMarkup(phase, 2).map((line) => (h(Text, null, line)))));
}
export function BrandLine() {
    return h(Text, null, gradient('DASHY', 0.3) + dim('  self-hosted control plane'));
}
