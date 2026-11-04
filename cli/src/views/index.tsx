import { h, type Element } from '../ui/jsx.js';
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
import type { AppState } from '../lib/state.js';

export function Screen({ state, width }: { state: AppState; width: number }): Element {
    if (state.phase === 'boot') {
        return <Boot frame={state.bootFrame} width={width} />;
    }

    if (state.phase === 'pair') {
        return <Pair state={state} />;
    }

    const body = state.error ? (
        <Box border title="Cannot reach the instance" borderColor={palette.red} paddingX={1}>
            <Text color={palette.red}>{state.error}</Text>
            <Text color={palette.faint}>{dim('r retries · dashy doctor explains what is missing.')}</Text>
        </Box>
    ) : state.tab === 0 ? (
        <Overview state={state} />
    ) : state.tab === 1 ? (
        <Servers state={state} />
    ) : state.tab === 2 ? (
        <Updates state={state} />
    ) : (
        <Help />
    );

    return (
        <Box direction="column" gap={1} border={false}>
            <Header state={state} />
            <Nav state={state} />
            {body}
            <Footer state={state} />
            <Text color={palette.faint}>{dim('  Dashy CLI 2.1')}</Text>
        </Box>
    );
}
