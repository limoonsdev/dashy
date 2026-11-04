import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, diffLine, painted, palette } from '../ui/theme.js';
import type { Me } from '../lib/api.js';
import type { AppState } from '../lib/state.js';
import { parseNotes } from '../lib/notes.js';

export function Updates({ state }: { state: AppState }): Element {
    const me = state.me as Me;
    const update = me.update;
    const notes = parseNotes(update.notes);

    const row = (label: string, value: string): Element => (
        <Text>{'  ' + dim(label.padEnd(14)) + value}</Text>
    );

    return (
        <Box border={false} direction="column" gap={1}>
            <Box border title="Release" borderColor={update.available ? palette.amber : palette.line} paddingX={1}>
                {row('Running', painted(update.current, palette.text, true))}
                {row('Available', painted(update.latest, update.available ? palette.amber : palette.green, true))}
                {row('Checked', update.checked_at ? new Date(update.checked_at).toLocaleString() : 'never')}
                {row('Channel', 'github · limoonsdev/dashy')}
                {update.error ? row('Error', painted(update.error, palette.red)) : null}
            </Box>

            <Box border title="Changelog" borderColor={palette.line} paddingX={1}>
                {notes.length === 0 ? (
                    <Text color={palette.faint}>{dim('  This release has no notes. `u` still installs it.')}</Text>
                ) : (
                    notes.map((line) => <Text>{'  ' + diffLine(line.kind, line.text)}</Text>)
                )}
            </Box>

            <Box border title="Actions" borderColor={palette.line} paddingX={1}>
                <Text>{painted('  u', palette.accent, true) + dim('  install the release (asks first)')}</Text>
                <Text>{painted('  c', palette.accent, true) + dim('  check GitHub again now')}</Text>
                <Text>{painted('  r', palette.accent, true) + dim('  refresh instance data')}</Text>
                {state.confirmUpgrade ? (
                    <Text color={palette.amber}>{'  ' + painted('Install and restart the instance?  y / n', palette.amber, true)}</Text>
                ) : null}
            </Box>
        </Box>
    );
}
