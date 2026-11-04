import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, painted, palette } from '../ui/theme.js';

const KEYS: Array<[string, string]> = [
    ['↑ ↓ / k j', 'move between screens'],
    ['1 – 4', 'jump to a screen'],
    ['r', 'refresh instance data'],
    ['c', 'check for an update'],
    ['u', 'install the update'],
    ['q', 'quit the console'],
];

const COMMANDS: Array<[string, string]> = [
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

export function Help(): Element {
    return (
        <Box direction="row" gap={1} border={false}>
            <Box border title="Keys" borderColor={palette.line} paddingX={1}>
                {KEYS.map(([key, label]) => (
                    <Text>{'  ' + painted(key.padEnd(12), palette.accent, true) + dim(label)}</Text>
                ))}
            </Box>
            <Box border title="Commands" borderColor={palette.line} paddingX={1}>
                {COMMANDS.map(([command, label]) => (
                    <Text>{'  ' + painted(command.padEnd(24).slice(0, 24), palette.text) + dim(label)}</Text>
                ))}
            </Box>
        </Box>
    );
}
