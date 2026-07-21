CREATE TABLE IF NOT EXISTS pipeline_runs (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name varchar(100) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'running' CHECK (status IN ('running', 'succeeded', 'failed')),
    records_in integer NOT NULL DEFAULT 0 CHECK (records_in >= 0),
    records_out integer NOT NULL DEFAULT 0 CHECK (records_out >= 0),
    output_path text NOT NULL DEFAULT '',
    output_sha256 char(64),
    message text NOT NULL DEFAULT '',
    started_at timestamptz NOT NULL DEFAULT now(),
    completed_at timestamptz
);

CREATE INDEX IF NOT EXISTS pipeline_runs_started_idx ON pipeline_runs (started_at DESC);

UPDATE backup_jobs
SET source_path = '/data/source/current',
    description = 'Incremental snapshot of normalized reports and exported datasets',
    updated_at = now()
WHERE id = '11111111-1111-4111-8111-111111111111';

