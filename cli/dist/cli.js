#!/usr/bin/env node
import { resolveApi } from './lib/config.js';
import { printError } from './lib/plain.js';
import { dim, painted, palette } from './ui/theme.js';
import { runApp } from './app.js';
import { check, deploy, doctor, logs, pairCode, servers, signout, status, upgrade } from './commands/remote.js';
const VERSION = '2.1.0';
const USAGE = `
${painted('◆ dashy', palette.brand, true)} ${dim(VERSION + ' · terminal console')}

${painted('USAGE', palette.muted)}
  dashy                        full-screen console
  dashy connect [code]         pair this terminal
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
function parseArguments(argv) {
    const options = { json: false, demo: false, yes: false, positionals: [] };
    for (let index = 0; index < argv.length; index++) {
        const argument = argv[index];
        if (argument === '--api') {
            options.api = argv[++index];
        }
        else if (argument.startsWith('--api=')) {
            options.api = argument.slice(6);
        }
        else if (argument === '--json') {
            options.json = true;
        }
        else if (argument === '--demo') {
            options.demo = true;
        }
        else if (argument === '--yes' || argument === '-y') {
            options.yes = true;
        }
        else {
            options.positionals.push(argument);
        }
    }
    return options;
}
export async function main(argv = process.argv.slice(2)) {
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
        const { demoState } = await import('./lib/state.js');
        const { draw } = await import('./app.js');
        process.stdout.write(draw(demoState(), process.stdout.columns ?? 100).join('\n') + '\n');
        return 0;
    }
    switch (command) {
        case 'ui':
        case 'console':
            return runApp({ api: options.api });
        case 'connect':
        case 'login': {
            const code = options.positionals[1];
            const api = resolveApi(options.api);
            if (!code) {
                return runApp({ api: options.api });
            }
            if (api === '') {
                printError('Pass --api <url> with the code, or run `dashy connect` interactively.');
                return 1;
            }
            return pairCode(api, code);
        }
        case 'status':
            return status(options);
        case 'servers':
            return servers(options);
        case 'check':
        case 'update':
            return check(options);
        case 'upgrade':
            return upgrade(options);
        case 'logs':
            return logs(options);
        case 'deploy':
            return deploy(options);
        case 'doctor':
            return doctor(options);
        case 'signout':
        case 'logout':
            return signout();
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
