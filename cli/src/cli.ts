#!/usr/bin/env node
import { homedir } from 'node:os';
import { ApiError, applicationLogs, deployApplication, fetchMe, pairWithCode, triggerUpgrade } from './api.js';
import { clearConfig, configDirectory, loadConfig, resolveApi, saveConfig } from './config.js';
import { printError, printMe, printPair, printServers } from './plain.js';
import { dim, painted, palette } from './theme.js';
import { runApp } from './app.js';
import type { Me } from './api.js';

const VERSION = '2.0.0';

const USAGE = `
${painted('◆ dashy', palette.brand, true)} ${dim(VERSION + ' · self-hosted control plane')}

${painted('USAGE', palette.muted)}
  dashy                        full-screen console
  dashy connect [code]         pair this terminal with an instance
  dashy status                 instance summary
  dashy servers                servers and reachability
  dashy check                  check for a new release
  dashy upgrade --yes          install the latest release
  dashy logs <uuid> [lines]    read application logs
  dashy deploy <uuid>          deploy an application
  dashy doctor                 diagnose configuration and connectivity
  dashy signout                remove the stored token

${painted('OPTIONS', palette.muted)}
  --api <url>                  instance URL (defaults to the paired one)
  --json                       print raw JSON
  --demo                       render the console with sample data
  --version, -v                print the version
  --help, -h                   print this help
`;

type Options = {
    api?: string;
    json: boolean;
    demo: boolean;
    yes: boolean;
    positionals: string[];
};

function parseArguments(argv: string[]): Options {
    const options: Options = { json: false, demo: false, yes: false, positionals: [] };

    for (let index = 0; index < argv.length; index++) {
        const argument = argv[index];

        if (argument === '--api') {
            options.api = argv[++index];
        } else if (argument.startsWith('--api=')) {
            options.api = argument.slice(6);
        } else if (argument === '--json') {
            options.json = true;
        } else if (argument === '--demo') {
            options.demo = true;
        } else if (argument === '--yes' || argument === '-y') {
            options.yes = true;
        } else {
            options.positionals.push(argument);
        }
    }

    return options;
}

function authenticated(options: Options): { api: string; token: string } | null {
    const config = loadConfig();
    const api = resolveApi(options.api);

    if (!config.token || api === '') {
        printError(
            config.token
                ? 'No instance configured. Pass --api <url>.'
                : 'Not connected. Run `dashy connect` and paste a code from Settings → CLI.',
        );
        return null;
    }

    return { api, token: config.token };
}

const fail = (error: unknown): number => {
    printError(error instanceof ApiError ? error.message : error instanceof Error ? error.message : String(error));
    return 1;
};

async function commandStatus(options: Options): Promise<number> {
    const auth = authenticated(options);

    if (!auth) {
        return 1;
    }

    try {
        const me = await fetchMe(auth.api, auth.token);

        if (options.json) {
            process.stdout.write(JSON.stringify(me, null, 2) + '\n');
        } else {
            printMe(me);
        }

        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandServers(options: Options): Promise<number> {
    const auth = authenticated(options);

    if (!auth) {
        return 1;
    }

    try {
        const me = await fetchMe(auth.api, auth.token);

        if (options.json) {
            process.stdout.write(JSON.stringify(me.servers, null, 2) + '\n');
        } else {
            printServers(me);
        }

        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandCheck(options: Options): Promise<number> {
    const auth = authenticated(options);

    if (!auth) {
        return 1;
    }

    try {
        const me: Me = await fetchMe(auth.api, auth.token);

        if (options.json) {
            process.stdout.write(JSON.stringify(me.update, null, 2) + '\n');
        } else if (me.update.available) {
            process.stdout.write(
                painted('▲ update available  ', palette.amber, true) +
                    me.update.current +
                    ' → ' +
                    painted(me.update.latest, palette.amber, true) +
                    '\n' + dim('  run `dashy upgrade --yes` to install it\n'),
            );
        } else {
            process.stdout.write(painted('● up to date  ', palette.green, true) + me.update.current + '\n');
        }

        if (me.update.error) {
            process.stdout.write(dim('  note: ' + me.update.error + '\n'));
        }

        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandUpgrade(options: Options): Promise<number> {
    const auth = authenticated(options);

    if (!auth) {
        return 1;
    }

    if (!options.yes) {
        process.stdout.write(
            painted('! ', palette.amber) +
                'This installs the latest release and restarts the instance.\n' +
                dim('  re-run with --yes to confirm\n'),
        );
        return 1;
    }

    try {
        const result = await triggerUpgrade(auth.api, auth.token);
        process.stdout.write(painted('✔ ', palette.green) + (result.message ?? 'Update started.') + '\n');
        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandConnect(options: Options): Promise<number> {
    const code = options.positionals[1];
    const api = resolveApi(options.api);

    if (!code) {
        return runApp({ api: options.api });
    }

    if (api === '') {
        printError('Pass --api <url> with the code, or run `dashy connect` interactively.');
        return 1;
    }

    try {
        const result = await pairWithCode(api, code.trim().toUpperCase(), 'CLI · ' + process.platform);
        saveConfig({
            api,
            token: result.token,
            abilities: result.abilities,
            instance: result.instance,
            user: result.user,
            team: result.team,
            paired_at: new Date().toISOString(),
            expires_at: result.expires_at,
        });
        printPair(result);
        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandLogs(options: Options): Promise<number> {
    const auth = authenticated(options);
    const uuid = options.positionals[1];
    const lines = Number(options.positionals[2] ?? 100);

    if (!auth) {
        return 1;
    }

    if (!uuid) {
        printError('Usage: dashy logs <application-uuid> [lines]');
        return 1;
    }

    try {
        const logs = await applicationLogs(auth.api, auth.token, uuid, Number.isFinite(lines) ? lines : 100);
        process.stdout.write(typeof logs === 'string' ? logs : JSON.stringify(logs, null, 2) + '\n');
        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandDeploy(options: Options): Promise<number> {
    const auth = authenticated(options);
    const uuid = options.positionals[1];

    if (!auth) {
        return 1;
    }

    if (!uuid) {
        printError('Usage: dashy deploy <application-uuid>');
        return 1;
    }

    try {
        await deployApplication(auth.api, auth.token, uuid);
        process.stdout.write(painted('✔ ', palette.green) + 'Deployment queued for ' + uuid + '\n');
        return 0;
    } catch (error) {
        return fail(error);
    }
}

async function commandDoctor(options: Options): Promise<number> {
    const config = loadConfig();
    const api = resolveApi(options.api);
    const checks: Array<[string, boolean, string]> = [];
    const nodeMajor = Number(process.versions.node.split('.')[0]);

    checks.push(['Node.js ≥ 18', nodeMajor >= 18, process.versions.node]);
    checks.push(['Config directory', true, configDirectory()]);
    checks.push(['Stored token', Boolean(config.token), config.token ? 'present' : 'missing — run `dashy connect`']);
    checks.push(['Instance URL', api !== '', api || 'missing']);

    if (api !== '' && config.token) {
        try {
            const me = await fetchMe(api, config.token);
            checks.push(['API reachable', true, me.instance.name + ' ' + me.instance.version]);
            checks.push(['Token valid', true, me.user?.email ?? 'unknown user']);
        } catch (error) {
            checks.push(['API reachable', false, error instanceof Error ? error.message : String(error)]);
        }
    }

    if (options.json) {
        process.stdout.write(
            JSON.stringify(checks.map(([label, ok, detail]) => ({ check: label, ok, detail })), null, 2) + '\n',
        );
    } else {
        for (const [label, ok, detail] of checks) {
            process.stdout.write(
                (ok ? painted('✔ ', palette.green) : painted('✖ ', palette.red)) +
                    label.padEnd(20) +
                    dim(detail) +
                    '\n',
            );
        }
    }

    return checks.every(([, ok]) => ok) ? 0 : 1;
}

async function commandSignout(): Promise<number> {
    clearConfig();
    process.stdout.write(painted('✔ ', palette.green) + 'Signed out. The token was removed from ' + homedir() + '.\n');
    return 0;
}

export async function main(argv = process.argv.slice(2)): Promise<number> {
    if (argv.includes('--version') || argv.includes('-v')) {
        process.stdout.write('dashy ' + VERSION + '\n');
        return 0;
    }

    if (argv.includes('--help') || argv.includes('-h') || argv[0] === 'help') {
        process.stdout.write(USAGE);
        return 0;
    }

    const options = parseArguments(argv);
    const command = options.positionals[0] ?? 'ui';

    if (options.demo) {
        const { demoState } = await import('./types.js');
        const { draw } = await import('./app.js');
        process.stdout.write(draw(demoState(), process.stdout.columns ?? 100).join('\n') + '\n');
        return 0;
    }

    switch (command) {
        case 'ui':
        case 'console':
            return runApp({ api: options.api });
        case 'connect':
        case 'login':
            return commandConnect(options);
        case 'status':
            return commandStatus(options);
        case 'servers':
            return commandServers(options);
        case 'check':
        case 'update':
            return commandCheck(options);
        case 'upgrade':
            return commandUpgrade(options);
        case 'logs':
            return commandLogs(options);
        case 'deploy':
            return commandDeploy(options);
        case 'doctor':
            return commandDoctor(options);
        case 'signout':
        case 'logout':
            return commandSignout();
        default:
            printError('Unknown command: ' + command);
            process.stdout.write(USAGE);
            return 1;
    }
}

main()
    .then((code) => {
        process.exitCode = code;
    })
    .catch((error) => {
        printError(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    });
