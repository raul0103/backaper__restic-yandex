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

# Отсечь пакеты MODX, vue-заглушки, jail-дубликаты Beget
is_junk_config() {
  local p="$1"
  case "$p" in
    */core/packages/*|*/modCategory/*) return 0 ;;
    */assets/components/*/vue/.env) return 0 ;;
    */node_modules/*|*/vendor/*|*/.git/*) return 0 ;;
    /srv/jail/*) return 0 ;;
    */.cagefs/*|*/.service/*) return 0 ;;
  esac
  return 1
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

  # Только корневые .env сайта — не vue/.env из компонентов
  for f in \
    "$HOME_DIR"/*/public_html/.env \
    "$HOME_DIR"/*/.env \
    "$HOME_DIR"/web/*/.env \
    "$HOME_DIR"/web/*/public_html/.env \
    "$HOME_DIR"/domains/*/public_html/.env
  do
    [ -f "$f" ] && found="$found
$f"
  done

  # Глубокий поиск — только явно (VPS) или если глабы ничего не дали
  local need_deep=0
  if [[ "${BACKAPER_FIND_VPS:-}" == "1" ]]; then
    need_deep=1
  elif [[ -z "$(printf '%s' "$found" | sed '/^$/d')" ]] && [[ -d /var/www ]]; then
    need_deep=1
  fi

  if [[ "$need_deep" -eq 1 ]]; then
    local roots="/var/www /home"
    case "${HOME:-}" in
      /home/*|/root|"") ;;
      *) roots="$roots $HOME" ;;
    esac
    # shellcheck disable=SC2086
    local extra
    extra="$(find $roots \
      \( -name node_modules -o -name vendor -o -name .git -o -name cache -o -name .cache \
         -o -name .npm -o -name storage -o -name packages -o -path '*/core/packages' \
         -o -path '*/assets/components' \) -prune -o \
      \( \
        -path '*/core/config/config.inc.php' -type f -print -o \
        -name 'wp-config.php' -type f -print -o \
        -path '*/public_html/.env' -type f -print \
      \) \
      2>/dev/null \
    | head -n 250)"
    found="$found
$extra"
  fi

  local line
  while IFS= read -r line; do
    [[ -z "$line" ]] && continue
    is_junk_config "$line" && continue
    printf '%s\n' "$line"
  done <<< "$(printf '%s' "$found" | sed '/^$/d' | sort -u)" | head -n 80
  set -e
}

# Волатильные таблицы MODX/WP — не нужны в бэкапе, ломают --single-transaction (Error 1412)
ignore_volatile_args() {
  local db="$1"
  local args=()
  local t
  for t in \
    modx_session \
    modx_session_history \
    modx_manager_log \
    wp_sessions \
    wp_woocommerce_sessions
  do
    args+=(--ignore-table="${db}.${t}")
  done
  printf '%s\n' "${args[@]}"
}

dump_database() {
  local db_host="$1" db_user="$2" db_pass="$3" db_name="$4" out="$5"
  local -a ignore=()
  local line
  while IFS= read -r line; do
    [[ -n "$line" ]] && ignore+=("$line")
  done < <(ignore_volatile_args "$db_name")

  local errf="$TMP_DIR/dump.err"
  # 1) обычный consistent dump без session-таблиц
  if "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" \
    --single-transaction --quick --routines --triggers --max-allowed-packet=512M \
    "${ignore[@]}" \
    "$db_name" > "$out" 2>"$errf"; then
    return 0
  fi

  local dump_err
  dump_err="$(grep -v 'Using a password' "$errf" | tr '\n' ' ' | head -c 240)"

  # 2) Error 1412 / schema changed — повтор
  if grep -qE 'Error 1412|Table definition has changed' "$errf"; then
    log "RETRY mysqldump (1412): ${db_name}"
    sleep 2
    if "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" \
      --single-transaction --quick --routines --triggers --max-allowed-packet=512M \
      "${ignore[@]}" \
      "$db_name" > "$out" 2>"$errf"; then
      return 0
    fi
    dump_err="$(grep -v 'Using a password' "$errf" | tr '\n' ' ' | head -c 240)"
  fi

  # 3) без single-transaction (хуже консистентность, но дамп часто проходит)
  log "RETRY mysqldump (без single-transaction): ${db_name}"
  if "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" \
    --quick --routines --triggers --max-allowed-packet=512M \
    --lock-tables=false \
    "${ignore[@]}" \
    "$db_name" > "$out" 2>"$errf"; then
    return 0
  fi

  dump_err="$(grep -v 'Using a password' "$errf" | tr '\n' ' ' | head -c 240)"
  printf '%s' "$dump_err" > "$TMP_DIR/dump.last_err"
  return 1
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

log "cloud: ${RCLONE_REMOTE}:${CLOUD_PREFIX}/"
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
skip=0

for cfg in "${CONFIGS[@]}"; do
  [[ -z "$cfg" || ! -f "$cfg" ]] && continue
  parsed=""
  parse_err=""
  if ! parsed="$(php "$PARSER" "$cfg" 2>"$TMP_DIR/parse.err")"; then
    parse_err="$(tr '\n' ' ' <"$TMP_DIR/parse.err" | head -c 160)"
    log "SKIP parse: $cfg${parse_err:+ ($parse_err)}"
    ((skip++)) || true
    continue
  fi
  IFS=$'\t' read -r db_host db_name db_user db_pass source label <<< "$parsed"
  key="${db_name}|${db_user}"
  if [[ -n "${SEEN[$key]:-}" ]]; then
    log "SKIP duplicate: ${db_name}"
    ((skip++)) || true
    continue
  fi
  SEEN[$key]=1

  db_slug="$(sanitize_slug "$db_name")"
  log "=== ${source}: ${label} (${db_name} @ ${db_host}) ← ${cfg}"

  dump_sql="${TMP_DIR}/${db_slug}.sql"
  dump_gz="${dump_sql}.gz"

  if ! dump_database "$db_host" "$db_user" "$db_pass" "$db_name" "$dump_sql"; then
    dump_err="$(cat "$TMP_DIR/dump.last_err" 2>/dev/null || true)"
    log "ERROR mysqldump: ${db_name}${dump_err:+ ($dump_err)}"
    rm -f "$dump_sql"
    ((fail++)) || true
    continue
  fi

  gzip -cf "$dump_sql" > "$dump_gz"
  dest="${RCLONE_REMOTE}:${CLOUD_PREFIX}/${db_slug}/${TIMESTAMP}.sql.gz"
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

log "готово: ok=${ok} fail=${fail} skip=${skip}"
[[ "$ok" -gt 0 ]]
