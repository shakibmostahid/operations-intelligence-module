#!/bin/sh
set -e

if [ "${SYNC_PUBLIC_ASSETS:-true}" = "true" ]; then
    find /var/www/html/public/build -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a /opt/app-build/. /var/www/html/public/build/
fi

exec "$@"
