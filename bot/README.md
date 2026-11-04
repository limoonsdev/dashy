# Dashy bot

A small, dependency-free HTTP service that answers commands in Discord,
Telegram and WhatsApp. It talks to Dashy through the public API, so it needs no
database, queue or session state of its own.

## Commands

Lookups are open to everyone allowed by `BOT_ALLOWED_USERS`:

`/help`, `/ping`, `/version`, `/status`, `/servers`, `/apps`, `/databases`,
`/projects`, `/deployments`, `/fleet`, `/search`, `/app`, `/db`, `/domain`,
`/dns`, `/whois`, `/ip`, `/id`

Management commands need `BOT_ADMIN_USERS`:

`/deploy <uuid>`

## Discord

1. Create an application at <https://discord.com/developers/applications>.
2. Under **Bot**, copy the token. Under **General Information**, copy the
   application id and public key.
3. Set the **Interactions Endpoint URL** to `https://bot.example.com/discord`.
   Discord sends a signed `PING` immediately; the service answers it with the
   correct `PONG` only when the signature validates.
4. Register the slash commands once:

```bash
cd bot
DISCORD_APPLICATION_ID=... DISCORD_BOT_TOKEN=... npm run register:discord
```

The endpoint verifies every request with Ed25519
(`X-Signature-Ed25519` over `timestamp + body`) using the public key. Requests
with a missing or wrong signature get `401` and are never parsed. Replies are
ephemeral embeds, so the channel is not filled with command output.

## Configuration

Copy `bot/.env.example` to `bot/.env` and fill in what you use:

| Variable | Purpose |
| --- | --- |
| `DASHY_URL` | Instance base URL |
| `DASHY_API_TOKEN` | API token with `read`, and `write` for `/deploy` |
| `DISCORD_PUBLIC_KEY` | Ed25519 public key from the Discord app |
| `DISCORD_APPLICATION_ID` | Used once to register commands |
| `DISCORD_BOT_TOKEN` | Used once to register commands |
| `BOT_ALLOWED_USERS` | Comma-separated ids, e.g. `discord:123,telegram:456` |
| `BOT_ADMIN_USERS` | Ids allowed to run management commands |
| `BOT_RATE_MAX` | Commands per window per user (default 20/minute) |

Telegram uses `TELEGRAM_BOT_TOKEN` and `TELEGRAM_WEBHOOK_SECRET` at `/telegram`
(the secret is compared in constant time). WhatsApp uses the Cloud API at
`/whatsapp` with `WHATSAPP_APP_SECRET` HMAC verification.

## Run

```bash
cd bot
npm test            # 37 tests, no network
node src/server.js
```

With Docker:

```bash
cd bot
cp .env.example .env
docker compose up -d --build
curl http://localhost:8787/health
```

The image has no dependencies and runs as the `node` user. `/health` reports
which platforms are configured, without exposing any secret.

## Security notes

- The bot only accepts signed payloads; there is no shared "admin password".
- Rate limiting is per platform user, and the bucket map is pruned.
- Admin commands are checked server-side, not hidden by Discord.
- The API token stays in the environment of the bot container. Give it the
  smallest ability set that covers the commands you enable.
