#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source "$(dirname "$0")/common.sh"

job_id="${1:-}"
snapshot_name="${2:-}"
if ! is_uuid "$job_id" || [[ ! "$snapshot_name" =~ ^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{6}Z-[0-9a-f]{8}$ ]]; then
    echo "Usage: verify.sh JOB_UUID SNAPSHOT_NAME" >&2
    exit 64
fi

target_path="$(db -At -v job_id="$job_id" -c "SELECT target_path FROM backup_jobs WHERE id = :'job_id'::uuid")"
snapshot="$(realpath -m -- "$target_path/$job_id/$snapshot_name")"
if ! path_is_inside "$snapshot" "$TARGET_ROOT" || [[ ! -d "$snapshot" ]]; then
    echo "Snapshot was not found inside the target root" >&2
    exit 1
fi

expected="$(db -At -v job_id="$job_id" -v snapshot_name="$snapshot_name" -c "SELECT manifest_sha256 FROM backup_runs WHERE job_id = :'job_id'::uuid AND snapshot_name = :'snapshot_name' AND status = 'succeeded' ORDER BY completed_at DESC LIMIT 1")"
actual="$(manifest_hash "$snapshot")"

if [[ -z "$expected" || "$actual" != "$expected" ]]; then
    echo "Verification failed: manifest mismatch" >&2
    exit 1
fi
echo "Verified $snapshot_name ($actual)"
