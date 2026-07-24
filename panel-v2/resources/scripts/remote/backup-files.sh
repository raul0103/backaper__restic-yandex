#!/usr/bin/env bash
# CLI: бэкап всех файлов (хостинг = $HOME) одним restic snapshot.
# Панель для файлов использует backup.sh с backup_files=true.
#
#   source ~/backaper/backaper.env
#   bash ~/backaper/scripts/backup-files.sh
#
# Опционально: BACKAPER_UPLOAD_LIMIT_KIB=1024 (KiB/s, по умолчанию 2048)
#              BACKAPER_BACKUP_ROOT=/  (VPS; по умолчанию $HOME)
set -euo pipefail

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
ENV_FILE="$BACKAPER_ROOT/backaper.env"
[[ -f "$ENV_FILE" ]] || ENV_FILE="$BACKAPER_ROOT/restic.env"
# shellcheck source=/dev/null
. "$ENV_FILE"

export RESTIC_REPOSITORY RESTIC_PASSWORD
UPLOAD_LIMIT="${BACKAPER_UPLOAD_LIMIT_KIB:-2048}"
ROOT="${BACKAPER_BACKUP_ROOT:-$HOME}"
TAG="${BACKAPER_FILES_TAG:-path:home}"
[[ "$ROOT" == "/" ]] && TAG="${BACKAPER_FILES_TAG:-path:root}"

echo "[files] root=$ROOT"
echo "[files] RESTIC_REPOSITORY=$RESTIC_REPOSITORY"
echo "[files] upload limit=${UPLOAD_LIMIT} KiB/s"
echo "[files] start: $(date -Is)"

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

# VPS: системные каталоги (если бэкапим /)
if [[ "$ROOT" == "/" ]]; then
  EXCLUDES+=(
    --exclude 'proc'
    --exclude 'proc/**'
    --exclude 'sys'
    --exclude 'sys/**'
    --exclude 'dev'
    --exclude 'dev/**'
    --exclude 'run'
    --exclude 'run/**'
    --exclude 'tmp'
    --exclude 'tmp/**'
    --exclude 'var/tmp'
    --exclude 'var/tmp/**'
    --exclude 'var/cache'
    --exclude 'var/cache/**'
    --exclude 'lost+found'
  )
fi

set +e
GOMAXPROCS="${GOMAXPROCS:-1}" GOGC="${GOGC:-40}" \
restic backup "$ROOT" \
  "${EXCLUDES[@]}" \
  --one-file-system \
  --limit-upload "$UPLOAD_LIMIT" \
  --tag "$TAG" \
  --host "$(hostname -s 2>/dev/null || hostname)" \
  --verbose=1
ec=$?
set -e

echo "[files] restic exit=$ec  end: $(date -Is)"
if [[ "$ec" -eq 0 || "$ec" -eq 3 ]]; then
  restic snapshots --tag "$TAG" | tail -5
  restic stats latest
  exit 0
fi
exit "$ec"
