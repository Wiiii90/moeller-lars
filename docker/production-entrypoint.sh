#!/bin/sh
set -eu

require_nonempty() {
    name="$1"
    eval "value=\${$name:-}"
    if [ -z "$value" ]; then
        echo "Required production configuration $name is missing." >&2
        exit 78
    fi
}

require_nonempty APP_KEY
require_nonempty APP_URL
require_nonempty DB_HOST
require_nonempty DB_PORT
require_nonempty DB_DATABASE
require_nonempty DB_USERNAME
require_nonempty DB_PASSWORD

if [ "${APP_ENV:-}" != "production" ]; then
    echo "Production image requires APP_ENV=production." >&2
    exit 78
fi
case "$APP_URL" in
    https://*) ;;
    *) echo "Production APP_URL must use HTTPS." >&2; exit 78 ;;
esac
if [ "${DB_CONNECTION:-}" != "pgsql" ]; then
    echo "Production DB_CONNECTION must be pgsql." >&2
    exit 78
fi
if [ "${SESSION_SECURE_COOKIE:-}" != "true" ]; then
    echo "Production SESSION_SECURE_COOKIE must be true." >&2
    exit 78
fi
if [ "${MEDIA_DISK:-}" != "local" ]; then
    echo "Production MEDIA_DISK must be local for the declared persistent media contract." >&2
    exit 78
fi

if [ "${MATOMO_ENABLED:-false}" = "true" ]; then
    require_nonempty MATOMO_BASE_URL
    require_nonempty MATOMO_SITE_ID
    case "$MATOMO_BASE_URL" in
        https://*) ;;
        *) echo "Production MATOMO_BASE_URL must use HTTPS when enabled." >&2; exit 78 ;;
    esac
fi

for path in /var/www/html/storage/framework /var/www/html/storage/logs /var/www/html/storage/app/private /var/www/html/bootstrap/cache; do
    if [ ! -d "$path" ] || [ ! -w "$path" ]; then
        echo "Required writable runtime path is unavailable: $path" >&2
        exit 73
    fi
done

# Build Laravel's production caches only after the runtime environment has been
# injected. This keeps configuration secrets out of the image build while
# avoiding route/config/view discovery on every web request.
php artisan optimize --no-interaction

exec "$@"
