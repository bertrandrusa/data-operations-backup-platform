#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/common.sh"

interval="${WORKER_INTERVAL_SECONDS:-5}"
[[ "$interval" =~ ^[0-9]+$ ]] || { echo "WORKER_INTERVAL_SECONDS must be numeric" >&2; exit 64; }

wait_for_database
echo "Backup worker online; polling every ${interval}s"

while true; do
    claim="$(db -At -F $'\t' <<'SQL'
WITH next_run AS (
    SELECT r.id
    FROM backup_runs r
    WHERE r.status = 'queued'
    ORDER BY r.created_at
    FOR UPDATE SKIP LOCKED
    LIMIT 1
), claimed AS (
    UPDATE backup_runs r
    SET status = 'running', started_at = now(), message = 'Worker claimed run'
    FROM next_run n
    WHERE r.id = n.id
    RETURNING r.id, r.job_id
)
SELECT c.id, c.job_id, j.source_path, j.target_path, j.retention_count
FROM claimed c JOIN backup_jobs j ON j.id = c.job_id;
SQL
)"

    if [[ -n "$claim" ]]; then
        IFS=$'\t' read -r run_id job_id source_path target_path retention_count <<< "$claim"
        /opt/dataops/run-backup.sh "$run_id" "$job_id" "$source_path" "$target_path" "$retention_count" || true
    else
        sleep "$interval"
    fi
done
