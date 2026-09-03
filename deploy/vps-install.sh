#!/usr/bin/env bash
#
# One-shot installation on a Debian/Ubuntu VPS that already runs Caddy as the
# host reverse proxy (as root):
#
#   curl -fsSL https://raw.githubusercontent.com/lucasfanech/employee_clocking/<branch>/deploy/vps-install.sh \
#     | APP_DOMAIN=clocking.example.com PMA_DOMAIN=phpmyadmin.clocking.example.com \
#       BRANCH=<branch> bash
#
# What it does: installs Docker (if missing), clones the repository into
# /opt/clocking on the given branch, generates /opt/clocking/.env.local with
# random secrets (kept on later runs), starts the stack bound to loopback
# ports, and appends two reverse_proxy blocks to /etc/caddy/Caddyfile (only if
# they are not already present) before reloading Caddy.
#
# Re-running the script updates the checkout and redeploys.
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/lucasfanech/employee_clocking.git}"
BRANCH="${BRANCH:-main}"
TARGET="${TARGET:-/opt/clocking}"
APP_DOMAIN="${APP_DOMAIN:?APP_DOMAIN is required (e.g. clocking.example.com)}"
PMA_DOMAIN="${PMA_DOMAIN:-phpmyadmin.${APP_DOMAIN}}"
APP_ADMIN_EMAIL="${APP_ADMIN_EMAIL:-admin@${APP_DOMAIN}}"
APP_PORT="${APP_PORT:-8090}"
PHPMYADMIN_PORT="${PHPMYADMIN_PORT:-8091}"
CADDYFILE="${CADDYFILE:-/etc/caddy/Caddyfile}"

log() { printf '\n\033[1;36m>> %s\033[0m\n' "$*"; }
random() { head -c 4096 /dev/urandom | tr -dc 'A-Za-z0-9' | head -c "${1:-32}" || true; }

# ---------------------------------------------------------------- Docker
if ! command -v docker >/dev/null 2>&1; then
    log "Installing Docker"
    curl -fsSL https://get.docker.com | sh
fi
if ! docker compose version >/dev/null 2>&1; then
    log "Installing the Docker Compose plugin"
    apt-get update -qq && apt-get install -y -qq docker-compose-plugin
fi
command -v git >/dev/null 2>&1 || { apt-get update -qq && apt-get install -y -qq git; }

# ---------------------------------------------------------------- Checkout
if [ -d "$TARGET/.git" ]; then
    log "Updating $TARGET ($BRANCH)"
    git -C "$TARGET" fetch origin "$BRANCH"
    git -C "$TARGET" checkout -q "$BRANCH"
    git -C "$TARGET" reset -q --hard "origin/$BRANCH"
else
    log "Cloning $REPO_URL ($BRANCH) into $TARGET"
    git clone --branch "$BRANCH" "$REPO_URL" "$TARGET"
fi
cd "$TARGET"

# ---------------------------------------------------------------- Secrets
if [ ! -f .env.local ]; then
    log "Generating .env.local"
    ADMIN_PASSWORD="$(random 20)"
    cat > .env.local <<EOF
APP_ENV=prod
APP_SECRET=$(random 48)

APP_DOMAIN=${APP_DOMAIN}
PMA_DOMAIN=${PMA_DOMAIN}

# Loopback ports exposed to the host; the reverse proxy targets these.
APP_PORT=${APP_PORT}
PHPMYADMIN_PORT=${PHPMYADMIN_PORT}

MYSQL_DATABASE=clocking
MYSQL_USER=clocking
MYSQL_PASSWORD=$(random 32)
MYSQL_ROOT_PASSWORD=$(random 32)

# Bootstrap administrator, created only while the user table is empty.
APP_ADMIN_EMAIL=${APP_ADMIN_EMAIL}
APP_ADMIN_PASSWORD=${ADMIN_PASSWORD}

TZ=Europe/Paris
EOF
    chmod 600 .env.local
    echo "   administrator: ${APP_ADMIN_EMAIL} / ${ADMIN_PASSWORD}  (also in ${TARGET}/.env.local)"
else
    log "Keeping the existing .env.local"
fi

# ---------------------------------------------------------------- Caddy vhosts
if [ -f "$CADDYFILE" ]; then
    added=0
    if ! grep -qE "^\s*${APP_DOMAIN//./\\.}\s*\{" "$CADDYFILE"; then
        log "Adding ${APP_DOMAIN} to ${CADDYFILE}"
        cat >> "$CADDYFILE" <<EOF

${APP_DOMAIN} {
	reverse_proxy 127.0.0.1:${APP_PORT}
}
EOF
        added=1
    fi
    if ! grep -qE "^\s*${PMA_DOMAIN//./\\.}\s*\{" "$CADDYFILE"; then
        log "Adding ${PMA_DOMAIN} to ${CADDYFILE}"
        cat >> "$CADDYFILE" <<EOF

${PMA_DOMAIN} {
	reverse_proxy 127.0.0.1:${PHPMYADMIN_PORT}
}
EOF
        added=1
    fi
    if [ "$added" = 1 ] && command -v systemctl >/dev/null 2>&1; then
        log "Reloading Caddy"
        systemctl reload caddy || systemctl restart caddy
    fi
else
    log "No ${CADDYFILE} found; skipping Caddy configuration"
    echo "   add manually:"
    echo "     ${APP_DOMAIN} { reverse_proxy 127.0.0.1:${APP_PORT} }"
    echo "     ${PMA_DOMAIN} { reverse_proxy 127.0.0.1:${PHPMYADMIN_PORT} }"
fi

# ---------------------------------------------------------------- Stack
log "Building and starting the stack"
docker compose --env-file .env.local -f compose.yaml -f compose.prod.yaml up -d --build --remove-orphans

log "Waiting for the application"
for _ in $(seq 1 60); do
    if docker compose --env-file .env.local -f compose.yaml -f compose.prod.yaml logs php 2>/dev/null | grep -q "NOTICE: ready to handle connections"; then
        break
    fi
    sleep 2
done

log "Done"
echo "   https://${APP_DOMAIN}"
echo "   https://${PMA_DOMAIN}   (MySQL user: clocking, password in .env.local)"
echo
echo "   Status:  cd ${TARGET} && docker compose --env-file .env.local -f compose.yaml -f compose.prod.yaml ps"
echo "   Logs:    cd ${TARGET} && docker compose --env-file .env.local -f compose.yaml -f compose.prod.yaml logs -f"
