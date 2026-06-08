#!/usr/bin/env bash
# Backaper — установка restic + rclone на удалённом Linux-сервере через SSH
# Использование (с машины, где есть SSH-доступ):
#
#   export BACKAPER_HOST=deploy@server.example.com
#   export BACKAPER_RCLONE_TOKEN='{"access_token":"..."}'
#   export BACKAPER_RESTIC_PASSWORD='your-password'
#   export BACKAPER_RCLONE_REMOTE=yandex
#   export BACKAPER_REPO_SLUG=my-server
#   bash scripts/backaper-remote-install.sh
#
# Или из панели: php artisan backaper:setup {server_id}
set -euo pipefail

HOST="${BACKAPER_HOST:?Set BACKAPER_HOST=user@host}"
RCLONE_REMOTE="${BACKAPER_RCLONE_REMOTE:-yandex}"
REPO_SLUG="${BACKAPER_REPO_SLUG:-$(echo "$HOST" | tr '@:' '--')}"
RESTIC_PASSWORD="${BACKAPER_RESTIC_PASSWORD:?Set BACKAPER_RESTIC_PASSWORD}"
RCLONE_TOKEN="${BACKAPER_RCLONE_TOKEN:?Set BACKAPER_RCLONE_TOKEN JSON}"
CLOUD_PREFIX="${BACKAPER_CLOUD_PREFIX:-backaper/${REPO_SLUG}}"
RESTIC_REPOSITORY="rclone:${RCLONE_REMOTE}:restic-repo/${REPO_SLUG}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_SH="${SCRIPT_DIR}/../panel/resources/scripts/remote/install.sh"

if [[ ! -f "$INSTALL_SH" ]]; then
  INSTALL_SH="$(dirname "$SCRIPT_DIR")/panel/resources/scripts/remote/install.sh"
fi

echo "→ SSH $HOST"
ssh "$HOST" 'mkdir -p ~/backaper/scripts ~/backaper/logs ~/bin'

scp "$INSTALL_SH" "${HOST}:~/backaper/scripts/install.sh"
scp "${SCRIPT_DIR}/../panel/resources/scripts/remote/backup.sh" "${HOST}:~/backaper/scripts/backup.sh" 2>/dev/null || true

echo "$RCLONE_TOKEN" | ssh "$HOST" 'cat > ~/backaper/rclone-token.json && chmod 600 ~/backaper/rclone-token.json'

ssh "$HOST" "chmod +x ~/backaper/scripts/install.sh ~/backaper/scripts/backup.sh 2>/dev/null; \
  env BACKAPER_RCLONE_REMOTE='${RCLONE_REMOTE}' \
      BACKAPER_RESTIC_PASSWORD='${RESTIC_PASSWORD}' \
      BACKAPER_RESTIC_REPOSITORY='${RESTIC_REPOSITORY}' \
      BACKAPER_CLOUD_PREFIX='${CLOUD_PREFIX}' \
      bash ~/backaper/scripts/install.sh"

echo "Done. Cloud layout:"
echo "  restic: ${RESTIC_REPOSITORY}"
echo "  dumps:  ${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/{db_name}/{date}.sql.gz"
echo "  archives: ${RCLONE_REMOTE}:${CLOUD_PREFIX}/projects/{project}/{date}.tar.gz"
