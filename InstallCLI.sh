#!/usr/bin/env bash
#
# Dashy CLI installer — Linux and macOS (Intel and Apple Silicon).
#
#   curl -fsSL https://raw.githubusercontent.com/limoonsdev/dashy/main/InstallCLI.sh | bash
#
# Installs the complete CLI (all modules, not just the entry file), a private
# Node runtime when needed, and the `dashy` command in ~/.dashy/bin. Nothing
# runs as root and nothing is written outside your home directory.
#
# Environment overrides:
#   DASHY_INSTALL_DIR   install location (default: ~/.dashy)
#   DASHY_NODE_VERSION  Node version to download (default: v22.11.0)
#   DASHY_BRANCH        repository branch (default: main)
#   DASHY_RAW_URL       raw CLI base URL (advanced)

set -euo pipefail

NODE_VERSION="${DASHY_NODE_VERSION:-v22.11.0}"
INSTALL_DIR="${DASHY_INSTALL_DIR:-$HOME/.dashy}"
BIN_DIR="$INSTALL_DIR/bin"
NODE_DIR="$INSTALL_DIR/node"
CLI_DIR="$INSTALL_DIR/cli"
BRANCH="${DASHY_BRANCH:-main}"
RAW_BASE="${DASHY_RAW_URL:-https://raw.githubusercontent.com/limoonsdev/dashy/$BRANCH/cli}"
RAW_BASE="${RAW_BASE%/}"
FALLBACK_URL="$RAW_BASE/dashy.js"

BRAND='\033[38;2;139;92;246m'
ACCENT='\033[38;2;34;211;238m'
GREEN='\033[38;2;52;211;153m'
AMBER='\033[38;2;251;191;36m'
RED='\033[38;2;248;113;113m'
GREY='\033[38;2;148;163;184m'
BOLD='\033[1m'
RESET='\033[0m'

logo() {
  printf '\n'
  printf "${BRAND}  ██████╗  █████╗ ███████╗██╗  ██╗██╗   ██╗${RESET}\n"
  printf "${BRAND}  ██╔══██╗██╔══██╗██╔════╝██║  ██║╚██╗ ██╔╝${RESET}\n"
  printf "${ACCENT}  ██║  ██║███████║███████╗███████║ ╚████╔╝ ${RESET}\n"
  printf "${ACCENT}  ██║  ██║██╔══██║╚════██║██╔══██║  ╚██╔╝  ${RESET}\n"
  printf "${GREY}  ██████╔╝██║  ██║███████║██║  ██║   ██║   ${RESET}\n"
  printf "${GREY}  ╚═════╝ ╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝   ╚═╝   ${RESET}\n"
  printf "\n  ${GREY}CLI installer · Linux / macOS · Intel & Apple Silicon${RESET}\n\n"
}

step() {
  printf "  ${ACCENT}▸${RESET} %s\n" "$1"
}

ok() {
  printf "  ${GREEN}✔${RESET} %s\n" "$1"
}

warn() {
  printf "  ${AMBER}!${RESET} %s\n" "$1"
}

fail() {
  printf "  ${RED}✖${RESET} %s\n" "$1" >&2
  exit 1
}

detect_platform() {
  case "$(uname -s)" in
    Linux) PLATFORM="linux" ;;
    Darwin) PLATFORM="darwin" ;;
    *) fail "Unsupported operating system: $(uname -s). Use the PowerShell installer on Windows." ;;
  esac

  case "$(uname -m)" in
    x86_64 | amd64) ARCH="x64" ;;
    arm64 | aarch64) ARCH="arm64" ;;
    *) fail "Unsupported architecture: $(uname -m)." ;;
  esac
}

node_major() {
  "$1" -p "process.versions.node.split('.')[0]" 2>/dev/null || printf '0'
}

install_node() {
  local archive="node-${NODE_VERSION}-${PLATFORM}-${ARCH}"
  local extension="tar.gz"
  [ "$PLATFORM" = "linux" ] && extension="tar.xz"

  local url="https://nodejs.org/dist/${NODE_VERSION}/${archive}.${extension}"

  step "Downloading a private Node runtime (${NODE_VERSION} ${PLATFORM}-${ARCH})"
  mkdir -p "$NODE_DIR"

  if ! curl -fsSL "$url" -o "$INSTALL_DIR/node.${extension}"; then
    fail "Could not download Node from nodejs.org. Install Node 18+ yourself and run this again."
  fi

  step "Extracting Node"
  tar -xf "$INSTALL_DIR/node.${extension}" -C "$NODE_DIR" --strip-components=1
  rm -f "$INSTALL_DIR/node.${extension}"

  NODE="$NODE_DIR/bin/node"
  ok "Node installed in $NODE_DIR"
}

install_cli() {
  mkdir -p "$CLI_DIR"
  local entry="$CLI_DIR/dist/cli.js"
  local source_dir
  source_dir="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")" 2>/dev/null && pwd)" || source_dir=""

  if [ -n "$source_dir" ] && [ -f "$source_dir/cli/dist/cli.js" ]; then
    rm -rf "$CLI_DIR/dist"
    cp -R "$source_dir/cli/dist" "$CLI_DIR/dist"
    ok "CLI copied from $source_dir/cli/dist"
  else
    step "Downloading the CLI modules"
    local tmp manifest file target
    tmp="$(mktemp -d)"

    if curl -fsSL "$RAW_BASE/dist/manifest.txt" -o "$tmp/manifest.txt"; then
      while IFS= read -r file; do
        file="$(printf '%s' "$file" | tr -d '\r' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
        [ -z "$file" ] && continue

        case "$file" in
          /* | *..*) fail "Unsafe path in the CLI manifest: $file" ;;
        esac

        target="$CLI_DIR/dist/$file"
        mkdir -p "$(dirname "$target")"

        if ! curl -fsSL "$RAW_BASE/dist/$file" -o "$target"; then
          rm -rf "$tmp" "$CLI_DIR/dist"
          fail "Could not download CLI module $file."
        fi
      done < "$tmp/manifest.txt"

      rm -rf "$tmp"

      if [ ! -f "$entry" ]; then
        fail "The CLI entry point is missing after download."
      fi

      ok "CLI installed in $CLI_DIR/dist"
    else
      rm -rf "$tmp"
      rm -rf "$CLI_DIR/dist"
      warn "The full CLI manifest is unavailable, using the portable CLI"
      entry="$CLI_DIR/dashy.js"

      if [ -n "$source_dir" ] && [ -f "$source_dir/cli/dashy.js" ]; then
        cp "$source_dir/cli/dashy.js" "$entry"
        ok "Portable CLI copied from $source_dir/cli/dashy.js"
      elif ! curl -fsSL "$FALLBACK_URL" -o "$entry"; then
        fail "Could not download the CLI from $FALLBACK_URL"
      else
        ok "Portable CLI downloaded"
      fi
    fi
  fi

  CLI_ENTRY="$entry"

  cat > "$BIN_DIR/dashy" <<EOF
#!/usr/bin/env bash
exec "$NODE" "$CLI_ENTRY" "\$@"
EOF
  chmod +x "$BIN_DIR/dashy"
}

wire_path() {
  local marker="# dashy cli"
  local line="export PATH=\"$BIN_DIR:\$PATH\""

  if printf '%s' ":$PATH:" | grep -q ":$BIN_DIR:"; then
    ok "$BIN_DIR is already on PATH"
    return
  fi

  for rc in "$HOME/.zshrc" "$HOME/.bashrc" "$HOME/.profile"; do
    if [ -f "$rc" ] && ! grep -q "$marker" "$rc" 2>/dev/null; then
      {
        printf '\n%s\n%s\n' "$marker" "$line"
      } >> "$rc"
      ok "Added $BIN_DIR to PATH in $rc"
      printf "  ${GREY}Restart the shell or run: source %s${RESET}\n" "$rc"
      return
    fi
  done

  warn "Add this to your shell profile:"
  printf "    %s\n" "$line"
}

main() {
  logo
  detect_platform

  mkdir -p "$INSTALL_DIR" "$BIN_DIR"

  if command -v node >/dev/null 2>&1 && [ "$(node_major node)" -ge 18 ]; then
    NODE="$(command -v node)"
    ok "Using Node $(node -v) from $NODE"
  elif [ -x "$NODE_DIR/bin/node" ] && [ "$(node_major "$NODE_DIR/bin/node")" -ge 18 ]; then
    NODE="$NODE_DIR/bin/node"
    ok "Using the private Node in $NODE_DIR"
  else
    install_node
  fi

  install_cli
  wire_path

  if [ -n "${CLI_ENTRY:-}" ] && "$NODE" "$CLI_ENTRY" --version >/dev/null 2>&1; then
    ok "Installed: $("$NODE" "$CLI_ENTRY" --version)"
  else
    warn "Installed, but the CLI did not report its version."
  fi

  printf "\n"
  printf "  ${GREEN}${BOLD}Installed.${RESET}\n"
  printf "  ${GREY}1.${RESET} In Dashy: ${BOLD}Settings → CLI → Generate a sign-in code${RESET}\n"
  printf "  ${GREY}2.${RESET} Run ${BOLD}dashy connect DASHY-XXXX-XXXX${RESET}\n"
  printf "  ${GREY}3.${RESET} Run ${BOLD}dashy${RESET} for the full-screen console\n"
  printf "\n  ${GREY}Docs: https://github.com/limoonsdev/dashy/tree/main/cli${RESET}\n\n"
}

main "$@"
