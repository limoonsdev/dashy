import http from 'node:http';
import { pathToFileURL } from 'node:url';
import { createCommands } from './commands.js';
import { loadConfig } from './config.js';
import { createDashyClient } from './dashy.js';
import * as lookup from './lookup.js';
import { handleDiscord } from './platforms/discord.js';
import { handleTelegram } from './platforms/telegram.js';
import { handleWhatsapp, whatsappChallenge } from './platforms/whatsapp.js';
import { createRouter } from './router.js';

const MAX_BODY_BYTES = 1024 * 1024;

function readBody(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        let size = 0;

        req.on('data', (chunk) => {
            size += chunk.length;

            if (size > MAX_BODY_BYTES) {
                reject(new Error('body too large'));
                req.destroy();

                return;
            }

            chunks.push(chunk);
        });
        req.on('end', () => resolve(Buffer.concat(chunks)));
        req.on('error', reject);
    });
}

function send(res, status, body, contentType = 'application/json') {
    const payload = contentType === 'application/json' ? JSON.stringify(body) : String(body);

    res.writeHead(status, {
        'content-type': contentType,
        'content-length': Buffer.byteLength(payload),
    });
    res.end(payload);
}

export function createBotServer({ config, router }) {
    return http.createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');

        try {
            if (req.method === 'GET' && url.pathname === '/health') {
                return send(res, 200, {
                    ok: true,
                    uptime: Math.round(process.uptime()),
                    platforms: {
                        discord: Boolean(config.discord.publicKey),
                        telegram: Boolean(config.telegram.token && config.telegram.secret),
                        whatsapp: Boolean(config.whatsapp.appSecret),
                    },
                });
            }

            if (url.pathname === '/whatsapp' && req.method === 'GET') {
                const { status, body } = whatsappChallenge(url.searchParams, config);

                return send(res, status, body, 'text/plain');
            }

            if (req.method === 'POST' && url.pathname === '/discord') {
                if (!config.discord.publicKey) {
                    return send(res, 503, { error: 'discord is not configured' });
                }

                const rawBody = await readBody(req);
                const result = await handleDiscord({ headers: req.headers, rawBody, config, router });

                return send(res, result.status, result.body);
            }

            if (req.method === 'POST' && url.pathname === '/telegram') {
                const rawBody = await readBody(req);
                const result = await handleTelegram({ headers: req.headers, rawBody, config, router });

                return send(res, result.status, result.body);
            }

            if (req.method === 'POST' && url.pathname === '/whatsapp') {
                const rawBody = await readBody(req);
                const result = await handleWhatsapp({ headers: req.headers, rawBody, config, router });

                return send(res, result.status, result.body);
            }

            return send(res, 404, { error: 'not found' });
        } catch (error) {
            return send(res, error?.message === 'body too large' ? 413 : 500, {
                error: 'internal error',
            });
        }
    });
}

export function buildBot(env = process.env) {
    const config = loadConfig(env);
    const dashy = createDashyClient(config.dashy);
    const commands = createCommands({ dashy, lookup, config });
    const router = createRouter({ commands, config });

    return { config, router, server: createBotServer({ config, router }) };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const { config, server } = buildBot();
    server.listen(config.port, config.host, () => {
        console.log(`Dashy bot listening on ${config.host}:${config.port}`);
    });
}
