#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/common.sh"

run_id="${1:-}"
job_id="${2:-}"
source_path="${3:-}"
target_path="${4:-}"
retention_count="${5:-}"

if ! is_uuid "$run_id" || ! is_uuid "$job_id" || [[ ! "$retention_count" =~ ^[0-9]+$ ]]; then
    echo "Invalid worker arguments" >&2
    exit 64
fi

fail_run() {
    local exit_code=$?
    trap - ERR
    set +e
    if [[ -n "${partial:-}" && -d "$partial" ]]; then
        rm -rf -- "$partial"
    fi
    db -v run_id="$run_id" -v job_id="$job_id" <<'SQL'
UPDATE backup_runs
SET status = 'failed', completed_at = now(), message = 'Snapshot failed; inspect worker logs'
WHERE id = :'run_id'::uuid;
INSERT INTO audit_logs (action, resource_type, resource_id, details)
VALUES ('backup.failed', 'backup_run', :'run_id', jsonb_build_object('job_id', :'job_id'));
SQL
    echo "Backup run $run_id failed with exit code $exit_code" >&2
    exit "$exit_code"
}
trap fail_run ERR

if ! path_is_inside "$source_path" "$SOURCE_ROOT" || ! path_is_inside "$target_path" "$TARGET_ROOT"; then
    echo "Job path escaped the configured safety boundary" >&2
    false
fi
if [[ ! -d "$source_path" ]]; then
    echo "Source directory does not exist: $source_path" >&2
    false
fi
if (( retention_count < 1 || retention_count > 365 )); then
    echo "Retention count is outside the accepted range" >&2
    false
fi

job_target="$(realpath -m -- "$target_path")/$job_id"
mkdir -p -- "$job_target"
snapshot_name="$(date -u +%Y-%m-%dT%H%M%SZ)-${run_id:0:8}"
partial="$job_target/.${snapshot_name}.partial"
snapshot="$job_target/$snapshot_name"
mkdir -p -- "$partial"

rsync_args=(-a --delete --numeric-ids --human-readable)
if [[ -L "$job_target/latest" ]]; then
    previous_name="$(readlink "$job_target/latest")"
    if [[ "$previous_name" != */* && -d "$job_target/$previous_name" ]]; then
        rsync_args+=(--link-dest="$job_target/$previous_name")
    fi
fi

echo "Starting snapshot $snapshot_name for job $job_id"
rsync "${rsync_args[@]}" -- "$source_path/" "$partial/"

file_count="$(find "$partial" -type f -print | wc -l | tr -d ' ')"
bytes_transferred="$(du -sb "$partial" | awk '{print $1}')"
manifest_sha256="$(manifest_hash "$partial")"

mv -- "$partial" "$snapshot"
ln -sfn -- "$snapshot_name" "$job_target/latest"

mapfile -t snapshots < <(find "$job_target" -mindepth 1 -maxdepth 1 -type d -name '20??-??-??T??????Z-*' -printf '%f\n' | sort -r)
if (( ${#snapshots[@]} > retention_count )); then
    for ((index=retention_count; index<${#snapshots[@]}; index++)); do
        expired="$job_target/${snapshots[$index]}"
        if path_is_inside "$expired" "$job_target"; then
            rm -rf -- "$expired"
        fi
    done
fi

db -v run_id="$run_id" -v job_id="$job_id" -v snapshot_name="$snapshot_name" \
   -v file_count="$file_count" -v bytes_transferred="$bytes_transferred" \
   -v manifest_sha256="$manifest_sha256" <<'SQL'
BEGIN;
UPDATE backup_runs
SET status = 'succeeded', completed_at = now(), snapshot_name = :'snapshot_name',
    file_count = :'file_count'::integer, bytes_transferred = :'bytes_transferred'::bigint,
    manifest_sha256 = :'manifest_sha256', message = 'Snapshot completed and manifest recorded'
WHERE id = :'run_id'::uuid;
UPDATE backup_jobs SET last_run_at = now(), updated_at = now() WHERE id = :'job_id'::uuid;
INSERT INTO audit_logs (action, resource_type, resource_id, details)
VALUES (
    'backup.succeeded', 'backup_run', :'run_id',
    jsonb_build_object('job_id', :'job_id', 'snapshot', :'snapshot_name', 'files', :'file_count'::integer)
);
COMMIT;
SQL

trap - ERR
echo "Backup run $run_id completed: $file_count files, $bytes_transferred bytes"
