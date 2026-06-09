# Production Deployment

Production images are built by GitHub Actions and published to GitHub Container Registry.
The production host pulls the published image and recreates the runtime containers.

## Build

Create and push a release tag:

```bash
git tag v0.1.0
git push origin v0.1.0
```

The `Release Image` workflow publishes:

- `ghcr.io/541968679/acg-faka:<version>`
- `ghcr.io/541968679/acg-faka:<major>.<minor>`
- `ghcr.io/541968679/acg-faka:latest`
- `ghcr.io/541968679/acg-faka:sha-<commit>`

The workflow can also be run manually from GitHub Actions.

## Deploy

On the production host:

```bash
cd /opt/acgfaka/repo
git fetch origin main
git checkout main
git pull --ff-only origin main
nohup bash deploy/update.sh &
```

If the GHCR package is private, log in once before deploying:

```bash
echo "$GHCR_TOKEN" | docker login ghcr.io -u 541968679 --password-stdin
```

By default the host deploys `ghcr.io/541968679/acg-faka:latest`.
To pin a version, set `ACGFAKA_IMAGE` in `/opt/acgfaka/.env.prod` or the legacy `/opt/acgfaka.env.prod`, for example:

```dotenv
ACGFAKA_IMAGE=ghcr.io/541968679/acg-faka:0.1.0
```

If the env file lives somewhere else, run with `ENV_FILE=/path/to/.env.prod`.

## Rollback

The deploy script tags the previously pulled image as `acgfaka-custom:prev`.
To roll back:

```bash
bash /opt/acgfaka/repo/deploy/update.sh rollback
```
