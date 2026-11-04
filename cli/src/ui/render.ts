import { Fragment, childrenOf, isElement, type Element, type Props, type Renderable } from './jsx.js';

const ANSI = /\x1b\[[0-9;]*m/g;

export const visibleLength = (value: string): number => value.replace(ANSI, '').length;

export const stripAnsi = (value: string): string => value.replace(ANSI, '');

export function padRight(value: string, width: number): string {
    const missing = width - visibleLength(value);

    return missing > 0 ? value + ' '.repeat(missing) : value;
}

export function truncate(value: string, width: number): string {
    if (visibleLength(value) <= width) {
        return value;
    }

    if (width <= 1) {
        return '…'.slice(0, width);
    }

    // Slicing by visible length: strip the ANSI codes first, then re-apply none.
    return stripAnsi(value).slice(0, width - 1) + '…';
}

export function hexToAnsi(hex: string): string {
    const clean = hex.replace('#', '');
    const full = clean.length === 3
        ? clean.split('').map((c) => c + c).join('')
        : clean;
    const r = parseInt(full.slice(0, 2), 16) || 0;
    const g = parseInt(full.slice(2, 4), 16) || 0;
    const b = parseInt(full.slice(4, 6), 16) || 0;

    return `\x1b[38;2;${r};${g};${b}m`;
}

export const RESET = '\x1b[0m';

const mix = (a: number, b: number, t: number): number => Math.round(a + (b - a) * t);

export function lerpHex(from: string, to: string, t: number): string {
    const parse = (hex: string): [number, number, number] => {
        const clean = hex.replace('#', '');
        return [
            parseInt(clean.slice(0, 2), 16),
            parseInt(clean.slice(2, 4), 16),
            parseInt(clean.slice(4, 6), 16),
        ];
    };

    const [r1, g1, b1] = parse(from);
    const [r2, g2, b2] = parse(to);

    return (
        '#' +
        [mix(r1, r2, t), mix(g1, g2, t), mix(b1, b2, t)]
            .map((value) => value.toString(16).padStart(2, '0'))
            .join('')
    );
}

export function style(text: string, props: Props): string {
    const codes: string[] = [];

    if (props.color) {
        codes.push(hexToAnsi(String(props.color)));
    }
    if (props.bold) {
        codes.push('\x1b[1m');
    }
    if (props.dim) {
        codes.push('\x1b[2m');
    }
    if (props.italic) {
        codes.push('\x1b[3m');
    }
    if (props.underline) {
        codes.push('\x1b[4m');
    }

    if (codes.length === 0) {
        return text;
    }

    return codes.join('') + text + RESET;
}

function wrap(text: string, width: number): string[] {
    if (width <= 0) {
        return [text];
    }

    const lines: string[] = [];

    for (const rawLine of text.split('\n')) {
        if (visibleLength(rawLine) <= width) {
            lines.push(rawLine);
            continue;
        }

        const words = rawLine.split(/\s+/).filter((word) => word !== '');

        if (words.length === 0) {
            lines.push('');
            continue;
        }

        let current = '';

        for (const word of words) {
            const candidate = current === '' ? word : current + ' ' + word;

            if (visibleLength(candidate) <= width) {
                current = candidate;
            } else {
                if (current !== '') {
                    lines.push(current);
                }
                current = word.length > width ? word.slice(0, width) : word;
            }
        }

        if (current !== '') {
            lines.push(current);
        }
    }

    return lines;
}

function alignLine(line: string, width: number, align: string): string {
    const missing = width - visibleLength(line);

    if (missing <= 0) {
        return line;
    }

    if (align === 'center') {
        const left = Math.floor(missing / 2);
        return ' '.repeat(left) + line + ' '.repeat(missing - left);
    }

    if (align === 'end') {
        return ' '.repeat(missing) + line;
    }

    return line + ' '.repeat(missing);
}

function textElement(element: Element, width: number): string[] {
    const props = element.props;
    const raw = flatten(childrenOf(props));
    const lines = wrap(raw, Number(props.wrapWidth ?? width));
    const styled = lines.map((line) => style(line, props));

    if (props.align) {
        return styled.map((line) => {
            const plain = stripAnsi(line);
            const aligned = alignLine(plain, width, String(props.align));
            const padding = aligned.length - plain.length;
            const left = aligned.indexOf(plain);

            return ' '.repeat(Math.max(0, left)) + line + ' '.repeat(Math.max(0, padding - left));
        });
    }

    return styled;
}

function flatten(node: Renderable): string {
    if (node === null || node === undefined || typeof node === 'boolean') {
        return '';
    }

    if (typeof node === 'string' || typeof node === 'number') {
        return String(node);
    }

    if (Array.isArray(node)) {
        return node.map(flatten).join('');
    }

    if (isElement(node)) {
        return flatten(childrenOf(node.props));
    }

    return '';
}

function distribute(total: number, count: number, gap: number): number[] {
    if (count <= 0) {
        return [];
    }

    const usable = Math.max(count, total - gap * (count - 1));
    const base = Math.floor(usable / count);
    const remainder = usable - base * count;
    const widths = Array.from({ length: count }, (_, index) => base + (index < remainder ? 1 : 0));

    return widths;
}

function boxElement(element: Element, width: number): string[] {
    const props = element.props;
    const border = props.border !== false;
    const title = props.title ? String(props.title) : '';
    const paddingX = Number(props.paddingX ?? (border ? 1 : 0));
    const paddingY = Number(props.paddingY ?? 0);
    const gap = Number(props.gap ?? 0);
    const direction = String(props.direction ?? 'column');
    const align = String(props.align ?? 'start');
    const borderColor = props.borderColor ? hexToAnsi(String(props.borderColor)) : '';
    const sideWidth = border ? 1 : 0;

    const innerWidth = Math.max(1, width - sideWidth * 2 - paddingX * 2);
    const kids = normaliseChildren(childrenOf(props));
    let content: string[] = [];

    if (direction === 'row') {
        const widths = distribute(innerWidth, kids.length, gap);
        const blocks = kids.map((kid, index) => render(kid, widths[index]));
        const height = Math.max(0, ...blocks.map((block) => block.length));

        for (let line = 0; line < height; line++) {
            content.push(
                blocks
                    .map((block, index) => padRight(block[line] ?? '', widths[index]))
                    .join(' '.repeat(gap)),
            );
        }
    } else {
        kids.forEach((kid, index) => {
            if (index > 0 && gap > 0) {
                content.push(...Array.from({ length: gap }, () => ''));
            }
            content.push(...render(kid, innerWidth));
        });
    }

    content = content.map((line) => alignLine(padRight(line, 0), innerWidth, align));
    content = content.map((line) => ' '.repeat(paddingX) + padRight(line, innerWidth) + ' '.repeat(paddingX));

    const blank = ' '.repeat(paddingX) + ' '.repeat(innerWidth) + ' '.repeat(paddingX);
    content = [
        ...Array.from({ length: paddingY }, () => blank),
        ...content,
        ...Array.from({ length: paddingY }, () => blank),
    ];

    if (!border) {
        return content.map((line) => padRight(line, width));
    }

    const top = title === ''
        ? (borderColor || '') + '╭' + '─'.repeat(Math.max(0, width - 2)) + '╮' + RESET
        : (() => {
            const label = ' ' + title + ' ';
            const line = '─'.repeat(Math.max(0, width - 2 - visibleLength(label)));
            return (borderColor || '') + '╭─' + RESET + style(label, { bold: true }) + (borderColor || '') + line + '╮' + RESET;
        })();

    const bottom = (borderColor || '') + '╰' + '─'.repeat(Math.max(0, width - 2)) + '╯' + RESET;
    const body = content.map((line) => (borderColor || '') + '│' + RESET + padRight(line, width - 2) + (borderColor || '') + '│' + RESET);

    return [top, ...body, bottom];
}

function normaliseChildren(node: Renderable): Renderable[] {
    if (node === null || node === undefined || typeof node === 'boolean') {
        return [];
    }

    return Array.isArray(node) ? node : [node];
}

export function render(node: Renderable, width: number): string[] {
    if (node === null || node === undefined || typeof node === 'boolean') {
        return [];
    }

    if (typeof node === 'string' || typeof node === 'number') {
        return wrap(String(node), width);
    }

    if (Array.isArray(node)) {
        return node.flatMap((child) => render(child, width));
    }

    if (!isElement(node)) {
        return [];
    }

    const { type, props } = node;

    if (type === Fragment) {
        return render(childrenOf(props), width);
    }

    if (typeof type === 'function') {
        return render(type(props), width);
    }

    switch (type) {
        case 'text':
            return textElement(node, width);
        case 'box':
            return boxElement(node, width);
        case 'divider':
            return [style('─'.repeat(Math.max(0, Number(props.width ?? width))), { color: props.color ?? '#27272a' })];
        case 'spacer':
            return Array.from({ length: Number(props.height ?? 1) }, () => '');
        case 'break':
            return [''];
        default:
            return render(childrenOf(props), width);
    }
}
