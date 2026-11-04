export type NoteLine = { kind: 'add' | 'del' | 'ctx'; text: string };

const ADDED_SECTION = /^#{1,6}\s*(added|new|features?|improvements?|highlights?)/i;
const REMOVED_SECTION = /^#{1,6}\s*(removed|deprecated|breaking|fixes? that remove)/i;

/**
 * GitHub release bodies are written as Markdown. The CLI shows them the way a
 * changelog is usually read: additions in green, removals in red, everything
 * else as context. Bullets under an "Added" or "Removed" heading inherit that
 * section, so a release written the normal way still renders correctly.
 */
export function parseNotes(notes: string | null | undefined, limit = 14): NoteLine[] {
    if (!notes) {
        return [];
    }

    const lines: NoteLine[] = [];
    let section: NoteLine['kind'] = 'ctx';

    for (const raw of notes.split('\n')) {
        const line = raw.trim();

        if (line === '' || line.startsWith('<!--') || line.startsWith('**Full Changelog**')) {
            continue;
        }

        if (ADDED_SECTION.test(line)) {
            section = 'add';
            continue;
        }

        if (REMOVED_SECTION.test(line)) {
            section = 'del';
            continue;
        }

        if (/^#{1,6}\s/.test(line)) {
            section = 'ctx';
            lines.push({ kind: 'ctx', text: line.replace(/^#{1,6}\s*/, '') });
            continue;
        }

        if (line.startsWith('+++') || line.startsWith('---')) {
            continue;
        }

        if (line.startsWith('+ ') || line.startsWith('+')) {
            lines.push({ kind: 'add', text: line.replace(/^\+\s*/, '') });
            continue;
        }

        if (line.startsWith('- ') || line.startsWith('* ')) {
            const text = line.replace(/^[-*]\s*/, '');
            lines.push({ kind: section === 'del' ? 'del' : section === 'add' ? 'add' : 'ctx', text });
            continue;
        }

        lines.push({ kind: section === 'del' ? 'del' : 'add', text: line.replace(/^\d+\.\s*/, '') });
    }

    return lines.slice(0, limit);
}
