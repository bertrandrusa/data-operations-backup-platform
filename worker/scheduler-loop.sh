#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/common.sh"

interval="${SCHEDULER_INTERVAL_SECONDS:-30}"
[[ "$interval" =~ ^[0-9]+$ ]] || { echo "SCHEDULER_INTERVAL_SECONDS must be numeric" >&2; exit 64; }

wait_for_database
echo "Scheduler online; polling every ${interval}s"

while true; do
    db <<'SQL'
WITH due AS (
    SELECT id, schedule_minutes
    FROM backup_jobs
    WHERE enabled = true AND next_run_at <= now()
    FOR UPDATE SKIP LOCKED
), queued AS (
    INSERT INTO backup_runs (job_id, status, trigger_type)
    SELECT d.id, 'queued', 'scheduled'
    FROM due d
    WHERE NOT EXISTS (
        SELECT 1 FROM backup_runs r
        WHERE r.job_id = d.id AND r.status IN ('queued', 'running')
    )
    RETURNING id, job_id
), audited AS (
    INSERT INTO audit_logs (action, resource_type, resource_id, details)
    SELECT 'backup.queued', 'backup_run', q.id::text,
           jsonb_build_object('job_id', q.job_id, 'source', 'scheduler')
    FROM queued q
)
UPDATE backup_jobs j
SET next_run_at = now() + make_interval(mins => d.schedule_minutes),
    updated_at = now()
FROM due d
WHERE j.id = d.id;
SQL
    sleep "$interval"
done
