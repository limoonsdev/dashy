import assert from 'node:assert/strict';
import test from 'node:test';
import { createCommands, helpText } from '../src/commands.js';

const dashy = {
    version: async () => ({ version: '4.1.0', name: 'dashy' }),
    servers: async () => [{ name: 'eu-1', is_reachable: true }, { name: 'eu-2', settings: { is_reachable: false } }],
    applications: async () => [{ name: 'api', status: 'running:healthy', fqdn: 'api.example.com' }],
    databases: async () => [{ name: 'db', database_type: 'standalone-postgresql', status: 'running' }],
    projects: async () => [{ name: 'shop' }],
    deployments: async () => [{ deployment_uuid: 'dep-1', status: 'finished' }],
    deploy: async (uuid) => ({ message: `Queued ${uuid}.` }),
};

const lookup = {
    rdap: async () => ({
        status: 'registered',
        domain: 'example.com',
        registrar: 'Example Registrar',
        created: '1995-08-14',
        expires: '2027-08-13',
        nameservers: ['a.iana-servers.net'],
    }),
    dnsRecords: async () => ({
        status: 'ok',
        domain: 'example.com',
        records: [{ type: 'A', value: '93.184.216.34' }],
    }),
    whois: async () => ({ status: 'registered', domain: 'example.com', raw: 'Domain Name: EXAMPLE.COM' }),
    findIp: async () => ({ status: 'ok', domain: 'example.com', addresses: ['93.184.216.34'] }),
};

const config = { dashy: { token: 'x' } };

function commandMap() {
    return Object.fromEntries(createCommands({ dashy, lookup, config }).map((command) => [command.name, command]));
}

const ctx = (args = []) => ({ platform: 'discord', userId: '1', args, isAdmin: true });

test('help lists every command', () => {
    assert.match(helpText(), /\/deploy —/);
    assert.match(helpText(), /\/dns —/);
});

test('status counts resources', async () => {
    const reply = await commandMap().status.run(ctx());

    assert.match(reply, /servers: 2/);
    assert.match(reply, /applications: 1/);
    assert.match(reply, /databases: 1/);
});

test('servers marks unreachable hosts', async () => {
    const reply = await commandMap().servers.run(ctx());

    assert.match(reply, /eu-1 — ok/);
    assert.match(reply, /eu-2 — unreachable/);
});

test('apps shows status and domain', async () => {
    const reply = await commandMap().apps.run(ctx());

    assert.match(reply, /api — up — api\.example\.com/);
});

test('domain reports registration details', async () => {
    const reply = await commandMap().domain.run(ctx(['example.com']));

    assert.match(reply, /example\.com is registered/);
    assert.match(reply, /Example Registrar/);
});

test('dns lists records', async () => {
    const reply = await commandMap().dns.run(ctx(['example.com']));

    assert.match(reply, /A 93\.184\.216\.34/);
});

test('deploy needs a uuid', async () => {
    assert.match(await commandMap().deploy.run(ctx([])), /Usage: \/deploy/);
    assert.match(await commandMap().deploy.run(ctx(['abc'])), /Queued abc\./);
});

test('version formats the instance version', async () => {
    assert.equal(await commandMap().version.run(ctx()), 'Dashy 4.1.0 (dashy)');
});
