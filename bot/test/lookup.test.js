import assert from 'node:assert/strict';
import test from 'node:test';
import { dnsRecords, normalizeHost, rdap } from '../src/lookup.js';

test('normalizeHost accepts URLs and rejects junk', () => {
    assert.equal(normalizeHost('HTTPS://Example.COM/path?q=1'), 'example.com');
    assert.equal(normalizeHost('www.example.com.'), 'example.com');
    assert.equal(normalizeHost('not a host'), null);
    assert.equal(normalizeHost('http://'), null);
});

test('rdap marks 404 as available', async () => {
    const fetchImpl = async () => ({ status: 404, ok: false, json: async () => ({}) });

    assert.deepEqual(await rdap('example.com', { fetchImpl }), { status: 'available', domain: 'example.com' });
});

test('rdap parses registrar, dates and nameservers', async () => {
    const payload = {
        ldhName: 'EXAMPLE.COM',
        entities: [{ roles: ['registrar'], vcardArray: ['vcard', [['fn', {}, 'text', 'Example Registrar']]] }],
        events: [
            { eventAction: 'registration', eventDate: '1995-08-14T04:00:00Z' },
            { eventAction: 'expiration', eventDate: '2027-08-13T04:00:00Z' },
        ],
        nameservers: [{ ldhName: 'A.IANA-SERVERS.NET' }],
    };
    const fetchImpl = async () => ({ status: 200, ok: true, json: async () => payload });

    const result = await rdap('example.com', { fetchImpl });

    assert.equal(result.status, 'registered');
    assert.equal(result.registrar, 'Example Registrar');
    assert.equal(result.created, '1995-08-14T04:00:00Z');
    assert.deepEqual(result.nameservers, ['A.IANA-SERVERS.NET']);
});

test('rdap handles invalid input without a request', async () => {
    let called = false;
    const fetchImpl = async () => { called = true; };

    assert.equal((await rdap('nope', { fetchImpl })).status, 'invalid');
    assert.equal(called, false);
});

test('dnsRecords aggregates all record types', async () => {
    const resolve = {
        resolve4: async () => ['93.184.216.34'],
        resolve6: async () => ['2606:2800:220:1::1'],
        resolveCname: async () => ['edge.example.net'],
        resolveMx: async () => [{ priority: 10, exchange: 'mail.example.com' }],
        resolveNs: async () => ['a.iana-servers.net'],
        resolveTxt: async () => [['v=spf1 -all']],
    };

    const result = await dnsRecords('example.com', { resolve });

    assert.equal(result.status, 'ok');
    assert.deepEqual(result.records.map((record) => record.type), ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT']);
    assert.equal(result.records[3].value, '10 mail.example.com');
});

test('dnsRecords survives missing record types', async () => {
    const fail = async () => { throw new Error('ENODATA'); };
    const result = await dnsRecords('example.com', {
        resolve: { resolve4: fail, resolve6: fail, resolveCname: fail, resolveMx: fail, resolveNs: fail, resolveTxt: fail },
    });

    assert.equal(result.status, 'ok');
    assert.deepEqual(result.records, []);
});
