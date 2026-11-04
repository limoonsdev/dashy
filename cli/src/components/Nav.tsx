import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, painted, palette } from '../ui/theme.js';
import type { AppState } from '../lib/state.js';

const LABELS = ['Overview', 'Servers', 'Updates', 'Help'];

export function Nav({ state }: { state: AppState }): Element {
    const tabs = LABELS.map((label, index) => {
        if (index === state.tab) {
            return painted('▎ ', palette.accent) + painted(`${index + 1} ${label}`, palette.text, true);
        }

        return '  ' + dim(`${index + 1} ${label}`);
    });

    return (
        <Box border={false} paddingX={1}>
            <Text>{tabs.join('     ')}</Text>
        </Box>
    );
}
