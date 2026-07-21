#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/common.sh"

job_id="${1:-}"
snapshot_name="${2:-}"
restore_to="${3:-}"
restore_root="${RESTORE_ROOT:-/data/restored}"

if ! is_uuid "$job_id" || [[ ! "$snapshot_name" =~ ^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{6}Z-[0-9a-f]{8}$ ]] || [[ -z "$restore_to" ]]; then
    echo "Usage: restore.sh JOB_UUID SNAPSHOT_NAME /data/restored/DESTINATION" >&2
    exit 64
fi

target_path="$(db -At -v job_id="$job_id" -c "SELECT target_path FROM backup_jobs WHERE id = :'job_id'::uuid")"
snapshot="$(realpath -m -- "$target_path/$job_id/$snapshot_name")"
restore_to="$(realpath -m -- "$restore_to")"

if ! path_is_inside "$snapshot" "$TARGET_ROOT" || [[ ! -d "$snapshot" ]]; then
    echo "Snapshot was not found inside the target root" >&2
    exit 1
fi
if ! path_is_inside "$restore_to" "$restore_root" || [[ "$restore_to" == "$(realpath -m -- "$restore_root")" ]]; then
    echo "Restore destination must be a child of $restore_root" >&2
    exit 1
fi
if [[ -d "$restore_to" && -n "$(find "$restore_to" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
    echo "Restore destination must be empty" >&2
    exit 1
fi

/opt/dataops/verify.sh "$job_id" "$snapshot_name"
mkdir -p -- "$restore_to"
rsync -a --numeric-ids -- "$snapshot/" "$restore_to/"

db -v job_id="$job_id" -v snapshot_name="$snapshot_name" -v restore_to="$restore_to" <<'SQL'
INSERT INTO audit_logs (action, resource_type, resource_id, details)
VALUES (
    'restore.completed', 'backup_job', :'job_id',
    jsonb_build_object('snapshot', :'snapshot_name', 'destination', :'restore_to', 'source', 'cli')
);
SQL
echo "Restored $snapshot_name to $restore_to"
