import { readdir, writeFile } from 'node:fs/promises';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const dist = fileURLToPath(new URL('../dist/', import.meta.url));

async function walk(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        if (entry.name === 'manifest.txt') {
            continue;
        }

        const full = join(directory, entry.name);

        if (entry.isDirectory()) {
            files.push(...await walk(full));
        } else if (entry.isFile()) {
            files.push(relative(dist, full).split(sep).join('/'));
        }
    }

    return files;
}

const files = (await walk(dist)).sort();
await writeFile(join(dist, 'manifest.txt'), `${files.join('\n')}\n`);
console.log(`manifest: ${files.length} files`);
