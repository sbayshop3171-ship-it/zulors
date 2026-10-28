# Zulors Deployment

This project is deployed from the `main` branch.

## Commit And Push

Run this from the project root:

```bash
bash scripts/commit-and-push.sh "Your commit message"
```

The script:

- stages all safe project changes;
- blocks local environment files, Firebase config, signing keys, and runtime database files from being committed;
- commits and pushes to GitHub.

Pushing to `main` starts the `Zulors CI` workflow. After the build passes, its deploy job publishes that commit to the live server. The commit helper does not also deploy from the Mac, which avoids deploying the same commit twice.

To make a change without deploying it, push a feature branch and merge it to `main` after review.

## GitHub Actions

The `Zulors CI` workflow runs on every push to `main`. It installs PHP and Node dependencies, builds assets, runs migrations on SQLite, and checks that Laravel boots.

The deploy job uses these repository Actions secrets:

- `LIVE_HOST`
- `LIVE_USER`
- `LIVE_PORT`
- `LIVE_PATH`
- `LIVE_SSH_PRIVATE_KEY`

The SSH user must be allowed to write to `LIVE_PATH` and run the deployment commands. Use a dedicated SSH key: add its public key to that server account's `authorized_keys`, then save the private key as `LIVE_SSH_PRIVATE_KEY`. Do not use a server password in GitHub Actions or commit passwords, `.env`, database dumps, SQLite files, private keys, Firebase configuration, or uploaded user media.

`deploy/rsync-live.sh` remains available for an intentional manual deployment. Do not run it for a commit that GitHub Actions is already deploying.

This workflow deploys the Laravel/Vue website. Android Play releases are separate: a native app change needs a signed AAB build and Play Console upload as described in [the Android release guide](../android/LocalAndroidPreview/PLAY_RELEASE_GUIDE_BN.md). A website-only change served by the web app reaches the webview when that site deploys.

## Live Media Storage Lock

Uploaded user files must live outside disposable release folders. The live deploy script stores public uploads in:

```bash
/var/www/zulors/data/www/zulors.com.shared/storage/app/public
```

Every deploy links `storage/app/public` to that shared folder before the release is promoted. The deploy also refuses to continue if the shared media guard would reduce the number of existing user avatar/cover files.

Do not manually delete, replace, or rsync `storage/app/public` with `--delete`. User uploads, profile photos, cover photos, post images, and videos are runtime data, not source code.
