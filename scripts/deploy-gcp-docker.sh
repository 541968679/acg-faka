#!/usr/bin/env bash
set -euo pipefail

: "${PROJECT_ID:?Set PROJECT_ID}"
: "${ZONE:?Set ZONE, for example asia-east2-a}"
: "${VM:?Set VM, for example acgfaka-hk}"
: "${REPO_URL:?Set REPO_URL to your writable fork, for example https://github.com/541968679/acg-faka.git}"

REMOTE_DIR="${REMOTE_DIR:-/opt/acgfaka}"
REMOTE_ENV_FILE="${REMOTE_ENV_FILE:-${REMOTE_DIR}/.env.prod}"
REMOTE_REPO_DIR="${REMOTE_REPO_DIR:-${REMOTE_DIR}/repo}"
BRANCH="${BRANCH:-main}"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"

if [ ! -f "$LOCAL_DIR/.env.prod" ]; then
  echo "Missing $LOCAL_DIR/.env.prod. Copy .env.prod.example to .env.prod and fill it first." >&2
  exit 1
fi

gcloud compute scp "$LOCAL_DIR/.env.prod" "$VM:/tmp/acgfaka.env.prod" \
  --project="$PROJECT_ID" --zone="$ZONE"

gcloud compute ssh "$VM" --project="$PROJECT_ID" --zone="$ZONE" --command="
  set -euo pipefail

  if ! command -v docker >/dev/null 2>&1 || ! sudo docker compose version >/dev/null 2>&1; then
    . /etc/os-release
    sudo apt-get update
    sudo apt-get install -y ca-certificates curl gnupg
    sudo install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/\${ID}/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    sudo chmod a+r /etc/apt/keyrings/docker.gpg
    echo \"deb [arch=\$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/\${ID} \${VERSION_CODENAME} stable\" | sudo tee /etc/apt/sources.list.d/docker.list >/dev/null
    sudo apt-get update
    sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  fi

  if ! command -v git >/dev/null 2>&1; then
    sudo apt-get update
    sudo apt-get install -y git
  fi

  sudo mkdir -p '$REMOTE_DIR'
  sudo install -m 600 /tmp/acgfaka.env.prod '$REMOTE_ENV_FILE'
  sudo rm -f /tmp/acgfaka.env.prod

  if [ ! -d '$REMOTE_REPO_DIR/.git' ]; then
    sudo rm -rf '$REMOTE_REPO_DIR'
    sudo git clone --branch '$BRANCH' '$REPO_URL' '$REMOTE_REPO_DIR'
  fi

  cd '$REMOTE_REPO_DIR'
  sudo git remote set-url origin '$REPO_URL'
  sudo env BRANCH='$BRANCH' BASE_DIR='$REMOTE_DIR' REPO_DIR='$REMOTE_REPO_DIR' ENV_FILE='$REMOTE_ENV_FILE' bash deploy/update.sh
"
