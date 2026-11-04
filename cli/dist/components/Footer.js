import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, palette } from '../ui/theme.js';
const TONES = { ok: palette.green, warn: palette.amber, err: palette.red };
export function Footer({ state }) {
    if (state.flash) {
        return (h(Box, { border: false, paddingX: 1 },
            h(Text, { color: TONES[state.flash.tone] }, state.flash.text)));
    }
    return (h(Box, { border: false, paddingX: 1 },
        h(Text, { color: palette.faint }, dim('↑↓ screens  ·  r refresh  ·  c check  ·  u update  ·  ? help  ·  q quit'))));
}
