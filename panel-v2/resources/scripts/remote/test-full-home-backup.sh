#!/usr/bin/env bash
# CLI-тест: весь домашний каталог хостинга одним restic backup.
# На сервере:
#   source ~/backaper/backaper.env
#   bash ~/backaper/scripts/test-full-home-backup.sh
#
# Опционально: BACKAPER_UPLOAD_LIMIT_KIB=1024 (KiB/s, по умолчанию 2048)
set -euo pipefail

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
ENV_FILE="$BACKAPER_ROOT/backaper.env"
[[ -f "$ENV_FILE" ]] || ENV_FILE="$BACKAPER_ROOT/restic.env"
# shellcheck source=/dev/null
. "$ENV_FILE"

export RESTIC_REPOSITORY RESTIC_PASSWORD
UPLOAD_LIMIT="${BACKAPER_UPLOAD_LIMIT_KIB:-2048}"

echo "[cli] HOME=$HOME"
echo "[cli] RESTIC_REPOSITORY=$RESTIC_REPOSITORY"
echo "[cli] upload limit=${UPLOAD_LIMIT} KiB/s"
echo "[cli] start: $(date -Is)"

# Те же исключения, что в панели (хостинг)
EXCLUDES=(
  --exclude 'core/cache/**'
  --exclude '**/node_modules/**'
  --exclude '**/.git/**'
  --exclude '**/vendor/**'
  --exclude '.service'
  --exclude '.service/**'
  --exclude '.cagefs'
  --exclude '.cagefs/**'
  --exclude '.cl.selector'
  --exclude '.cl.selector/**'
  --exclude '.spamassassin'
  --exclude '.spamassassin/**'
  --exclude '.softaculous'
  --exclude '.softaculous/**'
  --exclude '.local'
  --exclude '.local/**'
  --exclude '.cache'
  --exclude '.cache/**'
  --exclude '.composer'
  --exclude '.composer/**'
  --exclude '.npm'
  --exclude '.npm/**'
  --exclude '.config'
  --exclude '.config/**'
  --exclude 'mail'
  --exclude 'mail/**'
  --exclude 'tmp'
  --exclude 'tmp/**'
  --exclude '.tmp'
  --exclude '.tmp/**'
  --exclude '**/lscache/**'
  --exclude '**/cgi-bin/**'
  --exclude 'backaper/tmp/**'
  --exclude '.backaper/**'
)

set +e
GOMAXPROCS=1 GOGC=40 \
restic backup "$HOME" \
  "${EXCLUDES[@]}" \
  --one-file-system \
  --limit-upload "$UPLOAD_LIMIT" \
  --tag 'path:home' \
  --host "$(hostname -s 2>/dev/null || hostname)" \
  --verbose=1
ec=$?
set -e

echo "[cli] restic exit=$ec  end: $(date -Is)"
if [[ "$ec" -eq 0 || "$ec" -eq 3 ]]; then
  restic snapshots --tag path:home | tail -5
  restic stats latest
  exit 0
fi
exit "$ec"
