# Dashy

Dashy is a self-hosted control plane for servers, applications, databases and
the people who run them. It replaces a folder of shell scripts and a wall of
browser tabs with one workspace: projects, deployments, backups, monitoring,
access control, and a terminal client that talks to the same API.

- **Servers** — connect any Linux host over SSH, see what is running, and act on it.
- **Projects** — group applications, databases and services by environment.
- **Deployments** — Git, Dockerfile or image, with rollbacks and health checks.
- **Backups** — scheduled database and volume backups to S3-compatible storage.
- **Monitoring** — fleet history and live metrics on one page.
- **Teams** — roles, invitations, audit log, API and CLI tokens.
- **Editorial** — a small publishing workflow with connectors for Slack, Discord,
  WordPress, Ghost, GitHub and Notion.
- **CLI** — a full-screen terminal console for Linux, macOS and Windows.
- **Bot** — Discord, Telegram and WhatsApp commands for status and deploys.

## Install

Requirements: a Linux host with Docker and Docker Compose.

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallDASHY.sh | bash
```

The script clones the instance to `~/.dashy-instance` and starts it with Compose.
To choose the directory, set `DASHY_INSTALL_DIR`. To update later, run the same
command again.

Manual install:

```bash
git clone https://github.com/limoonsdev/dashy.git
cd dashy
docker compose up -d
```

Then open `http://localhost:8000` and create the first account. That account is
the instance administrator.

## Terminal client

**Linux and macOS**

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.sh | bash
```

**Windows (PowerShell)**

```powershell
irm https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.ps1 | iex
```

Both installers need no administrator rights. They use an existing Node 18+
runtime, or download a private one into `~/.dashy`.

**Settings → CLI** can also compile a standalone binary on the instance and
hand you a signed link that expires after two hours. Pick Linux, macOS or
Windows and the matching architecture; nothing else is left on disk.

Pair a terminal from **Settings → CLI**: choose what it may do, choose how long
it stays signed in (14 days by default, or no expiry), and generate a one-time
code.

```bash
dashy connect DASHY-XXXX-XXXX --api https://dashy.example.com
dashy            # full-screen console
dashy status
dashy servers
dashy check
dashy upgrade --yes
```

See [cli/README.md](cli/README.md) for every command and the security model.

## Chat bot

The bot in [bot/](bot/README.md) answers the same questions in Discord,
Telegram and WhatsApp: status, servers, apps, databases, deployments and
`/deploy`. It is a small Node service with no database; every request is
signature-checked, and management commands need an explicit admin list.

```bash
cd bot
cp .env.example .env    # add the platform keys and a Dashy API token
npm test
node src/server.js
```

With the repository checked out, `docker compose --profile bot up -d` starts it
alongside the instance. The Discord interactions endpoint is `/discord`; see
[bot/README.md](bot/README.md) for the step-by-step setup.

## Configuration

Instance settings live in **Settings**. Most values can also be provided through
the environment; see `.env.example`. Notable options:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public URL of the instance |
| `DB_*` | Database connection |
| `GITHUB_TOKEN` | Higher rate limit for update checks and releases |
| `EDITORIAL_*` | Connector credentials for the editorial workspace |
| `CLI_BUN_PATH` | Bun executable used to compile CLI binaries (defaults to `bun` on PATH) |

## Development

Dashy is a Laravel application (PHP 8.3+, Livewire, Tailwind) with a TypeScript
CLI. The usual loop:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

The CLI is built separately:

```bash
cd cli
npm install
npm run build      # writes dist/cli.js
npm run typecheck
```

## Contributing

Issues and pull requests are welcome. Keep changes focused, include a test when
the behaviour is testable, and describe the user-visible effect in the PR. For
security reports, do not open a public issue — contact the maintainers privately.

## License

See [LICENSE](LICENSE).
