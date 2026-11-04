import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, palette } from '../ui/theme.js';
import type { AppState } from '../lib/state.js';

const TONES = { ok: palette.green, warn: palette.amber, err: palette.red } as const;

export function Footer({ state }: { state: AppState }): Element {
    if (state.flash) {
        return (
            <Box border={false} paddingX={1}>
                <Text color={TONES[state.flash.tone]}>{state.flash.text}</Text>
            </Box>
        );
    }

    return (
        <Box border={false} paddingX={1}>
            <Text color={palette.faint}>
                {dim('↑↓ screens  ·  r refresh  ·  c check  ·  u update  ·  ? help  ·  q quit')}
            </Text>
        </Box>
    );
}
