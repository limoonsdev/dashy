# Dashy CLI

A full-screen terminal console for Dashy, written in TypeScript with a custom
TSX renderer. No runtime dependencies, no daemon, no stored password: a pairing
code generated in **Settings → CLI** becomes a scoped API token, and every
action is checked against that token's abilities.

```
 ██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗
 ██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝
 ██║  ██║███████║███████╗███████║ ╚████╔╝
 ██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝
 ██████╔╝██║  ██║███████║██║  ██║   ██║
 ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝
```

## Install

**Linux and macOS** (Intel and Apple Silicon):

```bash
curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.sh | bash
```

**Windows** (PowerShell, x64 and ARM64):

```powershell
irm https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.ps1 | iex
```

Both installers need no administrator rights. If `node` 18 or newer is missing,
they download a private Node runtime into `~/.dashy` (`%LOCALAPPDATA%\Dashy` on
Windows) instead of touching a system package manager.

The installers fetch the compiled console (`dist/cli.js`) and fall back to the
portable single-file CLI if that build is unavailable. Nothing is installed
with `npm`: the terminal only needs `node`.

## Pair a terminal

1. Open **Settings → CLI** in Dashy.
2. Pick what the terminal may do — read only, manage, or full operator.
3. Press **Generate a sign-in code**.
4. In the terminal:

```bash
dashy connect DASHY-XXXX-XXXX --api https://dashy.example.com
```

The code is single use, tied to your account and team, and expires after ten
minutes. The terminal stores a 30-day token in `~/.dashy/config.json` with mode
`0600`; `dashy signout` deletes it, and **Settings → CLI** can revoke it remotely.

## Commands

| Command | What it does |
| --- | --- |
| `dashy` | Full-screen console (arrow keys, `r`, `c`, `u`, `q`) |
| `dashy connect <code>` | Pair this terminal |
| `dashy status` | Instance, user, team, servers, update state |
| `dashy servers` | Servers and reachability |
| `dashy check` | Check for an update |
| `dashy upgrade` | Install the update (asks for confirmation) |
| `dashy deploy <uuid>` | Queue a deployment through the public API |
| `dashy logs <uuid> [lines]` | Read application logs |
| `dashy doctor` | Configuration and token diagnostics |
| `dashy signout` | Forget the token on this machine |

Options: `--api <url>` overrides the instance for one call, `--json` prints raw
API responses, `--demo` renders the console with sample data, `--version`
prints the CLI version.

## Build from source

The console is plain TypeScript with a small TSX renderer (`src/ui`). It builds
with `tsc` only — no bundler binary, no React.

```bash
cd cli
npm install
npm run build      # writes dist/cli.js
node dist/cli.js --demo
```

`dashy.js` remains the portable, single-file fallback used when the compiled
build cannot be downloaded.

## Environment

| Variable | Effect |
| --- | --- |
| `DASHY_API` | Default instance URL |
| `DASHY_HOME` | Where `config.json` lives (default `~/.dashy`) |
| `DASHY_RAW_URL` | Installer mirror for `dashy.js` |
| `DASHY_NODE_VERSION` | Node version the installer downloads |
| `NO_COLOR` | Plain text output |

## Security

- The CLI never asks for a password. The pairing code is the credential.
- A code is deleted from the server the moment a terminal redeems it, so a
  screenshot of the screen is useless after one use.
- The token keeps the abilities you chose; `deploy` and `upgrade` also require
  that the account behind the code is allowed to do it.
- Every pairing and revocation is written to the instance audit log.
