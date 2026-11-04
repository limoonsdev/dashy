import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, dot, painted, palette } from '../ui/theme.js';
import type { Me } from '../lib/api.js';
import type { AppState } from '../lib/state.js';

export function Servers({ state }: { state: AppState }): Element {
    const me = state.me as Me;

    return (
        <Box border title={`Servers · ${me.servers.length}`} borderColor={palette.line} paddingX={1}>
            <Text color={palette.faint}>{dim('  NAME                ADDRESS             STATUS')}</Text>
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
            <Text color={palette.faint}>{dim('  Same permissions as the web UI. Deploys and updates use the token abilities.')}</Text>
        </Box>
    );
}
