<div align="center">

<img src="./public/dashy-logo.svg" alt="Dashy logo" width="120" />

# Dashy

**An open-source platform to deploy applications, databases, and services on your own servers.**

Open source & free forever, backed by our [philosophy](https://dashy.io/philosophy).

![Latest Release Version](https://img.shields.io/badge/dynamic/json?labelColor=grey&color=6366f1&label=Latest%20released%20version&url=https%3A%2F%2Fcdn.coollabs.io%2Fdashy%2Fversions.json&query=stable.dashy.version&style=for-the-badge
)

[Website](https://dashy.io) · [Documentation](https://dashy.io/docs) · [Cloud](https://app.dashy.io) · [Discord](https://coollabs.io/discord) · [Community](https://github.com/dashyapp/dashy/discussions)

</div>

## What is Dashy?

Dashy is an open-source and self-hostable alternative to Heroku, Netlify, and Vercel. It helps you manage servers, applications, and databases on your own hardware. You only need an SSH connection.

You can use a VPS, a bare-metal server, a Raspberry Pi, or any other server that accepts SSH connections. Dashy gives you the convenience of a cloud platform while you keep control of your infrastructure.

Your configurations stay on your servers. If you stop using Dashy, your running resources continue to work and remain manageable.

## What can Dashy do?

- **Deploy any application:** Build from GitHub, GitLab, Bitbucket, or Gitea with Nixpacks, Railpack, Dockerfile, Docker Compose, or a prebuilt Docker image.
- **Run databases and services:** Deploy managed databases and more than 360 one-click services with persistent storage and generated credentials.
- **Automate deployments:** Deploy on every Git push, create pull-request previews, call deployment webhooks, and roll back to retained application images.
- **Manage networking:** Configure custom domains, automatic HTTPS certificates, reverse proxies, health checks, and container networks.
- **Operate your infrastructure:** Manage multiple servers, inspect deployment and runtime logs, open container terminals, and monitor resource status.
- **Protect your workloads:** Configure database and storage backups, scheduled tasks, environment variables, secrets, and notifications.
- **Integrate with your workflow:** Manage resources through the dashboard, API, CLI, MCP, and team-based access controls.

## Quick start

Install Dashy on a supported server with one command:

```bash
curl -fsSL https://cdn.coollabs.io/dashy/install.sh | bash
```

Read the [installation guide](https://dashy.io/docs/installation) for requirements and detailed instructions. You can also review the [installation script](./scripts/install.sh) before you run it.

## Self-hosted or Cloud

| Self-hosted | Dashy Cloud |
| --- | --- |
| Install Dashy on your own server. | Use a Dashy instance that we maintain. |
| Control and maintain the complete platform. | Get high availability and less maintenance. |
| Free and open source. | Paid service with email notifications and additional support. |

The recommended self-hosted setup uses one server for Dashy and one or more servers for deployed resources. If you do not want to maintain the Dashy server, use [Dashy Cloud](https://app.dashy.io). See [dashy.io](https://dashy.io) for current pricing.

## Virtual machines (Proxmox VE)

Dashy can create and manage VMs on a Proxmox VE node. Cloud-image setup is automatic: the image is
downloaded into ISO storage, imported as the boot disk, given a cloud-init drive with your user, SSH
keys and DHCP networking, then started.

```env
PROXMOX_URL=https://pve.example.com:8006
PROXMOX_TOKEN_ID=dashy@pve!vm-provisioner
PROXMOX_TOKEN_SECRET=...
PROXMOX_NODE=pve
PROXMOX_STORAGE=local-lvm
PROXMOX_ISO_STORAGE=local
PROXMOX_BRIDGE=vmbr0
```

Create the token under **Datacenter > Permissions > API Tokens** and grant `VM.Allocate`,
`VM.Config.*`, `Datastore.AllocateSpace`, `Datastore.Audit` and `VM.Audit` on the node and datastore.
Username/password is a fallback via `PROXMOX_USERNAME` / `PROXMOX_PASSWORD`.

- **Linux cloud images:** Ubuntu, Debian, AlmaLinux, Rocky, openSUSE, Alpine and Arch, plus any
  HTTPS qcow2/raw URL through the custom entry.
- **Windows / FreeBSD / anything else:** an ISO already present in Proxmox ISO storage.
- **Existing templates:** cloned into a full VM.

Open **Virtual machines** in the sidebar. Cloud-image VMs need no interaction after the form; ISO
installs boot the installer and need the usual interactive Setup (Windows unattended also needs an
answer file). Limits are `VM_MAX_CORES`, `VM_MAX_MEMORY` and `VM_MAX_DISK`; defaults are
`VM_DEFAULT_CORES`, `VM_DEFAULT_MEMORY`, `VM_DEFAULT_DISK`, `VM_SSH_KEYS`.

Provisioning is synchronous while the image downloads, so raise the PHP and reverse-proxy timeouts
if they cut the request. Deletion is asynchronous on the Proxmox side. The API client and
provisioner are tested against a fake Proxmox server, not a live cluster.

## Security baseline

Dashy ships a defence-in-depth baseline. It is not a promise of perfect protection, and it is not
end-to-end encryption for the control plane: Dashy terminates and routes TLS for the workloads it
manages, so by design it is an endpoint. True end-to-end encryption means the host cannot read the
payload, which would stop the proxy from routing it. Use application-level encryption inside a
workload when the payload must stay unreadable to the host.

- **Transport:** terminate TLS at the proxy. HSTS is added once a request arrives over HTTPS.
- **Response headers:** nosniff, frame denial, referrer policy, permissions policy, COOP/CORP, and a
  Content Security Policy that starts in **report-only** mode so it cannot break the dashboard.
- **Secrets at rest:** sensitive columns use Laravel encrypted casts. Run `php artisan security:audit`
  to check configuration, cookie flags, header/CSP mode and encrypted-field coverage.
- **Uploads:** archives are path-checked, size-capped, and streamed to ClamAV **before** extraction.
  Set `CLAMAV_HOST` to enable scanning and `CLAMAV_REQUIRED=true` to fail closed when clamd is down.
  ClamAV is signature based: it stops known malware, not every possible payload.
- **Bot:** webhook signatures are verified before any command runs (Discord Ed25519, WhatsApp
  HMAC-SHA256, Telegram secret header), with per-user rate limits.

Configuration: `SECURITY_HEADERS_ENABLED`, `SECURITY_HSTS_MAX_AGE`, `SECURITY_PERMISSIONS_POLICY`,
`SECURITY_CSP`, `SECURITY_CSP_REPORT_ONLY`, `CLAMAV_HOST`, `CLAMAV_PORT`, `CLAMAV_TIMEOUT`,
`CLAMAV_REQUIRED`, `CLAMAV_MAX_BYTES`.

## Dashy Bot (Discord, Telegram, WhatsApp)

The repository ships a chat bot in [`bot/`](./bot) that exposes Dashy management and lookup
commands on three platforms. It is deliberately small: one stateless Node process, no
dependencies, no database, no queue worker and no persistent gateway connection. Each platform
delivers messages over a webhook, so the process only does work when someone sends a command.

### Install

```bash
cd bot
cp .env.example .env
# Fill in DASHY_URL, DASHY_API_TOKEN and at least one platform block.
docker compose up -d --build
```

Without Docker:

```bash
cd bot
cp .env.example .env
node src/server.js          # needs Node 22 or newer
```

On Dashy, create the token under **Security > API tokens** with `read` and `write` abilities.
Publish the bot over HTTPS: every platform below needs a public callback URL.

### Platform setup

| Platform | Callback URL | Required variables |
| --- | --- | --- |
| Discord | `https://bot.example.com/discord` | `DISCORD_APPLICATION_ID`, `DISCORD_BOT_TOKEN`, `DISCORD_PUBLIC_KEY` |
| Telegram | `https://bot.example.com/telegram` | `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET` |
| WhatsApp | `https://bot.example.com/whatsapp` | `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_APP_SECRET` |

1. **Discord** — create an application, set the Interactions Endpoint URL to the callback above,
   copy its Public Key into `DISCORD_PUBLIC_KEY`, then register the slash commands:

   ```bash
   cd bot && node scripts/register-discord-commands.js
   ```

2. **Telegram** — register the webhook once, using the same secret as `TELEGRAM_WEBHOOK_SECRET`:

   ```bash
   curl -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook" \
     -d "url=https://bot.example.com/telegram" \
     -d "secret_token=$TELEGRAM_WEBHOOK_SECRET"
   ```

3. **WhatsApp** — use the Cloud API webhook with the verify token you set, and subscribe to the
   `messages` field. The bot answers the verification challenge and checks the `X-Hub-Signature-256`
   header on every delivery.

### Commands

| Command | What it does |
| --- | --- |
| `/help` | List every command. |
| `/ping` | Liveness and uptime. |
| `/version` | Dashy version. |
| `/status` | Count servers, applications and databases. |
| `/servers` | Servers and reachability. |
| `/apps` | Applications with status and domain. |
| `/databases` | Databases and engines. |
| `/projects` | Projects. |
| `/deployments [uuid]` | Latest deployments. |
| `/deploy <uuid>` | Queue a deployment (admin only). |
| `/domain example.com` | RDAP registration check. |
| `/dns example.com` | A/AAAA/CNAME/MX/NS/TXT records. |
| `/whois example.com` | Raw WHOIS record. |
| `/ip example.com` | Resolve a hostname. |
| `/id` | Your platform user id. |

### Access control and footprint

- `BOT_ALLOWED_USERS` limits who may use the bot; `BOT_ADMIN_USERS` limits `/deploy`. Both accept
  `platform:id` values such as `discord:1234567890`.
- Requests are rate limited per user, in memory, with no external store.
- Discord signatures are verified with Ed25519, WhatsApp with HMAC-SHA256, and Telegram with its
  secret header. A request that fails verification is rejected before any command runs.
- The compose file caps the container at 128 MB. There is no polling loop, so an idle bot uses only
  the Node process itself.

## Community and support

- Read the [documentation](https://dashy.io/docs).
- Join the community on [Discord](https://coollabs.io/discord).
- Ask questions in [GitHub Discussions](https://github.com/dashyapp/dashy/discussions).
- Read the [contribution guide](./CONTRIBUTING.md) before you submit a change.
- Review the [code of conduct](./CODE_OF_CONDUCT.md).
- Contact the team through the [support page](https://dashy.io/docs/contact).

## Donations
To stay completely free and open-source, with no feature behind the paywall and evolve the project, we need your help. If you like Dashy, please consider donating to help us fund the project's future development.

[dashy.io/sponsorships](https://dashy.io/sponsorships)

Thank you so much!

### Huge Sponsors

* [Ginernet](https://ginernet.com/) - Hosting powerful servers in Spain
* [SerpAPI](https://serpapi.com) - Google Search API — Scrape Google and other search engines from our fast, easy, and complete API.
* [MVPS](https://www.mvps.net) - Cheap VPS servers at the highest possible quality
* [ScreenshotOne](https://screenshotone.com) - Screenshot API for devs
* [PrivateAlps](https://privatealps.net) - Cloud Services Provider, VPS, servers infrastructure for people who care about privacy and control
* [Seibert Group](https://seibert.link/dashysoftware) - Boost productivity company-wide with AI agents like Claude Code
* [Contabo](https://contabo.com/en/dashy-vps/) - Cloud VPS & dedicated servers at unbeatable prices

### Big Sponsors

* [Vanaways](https://www.vanaways.co.uk) - New vans for sale and lease across the UK
* [Cloudways](https://www.cloudways.com/en/?id=2125302) - Managed cloud hosting platform by DigitalOcean
* [ByteBase](https://www.bytebase.com) - Database CI/CD and Security at Scale
* [Ramnode](https://ramnode.com/) - High Performance Cloud VPS Hosting
* [23M](https://23m.com) - Your experts for high-availability hosting solutions!
* [Macarne](https://macarne.com) - Best IP Transit & Carrier Ethernet Solutions for Simplified Network Connectivity
* [Hetzner](http://htznr.li/DashyXHetzner) - Server, cloud, hosting, and data center solutions
* [Logto](https://logto.io) - The better identity infrastructure for developers
* [Supadata](https://supadata.ai/) - Scrape YouTube, web, and files. Get AI-ready, clean data for your next project. 
* [Tolgee](https://tolgee.io) - The open source localization platform
* [ArcJet](https://arcjet.com) - Advanced web security and performance solutions
* [SupaGuide](https://supa.guide) - Your comprehensive guide to Supabase
* [CodeRabbit](https://coderabbit.ai) - Cut Code Review Time & Bugs in Half
* [Convex](https://convex.link/dashy.io) - Convex is the open-source reactive database for web app developers.
* [GoldenVM](https://billing.goldenvm.com) - Premium virtual machine hosting solutions
* [Comit International](https://comit.international) - New York Times award–winning contractor!
* [Compai](https://www.trycomp.ai) - The open source compliance automation platform that does everything you need to get compliant, fast. Open source alternative to Drata & Vanta.
* [Tigris](https://www.tigrisdata.com) - Modern S3 Alternative
* [Blacksmith](https://blacksmith.sh) - Infrastructure automation platform
* [JobsCollider](https://jobscollider.com/remote-jobs) - 30,000+ remote jobs for developers
* [Darweb](https://darweb.nl/?ref=dashy.io&utm_source=dashy.io) - Design. Develop. Deliver. Specialized in 3D CPQ Solutions for eCommerce.
* [Hostinger](https://www.hostinger.com/vps/dashy-hosting) - Web hosting and VPS solutions
* [Mobb](https://vibe.mobb.ai/) - Secure Your AI-Generated Code to Unlock Dev Productivity
* [Ubicloud](https://www.ubicloud.com) - Open source cloud infrastructure platform
* [PFGLabs](https://pfglabs.com) - Build Real Projects with Golang
* [JuxtDigital](https://juxtdigital.com) - Digital PR & AI Authority Building Agency
* [SaasyKit](https://saasykit.com) - Complete SaaS starter kit for developers
* [American Cloud](https://americancloud.com) - US-based cloud infrastructure services
* [Greptile](https://www.greptile.com) - The AI Code Reviewer
* [VPSDime](https://vpsdime.com/) - Cheap VPS Hosting - 4GB for $5/month
* [dataforest Cloud](https://cloud.dataforest.net/en) - Deploy cloud servers as seeds independently in seconds. Enterprise hardware, premium network, 100% made in Germany.
* [ISHosting](https://ishosting.com/) - Hosting and VPS solutions
* [NodePlus.ai](https://nodeplus.ai/) - AI that reads every system your business runs on

### Small Sponsors

<a href="https://serverside.com/?utm_source=dashy.io"><img width="60px" alt="Serverside" src="https://cdn.coollabs.io/sponsors/serverside.svg"/></a>
<a href="https://darkvps.pro"><img width="60px" alt="DarkVPS" src="https://cdn.coollabs.io/sponsors/darkvps.png"/></a>
<a href="https://www.opensourcealternatives.to"><img width="60px" alt="Open Source Alternatives" src="https://cdn.coollabs.io/sponsors/opensourcealternatives.png"/></a>
<a href="https://onserva.com/"><img width="60px" alt="Onserva" src="https://onserva.com/icon.svg"/></a>
<a href="https://www.movavi.com/mac.html?utm_source=dashy.io"><img width="60px" alt="Movavi" src="https://cdn.coollabs.io/sponsors/movavi.png"/></a>
<a href="https://usefoil.com/"><img width="60px" alt="ABXY" src="https://usefoil.com/favicon.svg"/></a>
<a href="https://www.launchfa.st/?utm_source=dashy.io"><img width="60px" alt="LaunchFast Boilerplates" src="https://github.com/LaunchFast-Boilerplates.png"/></a>
<a href="https://www.netrouting.com/?utm_source=dashy.io"><img width="60px" alt="Netrouting" src="https://github.com/netroutingcom.png"/></a>
<a href="https://github.com/mindedtech"><img width="60px" alt="MindEd Tech" src="https://github.com/mindedtech.png"/></a>
<a href="https://youstable.com/?utm_source=dashy.io"><img width="60px" alt="YouStable" src="https://github.com/youstable.png"/></a>
<a href="https://transcript.lol/?utm_source=dashy.io"><img width="60px" alt="Transcript LOL" src="https://transcript.lol/logo.png"/></a>
<a href="https://www.autom.dev/?utm_source=dashy.io"><img width="60px" alt="Autom" src="https://cdn.coollabs.io/sponsors/autom.png"/></a>
<a href="https://www.huntapi.com/?utm_source=dashy.io"><img width="60px" alt="HuntAPI" src="https://cdn.coollabs.io/sponsors/huntapi.png"/></a>
<a href="https://ultraservers.com/?utm_source=dashy.io"><img width="60px" alt="ULTRASERVERS" src="https://github.com/ULTRASERVERS.png"/></a>
<a href="https://vibetone.com/?utm_source=dashy.io"><img width="60px" alt="VibeTone" src="https://github.com/vibetonefm.png"/></a>
<a href="https://www.piloterr.com/?utm_source=dashy.io"><img width="60px" alt="Piloterr" src="https://cdn.coollabs.io/sponsors/piloterr.svg"/></a>
<a href="https://yoxel.com/?utm_source=dashy.io"><img width="60px" alt="Alexey Panteleev" src="https://github.com/aspantel.png"/></a>
<a href="https://summyt.app?utm_source=dashy.io"><img width="60px" alt="SummYT - YouTube Summarizer" src="https://summyt.app/logo.svg"/></a>
<a href="https://xaman.app/?utm_source=dashy.io"><img width="60px" alt="Xaman" src="https://github.com/XamanApp.png"/></a>
<a href="https://monadical.com/?utm_source=dashy.io"><img width="60px" alt="Monadical" src="https://github.com/Monadical-SAS.png"/></a>
<a href="https://maas.engineering/?utm_source=dashy.io"><img width="60px" alt="Magic as a Service" src="https://github.com/magicasaservice.png"/></a>
<a href="https://fivemanage.com?utm_source=dashy.io"><img width="60px" alt="FiveManage" src="https://cdn.coollabs.io/sponsors/fivemanage.jpg"/></a>
<a href="https://cryptojobslist.com/?utm_source=dashy.io"><img width="60px" alt="Crypto Jobs List" src="https://github.com/cryptojobslist.png"/></a>
<a href="https://serpapi.com/?utm_source=dashy.io"><img width="60px" alt="SerpAPI" src="https://github.com/serpapi.png"/></a>
<a href="https://typebot.io/?utm_source=dashy.io"><img width="60px" alt="typebot" src="https://cdn.bsky.app/img/avatar/plain/did:plc:gwxcta3pccyim4z5vuultdqx/bafkreig23hci7e2qpdxicsshnuzujbcbcgmydxhbybkewszdezhdodv42m@jpeg"/></a>
<a href="https://360creators.com/?utm_source=dashy.io"><img width="60px" alt="360Creators" src="https://opencollective-production.s3.us-west-1.amazonaws.com/account-avatar/503e0953-bff7-4296-b4cc-5e36d40eecc0/icon-360creators.png"/></a>
<a href="https://capgo.app/?utm_source=dashy.io"><img width="60px" alt="Cap-go" src="https://github.com/cap-go.png"/></a>
<a href="https://cirun.io/?utm_source=dashy.io"><img width="60px" alt="Cirun" src="https://cdn.coollabs.io/sponsors/cirun-logo.png"/></a>
<a href="https://github.com/puls-digital-group"><img width="60px" alt="Puls Digital Group" src="https://github.com/puls-digital-group.png"/></a>
<a href="https://github.com/jonathanprl"><img width="60px" alt="Jonathan Pereira" src="https://github.com/jonathanprl.png"/></a>
<a href="https://outboundgateway.com/"><img width="60px" alt="OutboundGateway" src="https://github.com/OutboundGateway.png"/></a>
<a href="https://github.com/t4dt"><img width="60px" alt="T4DT GmbH" src="https://github.com/t4dt.png"/></a>
<a href="https://internetgarden.co/?utm_source=dashy.io"><img width="60px" alt="Internet Garden" src="https://cdn.coollabs.io/sponsors/internetgarden.ico"/></a>
<a href="https://evercam.io/?utm_source=dashy.io"><img width="60px" alt="Evercam" src="https://github.com/evercam.png"/></a>
<a href="https://web3.career/?utm_source=dashy.io"><img width="60px" alt="Web3 Jobs" src="https://cdn.coollabs.io/sponsors/web3jobs.png"/></a>
<a href="https://linkdr.com?utm_source=dashy.io"><img width="60px" alt="LinkDr" src="https://cdn.coollabs.io/sponsors/linkdr.svg"/></a>
<a href="https://arvensis.systems/?utm_source=dashy.io"><img width="60px" alt="Arvensis Systems" src="https://cdn.coollabs.io/sponsors/arvensis.png"/></a>
<a href="https://www.runpod.io/?utm_source=dashy.io"><img width="60px" alt="RunPod" src="https://cdn.coollabs.io/sponsors/runpod.svg"/></a>
<a href="http://gravitywiz.com/?utm_source=dashy.io"><img width="60px" alt="Gravity Wiz" src="https://github.com/gravitywiz.png"/></a>
<a href="https://www.uxwizz.com/?utm_source=dashy.io"><img width="60px" alt="UXWizz" src="https://github.com/UXWizz.png"/></a>
<a href="https://codext.link/dashy-io?utm_source=dashy.io"><img width="60px" alt="Codext" src="https://cdn.coollabs.io/sponsors/codext.jpg"/></a>
<a href="https://interviewpal.com"><img width="60px" alt="InterviewPal" src="https://cdn.coollabs.io/sponsors/interviewpal.svg"/></a>
<a href="https://decidable.no?utm_source=dashy.io"><img width="60px" alt="Decidable" src="https://github.com/Decidable-AS.png"/></a>
<a href="https://hosthavoc.com"><img width="60px" alt="Host Havoc" src="https://cdn.coollabs.io/sponsors/hosthavoc.png"/></a>
<a href="https://martiinii.dev"><img width="60px" alt="martiinii" src="https://github.com/martiinii.png"/></a>
<a href="https://bragly.io"><img width="60px" alt="Bragly" src="https://cdn.coollabs.io/sponsors/bragly.png"/></a>

...and many more at [GitHub Sponsors](https://github.com/sponsors/coollabsio)

## Star History

[![Star History Chart](https://api.star-history.com/svg?repos=dashyapp/dashy&type=Date)](https://star-history.com/#dashyapp/dashy&Date)
