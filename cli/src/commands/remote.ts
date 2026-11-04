import { ApiError, applicationLogs, deployApplication, fetchMe, triggerUpgrade } from '../lib/api.js';
import { clearConfig, configDirectory, loadConfig, resolveApi } from '../lib/config.js';
import { printError, printMe, printServers } from '../lib/plain.js';
import { dim, painted, palette } from '../ui/theme.js';
import type { Me } from '../lib/api.js';
import { authenticated, fail, type Options } from './types.js';

export async function status(options: Options): Promise<number> {
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

export async function servers(options: Options): Promise<number> {
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

export async function check(options: Options): Promise<number> {
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
                    '\n' +
                    dim('  install with `dashy upgrade --yes`\n'),
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

export async function upgrade(options: Options): Promise<number> {
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

export async function logs(options: Options): Promise<number> {
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
        const output = await applicationLogs(auth.api, auth.token, uuid, Number.isFinite(lines) ? lines : 100);
        process.stdout.write(typeof output === 'string' ? output : JSON.stringify(output, null, 2) + '\n');

        return 0;
    } catch (error) {
        return fail(error);
    }
}

export async function deploy(options: Options): Promise<number> {
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

export async function doctor(options: Options): Promise<number> {
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
        process.stdout.write(JSON.stringify(checks.map(([label, ok, detail]) => ({ check: label, ok, detail })), null, 2) + '\n');
    } else {
        for (const [label, ok, detail] of checks) {
            process.stdout.write(
                (ok ? painted('✔ ', palette.green) : painted('✖ ', palette.red)) + label.padEnd(20) + dim(detail) + '\n',
            );
        }
    }

    return checks.every(([, ok]) => ok) ? 0 : 1;
}

export function signout(): number {
    clearConfig();
    process.stdout.write(painted('✔ ', palette.green) + 'Signed out. The token was removed from this machine.\n');

    return 0;
}

export async function pairCode(api: string, code: string): Promise<number> {
    try {
        const result = await (await import('../lib/api.js')).pairWithCode(api, code.trim().toUpperCase(), 'CLI · ' + process.platform);
        const { saveConfig } = await import('../lib/config.js');
        const { printPair } = await import('../lib/plain.js');

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
        return error instanceof ApiError ? fail(error) : fail(error);
    }
}
