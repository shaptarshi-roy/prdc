#!/bin/sh
# Daily batch-file purge, run by supervisord as the `app` user.
#
# Replaces cron, which needs root and the container runs non-root. As a
# supervisord child this inherits the container's env (DATABASE_*,
# BATCH_*, ...), so nothing has to be restored.
#
# Sleeps until PURGE_AT (container time, UTC), runs the purge, repeats.
# 03:17 — off-peak, avoids the on-the-hour herd. A failed purge is logged
# and retried the next day; the loop itself keeps running.

set -u

PURGE_AT="${PURGE_AT:-03:17}"
RETENTION="${BATCH_RETENTION_DAYS:-30}"

cd /app

while :; do
    now=$(date +%s)
    next=$(date -d "today $PURGE_AT" +%s)
    [ "$next" -le "$now" ] && next=$(date -d "tomorrow $PURGE_AT" +%s)

    echo "purge: next run at $(date -d "@$next" '+%Y-%m-%d %H:%M %Z')"
    sleep $((next - now))

    if php bin/console app:purge-batch-files --retention-days="$RETENTION"; then
        echo "purge: done"
    else
        echo "purge: FAILED (exit $?), retrying at next scheduled run" >&2
    fi

    # Never fire twice in the same minute if the purge finished fast.
    sleep 60
done
