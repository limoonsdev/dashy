import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, logoMarkup, painted, palette } from '../ui/theme.js';
import type { AppState } from '../lib/state.js';

export function Pair({ state }: { state: AppState }): Element {
    const pair = state.pair;
    const cursor = painted('▌', palette.accent);

    const field = (name: 'api' | 'code', label: string, placeholder: string, value: string): Element => {
        const active = pair.field === name;

        return (
            <Text>
                {'  ' +
                    (active ? painted('▎ ', palette.accent) : '  ') +
                    dim(label.padEnd(10)) +
                    (value === '' ? dim(placeholder) : painted(value, palette.text)) +
                    (active ? cursor : '')}
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
                <Text color={palette.faint}>
                    {'  ' + dim('Tab switch · Enter connect · Esc quit') + (pair.busy ? painted('  pairing…', palette.amber) : '')}
                </Text>
            </Box>
        </Box>
    );
}
