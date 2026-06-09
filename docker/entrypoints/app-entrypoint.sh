#!/bin/sh
set -e

if [ "$1" = "dev" ]; then
    if [ ! -d vendor ]; then
        composer install --no-interaction --prefer-dist
    fi

    if [ ! -d node_modules ]; then
        npm install
    fi

    npm run dev -- --host 0.0.0.0 &
    exec php-fpm
fi

exec "$@"
