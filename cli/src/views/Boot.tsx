import { h, type Element } from '../ui/jsx.js';
import { Box, Text } from '../ui/index.js';
import { bar, dim, logoMarkup, painted, palette } from '../ui/theme.js';

const SPINNER = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

export function Boot({ frame, width }: { frame: number; width: number }): Element {
    const progress = Math.min(1, frame / 14);

    return (
        <Box border={false} direction="column" gap={1} align="center">
            <Text>{' '}</Text>
            {logoMarkup(frame / 8, 0).map((line) => (
                <Text>{line}</Text>
            ))}
            <Text>{' '}</Text>
            <Text>{bar(progress, Math.min(40, Math.max(20, width - 20)))}</Text>
            <Text color={palette.faint}>
                {painted(SPINNER[frame % SPINNER.length] + ' ', palette.brand) + 'connecting · ' + Math.round(progress * 100) + '%'}
            </Text>
        </Box>
    );
}
