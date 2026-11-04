export function limit(text, max = 1900) {
    const value = String(text ?? '');

    if (value.length <= max) {
        return value;
    }

    return `${value.slice(0, Math.max(0, max - 1))}…`;
}

export function bullets(items, empty = 'Nothing to show.') {
    if (!Array.isArray(items) || items.length === 0) {
        return empty;
    }

    return items.map((item) => `• ${item}`).join('\n');
}

export function oneLine(value, max = 60) {
    const text = String(value ?? '').replace(/\s+/g, ' ').trim();

    return text.length > max ? `${text.slice(0, max - 1)}…` : text;
}

export function statusLabel(value) {
    const status = String(value ?? '').toLowerCase();

    if (status.includes('running') || status === 'up' || status === 'healthy') {
        return 'up';
    }

    if (status.includes('exited') || status.includes('stopped') || status === 'down') {
        return 'down';
    }

    if (status === '' || status === 'unknown') {
        return 'unknown';
    }

    return status;
}

export function table(rows, columns) {
    return rows
        .map((row) => columns.map((column) => String(row[column] ?? '')).join(' · '))
        .join('\n');
}
