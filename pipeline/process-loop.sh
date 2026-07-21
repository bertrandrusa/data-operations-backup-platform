#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source /opt/dataops/common.sh

interval="${PROCESSOR_INTERVAL_SECONDS:-300}"
[[ "$interval" =~ ^[0-9]+$ ]] || { echo "PROCESSOR_INTERVAL_SECONDS must be numeric" >&2; exit 64; }

wait_for_database
echo "Data processor online; running every ${interval}s"

while true; do
    /opt/pipeline/process-data.sh || true
    sleep "$interval"
done
