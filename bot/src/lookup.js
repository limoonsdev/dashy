import dns from 'node:dns/promises';
import net from 'node:net';

const HOST_RE = /^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i;
const WHOIS_MAX_BYTES = 200000;

export function normalizeHost(input) {
    let host = String(input ?? '').trim().toLowerCase();
    host = host.replace(/^[a-z][a-z0-9+.-]*:\/\//, '');
    host = host.split('/')[0].split('?')[0].split('#')[0].replace(/\.$/, '');

    if (host.startsWith('www.')) {
        host = host.slice(4);
    }

    return HOST_RE.test(host) ? host : null;
}

export async function rdap(domain, { fetchImpl = fetch, timeoutMs = 8000 } = {}) {
    const host = normalizeHost(domain);

    if (host === null) {
        return { status: 'invalid', error: 'Enter a domain such as example.com.' };
    }

    try {
        const response = await fetchImpl(`https://rdap.org/domain/${encodeURIComponent(host)}`, {
            headers: { accept: 'application/rdap+json' },
            signal: AbortSignal.timeout(timeoutMs),
        });

        if (response.status === 404) {
            return { status: 'available', domain: host };
        }

        if (response.status === 429) {
            return { status: 'unknown', domain: host, error: 'Registry rate limit reached. Try again shortly.' };
        }

        if (!response.ok) {
            return { status: 'unknown', domain: host, error: `Registry returned HTTP ${response.status}.` };
        }

        const data = await response.json();
        let registrar = null;

        for (const entity of data.entities ?? []) {
            if (!Array.isArray(entity.roles) || !entity.roles.includes('registrar')) {
                continue;
            }

            for (const field of entity.vcardArray?.[1] ?? []) {
                if (Array.isArray(field) && field[0] === 'fn' && typeof field[3] === 'string') {
                    registrar = field[3];
                    break;
                }
            }
        }

        const eventDate = (action) => (data.events ?? [])
            .find((event) => event.eventAction === action)?.eventDate ?? null;

        return {
            status: 'registered',
            domain: host,
            registrar,
            created: eventDate('registration'),
            expires: eventDate('expiration'),
            nameservers: (data.nameservers ?? [])
                .map((server) => server.ldhName)
                .filter(Boolean),
        };
    } catch (error) {
        return { status: 'unknown', domain: host, error: error?.name === 'TimeoutError' ? 'The registry timed out.' : 'The registry could not be reached.' };
    }
}

export async function dnsRecords(domain, { resolve = dns } = {}) {
    const host = normalizeHost(domain);

    if (host === null) {
        return { status: 'invalid', error: 'Enter a host such as example.com.' };
    }

    const safe = (promise) => promise.catch(() => []);

    const [a, aaaa, cname, mx, ns, txt] = await Promise.all([
        safe(resolve.resolve4(host)),
        safe(resolve.resolve6(host)),
        safe(resolve.resolveCname(host)),
        safe(resolve.resolveMx(host)),
        safe(resolve.resolveNs(host)),
        safe(resolve.resolveTxt(host)),
    ]);

    const records = [];

    for (const value of a) {
        records.push({ type: 'A', value });
    }

    for (const value of aaaa) {
        records.push({ type: 'AAAA', value });
    }

    for (const value of cname) {
        records.push({ type: 'CNAME', value });
    }

    for (const entry of mx) {
        records.push({ type: 'MX', value: `${entry.priority} ${entry.exchange}` });
    }

    for (const value of ns) {
        records.push({ type: 'NS', value });
    }

    for (const entry of txt) {
        records.push({ type: 'TXT', value: entry.join('') });
    }

    return { status: 'ok', domain: host, records };
}

export async function findIp(domain, { resolve = dns } = {}) {
    const host = normalizeHost(domain);

    if (host === null) {
        return { status: 'invalid', error: 'Enter a host such as example.com.' };
    }

    const addresses = await Promise.all([
        resolve.resolve4(host).catch(() => []),
        resolve.resolve6(host).catch(() => []),
    ]);

    const values = [...addresses[0], ...addresses[1]];

    return values.length === 0
        ? { status: 'unknown', domain: host, error: 'No address record found.' }
        : { status: 'ok', domain: host, addresses: values };
}

function queryWhois(server, query, timeoutMs = 8000) {
    return new Promise((resolve) => {
        const socket = net.createConnection({ host: server, port: 43 });
        let data = '';
        let settled = false;

        const finish = (value) => {
            if (!settled) {
                settled = true;
                socket.destroy();
                resolve(value);
            }
        };

        socket.setTimeout(timeoutMs);
        socket.setEncoding('utf8');
        socket.on('connect', () => socket.write(`${query}\r\n`));
        socket.on('data', (chunk) => {
            data += chunk;

            if (data.length >= WHOIS_MAX_BYTES) {
                finish(data.slice(0, WHOIS_MAX_BYTES));
            }
        });
        socket.on('end', () => finish(data));
        socket.on('timeout', () => finish(data));
        socket.on('error', () => finish(null));
    });
}

export async function whois(domain) {
    const host = normalizeHost(domain);

    if (host === null) {
        return { status: 'invalid', error: 'Enter a domain such as example.com.' };
    }

    const tld = host.split('.').pop();
    const referral = await queryWhois('whois.iana.org', tld);
    const server = referral?.match(/^refer:\s*(\S+)/mi)?.[1] ?? null;

    if (!server) {
        return { status: 'unknown', domain: host, error: `No WHOIS server is known for .${tld}.` };
    }

    const raw = await queryWhois(server, host);

    if (!raw) {
        return { status: 'unknown', domain: host, server, error: 'The WHOIS server did not answer.' };
    }

    const status = /(no match for|not found|no data found|no entries found|domain not found)/i.test(raw)
        ? 'available'
        : /(domain name:|registry domain id:|registrar:)/i.test(raw)
            ? 'registered'
            : 'unknown';

    return { status, domain: host, server, raw: raw.slice(0, 3500) };
}
