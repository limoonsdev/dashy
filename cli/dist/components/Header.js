import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, painted, palette } from '../ui/theme.js';
export function Header({ state }) {
    const name = state.me?.instance.name ?? 'Dashy';
    const url = state.me?.instance.url ?? state.pair.api ?? 'not connected';
    const version = state.me?.instance.version ?? '—';
    const clock = state.clock.toTimeString().slice(0, 8);
    const status = state.loading ? painted('● syncing', palette.amber) : painted('● live', palette.green);
    return (h(Box, { border: true, borderColor: palette.line, paddingX: 1 },
        h(Box, { direction: "row", border: false },
            h(Text, null, painted('◆ ', palette.brand, true) + painted(name, palette.text, true)),
            h(Text, { align: "end", color: palette.faint }, version + '  ·  ' + clock + '  ·  ' + status)),
        h(Text, { color: palette.faint }, dim(url))));
}
