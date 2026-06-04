# GCP Hong Kong + Cloudflare Deployment

This project is deployed as PHP 8.2 + Apache + MySQL behind Caddy.

## Current production state

- Primary site: `https://shop.kaynstech.com`
- Admin login: `https://shop.kaynstech.com/admin`
- Redirect aliases:
  - `https://kaynstech.com` -> `https://shop.kaynstech.com`
  - `https://www.kaynstech.com` -> `https://shop.kaynstech.com`
- GCP project: `myvps-2606to2608`
- VM: `acgfaka-hk`
- Zone: `asia-east2-a`
- Static IP: `34.96.139.162`
- Remote base directory: `/opt/acgfaka`
- Remote Git repo: `/opt/acgfaka/repo`
- Remote production env file: `/opt/acgfaka/.env.prod`
- Remote deploy log: `/opt/acgfaka/deploy.log`
- Cloudflare zone: `kaynstech.com`
- Cloudflare SSL/TLS mode: `Full (strict)`
- Cloudflare proxy mode: DNS only for the current records

Cloudflare DNS records currently expected:

```text
A      shop    34.96.139.162    DNS only
A      @       34.96.139.162    DNS only
CNAME  www     kaynstech.com    DNS only
```

Keep DNS only unless there is a specific reason to enable the orange-cloud proxy.
If the proxy is enabled later, test payment callbacks and admin login carefully.

## Production env

Copy `.env.prod.example` to `.env.prod` and replace every `change_this_*` value.
The bootstrap script uploads it to `/opt/acgfaka/.env.prod` on the server so secrets
are not stored inside the web root.

Required values:

- `SITE_DOMAIN`: `shop.kaynstech.com, kaynstech.com, www.kaynstech.com`
- `PRIMARY_DOMAIN`: `shop.kaynstech.com`
- `SITE_URL`: `https://shop.kaynstech.com`
- `SITE_EMAIL`: certificate contact email for Caddy/Let's Encrypt
- `ADMIN_EMAIL`: initial admin login email
- `ADMIN_PASSWORD`: initial admin login password
- `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `ADMIN_SALT`: random secret strings
- `EPAY_APPID`, `EPAY_APPSECRET`: HuPiJiao/Epay credentials
- `ORDER_CLEANUP_INTERVAL`: expired-order cleanup loop interval in seconds

`ADMIN_*` values are only used when the MySQL `db_data` volume is first created.
Do not commit `.env.prod`, API tokens, payment app secrets, or admin passwords.

## GCP VM

This creates billable resources: a VM, a static IP, and firewall rules for HTTP
and HTTPS.

```bash
cd /path/to/acgfaka
PROJECT_ID=myvps-2606to2608 VM=acgfaka-hk ZONE=asia-east2-a bash scripts/create-gcp-hk-vm.sh
```

Use the printed static IP in Cloudflare DNS. The current production static IP is
`34.96.139.162`.

## Cloudflare setup

1. Add the domain `kaynstech.com` to Cloudflare.
2. Set the registrar nameservers to the Cloudflare nameservers.
3. Add the DNS records listed in the current production state.
4. Keep records as DNS only during first certificate issuance.
5. Set SSL/TLS mode to `Full (strict)` after Caddy has issued valid certificates.
6. Enable `Always Use HTTPS`.

The Cloudflare API token used during setup was stored locally outside the repo.
Rotate or revoke any token that has been pasted into chat or logs.

## Deploy

This project now uses the same deployment shape as `api2sub`: local work is
committed and pushed to a writable fork, then the production server pulls the
fork, builds the Docker image on the server, switches to the new image only
after a health check passes, and keeps the previous image for rollback.

The current local `origin` points to the upstream source repo. Before the first
production deploy, create a writable fork and push a clean production branch to
it. Example remote layout:

```bash
git remote rename origin upstream
git remote add origin https://github.com/YOUR_ACCOUNT/acg-faka.git
git push -u origin production/main:main
```

Use a clean production branch for this push. Do not push local scratch/sync
branches that were created while importing the old working tree.

First bootstrap or env refresh:

```bash
cd /path/to/acgfaka
PROJECT_ID=myvps-2606to2608 \
VM=acgfaka-hk \
ZONE=asia-east2-a \
REPO_URL=https://github.com/YOUR_ACCOUNT/acg-faka.git \
BRANCH=main \
bash scripts/deploy-gcp-docker.sh
```

Daily deploy after pushing code:

```bash
gcloud compute ssh acgfaka-hk --project=myvps-2606to2608 --zone=asia-east2-a --command="sudo env BRANCH=main bash /opt/acgfaka/repo/deploy/update.sh"
```

Run detached if the network connection is unstable:

```bash
gcloud compute ssh acgfaka-hk --project=myvps-2606to2608 --zone=asia-east2-a --command="sudo sh -c 'cd /opt/acgfaka/repo && nohup env BRANCH=main bash deploy/update.sh >/opt/acgfaka/deploy.nohup.log 2>&1 &'"
```

Rollback to the previous server-built image:

```bash
gcloud compute ssh acgfaka-hk --project=myvps-2606to2608 --zone=asia-east2-a --command="sudo bash /opt/acgfaka/repo/deploy/update.sh rollback"
```

The deployment runs these production containers:

```text
acgfaka-caddy
acgfaka-app
acgfaka-cron
acgfaka-db
acgfaka-redis
```

`acgfaka-cron` replaces the old host cron job and runs
`close_expired_orders.php` every `ORDER_CLEANUP_INTERVAL` seconds.

## Verify

Check containers:

```bash
gcloud compute ssh acgfaka-hk --project=myvps-2606to2608 --zone=asia-east2-a --command="sudo docker compose -p acgfaka -f /opt/acgfaka/repo/docker-compose.prod.yml -f /opt/acgfaka/docker-compose.override.yml --env-file /opt/acgfaka/.env.prod ps"
```

Check DNS:

```powershell
Resolve-DnsName shop.kaynstech.com -Type A -Server 1.1.1.1
Resolve-DnsName kaynstech.com -Type A -Server 1.1.1.1
Resolve-DnsName www.kaynstech.com -Type CNAME -Server 1.1.1.1
```

Check HTTPS directly against the VM IP:

```powershell
curl.exe --noproxy "*" -I --resolve shop.kaynstech.com:443:34.96.139.162 https://shop.kaynstech.com/
curl.exe --noproxy "*" -I --resolve shop.kaynstech.com:443:34.96.139.162 https://shop.kaynstech.com/admin
curl.exe --noproxy "*" -I --resolve kaynstech.com:443:34.96.139.162 https://kaynstech.com/
curl.exe --noproxy "*" -I --resolve www.kaynstech.com:443:34.96.139.162 https://www.kaynstech.com/
```

Expected results:

- `https://shop.kaynstech.com/` returns `200 OK`
- `https://shop.kaynstech.com/admin` redirects to `/admin/authentication/login`
- `https://kaynstech.com/` returns `301` to `https://shop.kaynstech.com/`
- `https://www.kaynstech.com/` returns `301` to `https://shop.kaynstech.com/`

## Payment notes

The current production database has an `Epay` payment method enabled for both
commodity purchases and user recharge. The plugin currently in the codebase is
named `Epay`, with plugin metadata describing it as HuPiJiao Pay.

Sensitive payment values are read from:

```text
EPAY_APPID
EPAY_APPSECRET
```

Do not print or commit the actual `appid` or `appsecret`. If the current
production host has credentials in `app/Pay/Epay/Config/Config.php`, copy them
into `/opt/acgfaka/.env.prod` before switching to the remote-build deployment.

Third-party plugins installed from the app store write files under `app/Plugin`,
`app/Pay`, or `app/View/User/Theme`. The production image is now treated as the
source of application code. If production relies on a manually installed plugin
that is not committed to the repo, migrate it into Git or reinstall it after the
first Docker deployment.

The production payment callback domain is:

```text
https://shop.kaynstech.com
```

After domain changes, place a small test order and confirm the payment provider
accepts callbacks from `shop.kaynstech.com`.
