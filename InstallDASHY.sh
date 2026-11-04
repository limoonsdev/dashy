#!/usr/bin/env bash
#
# Dashy installer for Linux.
#
#   curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallDASHY.sh | sudo bash
#
# The actual installation lives in scripts/install.sh. That script prepares
# /data/dashy/source, generates .env, builds or pulls the Dashy image, creates
# the dashy Docker network and starts docker-compose.yml together with
# docker-compose.prod.yml. Running the base Compose file alone leaves the dashy
# service without an image or a build context, which is the error this entry
# point used to hit.

set -euo pipefail

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

if [ "$(uname -s 2>/dev/null || true)" != "Linux" ]; then
    fail "Dashy's server installer supports Linux only."
fi

if [ "$(id -u)" -ne 0 ]; then
    fail "Run the installer as root: curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallDASHY.sh | sudo bash"
fi

command -v curl >/dev/null 2>&1 || fail "curl is required."
command -v bash >/dev/null 2>&1 || fail "bash is required."

BRANCH="${DASHY_REF:-${DASHY_BRANCH:-main}}"
RAW_BASE="${DASHY_RAW_URL:-https://raw.githubusercontent.com/limoonsdev/dashy/${BRANCH}}"
RAW_BASE="${RAW_BASE%/}"
INSTALLER_URL="${RAW_BASE}/scripts/install.sh"

# Keep the environment variables that scripts/install.sh understands.
if [ -n "${DASHY_REPOSITORY:-}" ]; then
    export DASHY_REPO_URL="$DASHY_REPOSITORY"
fi
if [ -z "${DASHY_REF:-}" ] && [ -n "${DASHY_BRANCH:-}" ]; then
    export DASHY_REF="$DASHY_BRANCH"
fi

if [ -n "${DASHY_INSTALL_DIR:-}" ]; then
    say "DASHY_INSTALL_DIR is no longer used; Dashy installs to /data/dashy/source."
fi

TMP_INSTALLER="$(mktemp /tmp/dashy-install.XXXXXX)" || fail "Could not create a temporary installer file."
trap 'rm -f "$TMP_INSTALLER"' EXIT

say "Downloading the Dashy installer"
if ! curl -fsSL --retry 3 --retry-delay 2 --retry-connrefused "$INSTALLER_URL" -o "$TMP_INSTALLER"; then
    fail "Could not download $INSTALLER_URL. Check your connection and try again."
fi

# The official installer installs Docker Compose when needed, generates the
# secrets, builds the local image when no published image exists, and starts
# both Compose files on the dashy network.
ok "Starting the Dashy server installation"
bash "$TMP_INSTALLER" "$@"
