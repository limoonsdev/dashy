import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, painted, palette } from '../ui/theme.js';
const LABELS = ['Overview', 'Servers', 'Updates', 'Help'];
export function Nav({ state }) {
    const tabs = LABELS.map((label, index) => {
        if (index === state.tab) {
            return painted('▎ ', palette.accent) + painted(`${index + 1} ${label}`, palette.text, true);
        }
        return '  ' + dim(`${index + 1} ${label}`);
    });
    return (h(Box, { border: false, paddingX: 1 },
        h(Text, null, tabs.join('     '))));
}
