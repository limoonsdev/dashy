import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, painted, palette } from '../ui/theme.js';
const KEYS = [
    ['↑ ↓ / k j', 'move between screens'],
    ['1 – 4', 'jump to a screen'],
    ['r', 'refresh instance data'],
    ['c', 'check for an update'],
    ['u', 'install the update'],
    ['q', 'quit the console'],
];
const COMMANDS = [
    ['dashy', 'full-screen console'],
    ['dashy connect <code>', 'pair this terminal'],
    ['dashy status', 'instance summary'],
    ['dashy servers', 'servers and reachability'],
    ['dashy apps', 'applications and status'],
    ['dashy check', 'check for an update'],
    ['dashy upgrade --yes', 'install the update'],
    ['dashy logs <uuid>', 'application logs'],
    ['dashy deploy <uuid>', 'queue a deployment'],
    ['dashy doctor', 'configuration check'],
];
export function Help() {
    return (h(Box, { direction: "row", gap: 1, border: false },
        h(Box, { border: true, title: "Keys", borderColor: palette.line, paddingX: 1 }, KEYS.map(([key, label]) => (h(Text, null, '  ' + painted(key.padEnd(12), palette.accent, true) + dim(label))))),
        h(Box, { border: true, title: "Commands", borderColor: palette.line, paddingX: 1 }, COMMANDS.map(([command, label]) => (h(Text, null, '  ' + painted(command.padEnd(24).slice(0, 24), palette.text) + dim(label)))))));
}
