#!/bin/sh

set -eu

if [ "$(id -u)" = '0' ]; then
    mkdir -p \
        /home/laravel/.composer/cache \
        /var/www/html/vendor \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs \
        /var/www/html/bootstrap/cache \
        /var/www/html/public/build

    if [ ! -f /var/www/html/vendor/autoload.php ]; then
        if [ ! -f /opt/vendor/autoload.php ]; then
            echo 'Production dependencies are missing from both /var/www/html/vendor and /opt/vendor.' >&2
            exit 1
        fi

        cp -a /opt/vendor/. /var/www/html/vendor/
    fi

    if [ ! -f /opt/public-build/manifest.json ]; then
        echo 'Frontend build is missing from /opt/public-build.' >&2
        exit 1
    fi

    cp -a /opt/public-build/. /var/www/html/public/build/

    if [ ! -f /var/www/html/.env ] && [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    fi

    # Bind mounts may contain package caches generated with development-only dependencies.
    find /var/www/html/bootstrap/cache \
        -maxdepth 1 \
        -type f \
        -name '*.php' \
        -delete

    chown -R laravel:laravel \
        /home/laravel/.composer \
        /var/www/html/vendor \
        /var/www/html/storage \
        /var/www/html/bootstrap/cache

    if [ -f /var/www/html/.env ]; then
        chgrp laravel /var/www/html/.env
        chmod 640 /var/www/html/.env
    fi

    exec setpriv \
        --reuid="$(id -u laravel)" \
        --regid="$(id -g laravel)" \
        --init-groups \
        "$@"
fi

exec "$@"
