import assert from 'node:assert/strict';
import test from 'node:test';
import { createRouter, parseCommand } from '../src/router.js';

function makeRouter({ max = 10, admins = [], allowed = [] } = {}) {
    const config = {
        allowed: new Set(allowed),
        admins: new Set(admins),
        rateLimit: { windowMs: 60000, max },
    };

    const commands = [
        { name: 'help', run: async () => 'help text' },
        { name: 'echo', run: async (ctx) => ctx.args.join(' ') },
        { name: 'deploy', admin: true, run: async () => 'deployed' },
        { name: 'boom', run: async () => { throw new Error('kaput'); } },
    ];

    return createRouter({ commands, config, now: () => 1000 });
}

test('parse: strips prefix and bot mention', () => {
    assert.deepEqual(parseCommand('/echo@DashyBot hello world'), { name: 'echo', args: ['hello', 'world'] });
    assert.deepEqual(parseCommand('!echo  a  b '), { name: 'echo', args: ['a', 'b'] });
    assert.deepEqual(parseCommand('   '), { name: '', args: [] });
});

test('routes a known command and returns args', async () => {
    const handle = makeRouter();

    assert.equal(await handle({ platform: 'telegram', userId: '1', text: '/echo hi there' }), 'hi there');
});

test('help and unknown commands', async () => {
    const handle = makeRouter();

    assert.equal(await handle({ platform: 'telegram', userId: '1', text: '/help' }), 'help text');
    assert.match(await handle({ platform: 'telegram', userId: '1', text: '/nope' }), /Unknown command: nope/);
});

test('admin command is denied without admin rights', async () => {
    const handle = makeRouter({ admins: ['admin'] });

    assert.match(await handle({ platform: 'discord', userId: 'user', text: '/deploy abc' }), /requires an admin/);
    assert.equal(await handle({ platform: 'discord', userId: 'admin', text: '/deploy abc' }), 'deployed');
});

test('allowlist blocks unknown users when configured', async () => {
    const handle = makeRouter({ allowed: ['discord:42'] });

    assert.match(await handle({ platform: 'discord', userId: '99', text: '/help' }), /not allowed/);
    assert.equal(await handle({ platform: 'discord', userId: '42', text: '/help' }), 'help text');
});

test('rate limit stops the burst', async () => {
    const handle = makeRouter({ max: 2 });

    assert.equal(await handle({ platform: 'telegram', userId: '7', text: '/help' }), 'help text');
    assert.equal(await handle({ platform: 'telegram', userId: '7', text: '/help' }), 'help text');
    assert.match(await handle({ platform: 'telegram', userId: '7', text: '/help' }), /Too many commands/);
});

test('command errors are reported without a stack', async () => {
    const handle = makeRouter();
    const reply = await handle({ platform: 'telegram', userId: '1', text: '/boom' });

    assert.equal(reply, 'Command failed: kaput');
});
