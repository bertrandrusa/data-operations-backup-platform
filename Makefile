SHELL := /bin/sh

.PHONY: setup up down logs ps test lint backup restore clean

setup:
	@test -f .env || cp .env.example .env
	@echo "Created .env. Review its credentials, then run: make up"

up:
	docker compose up --build -d
	@echo "Dashboard: http://localhost:8080"

down:
	docker compose down

logs:
	docker compose logs -f --tail=100

ps:
	docker compose ps

test: lint
	php tests/run.php

lint:
	find app tests -name '*.php' -type f -print0 | xargs -0 -n1 php -l
	@if command -v shellcheck >/dev/null 2>&1; then shellcheck worker/*.sh; else echo "shellcheck not installed; skipped"; fi

backup:
	docker compose exec worker /opt/dataops/queue-backup.sh 11111111-1111-4111-8111-111111111111 cli

restore:
	@test -n "$(SNAPSHOT)" || (echo "Usage: make restore SNAPSHOT=2026-01-01T120000Z RESTORE_TO=/tmp/recovered"; exit 1)
	docker compose exec worker /opt/dataops/restore.sh 11111111-1111-4111-8111-111111111111 "$(SNAPSHOT)" "$(or $(RESTORE_TO),/data/restored)"

clean:
	@echo "This removes containers only. Volumes are preserved."
	docker compose down

