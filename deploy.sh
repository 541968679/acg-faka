#!/usr/bin/env bash
set -euo pipefail

PROJECT_ID="${PROJECT_ID:-myvps-2606to2608}"
ZONE="${ZONE:-asia-east2-a}"
VM="${VM:-acgfaka-hk}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

: "${REPO_URL:?Set REPO_URL to your writable fork before deploying}"

BRANCH="${BRANCH:-main}" \
PROJECT_ID="$PROJECT_ID" \
ZONE="$ZONE" \
VM="$VM" \
REPO_URL="$REPO_URL" \
bash "$SCRIPT_DIR/scripts/deploy-gcp-docker.sh"
