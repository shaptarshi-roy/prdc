#!/bin/sh
# Cron runs with a minimal environment that doesn't include the env
# vars Compose set on the container. This wrapper restores them by
# reading from PID 1's /proc/.../environ, then runs the purge command.

set -eu

while IFS= read -r -d '' kv; do
    case "$kv" in
        BATCH_*=*|DATABASE_*=*|APP_*=*|MESSENGER_*=*)
            export "$kv"
            ;;
    esac
done < /proc/1/environ

cd /app
RETENTION="${BATCH_RETENTION_DAYS:-30}"
exec php bin/console app:purge-batch-files --retention-days="$RETENTION"
