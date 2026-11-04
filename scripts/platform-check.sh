#!/usr/bin/env bash
# Read-only platform check for Dashy. Prints what this machine can run and the next step.
set -u

have() { command -v "$1" >/dev/null 2>&1; }

INSTALL_URL='https://raw.githubusercontent.com/limoonsdev/dashy/main/scripts/install.sh'

os="$(uname -s 2>/dev/null || echo unknown)"
arch="$(uname -m 2>/dev/null || echo unknown)"
platform="$os"
distro=""
version=""
pkg=""
init=""

if [ "$os" = "Linux" ]; then
    if [ -r /etc/os-release ]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        distro="${ID:-}"
        version="${VERSION_ID:-}"
    fi
    if grep -qi microsoft /proc/version 2>/dev/null; then
        platform="WSL2"
    fi
    if have systemctl; then
        init="systemd"
    elif have rc-service; then
        init="openrc"
    fi
    for candidate in apt-get dnf yum pacman zypper apk xbps-install emerge; do
        if have "$candidate"; then
            pkg="$candidate"
            break
        fi
    done
elif [[ "$os" == MINGW* || "$os" == MSYS* || "$os" == CYGWIN* ]]; then
    platform="Windows"
elif [ "$os" = "Darwin" ]; then
    platform="macOS"
elif [ "$os" = "FreeBSD" ] || [ "$os" = "OpenBSD" ] || [ "$os" = "NetBSD" ]; then
    platform="$os"
fi

docker="not installed"
compose="not installed"

if have docker; then
    docker="$(docker --version 2>/dev/null | head -n 1)"
    if docker compose version >/dev/null 2>&1; then
        compose="$(docker compose version --short 2>/dev/null | head -n 1)"
    elif have docker-compose; then
        compose="$(docker-compose --version 2>/dev/null | head -n 1)"
    fi
fi

printf '\nDashy platform check\n'
printf '====================\n'
printf 'OS:        %s %s\n' "$platform" "$arch"
[ -n "$distro" ] && printf 'Distro:    %s %s\n' "$distro" "$version"
[ -n "$pkg" ] && printf 'Packages:  %s\n' "$pkg"
[ -n "$init" ] && printf 'Init:      %s\n' "$init"
printf 'Docker:    %s\n' "$docker"
printf 'Compose:   %s\n' "$compose"
printf '\nNext step:\n'

case "$platform" in
    Linux)
        if [ "$pkg" = "apt-get" ] || [ "$pkg" = "dnf" ] || [ "$pkg" = "yum" ] || [ "$pkg" = "pacman" ] || [ "$pkg" = "zypper" ] || [ "$pkg" = "apk" ]; then
            printf '  Native install: curl -fsSL %s | sudo bash\n' "$INSTALL_URL"
        else
            printf '  Install Docker Engine + Compose, then: curl -fsSL %s | sudo bash\n' "$INSTALL_URL"
            printf '  See docs/compatibility.md#other-distributions\n'
        fi
        ;;
    WSL2)
        printf '  Run .\\scripts\\install.ps1 from an elevated PowerShell on Windows.\n'
        printf '  Or run the one-line installer inside this WSL distribution:\n'
        printf '    curl -fsSL %s | sudo bash\n' "$INSTALL_URL"
        ;;
    Windows)
        printf '  Run .\\scripts\\install.ps1 from an elevated PowerShell.\n'
        ;;
    macOS)
        printf '  Use a Linux VM (Multipass, OrbStack or UTM): docs/compatibility.md#macos\n'
        ;;
    FreeBSD|OpenBSD|NetBSD)
        printf '  Use a Linux VM (bhyve/vmm): docs/compatibility.md#freebsd-openbsd-and-netbsd\n'
        ;;
    *)
        printf '  Unknown platform. See docs/compatibility.md\n'
        ;;
esac

printf '\n'
