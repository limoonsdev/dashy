import { h } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { dim, palette } from '../ui/theme.js';
import { Header } from '../components/Header.js';
import { Nav } from '../components/Nav.js';
import { Footer } from '../components/Footer.js';
import { Boot } from './Boot.js';
import { Overview } from './Overview.js';
import { Servers } from './Servers.js';
import { Updates } from './Updates.js';
import { Help } from './Help.js';
import { Pair } from './Pair.js';
export function Screen({ state, width }) {
    if (state.phase === 'boot') {
        return h(Boot, { frame: state.bootFrame, width: width });
    }
    if (state.phase === 'pair') {
        return h(Pair, { state: state });
    }
    const body = state.error ? (h(Box, { border: true, title: "Cannot reach the instance", borderColor: palette.red, paddingX: 1 },
        h(Text, { color: palette.red }, state.error),
        h(Text, { color: palette.faint }, dim('r retries · dashy doctor explains what is missing.')))) : state.tab === 0 ? (h(Overview, { state: state })) : state.tab === 1 ? (h(Servers, { state: state })) : state.tab === 2 ? (h(Updates, { state: state })) : (h(Help, null));
    return (h(Box, { direction: "column", gap: 1, border: false },
        h(Header, { state: state }),
        h(Nav, { state: state }),
        body,
        h(Footer, { state: state }),
        h(Text, { color: palette.faint }, dim('  Dashy CLI 2.1'))));
}
