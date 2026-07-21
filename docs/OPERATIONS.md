# Operations and recovery runbook

## Start and inspect

```bash
cp .env.example .env
# Edit all credentials in .env before first start.
docker compose up --build -d
docker compose ps
curl --fail http://localhost:8080/health
```

Expected health response:

```json
{"status":"ok","database":"connected"}
```

Use `docker compose logs --tail=100 web database processor scheduler worker` for initial triage.

## Inspect data processing

The processor reads `sample-data/exports/customers.csv`, normalizes status and region fields, creates a status-count report, and publishes the results beneath `/data/source/current` in its named volume. Its latest status, record count, timestamp, and digest appear on the dashboard.

```bash
docker compose logs --tail=50 processor
docker compose exec processor find /data/source/current -maxdepth 3 -type f -print
```

## Queue and observe a backup

```bash
docker compose exec worker /opt/dataops/queue-backup.sh \
  11111111-1111-4111-8111-111111111111 cli
docker compose logs -f worker
```

The command returns a run UUID. A normal run moves through `queued`, `running`, and `succeeded`. If it fails, the UI contains the result and the worker log contains the execution detail.

## Verify a recovery point

1. Copy the exact snapshot name from the dashboard or backup volume.
2. Run the verifier:

   ```bash
   docker compose exec worker /opt/dataops/verify.sh JOB_UUID SNAPSHOT_NAME
   ```

3. Record the result and timestamp in the recovery test evidence for your environment.

The verifier recalculates a digest from every regular file and compares it with the digest captured before the snapshot was published.

## Restore drill

Never restore over a live source as the first recovery step.

```bash
docker compose exec worker /opt/dataops/restore.sh \
  JOB_UUID SNAPSHOT_NAME /data/restored/drill-YYYY-MM-DD
```

The command performs manifest verification, confirms the destination is below `/data/restored`, rejects a non-empty destination, copies the snapshot, and appends a `restore.completed` audit event.

Validate recovered content from the host:

```bash
docker compose run --rm worker sh -c \
  'find /data/restored/drill-YYYY-MM-DD -type f -maxdepth 3 -print'
```

Application owners must perform their own semantic checks before a recovered dataset is promoted.

## Common failure modes

| Symptom | Likely cause | Response |
|---|---|---|
| Web service remains unhealthy | Migration or database credentials failed | Compare `.env` credentials; inspect `web` and `database` logs |
| Run remains queued | Worker is stopped or cannot reach PostgreSQL | Check `docker compose ps worker` and worker logs |
| Run fails immediately | Source missing or path is outside its allowlist | Confirm the source mount and job path |
| Manifest mismatch | Snapshot content changed after publication | Quarantine the snapshot; inspect storage and audit history |
| Permission denied in target | Volume ownership differs from worker UID 10001 | Correct the explicit target volume ownership; do not run the worker as root by default |
| Restore refuses destination | Destination is outside the recovery root or is not empty | Choose a new child directory beneath `/data/restored` |

## Retention and capacity

Retention is count-based per job. After a successful publish, snapshots beyond the newest configured count are deleted. Monitor actual block use with:

```bash
docker compose exec worker du -sh /data/backups
```

Hard-linked snapshots can appear larger than the blocks they consume. Use `du`, not a sum of apparent file sizes, for capacity planning.

## Shutdown and data removal

`docker compose down` stops containers and keeps volumes. `docker compose down --volumes` permanently removes PostgreSQL history, snapshots, and restore drills; use it only when the environment is intentionally being destroyed.

## External scheduling

The built-in scheduler is convenient for Compose. Examples for an external [cron entry](cron.example) and [systemd timer](systemd/) show how an operator can queue the same job from the host instead.
