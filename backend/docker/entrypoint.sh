#!/bin/sh
set -eu

echo "Attente de MySQL…"
attempt=0
until php bin/console dbal:run-sql "SELECT 1" --quiet >/dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "MySQL reste indisponible après 60 secondes." >&2
        exit 1
    fi
    sleep 2
done

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
