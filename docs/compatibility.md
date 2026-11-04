# Compatibility

Dashy is a control plane for Linux containers. The dashboard itself runs on Linux, natively or inside WSL2 / a Linux VM. The servers it manages only need SSH, so they can run Linux, Windows or BSD.

Run the local check first:

```bash
./scripts/platform-check.sh
```

It reports the OS, package manager, Docker, Compose and init system, then prints the next step. It does not change anything.

## Linux

### Automated distributions

The installer detects the package manager and installs `curl`, `wget`, `git`, `jq`, `openssl` and Docker when needed.

| Family | Distributions | Package manager |
| --- | --- | --- |
| Debian | Debian, Ubuntu, Raspberry Pi OS, Linux Mint, Pop!_OS | `apt` |
| Red Hat | RHEL, Fedora, Rocky, AlmaLinux, CentOS Stream, Amazon Linux | `dnf` / `yum` |
| Arch | Arch, Manjaro, EndeavourOS, CachyOS | `pacman` |
| SUSE | openSUSE Leap, Tumbleweed, SLES | `zypper` |
| Alpine | Alpine, postmarketOS | `apk` |

```bash
git clone https://github.com/limoonsdev/dashy.git
cd dashy
sudo ./scripts/install.sh
```

### Other distributions

Gentoo, Void, NixOS, Slackware, Clear Linux and derivatives work when Docker Engine and the Compose plugin are available. Install Docker from your distribution, then run the installer; it reuses the existing engine.

```bash
docker --version
docker compose version
git clone https://github.com/limoonsdev/dashy.git
cd dashy
sudo ./scripts/install.sh
```

If the installer cannot install a package on an unusual distribution, install the five prerequisites manually (`curl`, `wget`, `git`, `jq`, `openssl`), install Docker, and rerun it as root.

There is no list of "supported distributions" beyond this rule: **if Docker Engine and Compose v2 run, Dashy runs**. The automated list is the set of package managers the installer knows.

### Requirements

- Linux kernel 5.10 or newer (any current distribution)
- 2 vCPU, 2 GB RAM minimum; 4 GB recommended
- 20 GB free disk
- systemd or OpenRC
- Ports 22, 80, 443, 8000, 6001 and 6002

## Windows

### Windows 10 2004+ and Windows 11

Use WSL2 with an Ubuntu distribution. The bootstrap script enables systemd, installs Git inside WSL, clones Dashy and runs the Linux installer as root.

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\scripts\install.ps1
```

Requirements: virtualization enabled in the BIOS/UEFI, WSL2 installed, and a reboot after the first `wsl --install`. The dashboard is then available at `http://localhost:8000` and on the Windows host's LAN IP.

The script is idempotent: it reuses an existing Ubuntu distribution and fetches the latest `main`.

### Windows Server 2019/2022/2025

WSL2 is available on current Windows Server releases with the WSL feature enabled. Run the same script from an elevated PowerShell.

If WSL2 is unavailable or systemd cannot run in your server build, use a **Hyper-V Linux VM** (Ubuntu Server) and follow the Linux instructions inside it. This is the most predictable path on older Windows Server versions.

Windows 7, 8, 8.1 and Server 2016 are not supported as hosts; use a Linux VM.

### Managing Windows servers

The host restriction is only for the control plane. Dashy can manage Windows servers over SSH (OpenSSH Server) the same way it manages Linux servers. Windows containers are not supported; workloads run as Linux containers on a Linux server.

## macOS

macOS does not run the control plane natively. Docker Desktop runs Linux containers inside a VM, but Dashy's installer manages `/data/dashy` and the Docker engine itself, so a dedicated Linux VM is cleaner.

### Multipass (recommended)

```bash
brew install --cask multipass
multipass launch --name dashy --cpus 2 --memory 4G --disk 20G
multipass shell dashy
```

Inside the VM:

```bash
sudo apt-get update
sudo apt-get install -y git
git clone https://github.com/limoonsdev/dashy.git
cd dashy
sudo ./scripts/install.sh
```

Find the VM address with `multipass info dashy` and open `http://<vm-ip>:8000`.

### OrbStack or UTM

Create an Ubuntu 22.04/24.04 machine, then run the Linux instructions inside it. Docker Desktop or OrbStack on the Mac can still be used as a Docker CLI, but the control plane should live in the Linux machine.

The macOS client can manage a remote Dashy instance normally in a browser. There is no native macOS menu-bar app.

## FreeBSD, OpenBSD and NetBSD

Docker does not run natively on these systems. Use a Linux VM:

- **FreeBSD:** `bhyve` with an Ubuntu cloud image, or `vm-bhyve`.
- **OpenBSD:** `vmm` with an Ubuntu cloud image.
- **NetBSD:** a Linux VM under NVMM or Xen.

Inside the VM, follow the Linux instructions. The BSD host can still be a managed server if it exposes SSH; it just cannot host the control plane.

Jails and zones are not a substitute: they run BSD userlands, not Linux containers.

## ARM64

Dashy runs on arm64 when the published container image has an arm64 manifest. On Raspberry Pi 4/5 and other arm64 boards, use a 64-bit OS and Docker. If the image is amd64-only, run it under emulation on a more capable host rather than on the board.

## Ports and networking

| Port | Purpose |
| --- | --- |
| 22 | SSH to managed servers |
| 80 / 443 | Reverse proxy and automatic HTTPS |
| 8000 | Dashboard (change with `APP_PORT`) |
| 6001 / 6002 | Realtime WebSocket |

For a public instance, put Caddy, Nginx or Traefik in front of port 8000 and terminate TLS there. Do not expose the dashboard directly without TLS.
