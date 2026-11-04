import { hexToAnsi, lerpHex, RESET, stripAnsi } from './render.js';

export const palette = {
    brand: '#8b5cf6',
    brandSoft: '#a78bfa',
    accent: '#22d3ee',
    green: '#34d399',
    amber: '#fbbf24',
    red: '#f87171',
    pink: '#f472b6',
    text: '#e4e4e7',
    muted: '#a1a1aa',
    faint: '#52525b',
    line: '#27272a',
    panel: '#0b0a10',
} as const;

export const LOGO = [
    '██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗',
    '██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝',
    '██║  ██║███████║███████╗███████║ ╚████╔╝ ',
    '██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝  ',
    '██████╔╝██║  ██║███████║██║  ██║   ██║   ',
    '╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝   ',
] as const;

/**
 * Colours every character of a line between two palette stops, with a moving
 * highlight so the wordmark looks lit rather than tinted.
 */
export function gradient(text: string, phase = 0, from = palette.brand, to = palette.accent): string {
    const characters = [...text];
    const length = Math.max(1, characters.length - 1);
    const hot = ((phase % 1) + 1) % 1;

    return characters
        .map((character, index) => {
            const t = index / length;
            const glow = Math.max(0, 1 - Math.abs(t - hot) * 6);
            const base = lerpHex(from, to, t);

            return hexToAnsi(glow > 0.55 ? lerpHex(base, '#ffffff', (glow - 0.55) * 0.7) : base) + character;
        })
        .join('') + RESET;
}

export function gradientLines(lines: readonly string[], phase = 0): string[] {
    return lines.map((line, index) => gradient(line, (phase + index * 0.16) % 1));
}

export function logoMarkup(phase = 0, indent = 2): string[] {
    return gradientLines(LOGO, phase).map((line) => ' '.repeat(indent) + line);
}

/** A solid progress/utilisation bar. */
export function bar(ratio: number, width: number, color = palette.brand): string {
    const clamped = Math.min(1, Math.max(0, ratio));
    const filled = Math.round(clamped * width);

    return (
        hexToAnsi(color) +
        '█'.repeat(filled) +
        RESET +
        '\x1b[38;2;39;39;42m' +
        '░'.repeat(Math.max(0, width - filled)) +
        RESET
    );
}

const SPARK = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];

export function sparkline(values: number[], color = palette.accent): string {
    if (values.length === 0) {
        return '';
    }

    const max = Math.max(...values, 1);

    return hexToAnsi(color) + values.map((value) => SPARK[Math.min(7, Math.floor((value / max) * 7))]).join('') + RESET;
}

export function dot(ok: boolean, colorTrue = palette.green, colorFalse = palette.red): string {
    return hexToAnsi(ok ? colorTrue : colorFalse) + '●' + RESET;
}

export function painted(text: string, color: string, bold = false): string {
    return (bold ? '\x1b[1m' : '') + hexToAnsi(color) + text + RESET;
}

export function dim(text: string): string {
    return '\x1b[2m' + text + RESET;
}

const DIFF_ADD = '\x1b[48;2;7;31;21m';
const DIFF_DEL = '\x1b[48;2;41;12;14m';

/** Changelog lines use the same green/red treatment as a code diff. */
export function diffLine(kind: 'add' | 'del' | 'ctx', text: string): string {
    if (kind === 'add') {
        return DIFF_ADD + '\x1b[38;2;110;231;183m' + '[+] ' + text + RESET;
    }

    if (kind === 'del') {
        return DIFF_DEL + '\x1b[38;2;248;113;113m' + '[-] ' + text + RESET;
    }

    return '\x1b[38;2;161;161;170m' + '[·] ' + text + RESET;
}

export function truncatePlain(text: string, width: number): string {
    const plain = stripAnsi(text);

    return plain.length <= width ? plain : plain.slice(0, Math.max(0, width - 1)) + '…';
}
