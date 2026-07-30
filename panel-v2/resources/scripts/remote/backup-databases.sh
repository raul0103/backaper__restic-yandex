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

# Корни поиска: $HOME + типичные VPS-пути (/home/web/docker/... часто не под $HOME SSH-юзера)
collect_find_roots() {
  local HOME_DIR="${HOME:-/home/$USER}"
  local r candidate
  local -a out=()

  add_root() {
    local p="$1"
    [[ -n "$p" && -d "$p" ]] || return 0
    for r in "${out[@]+"${out[@]}"}"; do
      [[ "$r" == "$p" ]] && return 0
    done
    out+=("$p")
  }

  add_root "$HOME_DIR"
  add_root "$HOME_DIR/web"
  add_root "$HOME_DIR/domains"
  add_root "$HOME_DIR/docker"
  # Часто проекты лежат у пользователя web, а бэкап идёт от root/другого юзера
  add_root /home/web
  add_root /home/web/docker
  add_root /var/www
  add_root /srv
  add_root /opt
  # Весь /home (VPS) — по умолчанию или BACKAPER_FIND_VPS=1
  if [[ "${BACKAPER_FIND_VPS:-1}" == "1" ]] || [[ -d /home/web ]] || [[ -d /var/www ]] || command -v docker >/dev/null 2>&1; then
    add_root /home
  fi

  # Явные подсказки из env (через запятую)
  if [[ -n "${BACKAPER_DB_SEARCH_ROOTS:-}" ]]; then
    IFS=',' read -ra candidate <<< "${BACKAPER_DB_SEARCH_ROOTS}"
    for r in "${candidate[@]}"; do
      r="$(echo "$r" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
      add_root "$r"
    done
  fi

  printf '%s\n' "${out[@]}"
}

find_configs() {
  set +e
  local HOME_DIR="${HOME:-/home/$USER}"
  local found=""
  local -a roots=()
  local d f line extra

  mapfile -t roots < <(collect_find_roots)
  # на случай пустого mapfile
  [[ ${#roots[@]} -eq 0 ]] && roots=("$HOME_DIR")

  # Быстрые глабы (Beget/типичный хостинг)
  for d in \
    "$HOME_DIR"/*/public_html/core/config \
    "$HOME_DIR"/web/*/public_html/core/config \
    "$HOME_DIR"/domains/*/public_html/core/config \
    /home/web/*/public_html/core/config \
    /home/web/docker/*/backend/core/config
  do
    [ -f "$d/config.inc.php" ] && found="$found
$d/config.inc.php"
  done

  for f in \
    "$HOME_DIR"/*/public_html/wp-config.php \
    "$HOME_DIR"/web/*/public_html/wp-config.php \
    "$HOME_DIR"/domains/*/public_html/wp-config.php \
    /home/web/*/public_html/wp-config.php
  do
    [ -f "$f" ] && found="$found
$f"
  done

  for f in \
    "$HOME_DIR"/*/public_html/.env \
    "$HOME_DIR"/*/.env \
    "$HOME_DIR"/web/*/.env \
    "$HOME_DIR"/web/*/public_html/.env \
    "$HOME_DIR"/domains/*/public_html/.env \
    /home/web/docker/*/*/.env \
    /home/web/docker/*/backend/.env \
    /home/web/*/backend/.env
  do
    [ -f "$f" ] && found="$found
$f"
  done

  # Глубокий find: docker/laravel (web/docker/passtore/backend/.env и т.п.)
  # shellcheck disable=SC2086
  extra="$(find "${roots[@]}" \
    -maxdepth 12 \
    \( -name node_modules -o -name vendor -o -name .git -o -name cache -o -name .cache \
       -o -name .npm -o -name storage -o -name packages -o -path '*/core/packages' \
       -o -path '*/assets/components' -o -name .cagefs -o -name .service \
       -o -name proc -o -name sys -o -name run \) -prune -o \
    \( \
      -path '*/core/config/config.inc.php' -type f -print -o \
      -name 'wp-config.php' -type f -print -o \
      -name '.env' -type f -print \
    \) \
    2>/dev/null \
  | head -n 400)"
  found="$found
$extra"

  # Диагностика в stderr (попадёт в лог бэкапа), если ничего нет
  if [[ -z "$(printf '%s' "$found" | sed '/^$/d')" ]]; then
    log "DEBUG: HOME=${HOME_DIR} USER=${USER:-?} roots=${roots[*]}" >&2
    log "DEBUG: ls /home → $(ls -1 /home 2>/dev/null | tr '\n' ' ' | head -c 200)" >&2
    log "DEBUG: test paths:" >&2
    for f in \
      /home/web/docker/passtore/backend/.env \
      "$HOME_DIR/web/docker/passtore/backend/.env" \
      /home/web/docker/passtore/.env
    do
      if [[ -e "$f" ]]; then
        log "DEBUG: EXISTS $f" >&2
      else
        log "DEBUG: missing $f" >&2
      fi
    done
  fi

  while IFS= read -r line; do
    [[ -z "$line" ]] && continue
    is_junk_config "$line" && continue
    printf '%s\n' "$line"
  done <<< "$(printf '%s' "$found" | sed '/^$/d' | sort -u)" | head -n 150
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

is_docker_db_host() {
  case "$(echo "$1" | tr '[:upper:]' '[:lower:]')" in
    mysql|mariadb|db|database|postgres|postgresql) return 0 ;;
    *) return 1 ;;
  esac
}

# Найти контейнер MySQL по каталогу с .env / compose.yaml (Sail и т.п.)
resolve_mysql_container() {
  local start_dir="$1"
  local dir="$start_dir" f svc cid i

  command -v docker >/dev/null 2>&1 || return 1

  for i in 1 2 3 4 5 6; do
    for f in compose.yaml compose.yml docker-compose.yml docker-compose.yaml compose.dev.yaml; do
      [[ -f "$dir/$f" ]] || continue
      for svc in mysql mariadb db database; do
        cid="$(
          cd "$dir" && docker compose -f "$f" ps -q "$svc" 2>/dev/null | head -1
        )"
        if [[ -n "$cid" ]]; then
          printf '%s' "$cid"
          return 0
        fi
      done
    done
    [[ "$dir" == "/" ]] && break
    dir="$(dirname "$dir")"
  done
  return 1
}

# Дамп внутри контейнера (mysqldump есть в mysql-образе, на хосте часто нет)
dump_via_docker() {
  local cid="$1" db_user="$2" db_pass="$3" db_name="$4" out="$5"
  local -a ignore=()
  local line errf dump_bin
  while IFS= read -r line; do
    [[ -n "$line" ]] && ignore+=("$line")
  done < <(ignore_volatile_args "$db_name")

  errf="$TMP_DIR/dump.err"
  dump_bin=mysqldump
  if ! docker exec "$cid" sh -c "command -v mysqldump >/dev/null 2>&1"; then
    if docker exec "$cid" sh -c "command -v mariadb-dump >/dev/null 2>&1"; then
      dump_bin=mariadb-dump
    else
      printf '%s' "docker: mysqldump not in container ${cid:0:12}" > "$TMP_DIR/dump.last_err"
      return 1
    fi
  fi

  log "docker dump: container=${cid:0:12} db=${db_name}"

  # 1) single-transaction
  if docker exec -e MYSQL_PWD="$db_pass" "$cid" \
    "$dump_bin" -u"$db_user" --single-transaction --quick --routines --triggers \
    --max-allowed-packet=512M "${ignore[@]}" "$db_name" \
    >"$out" 2>"$errf"; then
    [[ -s "$out" ]] && return 0
  fi

  # 2) без single-transaction
  log "RETRY docker mysqldump (без single-transaction): ${db_name}"
  if docker exec -e MYSQL_PWD="$db_pass" "$cid" \
    "$dump_bin" -u"$db_user" --quick --routines --triggers \
    --max-allowed-packet=512M --lock-tables=false "${ignore[@]}" "$db_name" \
    >"$out" 2>"$errf"; then
    [[ -s "$out" ]] && return 0
  fi

  grep -v 'Using a password' "$errf" 2>/dev/null | tr '\n' ' ' | head -c 240 > "$TMP_DIR/dump.last_err"
  return 1
}

dump_via_host() {
  local db_host="$1" db_user="$2" db_pass="$3" db_name="$4" out="$5"
  local -a ignore=()
  local line errf

  [[ "${HAS_HOST_DUMP}" == "1" ]] || return 1

  while IFS= read -r line; do
    [[ -n "$line" ]] && ignore+=("$line")
  done < <(ignore_volatile_args "$db_name")

  errf="$TMP_DIR/dump.err"

  if "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" \
    --single-transaction --quick --routines --triggers --max-allowed-packet=512M \
    "${ignore[@]}" \
    "$db_name" > "$out" 2>"$errf"; then
    return 0
  fi

  if grep -qE 'Error 1412|Table definition has changed' "$errf"; then
    log "RETRY mysqldump (1412): ${db_name}"
    sleep 2
    if "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" \
      --single-transaction --quick --routines --triggers --max-allowed-packet=512M \
      "${ignore[@]}" \
      "$db_name" > "$out" 2>"$errf"; then
      return 0
    fi
  fi

  log "RETRY mysqldump (без single-transaction): ${db_name}"
  if "${DUMP_BIN[@]}" -h "$db_host" -u "$db_user" --password="$db_pass" \
    --quick --routines --triggers --max-allowed-packet=512M \
    --lock-tables=false \
    "${ignore[@]}" \
    "$db_name" > "$out" 2>"$errf"; then
    return 0
  fi

  grep -v 'Using a password' "$errf" 2>/dev/null | tr '\n' ' ' | head -c 240 > "$TMP_DIR/dump.last_err"
  return 1
}

# cfg_path — путь к .env/config, чтобы найти docker compose рядом
dump_database() {
  local db_host="$1" db_user="$2" db_pass="$3" db_name="$4" out="$5" cfg_path="${6:-}"
  local cfg_dir cid host_try

  cfg_dir="$(dirname "${cfg_path:-.}")"
  cid=""
  if [[ -n "$cfg_path" ]]; then
    cid="$(resolve_mysql_container "$cfg_dir" || true)"
  fi

  # Docker-хост (mysql/mariadb) или нет клиента на хосте → сразу docker
  if [[ -n "$cid" ]] && { is_docker_db_host "$db_host" || [[ "${HAS_HOST_DUMP}" != "1" ]]; }; then
    if dump_via_docker "$cid" "$db_user" "$db_pass" "$db_name" "$out"; then
      return 0
    fi
  fi

  # Хост: как в .env, плюс 127.0.0.1 если DB_HOST=docker-имя
  host_try="$db_host"
  if is_docker_db_host "$db_host"; then
    host_try="127.0.0.1"
  fi
  if dump_via_host "$host_try" "$db_user" "$db_pass" "$db_name" "$out"; then
    return 0
  fi
  if [[ "$host_try" != "$db_host" ]] && dump_via_host "$db_host" "$db_user" "$db_pass" "$db_name" "$out"; then
    return 0
  fi

  # Запасной docker, если раньше не пробовали / хост не вышел
  if [[ -n "$cid" ]]; then
    if dump_via_docker "$cid" "$db_user" "$db_pass" "$db_name" "$out"; then
      return 0
    fi
  elif is_docker_db_host "$db_host"; then
    printf '%s' "DB_HOST=${db_host}, контейнер MySQL не найден рядом с ${cfg_path:-?}" > "$TMP_DIR/dump.last_err"
  fi

  [[ -s "$TMP_DIR/dump.last_err" ]] || printf '%s' 'dump failed' > "$TMP_DIR/dump.last_err"
  return 1
}

if ! command -v php >/dev/null 2>&1; then
  log "ERROR: php-cli нужен для разбора конфигов на лету"
  exit 1
fi

HAS_HOST_DUMP=0
DUMP_BIN=(mysqldump)
if command -v mariadb-dump >/dev/null 2>&1; then
  DUMP_BIN=(mariadb-dump)
  HAS_HOST_DUMP=1
elif command -v mysqldump >/dev/null 2>&1; then
  DUMP_BIN=(mysqldump)
  HAS_HOST_DUMP=1
fi
if [[ "${HAS_HOST_DUMP}" != "1" ]] && ! command -v docker >/dev/null 2>&1; then
  log "ERROR: нужен mysqldump/mariadb-dump на хосте или docker с MySQL-контейнером"
  exit 1
fi

log "cloud: ${RCLONE_REMOTE}:${CLOUD_PREFIX}/"
log "поиск конфигов…"
mapfile -t CONFIGS < <(find_configs)
log "найдено конфигов: ${#CONFIGS[@]}"

if [[ ${#CONFIGS[@]} -eq 0 ]]; then
  log "ERROR: конфиги не найдены (MODX/WP/.env под HOME/web, в т.ч. docker/*/backend)"
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
  # разные compose-проекты могут иметь одинаковые DB_DATABASE=laravel
  key="$(dirname "$cfg")|${db_name}|${db_user}"
  if [[ -n "${SEEN[$key]:-}" ]]; then
    log "SKIP duplicate: ${db_name} (${label})"
    ((skip++)) || true
    continue
  fi
  SEEN[$key]=1

  db_slug="$(sanitize_slug "${label}_${db_name}")"
  [[ -z "$db_slug" || "$db_slug" == "_" ]] && db_slug="$(sanitize_slug "$db_name")"
  log "=== ${source}: ${label} (${db_name} @ ${db_host}) ← ${cfg}"

  dump_sql="${TMP_DIR}/${db_slug}.sql"
  dump_gz="${dump_sql}.gz"
  : > "$TMP_DIR/dump.last_err"

  if ! dump_database "$db_host" "$db_user" "$db_pass" "$db_name" "$dump_sql" "$cfg"; then
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
