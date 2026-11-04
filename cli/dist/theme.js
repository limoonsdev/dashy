import { hexToAnsi, lerpHex, RESET, stripAnsi } from './ui/render.js';
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
};
export const LOGO = [
    '██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗',
    '██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝',
    '██║  ██║███████║███████╗███████║ ╚████╔╝ ',
    '██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝  ',
    '██████╔╝██║  ██║███████║██║  ██║   ██║   ',
    '╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝   ',
];
/**
 * Colours every character of a line between two palette stops, with a moving
 * highlight so the wordmark looks lit rather than tinted.
 */
export function gradient(text, phase = 0, from = palette.brand, to = palette.accent) {
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
export function gradientLines(lines, phase = 0) {
    return lines.map((line, index) => gradient(line, (phase + index * 0.16) % 1));
}
export function logoMarkup(phase = 0, indent = 2) {
    return gradientLines(LOGO, phase).map((line) => ' '.repeat(indent) + line);
}
/** A solid progress/utilisation bar. */
export function bar(ratio, width, color = palette.brand) {
    const clamped = Math.min(1, Math.max(0, ratio));
    const filled = Math.round(clamped * width);
    return (hexToAnsi(color) +
        '█'.repeat(filled) +
        RESET +
        '\x1b[38;2;39;39;42m' +
        '░'.repeat(Math.max(0, width - filled)) +
        RESET);
}
const SPARK = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█'];
export function sparkline(values, color = palette.accent) {
    if (values.length === 0) {
        return '';
    }
    const max = Math.max(...values, 1);
    return hexToAnsi(color) + values.map((value) => SPARK[Math.min(7, Math.floor((value / max) * 7))]).join('') + RESET;
}
export function dot(ok, colorTrue = palette.green, colorFalse = palette.red) {
    return hexToAnsi(ok ? colorTrue : colorFalse) + '●' + RESET;
}
export function painted(text, color, bold = false) {
    return (bold ? '\x1b[1m' : '') + hexToAnsi(color) + text + RESET;
}
export function dim(text) {
    return '\x1b[2m' + text + RESET;
}
export function truncatePlain(text, width) {
    const plain = stripAnsi(text);
    return plain.length <= width ? plain : plain.slice(0, Math.max(0, width - 1)) + '…';
}
