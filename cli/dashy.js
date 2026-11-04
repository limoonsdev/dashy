#!/usr/bin/env node
'use strict';

/*
 * Dashy CLI — a single-file terminal client.
 *
 * It has no dependencies on purpose: `dashy` must work on a fresh Linux box, a
 * Mac and Windows with only Node 18+ installed. Everything it does goes through
 * the instance HTTP API, so the token and its abilities decide what is possible.
 */

const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const VERSION = '1.0.0';
const CONFIG_DIR = process.env.DASHY_HOME || path.join(os.homedir(), '.dashy');
const CONFIG_FILE = path.join(CONFIG_DIR, 'config.json');
const COLOR = Boolean(process.stdout.isTTY) && !process.env.NO_COLOR;
const MIN_NODE = 18;

const LOGO = [
  '██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗',
  '██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝',
  '██║  ██║███████║███████╗███████║ ╚████╔╝ ',
  '██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝  ',
  '██████╔╝██║  ██║███████║██║  ██║   ██║   ',
  '╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝   ',
];

const P = {
  reset: '\x1b[0m',
  bold: '\x1b[1m',
  dim: '\x1b[2m',
  brand: '\x1b[38;2;139;92;246m',
  accent: '\x1b[38;2;34;211;238m',
  green: '\x1b[38;2;52;211;153m',
  amber: '\x1b[38;2;251;191;36m',
  red: '\x1b[38;2;248;113;113m',
  grey: '\x1b[38;2;148;163;184m',
  white: '\x1b[38;2;226;232;240m',
};

const paint = (name, text) => (COLOR ? P[name] + text + P.reset : String(text));
const bold = (text) => (COLOR ? P.bold + text + P.reset : text);
const dim = (text) => paint('grey', text);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function rgb(r, g, b, text) {
  return `\x1b[38;2;${r};${g};${b}m${text}\x1b[0m`;
}

function logoFrame(phase) {
  if (!COLOR) {
    return LOGO.map((line) => '  ' + line).join('\n');
  }

  const hot = Math.floor((Math.sin(phase) * 0.5 + 0.5) * (LOGO.length - 1));

  return LOGO.map((line, i) => {
    const k = i / (LOGO.length - 1);
    let r = Math.round(139 + (34 - 139) * k);
    let g = Math.round(92 + (211 - 92) * k);
    let b = Math.round(246 + (238 - 246) * k);

    if (i === hot) {
      r = Math.min(255, r + 70);
      g = Math.min(255, g + 45);
      b = Math.min(255, b + 10);
    }

    return '  ' + rgb(r, g, b, line);
  }).join('\n');
}

const clear = () => process.stdout.write('\x1b[2J\x1b[H');
const hideCursor = () => process.stdout.isTTY && process.stdout.write('\x1b[?25l');
const showCursor = () => process.stdout.isTTY && process.stdout.write('\x1b[?25h');

async function boot(tagline = 'self-hosted control plane') {
  if (!COLOR) {
    console.log(logoFrame(0));
    return;
  }

  hideCursor();

  for (let frame = 0; frame < 16; frame++) {
    clear();
    console.log('');
    console.log(logoFrame(frame / 2.2));
    console.log('');
    console.log('  ' + dim('initialising · ' + tagline));
    await sleep(55);
  }

  clear();
  console.log('');
  console.log(logoFrame(1.4));
  console.log('');
  console.log('  ' + paint('brand', 'Dashy CLI ' + VERSION) + dim('  ·  ' + tagline));
  console.log('  ' + dim('─'.repeat(46)));
  showCursor();
}

function loadConfig() {
  try {
    return JSON.parse(fs.readFileSync(CONFIG_FILE, 'utf8'));
  } catch {
    return {};
  }
}

function saveConfig(config) {
  fs.mkdirSync(CONFIG_DIR, { recursive: true, mode: 0o700 });
  fs.writeFileSync(CONFIG_FILE, JSON.stringify(config, null, 2) + '\n', { mode: 0o600 });

  if (process.platform !== 'win32') {
    try {
      fs.chmodSync(CONFIG_FILE, 0o600);
    } catch {
      // Best effort: the file was created with 0600 above.
    }
  }
}

function normalizeApi(value) {
  let api = String(value || '').trim().replace(/\/+$/, '');

  if (api === '') {
    return '';
  }

  if (!/^https?:\/\//i.test(api)) {
    api = (api.startsWith('localhost') || api.startsWith('127.') ? 'http://' : 'https://') + api;
  }

  return api.replace(/\/api(\/v1)?$/i, '');
}

function configApi(override) {
  return normalizeApi(override || process.env.DASHY_API || loadConfig().api || '');
}

async function request(method, url, { token, body, timeout = 20000 } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (token) {
    headers.Authorization = 'Bearer ' + token;
  }

  const response = await fetch(url, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
    signal: AbortSignal.timeout(timeout),
  });

  const text = await response.text();
  let data = null;

  try {
    data = text === '' ? null : JSON.parse(text);
  } catch {
    data = { raw: text };
  }

  if (!response.ok) {
    const message = (data && (data.message || data.error)) || 'HTTP ' + response.status;
    const error = new Error(message);
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

function requireConfig() {
  const config = loadConfig();

  if (!config.api || !config.token) {
    throw new Error('Not connected yet. Run: dashy connect <code>');
  }

  return config;
}

function printError(error) {
  console.error(paint('red', '✖ ') + error.message);

  if (error.status === 401) {
    console.error(dim('  The token was revoked or expired. Run `dashy connect <code>` again.'));
  }

  if (error.status === 403) {
    console.error(dim('  This code grants fewer abilities than that action needs.'));
  }
}

async function connect(code, apiOverride) {
  if (!code) {
    console.error(paint('red', '✖ ') + 'Missing code. Usage: dashy connect <code>');
    process.exitCode = 1;
    return;
  }

  const api = configApi(apiOverride);
  if (!api) {
    console.error(paint('red', '✖ ') + 'Missing instance URL. Usage: dashy connect <code> --api https://dashy.example.com');
    process.exitCode = 1;
    return;
  }

  await boot('pairing terminal');

  process.stdout.write('  ' + dim('Exchanging the code with ' + api + ' … '));
  const result = await request('POST', api + '/api/cli/pair', {
    body: { code, name: os.hostname() || 'terminal' },
  });
  console.log(paint('green', 'ok'));

  saveConfig({
    api,
    token: result.token,
    abilities: result.abilities || ['read'],
    instance: result.instance || null,
    user: result.user || null,
    team: result.team || null,
    paired_at: new Date().toISOString(),
    expires_at: result.expires_at || null,
  });

  console.log('');
  console.log('  ' + paint('green', '✔ Connected') + ' as ' + bold(result.user?.name || 'user') +
    (result.team ? dim(' · team ' + result.team.name) : ''));
  console.log('  ' + dim('Instance: ') + (result.instance?.name || 'Dashy') + dim(' (' + api + ')'));
  console.log('  ' + dim('Abilities: ') + (result.abilities || []).join(', '));
  console.log('');
  console.log('  ' + dim('Run `dashy` for the console, or `dashy status`.'));
  console.log('');
}

async function fetchMe() {
  const config = requireConfig();

  return request('GET', config.api + '/api/cli/me', { token: config.token });
}

function bar(label, value) {
  const width = 12;
  return '  ' + dim(label.padEnd(width)) + value;
}

function printMe(me, config) {
  console.log('');
  console.log('  ' + paint('brand', me.instance.name) + ' ' + dim('· ' + me.instance.url));
  console.log('  ' + dim('─'.repeat(46)));
  console.log(bar('Version', me.instance.version));
  console.log(bar('Signed in', (me.user?.name || 'user') + ' ' + dim('<' + (me.user?.email || '') + '>')));
  console.log(bar('Team', me.team ? me.team.name : '—'));
  console.log(bar('Abilities', (me.abilities || []).join(', ')));
  console.log(bar('Servers', String(me.servers.length)));
  console.log(bar('Reachable', me.servers.filter((server) => server.reachable).length + ' / ' + me.servers.length));
  console.log(bar('Update', me.update.available
    ? paint('amber', 'available · ' + me.update.latest)
    : paint('green', 'up to date · ' + me.update.current)));
  console.log(bar('Checked', me.update.checked_at ? new Date(me.update.checked_at).toLocaleString() : '—'));

  if (me.update.error) {
    console.log(bar('Check note', paint('amber', me.update.error)));
  }

  if (config.expires_at) {
    console.log(bar('Token ends', new Date(config.expires_at).toLocaleDateString()));
  }

  console.log('');
}

async function cmdStatus() {
  const config = requireConfig();
  printMe(await fetchMe(), config);
}

async function cmdServers() {
  const me = await fetchMe();

  console.log('');
  if (me.servers.length === 0) {
    console.log('  ' + dim('No servers in this team yet.'));
  } else {
    const nameWidth = Math.min(28, Math.max(8, ...me.servers.map((server) => server.name.length)));
    for (const server of me.servers) {
      const dot = server.reachable ? paint('green', '●') : paint('red', '●');
      console.log('  ' + dot + ' ' + server.name.padEnd(nameWidth) + ' ' + dim(server.ip || '—'));
    }
  }
  console.log('');
}

async function cmdCheck() {
  const me = await fetchMe();

  console.log('  Running ' + bold(me.update.current) + ', latest ' + bold(me.update.latest));
  console.log('  ' + (me.update.available ? paint('amber', 'An update is available.') : paint('green', 'This instance is up to date.')));
}

async function cmdUpgrade() {
  const config = requireConfig();

  if (!confirmAction('Install the update now? The instance restarts.')) {
    console.log('  ' + dim('Cancelled.'));
    return;
  }

  const result = await request('POST', config.api + '/api/cli/upgrade', { token: config.token });
  console.log('  ' + paint('green', '✔ ') + (result.message || 'Update queued.'));
}

async function cmdDeploy(uuid) {
  const config = requireConfig();

  if (!uuid) {
    throw new Error('Usage: dashy deploy <application-uuid>');
  }

  const result = await request('POST', config.api + '/api/v1/deploy', {
    token: config.token,
    body: { tag: uuid },
  });

  console.log('  ' + paint('green', '✔ ') + ((result.deployments && result.deployments[0] && result.deployments[0].message) || 'Deployment queued.'));
}

async function cmdLogs(uuid) {
  const config = requireConfig();

  if (!uuid) {
    throw new Error('Usage: dashy logs <application-uuid> [lines]');
  }

  const lines = process.argv[3] || '100';
  const data = await request('GET', config.api + '/api/v1/applications/' + uuid + '/logs?lines=' + encodeURIComponent(lines), {
    token: config.token,
  });

  const output = typeof data === 'string' ? data : (data.logs || data.raw || JSON.stringify(data, null, 2));
  console.log(output);
}

function cmdDoctor() {
  const config = loadConfig();

  console.log('');
  console.log('  ' + bold('dashy doctor'));
  console.log('  ' + dim('─'.repeat(46)));
  console.log(bar('Node', process.version));
  console.log(bar('Config', CONFIG_FILE));
  console.log(bar('Instance', config.api || paint('red', 'not configured')));
  console.log(bar('Token', config.token ? paint('green', 'present') : paint('red', 'missing')));
  console.log(bar('Abilities', (config.abilities || []).join(', ') || '—'));
  console.log(bar('Paired', config.paired_at ? new Date(config.paired_at).toLocaleString() : '—'));
  console.log('');
}

function signOut() {
  try {
    fs.unlinkSync(CONFIG_FILE);
  } catch {
    // Already gone.
  }
  console.log('  ' + paint('green', '✔ ') + 'This terminal is signed out.');
}

function confirmAction(question) {
  if (!process.stdin.isTTY) {
    return Promise.resolve(true);
  }

  process.stdout.write('  ' + question + ' ' + dim('[y/N] '));

  return readLineOnce().then((answer) => /^y(es)?$/i.test(answer.trim()));
}

function readLineOnce() {
  return new Promise((resolve) => {
    process.stdin.resume();
    process.stdin.setEncoding('utf8');

    let buffer = '';
    const onData = (chunk) => {
      buffer += chunk;

      const newline = buffer.search(/[\r\n]/);
      if (newline === -1) {
        return;
      }

      process.stdin.pause();
      process.stdin.removeListener('data', onData);
      resolve(buffer.slice(0, newline));
    };

    process.stdin.on('data', onData);
  });
}

function readKey() {
  return new Promise((resolve) => {
    const stdin = process.stdin;
    const wasRaw = stdin.isRaw;

    stdin.setRawMode(true);
    stdin.resume();

    const onData = (buffer) => {
      stdin.setRawMode(Boolean(wasRaw));
      stdin.pause();
      stdin.removeListener('data', onData);
      resolve(buffer.toString('utf8'));
    };

    stdin.on('data', onData);
  });
}

const waitForAnyKey = async () => {
  process.stdout.write('  ' + dim('Press any key to go back.'));
  await readKey();
};

async function interactivePair() {
  console.log('');
  console.log('  ' + paint('amber', 'This terminal is not connected.'));
  console.log('  ' + dim('In Dashy: Settings → CLI → Generate a sign-in code.'));
  console.log('');

  process.stdout.write('  ' + dim('Instance URL: '));
  const api = normalizeApi((await readLineOnce()).trim());

  process.stdout.write('  ' + dim('Code: '));
  const code = (await readLineOnce()).trim();

  await connect(code, api);
}

async function tui() {
  await boot();

  if (!loadConfig().token) {
    await interactivePair();

    if (!loadConfig().token) {
      return;
    }
  }

  const config = () => loadConfig();
  let index = 0;
  const items = [
    {
      label: 'Status',
      run: async () => { printMe(await fetchMe(), config()); await waitForAnyKey(); },
    },
    {
      label: 'Servers',
      run: async () => { await cmdServers(); await waitForAnyKey(); },
    },
    {
      label: 'Check for updates',
      run: async () => { await cmdCheck(); await waitForAnyKey(); },
    },
    {
      label: 'Install update',
      run: async () => { await cmdUpgrade(); await waitForAnyKey(); },
    },
    {
      label: 'Open dashboard',
      run: async () => {
        const url = config().instance?.url && config().instance.url.startsWith('http')
          ? config().instance.url
          : config().api;
        console.log('  ' + dim('Open ') + url);
        await waitForAnyKey();
      },
    },
    {
      label: 'Sign out',
      run: async () => { signOut(); await waitForAnyKey(); },
    },
    {
      label: 'Quit',
      run: async () => { process.exitCode = 0; },
    },
  ];

  for (;;) {
    clear();
    console.log('');
    console.log(logoFrame(1.2).split('\n').slice(0, 3).join('\n'));
    console.log('  ' + dim((config().instance?.name || 'Dashy') + ' · ' + config().api));
    console.log('');

    items.forEach((item, i) => {
      const active = i === index;
      console.log('  ' + (active ? paint('accent', '▸ ') + bold(item.label) : dim('  ' + item.label)));
    });

    console.log('');
    console.log('  ' + dim('↑ ↓ move · enter select · q quit'));

    const key = await readKey();

    if (key === '\u0003' || key === 'q') {
      break;
    }

    if (key === '\u001b[A' || key === 'k') {
      index = (index - 1 + items.length) % items.length;
    } else if (key === '\u001b[B' || key === 'j') {
      index = (index + 1) % items.length;
    } else if (key === '\r' || key === '\n') {
      try {
        await items[index].run();
      } catch (error) {
        console.log('');
        printError(error);
        await waitForAnyKey();
      }
    }
  }

  clear();
  showCursor();
  console.log('  ' + paint('brand', 'Dashy') + dim(' · see you soon.'));
  console.log('');
}

function usage() {
  console.log('');
  console.log(logoFrame(0));
  console.log('');
  console.log('  ' + paint('brand', 'Dashy CLI ' + VERSION));
  console.log('');
  console.log('  ' + bold('Usage'));
  console.log('    dashy                          full-screen console');
  console.log('    dashy connect <code>           pair this terminal');
  console.log('    dashy status                   instance, user, update state');
  console.log('    dashy servers                  servers and reachability');
  console.log('    dashy check                    check for an update');
  console.log('    dashy upgrade                  install the update');
  console.log('    dashy deploy <uuid>            deploy an application');
  console.log('    dashy logs <uuid> [lines]      read application logs');
  console.log('    dashy doctor                   configuration and token check');
  console.log('    dashy signout                  forget the token');
  console.log('');
  console.log('  ' + bold('Options'));
  console.log('    --api <url>                    instance URL for connect/requests');
  console.log('    --version                      print the CLI version');
  console.log('');
}

async function main() {
  if (Number(process.versions.node.split('.')[0]) < MIN_NODE) {
    console.error('Dashy CLI needs Node ' + MIN_NODE + ' or newer (found ' + process.version + ').');
    process.exit(1);
  }

  const args = process.argv.slice(2);

  if (args.includes('--version') || args.includes('-v')) {
    console.log('dashy ' + VERSION);
    return;
  }

  const apiIndex = args.indexOf('--api');
  const apiOverride = apiIndex !== -1 ? args[apiIndex + 1] : undefined;
  const command = args[0] && !args[0].startsWith('-') ? args[0] : undefined;

  try {
    switch (command) {
      case undefined:
        await tui();
        break;
      case 'connect':
        await connect(args[1], apiOverride);
        break;
      case 'status':
        await cmdStatus();
        break;
      case 'servers':
        await cmdServers();
        break;
      case 'check':
        await cmdCheck();
        break;
      case 'upgrade':
        await cmdUpgrade();
        break;
      case 'deploy':
        await cmdDeploy(args[1]);
        break;
      case 'logs':
        await cmdLogs(args[1]);
        break;
      case 'doctor':
        cmdDoctor();
        break;
      case 'signout':
      case 'logout':
        signOut();
        break;
      case 'version':
      case '--version':
      case '-v':
        console.log('dashy ' + VERSION);
        break;
      case 'help':
      case '--help':
      case '-h':
        usage();
        break;
      default:
        console.error('Unknown command: ' + command);
        usage();
        process.exitCode = 1;
    }
  } catch (error) {
    printError(error);
    process.exitCode = 1;
  } finally {
    showCursor();
  }
}

main();
