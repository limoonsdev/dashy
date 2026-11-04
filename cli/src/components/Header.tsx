import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, painted, palette } from '../ui/theme.js';
import type { AppState } from '../lib/state.js';

export function Header({ state }: { state: AppState }): Element {
    const name = state.me?.instance.name ?? 'Dashy';
    const url = state.me?.instance.url ?? state.pair.api ?? 'not connected';
    const version = state.me?.instance.version ?? '—';
    const clock = state.clock.toTimeString().slice(0, 8);
    const status = state.loading ? painted('● syncing', palette.amber) : painted('● live', palette.green);

    return (
        <Box border borderColor={palette.line} paddingX={1}>
            <Box direction="row" border={false}>
                <Text>{painted('◆ ', palette.brand, true) + painted(name, palette.text, true)}</Text>
                <Text align="end" color={palette.faint}>{version + '  ·  ' + clock + '  ·  ' + status}</Text>
            </Box>
            <Text color={palette.faint}>{dim(url)}</Text>
        </Box>
    );
}
