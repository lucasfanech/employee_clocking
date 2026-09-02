#!/usr/bin/env bash
set -euo pipefail

# Only bootstrap the application when php-fpm (or bin/console) is started.
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    # Dev image: the source tree is bind-mounted, dependencies may be missing.
    if [ "${APP_ENV:-prod}" = 'dev' ] && [ ! -f vendor/autoload_runtime.php ]; then
        echo '>> Installing Composer dependencies'
        composer install --prefer-dist --no-progress --no-interaction
    fi

    mkdir -p var/cache var/log
    chown -R www-data:www-data var || true

    if [ -n "${DATABASE_URL:-}" ]; then
        echo '>> Waiting for the database'
        attempts=0
        until php bin/console dbal:run-sql -q 'SELECT 1' >/dev/null 2>&1; do
            attempts=$((attempts + 1))
            if [ "$attempts" -ge 60 ]; then
                echo '!! Database unreachable after 60 attempts' >&2
                exit 1
            fi
            sleep 2
        done

        echo '>> Running migrations'
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

        if [ -n "${APP_ADMIN_PASSWORD:-}" ]; then
            echo '>> Ensuring the bootstrap administrator exists'
            php bin/console app:user:create "${APP_ADMIN_EMAIL:-admin@example.com}" "${APP_ADMIN_PASSWORD}" --admin --only-if-empty
        fi
    fi
fi

exec docker-php-entrypoint "$@"
