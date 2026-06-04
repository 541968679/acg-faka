#!/usr/bin/env bash
set -euo pipefail

: "${PROJECT_ID:?Set PROJECT_ID, for example myvps-2606to2608}"

ZONE="${ZONE:-asia-east2-a}"
REGION="${REGION:-${ZONE%-*}}"
VM="${VM:-acgfaka-hk}"
MACHINE_TYPE="${MACHINE_TYPE:-e2-small}"
BOOT_DISK_SIZE="${BOOT_DISK_SIZE:-30GB}"
IMAGE_FAMILY="${IMAGE_FAMILY:-debian-12}"
IMAGE_PROJECT="${IMAGE_PROJECT:-debian-cloud}"
NETWORK_TAG="${NETWORK_TAG:-acgfaka-web}"
IP_NAME="${IP_NAME:-${VM}-ip}"
FIREWALL_NAME="${FIREWALL_NAME:-allow-acgfaka-web}"

if ! gcloud compute addresses describe "$IP_NAME" --project="$PROJECT_ID" --region="$REGION" >/dev/null 2>&1; then
  gcloud compute addresses create "$IP_NAME" --project="$PROJECT_ID" --region="$REGION"
fi

STATIC_IP="$(gcloud compute addresses describe "$IP_NAME" --project="$PROJECT_ID" --region="$REGION" --format='value(address)')"

if ! gcloud compute firewall-rules describe "$FIREWALL_NAME" --project="$PROJECT_ID" >/dev/null 2>&1; then
  gcloud compute firewall-rules create "$FIREWALL_NAME" \
    --project="$PROJECT_ID" \
    --network=default \
    --direction=INGRESS \
    --priority=1000 \
    --action=ALLOW \
    --rules=tcp:80,tcp:443 \
    --target-tags="$NETWORK_TAG"
fi

if ! gcloud compute instances describe "$VM" --project="$PROJECT_ID" --zone="$ZONE" >/dev/null 2>&1; then
  gcloud compute instances create "$VM" \
    --project="$PROJECT_ID" \
    --zone="$ZONE" \
    --machine-type="$MACHINE_TYPE" \
    --image-family="$IMAGE_FAMILY" \
    --image-project="$IMAGE_PROJECT" \
    --boot-disk-size="$BOOT_DISK_SIZE" \
    --address="$STATIC_IP" \
    --tags="$NETWORK_TAG"
fi

printf 'VM: %s\nZone: %s\nStatic IP: %s\n' "$VM" "$ZONE" "$STATIC_IP"
