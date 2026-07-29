#!/bin/sh

set -eu

if ! grep -q '^APP_KEY=.' /var/www/html/.env; then
    php artisan key:generate --force --no-interaction
fi

php artisan package:discover --ansi
php artisan migrate --force --no-interaction

case "${DB_SEED_ON_STARTUP:-true}" in
    1|true|yes)
        php artisan db:seed --class=DeploymentSeeder --force --no-interaction
        ;;
    0|false|no)
        echo 'Database seeding is disabled by DB_SEED_ON_STARTUP.'
        ;;
    *)
        echo 'DB_SEED_ON_STARTUP must be true/false, yes/no, or 1/0.' >&2
        exit 1
        ;;
esac

exec php artisan octane:start \
    --server=swoole \
    --host=0.0.0.0 \
    --port=8000
