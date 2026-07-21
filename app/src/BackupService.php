<?php

declare(strict_types=1);

namespace DataOps;

use PDO;

final class BackupService
{
    public function __construct(
        private readonly PDO $db,
        private readonly Audit $audit
    ) {
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $jobs = $this->db->query(
            "SELECT j.*,
                    (SELECT status FROM backup_runs r WHERE r.job_id = j.id ORDER BY r.created_at DESC LIMIT 1) AS last_status
             FROM backup_jobs j ORDER BY j.created_at"
        )->fetchAll();
        $runs = $this->db->query(
            "SELECT r.*, j.name AS job_name
             FROM backup_runs r JOIN backup_jobs j ON j.id = r.job_id
             ORDER BY r.created_at DESC LIMIT 12"
        )->fetchAll();
        $logs = $this->db->query(
            "SELECT a.*, u.email
             FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.created_at DESC LIMIT 8"
        )->fetchAll();
        $metrics = $this->db->query(
            "SELECT
                count(*) FILTER (WHERE status = 'succeeded') AS successful_runs,
                count(*) FILTER (WHERE status = 'failed') AS failed_runs,
                count(*) FILTER (WHERE status IN ('queued', 'running')) AS active_runs,
                coalesce(sum(bytes_transferred) FILTER (WHERE status = 'succeeded'), 0) AS protected_bytes
             FROM backup_runs"
        )->fetch();
        $pipelineRun = $this->db->query(
            "SELECT * FROM pipeline_runs ORDER BY started_at DESC LIMIT 1"
        )->fetch();

        return [
            'jobs' => $jobs,
            'runs' => $runs,
            'logs' => $logs,
            'metrics' => is_array($metrics) ? $metrics : [],
            'pipelineRun' => is_array($pipelineRun) ? $pipelineRun : null,
        ];
    }

    public function queue(string $jobId, string $userId): string
    {
        if (!Security::validUuid($jobId)) {
            throw new \InvalidArgumentException('Invalid job identifier.');
        }

        $this->db->beginTransaction();
        try {
            $job = $this->db->prepare('SELECT id, enabled FROM backup_jobs WHERE id = :id FOR UPDATE');
            $job->execute(['id' => $jobId]);
            $row = $job->fetch();
            if (!is_array($row)) {
                throw new \RuntimeException('Backup job not found.');
            }
            if (!\asBool($row['enabled'])) {
                throw new \RuntimeException('Enable the job before starting a backup.');
            }

            $existing = $this->db->prepare(
                "SELECT id FROM backup_runs WHERE job_id = :job_id AND status IN ('queued', 'running') LIMIT 1"
            );
            $existing->execute(['job_id' => $jobId]);
            if ($existing->fetchColumn() !== false) {
                throw new \RuntimeException('This job already has an active run.');
            }

            $run = $this->db->prepare(
                "INSERT INTO backup_runs (job_id, status, trigger_type, requested_by)
                 VALUES (:job_id, 'queued', 'manual', :user_id) RETURNING id"
            );
            $run->execute(['job_id' => $jobId, 'user_id' => $userId]);
            $runId = (string) $run->fetchColumn();
            $this->audit->record($userId, 'backup.queued', 'backup_run', $runId, ['job_id' => $jobId]);
            $this->db->commit();

            return $runId;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function setEnabled(string $jobId, bool $enabled, string $userId): void
    {
        if (!Security::validUuid($jobId)) {
            throw new \InvalidArgumentException('Invalid job identifier.');
        }

        $statement = $this->db->prepare(
            'UPDATE backup_jobs SET enabled = :enabled, updated_at = now() WHERE id = :id'
        );
        $statement->bindValue('enabled', $enabled, PDO::PARAM_BOOL);
        $statement->bindValue('id', $jobId);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Backup job not found.');
        }
        $this->audit->record(
            $userId,
            $enabled ? 'job.enabled' : 'job.disabled',
            'backup_job',
            $jobId
        );
    }
}
