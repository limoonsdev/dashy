<div align="center">

<img src="./docs/assets/dashy-hero.svg" alt="Dashy — self-hosted control plane" width="100%" />

<a href="https://github.com/limoonsdev/dashy/stargazers"><img src="https://img.shields.io/github/stars/limoonsdev/dashy?style=for-the-badge&logo=github&logoColor=white&labelColor=0a0a0a&color=6b16ed" alt="GitHub stars" /></a>
<a href="./LICENSE"><img src="https://img.shields.io/badge/license-Apache--2.0-6b16ed?style=for-the-badge&labelColor=0a0a0a" alt="Apache 2.0" /></a>
<img src="https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white&labelColor=0a0a0a" alt="PHP 8.4" />
<img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white&labelColor=0a0a0a" alt="Laravel 12" />
<img src="https://img.shields.io/badge/Docker-ready-2496ED?style=for-the-badge&logo=docker&logoColor=white&labelColor=0a0a0a" alt="Docker" />
<img src="https://img.shields.io/badge/Linux-all%20distros-FCC624?style=for-the-badge&logo=linux&logoColor=0a0a0a&labelColor=0a0a0a" alt="Linux" />
<img src="https://img.shields.io/badge/Windows-WSL2-0078D6?style=for-the-badge&logo=windows&logoColor=white&labelColor=0a0a0a" alt="Windows WSL2" />
<img src="https://img.shields.io/badge/macOS-Linux%20VM-000000?style=for-the-badge&logo=apple&logoColor=white&labelColor=0a0a0a" alt="macOS via Linux VM" />
<img src="https://img.shields.io/badge/FreeBSD-Linux%20VM-AB2B28?style=for-the-badge&logo=freebsd&logoColor=white&labelColor=0a0a0a" alt="FreeBSD via Linux VM" />

**Deploy applications, databases, servers and virtual machines from one dashboard.**

[Quick start](#quick-start) · [Compatibility](#compatibility) · [Themes](#themes) · [Documentation](docs/compatibility.md) · [Bot](bot/)

</div>

---

## What Dashy is

Dashy is a self-hosted control plane for your own infrastructure. It deploys applications from Git, runs databases and one-click services, manages multiple servers over SSH, and provisions virtual machines on Proxmox VE.

The control plane runs on Linux — natively, in WSL2, or in a Linux VM. It does not need an agent on the servers it manages: SSH is enough. If you stop using Dashy, the workloads keep running and remain manageable.

This fork is deliberately narrower than a general-purpose PaaS. It focuses on a black-first interface with real theme support, a small default footprint, a security baseline that is enabled by default, Proxmox VM provisioning, a stateless chat bot, and a network toolkit.

## Features

| Area | What you get |
| --- | --- |
| **Deployments** | Git push deployments, pull-request previews, webhooks, rollbacks, Nixpacks, Railpack, Dockerfile, Docker Compose and prebuilt images. |
| **Services** | Managed databases and 360+ one-click services with persistent storage and generated credentials. |
| **Servers** | Multiple SSH servers, health checks, deployment and runtime logs, container terminals, resource metrics. |
| **Networking** | Custom domains, automatic HTTPS, reverse proxy configuration, container networks and health checks. |
| **Virtual machines** | Proxmox VE provisioning from Linux cloud images, Windows and BSD ISO installers, and existing templates. |
| **Security** | Security headers, report-only CSP, encrypted secret columns, ClamAV upload scanning and `php artisan security:audit`. |
| **Toolkit** | DNS, WHOIS and RDAP lookups, ZIP project import, and host hardening checks. |
| **Automation** | REST API, CLI, MCP server integration and a Discord / Telegram / WhatsApp bot in [`bot/`](bot/). |
| **Themes** | 16 built-in palettes, a four-colour custom editor, and a quick switcher in the account menu. |

## Quick start

Install on a Linux host with Git and root access:

```bash
git clone https://github.com/limoonsdev/dashy.git
cd dashy
sudo ./scripts/install.sh
```

The installer detects the distribution, installs Docker when it is missing, creates `/data/dashy`, fetches the source from Git, prepares the environment and starts the stack.

Open `http://SERVER_IP:8000` and create the first account within 10 minutes. Put the instance behind a reverse proxy before exposing it to the internet.

### Requirements

| Resource | Minimum | Recommended |
| --- | --- | --- |
| CPU | 2 vCPU | 4 vCPU |
| RAM | 2 GB | 4 GB |
| Disk | 20 GB | 40 GB |
| OS | Linux x86_64 or arm64 | Latest LTS |
| Docker | 24+ with Compose v2 | Current stable |

Ports: `22` (managed servers), `80` and `443` (proxy), `8000` (dashboard), `6001` and `6002` (realtime).

### Install from a specific ref

```bash
sudo DASHY_REF=main ./scripts/install.sh
```

The source is kept in `/data/dashy/source`; application data lives in `/data/dashy`. To update, rerun the installer:

```bash
sudo /data/dashy/source/scripts/install.sh
```

`DASHY_REPO_URL` overrides the Git remote, which is useful for a private mirror.

## Compatibility

Dashy orchestrates **Linux containers**. The control plane therefore runs on Linux (native, WSL2 or a Linux VM). The servers it manages can run any OS reachable over SSH, including Windows and BSD.

| Platform | Status | How to run the control plane |
| --- | --- | --- |
| Debian, Ubuntu, Raspberry Pi OS | Native | `sudo ./scripts/install.sh` |
| RHEL, Fedora, Rocky, AlmaLinux, CentOS Stream | Native | `sudo ./scripts/install.sh` |
| Arch Linux, Manjaro, EndeavourOS | Native | `sudo ./scripts/install.sh` |
| openSUSE Leap / Tumbleweed, SLES | Native | `sudo ./scripts/install.sh` |
| Alpine Linux, postmarketOS | Native | `sudo ./scripts/install.sh` |
| Other Linux distributions | Native with Docker | Install Docker Engine + Compose, then run the installer |
| Windows 10 2004+ and Windows 11 | WSL2 | `.\scripts\install.ps1` |
| Windows Server 2019/2022/2025 | WSL2 or Linux VM | `.\scripts\install.ps1`, or a Hyper-V Ubuntu VM |
| macOS 13+ | Linux VM | Multipass, OrbStack or UTM, then the Linux installer |
| FreeBSD, OpenBSD, NetBSD | Linux VM | bhyve / vmm Linux VM, then the Linux installer |
| ARM64 (Raspberry Pi, Ampere, Apple silicon VM) | Native with Docker | Use an image with an arm64 manifest |

Any Linux distribution that can run Docker Engine and Compose can run Dashy. The installer automates the package managers above; distributions outside that list use the manual Docker path. Windows, macOS and BSD do not run the control plane natively — they host it in WSL2 or a Linux VM. See [docs/compatibility.md](docs/compatibility.md) for the exact commands and caveats.

Check the current machine before installing:

```bash
./scripts/platform-check.sh
```

## Themes

<img src="./docs/assets/themes.svg" alt="A selection of Dashy themes" width="100%" />

Dashy ships with 16 palettes: Dashy, Midnight, Forest, Ember, Slate, Nord, Dracula, GitHub Dark, Aubergine, GitHub Light, Paper, Blossom, Tokyo Night, Catppuccin, Rosé Pine and Solarized Light.

- Switch from the account menu, or open **Appearance** for the full picker.
- The choice is stored per browser; it is not a server-side setting.
- The custom editor takes four colours (sidebar, selected row, accent, background) and derives readable text and surfaces from them.
- Themes apply to the dashboard, navigation, tables, logs, terminals and charts.

## Security baseline

Dashy is a control plane: it terminates and routes TLS for the workloads it manages, so it is an endpoint by design. It is not end-to-end encrypted for the control plane.

- **Transport:** terminate TLS at the proxy. HSTS is added after an HTTPS request.
- **Headers:** nosniff, frame denial, referrer policy, permissions policy, COOP/CORP, and a CSP that starts in report-only mode.
- **Secrets:** sensitive columns use Laravel encrypted casts. Run `php artisan security:audit` to check configuration and coverage.
- **Uploads:** archives are path-checked, size-capped and streamed to ClamAV before extraction. Set `CLAMAV_HOST` and optionally `CLAMAV_REQUIRED=true`.
- **Bot:** Discord Ed25519, WhatsApp HMAC-SHA256 and Telegram secret headers are verified before any command runs.

Configuration lives in `config/security.php` and the `SECURITY_*` / `CLAMAV_*` environment variables.

## Virtual machines (Proxmox VE)

Dashy creates VMs on a Proxmox VE node. Cloud-image provisioning downloads the image, imports it as the boot disk, attaches a cloud-init drive with your user, SSH keys and DHCP networking, then starts the VM.

```env
PROXMOX_URL=https://pve.example.com:8006
PROXMOX_TOKEN_ID=dashy@pve!vm-provisioner
PROXMOX_TOKEN_SECRET=...
PROXMOX_NODE=pve
PROXMOX_STORAGE=local-lvm
PROXMOX_ISO_STORAGE=local
PROXMOX_BRIDGE=vmbr0
```

Grant `VM.Allocate`, `VM.Config.*`, `Datastore.AllocateSpace`, `Datastore.Audit` and `VM.Audit` on the node and datastore. Linux cloud images are supported out of the box; Windows and BSD use an ISO already present in Proxmox ISO storage. Limits and defaults are configured with `VM_MAX_*` and `VM_DEFAULT_*`.

Open **Virtual machines** in the sidebar. Provisioning is synchronous while the image downloads; raise the PHP and reverse-proxy timeouts on slow links.

## Dashy Bot

The [`bot/`](bot/) directory contains a stateless Node process for Discord, Telegram and WhatsApp. It has no database, no queue worker and no persistent gateway connection; each platform calls a webhook.

```bash
cd bot
cp .env.example .env
# Fill in DASHY_URL, DASHY_API_TOKEN and at least one platform block.
docker compose up -d --build
```

Create the API token in **Security → API tokens** with `read` and `write` abilities. The bot verifies every webhook signature before running a command. See [`bot/`](bot/) for platform setup and the command list.

## Documentation

- [Compatibility and platform setup](docs/compatibility.md)
- [Installation script](scripts/install.sh)
- [Windows / WSL2 bootstrap](scripts/install.ps1)
- [Platform check](scripts/platform-check.sh)
- [Bot](bot/)
- [Contributing](CONTRIBUTING.md) · [Code of conduct](CODE_OF_CONDUCT.md)

## Credits and license

Dashy is a fork of [Coolify](https://github.com/coollabsio/coolify), released under the Apache License 2.0. Upstream copyright remains with the Coolify authors. Dashy is not affiliated with or endorsed by Coolify.

See [LICENSE](LICENSE) for the full text.
