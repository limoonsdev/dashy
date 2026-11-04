import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, diffLine, painted, palette } from '../ui/theme.js';
import { parseNotes } from '../lib/notes.js';
export function Updates({ state }) {
    const me = state.me;
    const update = me.update;
    const notes = parseNotes(update.notes);
    const row = (label, value) => (h(Text, null, '  ' + dim(label.padEnd(14)) + value));
    return (h(Box, { border: false, direction: "column", gap: 1 },
        h(Box, { border: true, title: "Release", borderColor: update.available ? palette.amber : palette.line, paddingX: 1 },
            row('Running', painted(update.current, palette.text, true)),
            row('Available', painted(update.latest, update.available ? palette.amber : palette.green, true)),
            row('Checked', update.checked_at ? new Date(update.checked_at).toLocaleString() : 'never'),
            row('Channel', 'github · limoonsdev/dashy'),
            update.error ? row('Error', painted(update.error, palette.red)) : null),
        h(Box, { border: true, title: "Changelog", borderColor: palette.line, paddingX: 1 }, notes.length === 0 ? (h(Text, { color: palette.faint }, dim('  This release has no notes. `u` still installs it.'))) : (notes.map((line) => h(Text, null, '  ' + diffLine(line.kind, line.text))))),
        h(Box, { border: true, title: "Actions", borderColor: palette.line, paddingX: 1 },
            h(Text, null, painted('  u', palette.accent, true) + dim('  install the release (asks first)')),
            h(Text, null, painted('  c', palette.accent, true) + dim('  check GitHub again now')),
            h(Text, null, painted('  r', palette.accent, true) + dim('  refresh instance data')),
            state.confirmUpgrade ? (h(Text, { color: palette.amber }, '  ' + painted('Install and restart the instance?  y / n', palette.amber, true))) : null)));
}
