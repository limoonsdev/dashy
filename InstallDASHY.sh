#!/usr/bin/env bash
#
# Dashy installer for Linux and macOS.
#
#   curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallDASHY.sh | bash
#
# Clones the instance into ~/.dashy-instance (or $DASHY_INSTALL_DIR) and starts
# it with Docker Compose. Nothing runs as root unless Docker itself needs it.

set -euo pipefail

REPOSITORY="${DASHY_REPOSITORY:-https://github.com/limoonsdev/dashy.git}"
INSTALL_DIR="${DASHY_INSTALL_DIR:-$HOME/.dashy-instance}"
BRAND='\033[38;2;139;92;246m'
GREEN='\033[38;2;52;211;153m'
GREY='\033[38;2;148;163;184m'
RED='\033[38;2;248;113;113m'
RESET='\033[0m'

say() { printf "${BRAND}▸${RESET} %s\n" "$1"; }
ok() { printf "${GREEN}✔${RESET} %s\n" "$1"; }
fail() { printf "${RED}✖${RESET} %s\n" "$1" >&2; exit 1; }

printf '\n'
printf "${BRAND}  ██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗${RESET}\n"
printf "${BRAND}  ██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝${RESET}\n"
printf "${GREY}  ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝  installer${RESET}\n\n"

command -v git >/dev/null || fail "git is required."
command -v docker >/dev/null || fail "Docker is required: https://docs.docker.com/engine/install/"

if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE="docker-compose"
else
    fail "Docker Compose is required (v2 plugin or docker-compose)."
fi

if [ -d "$INSTALL_DIR/.git" ]; then
    say "Updating $INSTALL_DIR"
    git -C "$INSTALL_DIR" pull --ff-only
else
    say "Installing into $INSTALL_DIR"
    git clone --depth 1 "$REPOSITORY" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"
[ -f docker-compose.yml ] || [ -f compose.yaml ] || fail "No compose file in $INSTALL_DIR."

say "Starting Dashy"
$COMPOSE up -d --remove-orphans

ok "Dashy is running"
printf "${GREY}  Open http://localhost:8000 and create the first account.${RESET}\n"
printf "${GREY}  Logs: cd %s && %s logs -f${RESET}\n\n" "$INSTALL_DIR" "$COMPOSE"
