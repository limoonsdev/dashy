import { h } from './ui/jsx.js';
import { render } from './ui/render.js';
import { Screen } from './views.js';
import { ApiError, fetchMe, pairWithCode, triggerUpgrade } from './api.js';
import { clearConfig, loadConfig, normalizeApi, saveConfig } from './config.js';
import readline from 'node:readline';
const COMPACT_WIDTH = 76;
const MAX_WIDTH = 118;
export function draw(state, terminalWidth) {
    const width = Math.min(MAX_WIDTH, Math.max(COMPACT_WIDTH, terminalWidth));
    const lines = render(h(Screen, { state: state, width: width }), width);
    const margin = Math.max(0, Math.floor((terminalWidth - width) / 2));
    return lines.map((line) => ' '.repeat(margin) + line);
}
const describeError = (error) => error instanceof ApiError ? error.message : error instanceof Error ? error.message : String(error);
export async function runApp(options = {}) {
    const config = loadConfig();
    const state = {
        phase: 'boot',
        bootFrame: 0,
        tab: 0,
        me: null,
        error: null,
        loading: false,
        flash: null,
        confirmUpgrade: false,
        pair: {
            api: normalizeApi(options.api || config.api || process.env.DASHY_API || ''),
            code: '',
            field: config.api ? 'code' : 'api',
            error: null,
            busy: false,
        },
        clock: new Date(),
    };
    let api = state.pair.api;
    let token = config.token ?? '';
    let flashTimer = null;
    const setFlash = (text, tone) => {
        state.flash = { text, tone };
        if (flashTimer) {
            clearTimeout(flashTimer);
        }
        flashTimer = setTimeout(() => {
            state.flash = null;
        }, 4500);
    };
    const refresh = async () => {
        if (!token || api === '') {
            return;
        }
        state.loading = true;
        try {
            state.me = await fetchMe(api, token);
            state.error = null;
            if (state.me.instance.url) {
                api = normalizeApi(state.me.instance.url);
            }
        }
        catch (error) {
            if (error instanceof ApiError && error.status === 401) {
                token = '';
                clearConfig();
                state.me = null;
                state.phase = 'pair';
                state.pair.error = 'This token expired. Generate a new code in Settings → CLI.';
            }
            else {
                state.error = describeError(error);
            }
        }
        finally {
            state.loading = false;
        }
    };
    const connect = async () => {
        if (state.pair.busy) {
            return;
        }
        const target = normalizeApi(state.pair.api);
        if (target === '') {
            state.pair.error = 'Enter the URL of your Dashy instance.';
            return;
        }
        if (state.pair.code.trim() === '') {
            state.pair.error = 'Enter the sign-in code from Settings → CLI.';
            return;
        }
        state.pair.busy = true;
        state.pair.error = null;
        try {
            const result = await pairWithCode(target, state.pair.code.trim().toUpperCase(), 'CLI · ' + process.platform);
            api = target;
            token = result.token;
            saveConfig({
                api,
                token,
                abilities: result.abilities,
                instance: result.instance,
                user: result.user,
                team: result.team,
                paired_at: new Date().toISOString(),
                expires_at: result.expires_at,
            });
            state.me = await fetchMe(api, token);
            state.phase = 'main';
            state.pair.busy = false;
            setFlash('Paired as ' + result.user.email + ' · token valid until ' + new Date(result.expires_at).toLocaleDateString(), 'ok');
            return;
        }
        catch (error) {
            state.pair.error = describeError(error);
        }
        state.pair.busy = false;
    };
    const installUpdate = async () => {
        if (!token) {
            return;
        }
        setFlash('Installing the update…', 'warn');
        try {
            const result = await triggerUpgrade(api, token);
            setFlash(result.message ?? 'Update started. The instance restarts in a moment.', 'ok');
        }
        catch (error) {
            setFlash(describeError(error), 'err');
        }
    };
    if (token && api !== '') {
        void refresh();
    }
    if (!process.stdin.isTTY || !process.stdout.isTTY) {
        // Non-interactive shell: print one frame so `dashy | cat` still works.
        if (token && api !== '') {
            await refresh();
        }
        state.phase = token ? 'main' : 'pair';
        process.stdout.write(draw(state, process.stdout.columns ?? 100).join('\n') + '\n');
        return state.error ? 1 : 0;
    }
    const write = (value) => {
        process.stdout.write(value);
    };
    process.stdout.write('\x1b[?1049h\x1b[?25l');
    write('\x1b[2J');
    const cleanup = () => {
        write('\x1b[?25h\x1b[?1049l');
    };
    const paint = () => {
        const width = Math.max(COMPACT_WIDTH, process.stdout.columns ?? 100);
        write('\x1b[H' + draw(state, width).join('\r\n') + '\r\n\x1b[0J');
    };
    return await new Promise((resolve) => {
        let finished = false;
        const finish = (code) => {
            if (finished) {
                return;
            }
            finished = true;
            if (flashTimer) {
                clearTimeout(flashTimer);
            }
            clearInterval(ticker);
            process.stdin.setRawMode(false);
            process.stdin.pause();
            cleanup();
            resolve(code);
        };
        const ticker = setInterval(() => {
            state.clock = new Date();
            if (state.phase === 'boot') {
                state.bootFrame += 1;
                if (state.bootFrame > 12 && (token === '' || state.me !== null || state.error !== null)) {
                    state.phase = token === '' ? 'pair' : 'main';
                }
            }
            paint();
        }, 90);
        readline.emitKeypressEvents(process.stdin);
        process.stdin.setRawMode(true);
        process.stdin.resume();
        process.stdin.on('keypress', (sequence, key) => {
            if (key.ctrl && key.name === 'c') {
                finish(0);
                return;
            }
            if (state.phase === 'pair') {
                if (key.name === 'escape') {
                    finish(0);
                    return;
                }
                if (key.name === 'tab') {
                    state.pair.field = state.pair.field === 'api' ? 'code' : 'api';
                    return;
                }
                if (key.name === 'return' || key.name === 'enter') {
                    void connect();
                    return;
                }
                const field = state.pair.field;
                if (key.name === 'backspace') {
                    state.pair[field] = state.pair[field].slice(0, -1);
                    return;
                }
                if (sequence && !key.ctrl && !key.meta && sequence >= ' ') {
                    state.pair[field] += sequence;
                }
                return;
            }
            if (state.phase !== 'main') {
                return;
            }
            if (state.confirmUpgrade) {
                if (key.name === 'y') {
                    state.confirmUpgrade = false;
                    void installUpdate();
                }
                else if (key.name === 'n' || key.name === 'escape') {
                    state.confirmUpgrade = false;
                }
                return;
            }
            switch (key.name) {
                case 'q':
                    finish(0);
                    return;
                case 'up':
                case 'k':
                    state.tab = ((state.tab + 3) % 4);
                    return;
                case 'down':
                case 'j':
                case 'tab':
                    state.tab = ((state.tab + 1) % 4);
                    return;
                case 'left':
                    state.tab = 0;
                    return;
                case 'right':
                    state.tab = 3;
                    return;
                case 'r':
                    void refresh().then(() => setFlash('Refreshed from the instance.', 'ok'));
                    return;
                case 'c':
                    void refresh().then(() => setFlash(state.me?.update.available
                        ? 'A new release is available: ' + (state.me?.update.latest ?? '')
                        : 'You are running the latest release.', state.me?.update.available ? 'warn' : 'ok'));
                    return;
                case 'u':
                    state.confirmUpgrade = true;
                    return;
                case '?':
                case 'h':
                    state.tab = 3;
                    return;
                case 'escape':
                    state.confirmUpgrade = false;
                    state.flash = null;
                    return;
                default:
                    break;
            }
            if (sequence >= '1' && sequence <= '4') {
                state.tab = (Number(sequence) - 1);
            }
        });
    });
}
