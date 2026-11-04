import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { bar, logoMarkup, painted, palette } from '../ui/theme.js';
const SPINNER = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
export function Boot({ frame, width }) {
    const progress = Math.min(1, frame / 14);
    return (h(Box, { border: false, direction: "column", gap: 1, align: "center" },
        h(Text, null, ' '),
        logoMarkup(frame / 8, 0).map((line) => (h(Text, null, line))),
        h(Text, null, ' '),
        h(Text, null, bar(progress, Math.min(40, Math.max(20, width - 20)))),
        h(Text, { color: palette.faint }, painted(SPINNER[frame % SPINNER.length] + ' ', palette.brand) + 'connecting · ' + Math.round(progress * 100) + '%')));
}
