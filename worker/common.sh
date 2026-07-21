#!/usr/bin/env bash
set -Eeuo pipefail

export PGHOST="${PGHOST:-database}"
export PGPORT="${PGPORT:-5432}"
export PGDATABASE="${POSTGRES_DB:-dataops}"
export PGUSER="${POSTGRES_USER:-dataops}"
export PGPASSWORD="${POSTGRES_PASSWORD:?POSTGRES_PASSWORD is required}"

# Exported for scripts that source this shared file.
# shellcheck disable=SC2034
SOURCE_ROOT="${BACKUP_SOURCE_ROOT:-/data/source}"
# shellcheck disable=SC2034
TARGET_ROOT="${BACKUP_TARGET_ROOT:-/data/backups}"

db() {
    psql -X -q -v ON_ERROR_STOP=1 "$@"
}

is_uuid() {
    [[ "$1" =~ ^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$ ]]
}

path_is_inside() {
    local candidate root
    candidate="$(realpath -m -- "$1")"
    root="$(realpath -m -- "$2")"
    [[ "$candidate" == "$root" || "$candidate" == "$root/"* ]]
}

wait_for_database() {
    local attempt=0
    until pg_isready -q; do
        attempt=$((attempt + 1))
        if (( attempt >= 30 )); then
            echo "Database did not become ready" >&2
            return 1
        fi
        sleep 2
    done
}

manifest_hash() {
    local directory="$1"
    (
        cd "$directory"
        find . -type f -print0 | sort -z | xargs -0 -r sha256sum
        while IFS= read -r -d '' link; do
            printf 'SYMLINK %s -> %s\n' "$link" "$(readlink -- "$link")"
        done < <(find . -type l -print0 | sort -z)
    ) | sha256sum | awk '{print $1}'
}
