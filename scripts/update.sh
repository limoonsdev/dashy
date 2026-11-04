#!/usr/bin/env bash
# Rebuild a Dashy instance that runs a locally built image from this repository.
#
#   bash update.sh [commit]
#
# The installer builds dashyapp/dashy:local-<commit> from the source checkout, so an
# update fetches the branch, builds the new image, and restarts the stack with it. The
# work runs detached because restarting the stack kills the connection that started it.
# Progress is written to /data/dashy/source/.upgrade-status, which the dashboard reads.

set -uo pipefail

SOURCE_DIR="/data/dashy/source"
ENV_FILE="$SOURCE_DIR/.env"
STATUS_FILE="$SOURCE_DIR/.upgrade-status"
LOGFILE="$SOURCE_DIR/update-$(date +%Y%m%d-%H%M%S).log"
BRANCH="${DASHY_SOURCE_BRANCH:-main}"

write_status() {
    printf '%s|%s|%s\n' "$1" "$2" "$(date -Iseconds)" > "$STATUS_FILE"
}

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" >> "$LOGFILE"
}

if [ "${DASHY_UPDATE_DETACHED:-}" != "1" ]; then
    DASHY_UPDATE_DETACHED=1 nohup bash "$0" "$@" >/dev/null 2>&1 &
    exit 0
fi

if [ ! -d "$SOURCE_DIR/.git" ]; then
    write_status "error" "No source checkout found at $SOURCE_DIR."
    exit 1
fi

write_status 1 "Fetching the latest Dashy source"

# The checkout belongs to the container user (UID 9999), so Git refuses to touch it as
# root with "detected dubious ownership". Trust only this directory, like the installer.
if ! git config --system --get-all safe.directory 2>/dev/null | grep -qxF "$SOURCE_DIR"; then
    git config --system --add safe.directory "$SOURCE_DIR" 2>/dev/null ||
        git config --global --add safe.directory "$SOURCE_DIR" 2>/dev/null || true
fi

if ! git -C "$SOURCE_DIR" fetch --depth=1 origin "$BRANCH" >> "$LOGFILE" 2>&1; then
    write_status "error" "Could not fetch $BRANCH from the Dashy repository. Check $LOGFILE"
    exit 1
fi

git -C "$SOURCE_DIR" checkout -q -f -B "$BRANCH" FETCH_HEAD >> "$LOGFILE" 2>&1 || {
    write_status "error" "Could not check out the fetched source. Check $LOGFILE"
    exit 1
}

SHA=$(git -C "$SOURCE_DIR" rev-parse --short HEAD)
# Visible version: the release in release.json plus the commit being deployed (4.7.0-abc1234).
RELEASE_VERSION=$(sed -n '/"stable"/,/}/s/.*"dashy": *"\([^"]*\)".*/\1/p' "$SOURCE_DIR/release.json" 2>/dev/null | head -n 1)
RELEASE_VERSION=${RELEASE_VERSION:-0.0.0}
DASHY_BUILD_VERSION="${RELEASE_VERSION}-${SHA}"
for helper in upgrade.sh upgrade-postgres.sh; do
    if [ -f "$SOURCE_DIR/scripts/$helper" ]; then
        cp -f "$SOURCE_DIR/scripts/$helper" "$SOURCE_DIR/$helper"
    fi
done
log "Updating to $DASHY_BUILD_VERSION"

REGISTRY="${REGISTRY_URL:-docker.io}"
IMAGE="$REGISTRY/dashyapp/dashy:local-$SHA"

write_status 2 "Building the Dashy image for $SHA"
log "Building $IMAGE"
if ! DOCKER_BUILDKIT=1 docker build --progress=plain \
    --build-arg "DASHY_VERSION=$DASHY_BUILD_VERSION" \
    -f "$SOURCE_DIR/docker/production/Dockerfile" \
    -t "$IMAGE" \
    "$SOURCE_DIR" >> "$LOGFILE" 2>&1; then
    write_status "error" "Could not build the new Dashy image. Check $LOGFILE"
    exit 1
fi

if grep -q '^DASHY_LOCAL_IMAGE=' "$ENV_FILE" 2>/dev/null; then
    sed -i 's|^DASHY_LOCAL_IMAGE=.*|DASHY_LOCAL_IMAGE=true|' "$ENV_FILE"
else
    printf 'DASHY_LOCAL_IMAGE=true\n' >> "$ENV_FILE"
fi

COMPOSE_FILES="-f $SOURCE_DIR/docker-compose.yml -f $SOURCE_DIR/docker-compose.prod.yml"
if [ -f "$SOURCE_DIR/docker-compose.custom.yml" ]; then
    COMPOSE_FILES="$COMPOSE_FILES -f $SOURCE_DIR/docker-compose.custom.yml"
fi

write_status 3 "Restarting Dashy on $SHA"
log "Starting Dashy with LATEST_IMAGE=local-$SHA"

LATEST_IMAGE="local-$SHA" docker compose --env-file "$ENV_FILE" $COMPOSE_FILES \
    up -d --remove-orphans --wait --wait-timeout 90 >> "$LOGFILE" 2>&1

if [ "$(docker inspect --format '{{.State.Running}}' dashy 2>/dev/null)" != "true" ]; then
    write_status "error" "Dashy did not start with the new image. Check $LOGFILE"
    exit 1
fi

write_status 6 "Update complete"
log "Dashy is running $SHA"

# Keep the completion around long enough for the dashboard to read it, like upgrade.sh.
sleep 60
if [ "$(cut -d'|' -f1 "$STATUS_FILE" 2>/dev/null)" = "6" ]; then
    rm -f "$STATUS_FILE"
fi
