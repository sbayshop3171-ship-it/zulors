# Zulors Deployment

This project is deployed from the `main` branch.

## Local Commit And Live Deploy

Run this from the project root:

```bash
bash scripts/commit-and-push.sh "Your commit message"
```

The script:

- stages all safe project changes;
- blocks secrets and runtime database files from being committed;
- commits and pushes to GitHub;
- syncs the source to the live server;
- runs Composer, npm build, migrations, Laravel cache rebuild, and smoke checks.

To push without deploying:

```bash
SKIP_DEPLOY=1 bash scripts/commit-and-push.sh "Your commit message"
```

## GitHub Actions

The `Zulors CI` workflow runs on every push to `main`. It installs PHP and Node dependencies, builds assets, runs migrations on SQLite, and checks that Laravel boots.

The deploy job is ready but only runs when these repository secrets are configured:

- `LIVE_HOST`
- `LIVE_USER`
- `LIVE_PORT`
- `LIVE_PATH`
- `LIVE_SSH_PRIVATE_KEY`

Do not commit server passwords, `.env`, database dumps, SQLite files, private keys, or uploaded user media.
