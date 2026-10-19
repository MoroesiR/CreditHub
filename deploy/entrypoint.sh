#!/bin/sh
set -e

: "${PORT:=80}"

sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

if [ -z "${APP_KEY}" ]; then
    APP_KEY=$(php artisan key:generate --show)
    export APP_KEY
fi

if [ ! -f "${DB_DATABASE}" ]; then
    touch "${DB_DATABASE}"
    chown www-data:www-data "${DB_DATABASE}"
fi

php artisan migrate --force --seed
php artisan config:cache
php artisan route:cache

exec apache2-foreground
