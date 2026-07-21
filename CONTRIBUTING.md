# Contributing

Small, focused pull requests are welcome.

1. Create a branch from `main`.
2. Keep secrets and real backup data out of commits.
3. Run `make test` and `docker compose config --quiet`.
4. Explain behavior changes and recovery impact in the pull request.
5. Add or update tests for validation, state transitions, or safety controls.

Changes to destructive retention or restore behavior require an explicit safety review and a documented recovery test.

