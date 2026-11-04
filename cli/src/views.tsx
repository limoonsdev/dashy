import { h, type Element } from './ui/jsx.js';
import { Box, Text } from './ui/index.js';
import { bar, dim, dot, gradient, logoMarkup, painted, palette } from './theme.js';
import type { AppState } from './types.js';
import type { Me } from './api.js';

const rule = (width: number): string => painted('─'.repeat(Math.max(0, width)), palette.line);

export function Boot(props: { frame: number; width: number }): Element {
    const { frame } = props;
    const progress = Math.min(1, frame / 14);
    const spinner = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'][frame % 10];

    return (
        <Box border={false} direction="column" gap={1} align="center">
            <Text>{' '}</Text>
            {logoMarkup(frame / 8, 0).map((line) => (
                <Text>{line}</Text>
            ))}
            <Text>{' '}</Text>
            <Text color={palette.muted}>{painted('◆ ', palette.accent) + dim('self-hosted control plane')}</Text>
            <Text>{' '}</Text>
            <Text>{bar(progress, Math.min(40, Math.max(20, props.width - 20)))}</Text>
            <Text color={palette.faint}>{painted(spinner + ' ', palette.brand) + 'loading workspace · ' + Math.round(progress * 100) + '%'}</Text>
        </Box>
    );
}

function Header(props: { state: AppState }): Element {
    const { state } = props;
    const name = state.me?.instance.name ?? 'Dashy';
    const url = state.me?.instance.url ?? state.pair.api ?? 'not connected';
    const version = state.me?.instance.version ?? '—';
    const clock = state.clock.toTimeString().slice(0, 8);
    const status = state.loading
        ? painted('● syncing', palette.amber)
        : painted('● live', palette.green);

    return (
        <Box border borderColor={palette.line} paddingX={1}>
            <Box direction="row" border={false}>
                <Text>{painted('◆ ', palette.brand, true) + painted(name, palette.text, true)}</Text>
                <Text align="end" color={palette.faint}>{version + '   ' + clock + '   ' + status}</Text>
            </Box>
            <Text color={palette.faint}>{dim(url)}</Text>
        </Box>
    );
}

function Nav(props: { state: AppState }): Element {
    const { state } = props;
    const labels = ['Overview', 'Servers', 'Updates', 'Help'];
    const parts = labels.map((label, index) => {
        const active = index === state.tab;
        const hint = active
            ? painted('▎', palette.accent) + ' ' + painted(String(index + 1) + ' ' + label, palette.text, true)
            : '  ' + dim(String(index + 1) + ' ' + label);

        return hint;
    });

    return (
        <Box border={false} paddingX={1}>
            <Text>{parts.join('    ')}</Text>
        </Box>
    );
}

function Tile(props: { label: string; value: number | string; hint: string; accent: string }): Element {
    return (
        <Box border paddingX={1} title={props.label} borderColor={palette.line}>
            <Text>{painted(String(props.value), props.accent, true)}</Text>
            <Text color={palette.faint}>{dim(props.hint)}</Text>
        </Box>
    );
}

function Overview(props: { state: AppState }): Element {
    const me = props.state.me as Me;
    const available = me.update.available;
    const servers = me.servers;

    return (
        <Box border={false} direction="column" gap={1}>
            <Box direction="row" gap={1} border={false}>
                <Tile label="Applications" value={me.counts.applications} hint="deployed" accent={palette.accent} />
                <Tile label="Databases" value={me.counts.databases} hint="managed" accent={palette.brandSoft} />
                <Tile label="Services" value={me.counts.services} hint="compose" accent={palette.pink} />
                <Tile label="Servers" value={servers.length} hint={servers.filter((server) => server.reachable).length + ' reachable'} accent={palette.green} />
            </Box>

            <Box border title="Update" borderColor={available ? palette.amber : palette.line} paddingX={1}>
                {available ? (
                    <Text color={palette.amber}>
                        {'▲ '}
                        {painted('Update available', palette.amber, true) +
                            '  ' +
                            me.update.current +
                            ' → ' +
                            me.update.latest +
                            dim('   press u to install')}
                    </Text>
                ) : (
                    <Text color={palette.green}>
                        {'● ' + painted('Up to date', palette.green, true) + dim('  running ' + me.update.current + ' · checked ' + (me.update.checked_at ? new Date(me.update.checked_at).toLocaleTimeString() : '—'))}
                    </Text>
                )}
                {me.update.error ? <Text color={palette.faint}>{dim('note: ' + me.update.error)}</Text> : null}
            </Box>

            <Box direction="row" gap={1} border={false}>
                <Box border title="Servers" borderColor={palette.line} paddingX={1}>
                    {servers.length === 0 ? <Text color={palette.faint}>{dim('No servers yet.')}</Text> : null}
                    {servers.slice(0, 6).map((server) => (
                        <Text>
                            {dot(server.reachable) +
                                ' ' +
                                painted(server.name.padEnd(20).slice(0, 20), palette.text) +
                                ' ' +
                                dim(server.ip ?? '—')}
                        </Text>
                    ))}
                </Box>
                <Box border title="Session" borderColor={palette.line} paddingX={1}>
                    <Text>{painted(me.user?.name ?? 'user', palette.text, true) + dim('  <' + (me.user?.email ?? '') + '>')}</Text>
                    <Text>{dim('team     ') + (me.team?.name ?? '—')}</Text>
                    <Text>{dim('abilities ') + me.abilities.join(', ')}</Text>
                    <Text>{dim('timezone  ') + me.instance.timezone}</Text>
                </Box>
            </Box>
        </Box>
    );
}

function Servers(props: { state: AppState }): Element {
    const me = props.state.me as Me;

    return (
        <Box border title={'Servers · ' + me.servers.length} borderColor={palette.line} paddingX={1}>
            <Text color={palette.faint}>
                {dim('  NAME                ADDRESS             STATUS')}
            </Text>
            {me.servers.length === 0 ? (
                <Text color={palette.faint}>{dim('  No servers in this team yet.')}</Text>
            ) : null}
            {me.servers.map((server) => (
                <Text>
                    {'  ' +
                        painted(server.name.padEnd(20).slice(0, 20), palette.text, true) +
                        ' ' +
                        (server.ip ?? '—').padEnd(19) +
                        ' ' +
                        dot(server.reachable) +
                        ' ' +
                        (server.reachable ? painted('online', palette.green) : painted('unreachable', palette.red))}
                </Text>
            ))}
            <Text>{' '}</Text>
            <Text color={palette.faint}>{dim('  Each server is managed through the same permissions as the web UI.')}</Text>
        </Box>
    );
}

function Updates(props: { state: AppState }): Element {
    const me = props.state.me as Me;
    const row = (label: string, value: string) => (
        <Text>
            {'  ' + dim(label.padEnd(16)) + value}
        </Text>
    );

    return (
        <Box border={false} direction="column" gap={1}>
            <Box border title="Update state" borderColor={me.update.available ? palette.amber : palette.line} paddingX={1}>
                {row('Running', painted(me.update.current, palette.text, true))}
                {row('Latest', painted(me.update.latest, me.update.available ? palette.amber : palette.green, true))}
                {row('Result', me.update.available ? painted('▲ update available', palette.amber, true) : painted('● up to date', palette.green, true))}
                {row('Checked', me.update.checked_at ? new Date(me.update.checked_at).toLocaleString() : 'never')}
                {row('Source', 'github.com/limoonsdev/dashy')}
                {me.update.error ? row('Error', painted(me.update.error, palette.red)) : null}
            </Box>

            <Box border title="Actions" borderColor={palette.line} paddingX={1}>
                <Text>{painted('  c', palette.accent, true) + dim('  check GitHub for a new release')}</Text>
                <Text>{painted('  u', palette.accent, true) + dim('  install the update (asks first)')}</Text>
                <Text>{painted('  r', palette.accent, true) + dim('  refresh this screen')}</Text>
                {props.state.confirmUpgrade ? (
                    <Text color={palette.amber}>{'  ' + painted('Install the update and restart the instance?  y / n', palette.amber, true)}</Text>
                ) : null}
            </Box>
        </Box>
    );
}

function Help(): Element {
    const keys: Array<[string, string]> = [
        ['↑ ↓ / k j', 'move between screens'],
        ['1 – 4', 'jump to a screen'],
        ['r', 'refresh instance data'],
        ['c', 'check for an update'],
        ['u', 'install the update'],
        ['q', 'quit the console'],
    ];
    const commands: Array<[string, string]> = [
        ['dashy', 'full-screen console'],
        ['dashy connect <code>', 'pair this terminal'],
        ['dashy status', 'instance summary'],
        ['dashy servers', 'servers and reachability'],
        ['dashy check', 'check for an update'],
        ['dashy upgrade', 'install the update'],
        ['dashy logs <uuid>', 'read application logs'],
        ['dashy doctor', 'configuration check'],
    ];

    return (
        <Box direction="row" gap={1} border={false}>
            <Box border title="Keys" borderColor={palette.line} paddingX={1}>
                {keys.map(([key, label]) => (
                    <Text>
                        {'  ' + painted(key.padEnd(12), palette.accent, true) + dim(label)}
                    </Text>
                ))}
            </Box>
            <Box border title="Commands" borderColor={palette.line} paddingX={1}>
                {commands.map(([command, label]) => (
                    <Text>
                        {'  ' + painted(command.padEnd(24).slice(0, 24), palette.text) + dim(label)}
                    </Text>
                ))}
            </Box>
        </Box>
    );
}

function Pair(props: { state: AppState }): Element {
    const pair = props.state.pair;
    const cursor = painted('▌', palette.accent);
    const field = (name: 'api' | 'code', label: string, placeholder: string, value: string) => {
        const active = pair.field === name;

        return (
            <Text>
                {'  ' + (active ? painted('▎ ', palette.accent) : '  ') + dim(label.padEnd(10)) + (value === '' ? dim(placeholder) : painted(value, palette.text)) + (active ? cursor : '')}
            </Text>
        );
    };

    return (
        <Box border={false} direction="column" gap={1} align="center">
            <Text>{' '}</Text>
            {logoMarkup(0.55, 0).map((line) => (
                <Text>{line}</Text>
            ))}
            <Text>{' '}</Text>
            <Box border title="Connect this terminal" borderColor={palette.brand} paddingX={2} width={72}>
                <Text color={palette.faint}>{dim('Settings → CLI → Generate a sign-in code')}</Text>
                <Text>{' '}</Text>
                {field('api', 'Instance', 'https://dashy.example.com', pair.api)}
                {field('code', 'Code', 'DASHY-XXXX-XXXX', pair.code)}
                <Text>{' '}</Text>
                {pair.error ? <Text color={palette.red}>{'  ' + pair.error}</Text> : null}
                <Text color={palette.faint}>{'  ' + dim('Tab switch · Enter connect · Esc quit') + (pair.busy ? painted('  ⠋ pairing…', palette.amber) : '')}</Text>
            </Box>
        </Box>
    );
}

export function Footer(props: { state: AppState }): Element {
    const flash = props.state.flash;

    if (flash) {
        const color = flash.tone === 'ok' ? palette.green : flash.tone === 'warn' ? palette.amber : palette.red;

        return (
            <Box border={false} paddingX={1}>
                <Text color={color}>{flash.text}</Text>
            </Box>
        );
    }

    return (
        <Box border={false} paddingX={1}>
            <Text color={palette.faint}>
                {dim('↑↓ navigate  ·  r refresh  ·  c check  ·  u update  ·  ? help  ·  q quit')}
            </Text>
        </Box>
    );
}

export function Screen(props: { state: AppState; width: number }): Element {
    const { state, width } = props;

    if (state.phase === 'boot') {
        return <Boot frame={state.bootFrame} width={width} />;
    }

    if (state.phase === 'pair') {
        return <Pair state={state} />;
    }

    const body = state.error
        ? (
            <Box border title="Error" borderColor={palette.red} paddingX={1}>
                <Text color={palette.red}>{state.error}</Text>
                <Text color={palette.faint}>{dim('Press r to retry, or run `dashy doctor`.')}</Text>
            </Box>
        )
        : state.tab === 0
            ? <Overview state={state} />
            : state.tab === 1
                ? <Servers state={state} />
                : state.tab === 2
                    ? <Updates state={state} />
                    : <Help />;

    return (
        <Box direction="column" gap={1} border={false}>
            <Header state={state} />
            <Nav state={state} />
            {body}
            <Footer state={state} />
        </Box>
    );
}

export function MiniLogo(phase = 0): Element {
    return (
        <Box border={false} direction="column" gap={0}>
            {logoMarkup(phase, 2).map((line) => (
                <Text>{line}</Text>
            ))}
        </Box>
    );
}

export function BrandLine(): Element {
    return <Text>{gradient('DASHY', 0.3) + dim('  self-hosted control plane')}</Text>;
}
