<?php

declare(strict_types=1);

use DataOps\Security;

$pageTitle = 'Operations overview';
$successfulRuns = (int) ($metrics['successful_runs'] ?? 0);
$failedRuns = (int) ($metrics['failed_runs'] ?? 0);
$totalFinished = $successfulRuns + $failedRuns;
$successRate = $totalFinished > 0 ? (int) round(($successfulRuns / $totalFinished) * 100) : 100;
?>
<section class="hero-row">
    <div>
        <div class="eyebrow">Operations overview</div>
        <h1>Backup control center</h1>
        <p>Monitor scheduled data protection, review transfer integrity, and keep recovery evidence close at hand.</p>
    </div>
    <div class="time-card">
        <span>Environment</span>
        <strong>Local Docker cluster</strong>
        <small>UTC · <?= e(gmdate('M j, Y H:i')) ?></small>
    </div>
</section>

<?php if (is_array($flash)): ?>
    <div class="alert <?= e($flash['type'] ?? 'success') ?>" role="status">
        <?= e($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<section class="metric-grid" aria-label="Backup metrics">
    <article class="metric-card accent">
        <span>Protected data</span>
        <strong><?= e(formatBytes($metrics['protected_bytes'] ?? 0)) ?></strong>
        <small>Across completed snapshots</small>
    </article>
    <article class="metric-card">
        <span>Successful runs</span>
        <strong><?= $successfulRuns ?></strong>
        <small><?= $successRate ?>% completion rate</small>
    </article>
    <article class="metric-card">
        <span>Active operations</span>
        <strong><?= (int) ($metrics['active_runs'] ?? 0) ?></strong>
        <small>Queued or processing</small>
    </article>
    <article class="metric-card">
        <span>Recovery points</span>
        <strong><?= $successfulRuns ?></strong>
        <small>Retention enforced per job</small>
    </article>
</section>

<section class="content-grid">
    <div class="main-column">
        <div class="section-heading">
            <div><span class="eyebrow">Protection plans</span><h2>Backup jobs</h2></div>
            <span class="count-pill"><?= count($jobs) ?> configured</span>
        </div>

        <div class="job-list">
            <?php foreach ($jobs as $job): ?>
                <article class="job-card">
                    <div class="job-icon" aria-hidden="true">↗</div>
                    <div class="job-detail">
                        <div class="job-title-row">
                            <div>
                                <h3><?= e($job['name']) ?></h3>
                                <p><?= e($job['description']) ?></p>
                            </div>
                            <span class="status <?= asBool($job['enabled']) ? 'enabled' : 'paused' ?>">
                                <i></i><?= asBool($job['enabled']) ? 'Enabled' : 'Paused' ?>
                            </span>
                        </div>
                        <div class="job-paths">
                            <span><small>Source</small><code><?= e($job['source_path']) ?></code></span>
                            <b aria-hidden="true">→</b>
                            <span><small>Snapshot store</small><code><?= e($job['target_path']) ?></code></span>
                        </div>
                        <div class="job-meta">
                            <span>Every <?= (int) $job['schedule_minutes'] ?> min</span>
                            <span><?= (int) $job['retention_count'] ?> snapshots retained</span>
                            <span>Last run: <?= e(formatDate($job['last_run_at'])) ?></span>
                        </div>
                    </div>
                    <div class="job-actions">
                        <form action="/jobs/<?= e($job['id']) ?>/run" method="post">
                            <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
                            <button class="primary-button" type="submit" <?= !asBool($job['enabled']) ? 'disabled' : '' ?>>Run now <span>→</span></button>
                        </form>
                        <form action="/jobs/<?= e($job['id']) ?>/toggle" method="post" data-confirm="<?= asBool($job['enabled']) ? 'Pause this automatic backup schedule?' : 'Enable this automatic backup schedule?' ?>">
                            <input type="hidden" name="_token" value="<?= e(Security::csrfToken()) ?>">
                            <input type="hidden" name="enabled" value="<?= asBool($job['enabled']) ? '0' : '1' ?>">
                            <button class="secondary-button" type="submit"><?= asBool($job['enabled']) ? 'Pause schedule' : 'Enable schedule' ?></button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="section-heading spaced">
            <div><span class="eyebrow">Execution history</span><h2>Recent runs</h2></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Run</th><th>Job</th><th>Status</th><th>Started</th><th>Files</th><th>Size</th></tr></thead>
                <tbody>
                <?php if (count($runs) === 0): ?>
                    <tr><td colspan="6" class="empty-state">No runs yet. Start the first backup above.</td></tr>
                <?php else: ?>
                    <?php foreach ($runs as $run): ?>
                        <tr>
                            <td><code><?= e(substr((string) $run['id'], 0, 8)) ?></code><small><?= e($run['trigger_type']) ?></small></td>
                            <td><?= e($run['job_name']) ?></td>
                            <td><span class="run-status <?= e($run['status']) ?>"><i></i><?= e(ucfirst((string) $run['status'])) ?></span></td>
                            <td><?= e(formatDate($run['started_at'] ?? $run['created_at'])) ?></td>
                            <td><?= number_format((int) $run['file_count']) ?></td>
                            <td><?= e(formatBytes($run['bytes_transferred'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <aside class="side-column">
        <section class="side-card health-card">
            <div class="section-heading"><div><span class="eyebrow">Readiness</span><h2>Recovery posture</h2></div><span class="score">A</span></div>
            <div class="health-ring"><div><strong><?= $successRate ?>%</strong><span>run reliability</span></div></div>
            <ul class="check-list">
                <li><i>✓</i><span><strong>Incremental snapshots</strong>Hard-link strategy configured</span></li>
                <li><i>✓</i><span><strong>Integrity evidence</strong>SHA-256 manifest per run</span></li>
                <li><i>✓</i><span><strong>Retention control</strong>Old recovery points rotated</span></li>
            </ul>
            <span class="inline-link">Recovery runbook included <span>✓</span></span>
        </section>

        <section class="side-card pipeline-card">
            <div class="section-heading"><div><span class="eyebrow">Data workflow</span><h2>Export normalization</h2></div></div>
            <?php if (is_array($pipelineRun)): ?>
                <div class="pipeline-flow">
                    <span><small>Input</small>CSV export</span><b>→</b><span><small>Published</small>Clean dataset</span>
                </div>
                <div class="pipeline-result">
                    <span class="run-status <?= e($pipelineRun['status']) ?>"><i></i><?= e(ucfirst((string) $pipelineRun['status'])) ?></span>
                    <strong><?= number_format((int) $pipelineRun['records_out']) ?> records</strong>
                </div>
                <dl class="pipeline-meta">
                    <div><dt>Completed</dt><dd><?= e(formatDate($pipelineRun['completed_at'] ?? $pipelineRun['started_at'])) ?></dd></div>
                    <div><dt>Output hash</dt><dd><code><?= e(substr((string) ($pipelineRun['output_sha256'] ?? 'pending'), 0, 12)) ?></code></dd></div>
                </dl>
            <?php else: ?>
                <p class="muted">The processor is preparing its first normalized dataset.</p>
            <?php endif; ?>
        </section>

        <section class="side-card">
            <div class="section-heading"><div><span class="eyebrow">Traceability</span><h2>Audit activity</h2></div></div>
            <div class="timeline">
                <?php if (count($logs) === 0): ?>
                    <p class="muted">Activity appears here after an operator action.</p>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="timeline-item">
                            <i></i>
                            <div><strong><?= e(str_replace('.', ' · ', (string) $log['action'])) ?></strong><span><?= e($log['email'] ?? 'system') ?></span><small><?= e(formatDate($log['created_at'])) ?></small></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </aside>
</section>
