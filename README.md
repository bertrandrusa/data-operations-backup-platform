<h1 align="center">Data Operations & Backup Platform</h1>

<p align="center">
  <strong>Backups you can schedule, see, verify, and restore.</strong>
</p>

<p align="center">
  <img alt="Docker" src="https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&amp;logoColor=white">
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&amp;logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&amp;logoColor=white">
  <img alt="rsync" src="https://img.shields.io/badge/Backup-rsync-23865F">
  <img alt="License" src="https://img.shields.io/badge/License-MIT-1F704A">
</p>

![Data operations dashboard](docs/images/dashboard-preview.svg)

A containerized platform for running data-processing jobs and managing incremental backups from one authenticated dashboard. PostgreSQL tracks every job, run, recovery point, and audit event while a separate worker handles filesystem access.

## What it does

- schedules and queues backup jobs;
- creates incremental `rsync` snapshots;
- verifies recovery points with SHA-256 manifests;
- restores snapshots into a protected recovery area;
- processes and normalizes CSV data on a schedule;
- records run history and append-only audit events.

## The stack

| Service | Role |
|---|---|
| **PHP + Apache** | Authenticated operations dashboard |
| **PostgreSQL** | Jobs, runs, users, schedules, and audit state |
| **Scheduler** | Queues jobs when they become due |
| **Processor** | Normalizes source data and publishes outputs atomically |
| **Backup worker** | Claims jobs, runs `rsync`, verifies, and restores |

The dashboard never runs `rsync` directly. It queues work in PostgreSQL, and the worker claims one run atomically before touching the filesystem.

## Quick start

```bash
git clone https://github.com/bertrandrusa/data-operations-backup-platform.git
cd data-operations-backup-platform
cp .env.example .env
```

On Windows PowerShell, use `Copy-Item .env.example .env`.

Set `POSTGRES_PASSWORD`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in `.env`, then start everything:

```bash
docker compose up --build -d
docker compose ps
```

Open [http://localhost:8080](http://localhost:8080), sign in, and select **Run now** to queue the sample backup job.

## Backup and recovery

Queue a backup and follow the worker:

```bash
make backup
docker compose logs -f worker
```

Verify a recovery point:

```bash
docker compose exec worker /opt/dataops/verify.sh \
  11111111-1111-4111-8111-111111111111 \
  <snapshot-id>
```

Restore it into an isolated directory:

```bash
make restore \
  SNAPSHOT=<snapshot-id> \
  RESTORE_TO=/data/restored/recovery-drill
```

The restore workflow verifies the manifest first and refuses destinations outside `/data/restored` or directories that already contain files.

## Incremental snapshot design

```text
/data/backups/<job-id>/
├── 2026-07-21T120000Z-acde1234/
├── 2026-07-21T130000Z-bcde2345/
└── latest -> 2026-07-21T130000Z-bcde2345
```

Unchanged files are hard-linked from the previous snapshot with `rsync --link-dest`. Each recovery point looks complete without storing duplicate blocks. Partial runs stay hidden and are published only after transfer and hashing succeed.

## Safety built in

- source data is mounted read-only;
- backup and restore paths are restricted to configured roots;
- PostgreSQL is not exposed to the host network;
- containers use `no-new-privileges`;
- the worker runs as an unprivileged user;
- secrets and generated backups remain outside Git.

## Test it

```bash
make test
docker compose config --quiet
docker compose build
```

## Explore

- [Architecture and trust boundaries](docs/ARCHITECTURE.md)
- [Operations and recovery runbook](docs/OPERATIONS.md)
- [Security guidance](SECURITY.md)
- [Repository contribution guide](CONTRIBUTING.md)

Built by **Bertrand Rusanganwa** as a data operations, infrastructure, and recovery engineering portfolio project.

Released under the [MIT License](LICENSE).
