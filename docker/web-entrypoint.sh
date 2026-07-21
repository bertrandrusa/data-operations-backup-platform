#!/bin/sh
set -eu

attempt=0
until php /var/www/html/bin/migrate.php; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 12 ]; then
        echo "Database migration failed after $attempt attempts" >&2
        exit 1
    fi
    echo "Database unavailable; retrying migration in 5 seconds..." >&2
    sleep 5
done

exec "$@"

