#!/usr/bin/env bash
#
# One-shot installation on a fresh Debian/Ubuntu VPS (run as root):
#
#   curl -fsSL https://raw.githubusercontent.com/lucasfanech/employee_clocking/<branch>/deploy/vps-install.sh \
#     | APP_DOMAIN=clocking.alpinecode.tech PMA_DOMAIN=phpmyadmin.clocking.alpinecode.tech \
#       ACME_EMAIL=you@example.com BRANCH=<branch> bash
#
# What it does: installs Docker (if missing), clones the repository into /opt/clocking on the
# given branch, generates /opt/clocking/.env.local with random secrets (kept on later runs),
# then builds and starts the production stack (Traefik + nginx + php + MySQL + phpMyAdmin).
# Re-running the script updates the checkout and redeploys.
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/lucasfanech/employee_clocking.git}"
BRANCH="${BRANCH:-main}"
TARGET="${TARGET:-/opt/clocking}"
APP_DOMAIN="${APP_DOMAIN:?APP_DOMAIN is required (e.g. clocking.example.com)}"
PMA_DOMAIN="${PMA_DOMAIN:-phpmyadmin.${APP_DOMAIN}}"
ACME_EMAIL="${ACME_EMAIL:?ACME_EMAIL is required (e-mail for the LetsEncrypt account)}"
APP_ADMIN_EMAIL="${APP_ADMIN_EMAIL:-admin@${APP_DOMAIN}}"

log() { printf '\n\033[1;36m>> %s\033[0m\n' "$*"; }
random() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c "${1:-32}"; }

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
ACME_EMAIL=${ACME_EMAIL}

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

# ---------------------------------------------------------------- Firewall (if ufw is active)
if command -v ufw >/dev/null 2>&1 && ufw status | grep -q "Status: active"; then
    log "Opening ports 80 and 443 in ufw"
    ufw allow 80/tcp >/dev/null && ufw allow 443/tcp >/dev/null
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
