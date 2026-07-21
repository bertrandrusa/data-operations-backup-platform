#!/usr/bin/env bash
set -Eeuo pipefail
# shellcheck disable=SC1091
source /opt/dataops/common.sh

input_root="${PIPELINE_INPUT_ROOT:-/data/input}"
output_root="${PIPELINE_OUTPUT_ROOT:-/data/source}"
input_csv="$input_root/exports/customers.csv"
staging="$output_root/.staging-$$"
run_id=""

fail_pipeline() {
    local exit_code=$?
    trap - ERR
    set +e
    rm -rf -- "$staging"
    if [[ -n "$run_id" ]]; then
        db -v run_id="$run_id" <<'SQL'
UPDATE pipeline_runs
SET status = 'failed', completed_at = now(), message = 'Processing failed; inspect processor logs'
WHERE id = :'run_id'::uuid;
INSERT INTO audit_logs (action, resource_type, resource_id, details)
VALUES ('pipeline.failed', 'pipeline_run', :'run_id', jsonb_build_object('pipeline', 'customer-export-normalization'));
SQL
    fi
    echo "Data pipeline failed with exit code $exit_code" >&2
    exit "$exit_code"
}
trap fail_pipeline ERR

[[ -f "$input_csv" ]] || { echo "Missing input: $input_csv" >&2; false; }
mkdir -p -- "$staging/exports" "$staging/reports"

run_id="$(db -At <<'SQL'
INSERT INTO pipeline_runs (name, status, message)
VALUES ('customer-export-normalization', 'running', 'Processing source exports')
RETURNING id;
SQL
)"

awk -F',' 'BEGIN { OFS="," }
    NR == 1 { print "customer_id","status","region"; next }
    NF >= 3 {
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", $1)
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", $2)
        gsub(/^[[:space:]]+|[[:space:]]+$/, "", $3)
        print $1, tolower($2), tolower($3)
    }
' "$input_csv" > "$staging/exports/customers-normalized.csv"

{
    echo "status,count"
    tail -n +2 "$staging/exports/customers-normalized.csv" | cut -d',' -f2 | sort | uniq -c | awk '{ print $2 "," $1 }'
} > "$staging/reports/customer-status-summary.csv"

rsync -a --exclude='.*' -- "$input_root/reports/" "$staging/reports/"

records_in="$(tail -n +2 "$input_csv" | sed '/^[[:space:]]*$/d' | wc -l | tr -d ' ')"
records_out="$(tail -n +2 "$staging/exports/customers-normalized.csv" | sed '/^[[:space:]]*$/d' | wc -l | tr -d ' ')"
output_sha256="$(manifest_hash "$staging")"

rm -rf -- "$output_root/previous"
if [[ -d "$output_root/current" ]]; then
    mv -- "$output_root/current" "$output_root/previous"
fi
mv -- "$staging" "$output_root/current"
touch "$output_root/.ready"

db -v run_id="$run_id" -v records_in="$records_in" -v records_out="$records_out" -v output_sha256="$output_sha256" <<'SQL'
BEGIN;
UPDATE pipeline_runs
SET status = 'succeeded', records_in = :'records_in'::integer, records_out = :'records_out'::integer,
    output_path = '/data/source/current', output_sha256 = :'output_sha256',
    message = 'Normalized export and summary published', completed_at = now()
WHERE id = :'run_id'::uuid;
INSERT INTO audit_logs (action, resource_type, resource_id, details)
VALUES (
    'pipeline.succeeded', 'pipeline_run', :'run_id',
    jsonb_build_object('pipeline', 'customer-export-normalization', 'records', :'records_out'::integer)
);
COMMIT;
SQL

trap - ERR
echo "Pipeline $run_id completed: $records_out records published"
