#!/bin/sh
set -eu

cd /var/www/html

composer install --prefer-dist --no-interaction --no-progress

mkdir -p var/cache var/log var/tailwind
chown -R www-data:www-data var

php bin/console tailwind:build --no-interaction

exec "$@"
