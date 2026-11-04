import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, dot, painted, palette } from '../ui/theme.js';
import type { Me } from '../lib/api.js';
import type { AppState } from '../lib/state.js';

function Tile({ label, value, hint, accent }: { label: string; value: string | number; hint: string; accent: string }): Element {
    return (
        <Box border paddingX={1} title={label} borderColor={palette.line}>
            <Text>{painted(String(value), accent, true)}</Text>
            <Text color={palette.faint}>{dim(hint)}</Text>
        </Box>
    );
}

const when = (value: string | null): string => (value ? new Date(value).toLocaleTimeString() : 'never');

export function Overview({ state }: { state: AppState }): Element {
    const me = state.me as Me;
    const update = me.update;

    return (
        <Box border={false} direction="column" gap={1}>
            <Box direction="row" gap={1} border={false}>
                <Tile label="Applications" value={me.counts.applications} hint="deployed" accent={palette.accent} />
                <Tile label="Databases" value={me.counts.databases} hint="managed" accent={palette.brandSoft} />
                <Tile label="Services" value={me.counts.services} hint="compose" accent={palette.pink} />
                <Tile label="Servers" value={me.servers.length} hint={`${me.servers.filter((server) => server.reachable).length} reachable`} accent={palette.green} />
            </Box>

            <Box border title="Release" borderColor={update.available ? palette.amber : palette.line} paddingX={1}>
                {update.available ? (
                    <Text>
                        {painted('▲ ', palette.amber, true) +
                            painted('Update available', palette.amber, true) +
                            '  ' +
                            update.current +
                            ' → ' +
                            update.latest +
                            dim('   press u for the notes')}
                    </Text>
                ) : (
                    <Text>
                        {painted('● ', palette.green) +
                            painted('Up to date', palette.green, true) +
                            dim(`  ${update.current} · checked ${when(update.checked_at)}`)}
                    </Text>
                )}
            </Box>

            <Box direction="row" gap={1} border={false}>
                <Box border title="Servers" borderColor={palette.line} paddingX={1}>
                    {me.servers.length === 0 ? <Text color={palette.faint}>{dim('No servers yet.')}</Text> : null}
                    {me.servers.slice(0, 6).map((server) => (
                        <Text>
                            {dot(server.reachable) + ' ' + painted(server.name.padEnd(20).slice(0, 20), palette.text) + ' ' + dim(server.ip ?? '—')}
                        </Text>
                    ))}
                </Box>
                <Box border title="Session" borderColor={palette.line} paddingX={1}>
                    <Text>{painted(me.user?.name ?? 'user', palette.text, true) + dim('  <' + (me.user?.email ?? '') + '>')}</Text>
                    <Text>{dim('team       ') + (me.team?.name ?? '—')}</Text>
                    <Text>{dim('abilities  ') + me.abilities.join(', ')}</Text>
                    <Text>{dim('timezone   ') + me.instance.timezone}</Text>
                </Box>
            </Box>
        </Box>
    );
}
