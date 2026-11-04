import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, logoMarkup, painted, palette } from '../ui/theme.js';
export function Pair({ state }) {
    const pair = state.pair;
    const cursor = painted('▌', palette.accent);
    const field = (name, label, placeholder, value) => {
        const active = pair.field === name;
        return (h(Text, null, '  ' +
            (active ? painted('▎ ', palette.accent) : '  ') +
            dim(label.padEnd(10)) +
            (value === '' ? dim(placeholder) : painted(value, palette.text)) +
            (active ? cursor : '')));
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
            h(Text, { color: palette.faint }, '  ' + dim('Tab switch · Enter connect · Esc quit') + (pair.busy ? painted('  pairing…', palette.amber) : '')))));
}
