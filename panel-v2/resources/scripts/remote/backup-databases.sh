#!/usr/bin/env bash
# CLI: найти конфиги сайтов на лету → mysqldump → rclone на Яндекс.Диск.
# Панель для БД использует backup.sh + манифест (уже найденные доступы).
#
#   source ~/backaper/backaper.env
#   bash ~/backaper/scripts/backup-databases.sh
#
# Нужны: php-cli, mysqldump|mariadb-dump, rclone, gzip
set -euo pipefail

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$BACKAPER_ROOT/backaper.env"
[[ -f "$ENV_FILE" ]] || ENV_FILE="$BACKAPER_ROOT/restic.env"
# shellcheck source=/dev/null
. "$ENV_FILE"

export PATH="${HOME}/bin:${PATH:-}"
RCLONE_REMOTE="${BACKAPER_RCLONE_REMOTE:?BACKAPER_RCLONE_REMOTE not set — source backaper.env}"
CLOUD_PREFIX="${BACKAPER_CLOUD_PREFIX:?BACKAPER_CLOUD_PREFIX not set}"
RCLONE_CONFIG="${RCLONE_CONFIG:-$BACKAPER_ROOT/rclone.conf}"
export RCLONE_CONFIG

PARSER="${SCRIPT_DIR}/parse-db-config.php"
[[ -f "$PARSER" ]] || { echo "[db] ERROR: missing parse-db-config.php" >&2; exit 1; }

TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"
TMP_DIR="${BACKAPER_ROOT}/tmp/db-dump-$$"
mkdir -p "$TMP_DIR"
cleanup() { rm -rf "$TMP_DIR"; }
trap cleanup EXIT

log() { printf '%s\n' "[db] $(date -Is) $*"; }

sanitize_slug() {
  echo "$1" | tr ' /:' '___' | tr -cd 'a-zA-Z0-9._-' | cut -c1-120
}

find_configs() {
  set +e
  local HOME_DIR="${HOME:-/home/$USER}"
  local found=""

  for d in \
    "$HOME_DIR"/*/public_html/core/config \
    "$HOME_DIR"/web/*/public_html/core/config \
    "$HOME_DIR"/domains/*/public_html/core/config
  do
    [ -f "$d/config.inc.php" ] && found="$found
$d/config.inc.php"
  done

  for f in \
    "$HOME_DIR"/*/public_html/wp-config.php \
    "$HOME_DIR"/web/*/public_html/wp-config.php \
    "$HOME_DIR"/domains/*/public_html/wp-config.php
  do
    [ -f "$f" ] && found="$found
$f"
  done

  for f in \
    "$HOME_DIR"/*/public_html/.env \
    "$HOME_DIR"/*/.env \
    "$HOME_DIR"/web/*/.env \
    "$HOME_DIR"/web/*/public_html/.env
  do
    [ -f "$f" ] && found="$found
$f"
  done

  if [[ -d /var/www ]] || [[ "${BACKAPER_FIND_VPS:-}" == "1" ]]; then
    local roots="/var/www /home /srv /opt"
    case "${HOME:-}" in
      /home/*|/root|"") ;;
      *) roots="$roots $HOME" ;;
    esac
    # shellcheck disable=SC2086
    local extra
    extra="$(find $roots \
      \( -name node_modules -o -name vendor -o -name .git -o -name cache -o -name .cache -o -name .npm -o -name storage \) -prune -o \
      \( \
        -path '*/core/config/config.inc.php' -type f -print -o \
        -name 'wp-config.php' -type f -print -o \
        -name '.env' -type f -print \
      \) \
      2>/dev/null \
    | grep -Ev '/(vendor|node_modules|\.git|cache|\.cache)/' \
    | grep -Ev '/\.env\.' \
    | head -n 250)"
    found="$found
$extra"
  fi

  printf '%s' "$found" | sed '/^$/d' | sort -u | head -n 250
  set -e
}

if ! command -v php >/dev/null 2>&1; then
  log "ERROR: php-cli нужен для разбора конфигов на лету"
  exit 1
fi

if command -v mariadb-dump >/dev/null 2>&1; then
  DUMP_BIN=(mariadb-dump)
else
  DUMP_BIN=(mysqldump)
fi

log "cloud: ${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/"
log "поиск конфигов…"
mapfile -t CONFIGS < <(find_configs)
log "найдено конфигов: ${#CONFIGS[@]}"

if [[ ${#CONFIGS[@]} -eq 0 ]]; then
  log "ERROR: конфиги не найдены (MODX/WP/Laravel)"
  exit 1
fi

declare -A SEEN=()
ok=0
fail=0

for cfg in "${CONFIGS[@]}"; do
  [[ -z "$cfg" || ! -f "$cfg" ]] && continue
  parsed=""
  if ! parsed="$(php "$PARSER" "$cfg" 2>/dev/null)"; then
    log "SKIP parse: $cfg"
    ((fail++)) || true
    continue
  fi
  IFS=$'\t' read -r db_host db_name db_user db_pass source label <<< "$parsed"
  key="${db_name}|${db_user}"
  if [[ -n "${SEEN[$key]:-}" ]]; then
    log "SKIP duplicate: ${db_name}"
    continue
  fi
  SEEN[$key]=1

  db_slug="$(sanitize_slug "$db_name")"
  log "=== ${source}: ${label} (${db_name} @ ${db_host}) ← ${cfg}"

  dump_sql="${TMP_DIR}/${db_slug}.sql"
  dump_gz="${dump_sql}.gz"

  if ! "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" --connect-timeout=10 \
    --single-transaction --routines --triggers --max-allowed-packet=512M \
    "$db_name" > "$dump_sql" 2>/dev/null; then
    log "ERROR mysqldump: ${db_name}"
    rm -f "$dump_sql"
    ((fail++)) || true
    continue
  fi

  gzip -cf "$dump_sql" > "$dump_gz"
  dest="${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/${db_slug}/${TIMESTAMP}.sql.gz"
  if ! rclone copyto "$dump_gz" "$dest"; then
    log "ERROR rclone: ${db_name}"
    rm -f "$dump_sql" "$dump_gz"
    ((fail++)) || true
    continue
  fi
  bytes="$(stat -c%s "$dump_gz" 2>/dev/null || stat -f%z "$dump_gz" 2>/dev/null || echo 0)"
  log "OK ${db_name} → ${dest} (${bytes} bytes)"
  rm -f "$dump_sql" "$dump_gz"
  ((ok++)) || true
done

log "готово: ok=${ok} fail=${fail}"
[[ "$ok" -gt 0 ]]
