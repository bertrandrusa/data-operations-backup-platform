#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/common.sh"

job_id="${1:-}"
trigger_type="${2:-cli}"

if ! is_uuid "$job_id"; then
    echo "Usage: queue-backup.sh JOB_UUID [cli|manual|scheduled]" >&2
    exit 64
fi
if [[ ! "$trigger_type" =~ ^(cli|manual|scheduled)$ ]]; then
    echo "Invalid trigger type" >&2
    exit 64
fi

run_id="$(db -At -v job_id="$job_id" -v trigger_type="$trigger_type" <<'SQL'
INSERT INTO backup_runs (job_id, status, trigger_type)
SELECT :'job_id'::uuid, 'queued', :'trigger_type'
WHERE EXISTS (SELECT 1 FROM backup_jobs WHERE id = :'job_id'::uuid AND enabled = true)
  AND NOT EXISTS (
      SELECT 1 FROM backup_runs
      WHERE job_id = :'job_id'::uuid AND status IN ('queued', 'running')
  )
RETURNING id;
SQL
)"

if [[ -z "$run_id" ]]; then
    echo "Job does not exist, is disabled, or already has an active run" >&2
    exit 1
fi

db -v run_id="$run_id" -v job_id="$job_id" <<'SQL'
INSERT INTO audit_logs (action, resource_type, resource_id, details)
VALUES ('backup.queued', 'backup_run', :'run_id', jsonb_build_object('job_id', :'job_id', 'source', 'cli'));
SQL

echo "$run_id"
