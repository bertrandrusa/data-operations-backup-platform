CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS schema_migrations (
    version varchar(100) PRIMARY KEY,
    applied_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS users (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    email varchar(254) NOT NULL UNIQUE,
    password_hash varchar(255) NOT NULL,
    role varchar(30) NOT NULL DEFAULT 'operator' CHECK (role IN ('admin', 'operator', 'viewer')),
    active boolean NOT NULL DEFAULT true,
    created_at timestamptz NOT NULL DEFAULT now(),
    last_login_at timestamptz
);

CREATE TABLE IF NOT EXISTS backup_jobs (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    name varchar(100) NOT NULL,
    description text NOT NULL DEFAULT '',
    source_path text NOT NULL,
    target_path text NOT NULL,
    schedule_minutes integer NOT NULL DEFAULT 60 CHECK (schedule_minutes BETWEEN 5 AND 10080),
    retention_count integer NOT NULL DEFAULT 7 CHECK (retention_count BETWEEN 1 AND 365),
    enabled boolean NOT NULL DEFAULT true,
    last_run_at timestamptz,
    next_run_at timestamptz NOT NULL DEFAULT now(),
    created_by uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS backup_runs (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    job_id uuid NOT NULL REFERENCES backup_jobs(id) ON DELETE CASCADE,
    status varchar(20) NOT NULL DEFAULT 'queued' CHECK (status IN ('queued', 'running', 'succeeded', 'failed')),
    trigger_type varchar(20) NOT NULL DEFAULT 'manual' CHECK (trigger_type IN ('manual', 'scheduled', 'cli')),
    snapshot_name varchar(80),
    started_at timestamptz,
    completed_at timestamptz,
    bytes_transferred bigint NOT NULL DEFAULT 0 CHECK (bytes_transferred >= 0),
    file_count integer NOT NULL DEFAULT 0 CHECK (file_count >= 0),
    manifest_sha256 char(64),
    message text NOT NULL DEFAULT '',
    requested_by uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id bigserial PRIMARY KEY,
    user_id uuid REFERENCES users(id) ON DELETE SET NULL,
    action varchar(80) NOT NULL,
    resource_type varchar(50) NOT NULL,
    resource_id varchar(100),
    details jsonb NOT NULL DEFAULT '{}'::jsonb,
    ip_address inet,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id bigserial PRIMARY KEY,
    email varchar(254) NOT NULL,
    ip_address inet NOT NULL,
    succeeded boolean NOT NULL DEFAULT false,
    attempted_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS backup_runs_job_created_idx ON backup_runs (job_id, created_at DESC);
CREATE INDEX IF NOT EXISTS backup_runs_queue_idx ON backup_runs (created_at) WHERE status = 'queued';
CREATE UNIQUE INDEX IF NOT EXISTS backup_runs_one_active_idx ON backup_runs (job_id) WHERE status IN ('queued', 'running');
CREATE INDEX IF NOT EXISTS backup_jobs_due_idx ON backup_jobs (next_run_at) WHERE enabled = true;
CREATE INDEX IF NOT EXISTS audit_logs_created_idx ON audit_logs (created_at DESC);
CREATE INDEX IF NOT EXISTS login_attempts_lookup_idx ON login_attempts (email, ip_address, attempted_at DESC);

CREATE OR REPLACE FUNCTION reject_audit_mutation()
RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    RAISE EXCEPTION 'audit log records are append-only';
END;
$$;

DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
CREATE TRIGGER audit_logs_append_only
BEFORE UPDATE OR DELETE ON audit_logs
FOR EACH ROW EXECUTE FUNCTION reject_audit_mutation();

INSERT INTO backup_jobs (
    id, name, description, source_path, target_path,
    schedule_minutes, retention_count, enabled, next_run_at
)
VALUES (
    '11111111-1111-4111-8111-111111111111',
    'Operations data',
    'Incremental snapshot of reports and exported datasets',
    '/data/source',
    '/data/backups',
    60,
    7,
    true,
    now() + interval '1 hour'
)
ON CONFLICT (id) DO NOTHING;
