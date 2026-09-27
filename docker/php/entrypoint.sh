#!/bin/sh
set -e

# Ensure Laravel writable paths exist. Do not chown or chmod the bind-mounted tree.
for path in \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
do
    mkdir -p "/var/www/${path}"
done

# Align PHP-FPM's www-data user with the project directory owner on the host mount.
# This only changes IDs inside the container; it does not alter ownership on the WSL filesystem.
USER_ID="${APP_USER_ID:-}"
GROUP_ID="${APP_GROUP_ID:-}"

if [ -z "$USER_ID" ] || [ -z "$GROUP_ID" ]; then
    USER_ID=$(stat -c '%u' /var/www)
    GROUP_ID=$(stat -c '%g' /var/www)
fi

if [ "$USER_ID" != "0" ] && [ "$GROUP_ID" != "0" ]; then
    groupmod -o -g "$GROUP_ID" www-data 2>/dev/null || true
    usermod -o -u "$USER_ID" -g "$GROUP_ID" www-data 2>/dev/null || true
fi

exec docker-php-entrypoint "$@"
