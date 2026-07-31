#!/usr/bin/env bash
# Backaper v2:
#   1) restic — полный бэкап указанных путей (с исключениями)
#   2) rclone — отдельные дампы БД в {cloud}/{db}/{date}.sql.gz
set -euo pipefail

# systemd/minimal env может не передать HOME (set -u → unbound variable)
if [[ -z "${HOME:-}" ]]; then
  HOME="$(getent passwd "$(id -un)" 2>/dev/null | cut -d: -f6 || true)"
  HOME="${HOME:-/root}"
  export HOME
fi

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
MANIFEST_FILE="$(mktemp)"
TMP_DIR="${BACKAPER_ROOT}/tmp/backup-$$"
mkdir -p "$TMP_DIR"

cleanup() { rm -f "$MANIFEST_FILE"; rm -rf "$TMP_DIR"; }
trap cleanup EXIT

log() {
  local msg="[backup] $(date -Is) $*"
  # В файл — сразу (панель читает run.log). Не дублируем в stdout, если лог уже туда же редиректится.
  if [[ -n "${BACKAPER_RUN_LOG:-}" ]]; then
    printf '%s\n' "$msg" >> "$BACKAPER_RUN_LOG" || true
  else
    printf '%s\n' "$msg"
  fi
}

# Построчный вывод restic в run.log (прогресс с \r тоже фиксируем как строки)
restic_run() {
  local line ec
  set +e
  if command -v stdbuf >/dev/null 2>&1; then
    stdbuf -oL -eL restic "$@" 2>&1 | while IFS= read -r line || [[ -n "$line" ]]; do
      line="${line//$'\r'/}"
      [[ -z "$line" ]] && continue
      if [[ -n "${BACKAPER_RUN_LOG:-}" ]]; then
        printf '%s\n' "$line" >> "$BACKAPER_RUN_LOG" || true
      else
        printf '%s\n' "$line"
      fi
    done
    ec=${PIPESTATUS[0]}
  else
    restic "$@"
    ec=$?
  fi
  set -e
  return "$ec"
}

file_bytes() {
  stat -c%s "$1" 2>/dev/null || stat -f%z "$1" 2>/dev/null || echo 0
}

log_size() {
  local type="$1" name="$2" path="$3" uploaded="${4:-no}"
  log "SIZE type=${type} name=${name} bytes=$(file_bytes "$path") uploaded=${uploaded}"
}

run_timeout() {
  local secs="$1"; shift
  if command -v timeout >/dev/null 2>&1; then
    timeout "$secs" "$@"
  else
    "$@"
  fi
}

sanitize_slug() {
  echo "$1" | tr ' /:' '___' | tr -cd 'a-zA-Z0-9._-' | cut -c1-120
}

if [[ -n "${BACKAPER_MANIFEST:-}" && -f "$BACKAPER_MANIFEST" ]]; then
  cp "$BACKAPER_MANIFEST" "$MANIFEST_FILE"
else
  echo "BACKAPER_MANIFEST required" >&2
  exit 1
fi

ENV_FILE="$BACKAPER_ROOT/backaper.env"
[[ -f "$ENV_FILE" ]] || ENV_FILE="$BACKAPER_ROOT/restic.env"
# shellcheck source=/dev/null
. "$ENV_FILE"

export RESTIC_REPOSITORY RESTIC_PASSWORD
if ! command -v jq >/dev/null 2>&1; then
  mkdir -p "$HOME/bin"
  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "https://github.com/jqlang/jq/releases/download/jq-1.7.1/jq-linux-amd64" -o "$HOME/bin/jq"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$HOME/bin/jq" "https://github.com/jqlang/jq/releases/download/jq-1.7.1/jq-linux-amd64"
  else
    log "ERROR: jq не найден и нет curl/wget для установки"
    exit 1
  fi
  chmod +x "$HOME/bin/jq"
  export PATH="$HOME/bin:$PATH"
fi

RCLONE_REMOTE="$(jq -r '.rclone_remote' "$MANIFEST_FILE")"
CLOUD_PREFIX="$(jq -r '.cloud_prefix' "$MANIFEST_FILE")"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"

if [[ -z "$RCLONE_REMOTE" || -z "$CLOUD_PREFIX" ]]; then
  log "ERROR: rclone_remote and cloud_prefix required"
  exit 1
fi

DO_FILES="$(jq -r 'if .backup_files == false then "0" else "1" end' "$MANIFEST_FILE")"
DO_DBS="$(jq -r 'if .backup_databases == false then "0" else "1" end' "$MANIFEST_FILE")"
path_count="$(jq '.paths | length' "$MANIFEST_FILE")"
db_count="$(jq '.databases | length' "$MANIFEST_FILE")"
log "mode: files=${DO_FILES} databases=${DO_DBS} | Paths: ${path_count} | Databases: ${db_count} | cloud: ${RCLONE_REMOTE}:${CLOUD_PREFIX}"

log "=== Yandex Disk (${RCLONE_REMOTE}) ==="
rclone about "${RCLONE_REMOTE}:" 2>&1 | sed 's/^/[backup]   /' || log "WARN: rclone about failed"

# --- 1) Полный бэкап путей ---
backup_one_tree() {
  local root="$1"
  local label="$2"
  local slug="$3"

  log "=== FILES: ${label} (${root}) ==="
  if [[ ! -d "$root" ]]; then
    log "SKIP: path missing"
    return 0
  fi

  local exclude_args=()
  local ex
  while IFS= read -r ex; do
    [[ -z "$ex" ]] && continue
    exclude_args+=(--exclude "$ex")
  done < <(jq -r '.exclusions[]?' "$MANIFEST_FILE")

  log "restic backup ${root}"
  # limit-upload: KiB/s — спокойная отправка чанками на Яндекс (меньше шанс kill на Beget)
  local upload_limit="${BACKAPER_UPLOAD_LIMIT_KIB:-2048}"
  set +e
  (
    while true; do
      sleep 60
      echo "[backup] $(date -Is) … ${label}: restic ещё работает (upload ≤${upload_limit} KiB/s)"
    done
  ) &
  local heartbeat_pid=$!

  GOMAXPROCS="${GOMAXPROCS:-1}" GOGC="${GOGC:-40}" \
  restic_run backup "$root" \
    "${exclude_args[@]}" \
    --one-file-system \
    --limit-upload "$upload_limit" \
    --tag "path:${slug}" \
    --host "$(hostname -s 2>/dev/null || hostname)" \
    --verbose=1
  local ec=$?

  kill "$heartbeat_pid" 2>/dev/null || true
  wait "$heartbeat_pid" 2>/dev/null || true
  set -e

  if [[ "$ec" -eq 0 ]]; then
    log "OK files: ${label}"
  elif [[ "$ec" -eq 3 ]]; then
    log "WARN: ${label}: часть файлов без доступа — пропущены, снимок сохранён"
  elif [[ "$ec" -ge 128 ]]; then
    log "ERROR: ${label}: restic убит сигналом (exit ${ec}) — часто лимит RAM на хостинге. Продолжаем."
  else
    log "ERROR: ${label}: restic exit ${ec} — продолжаем"
  fi
}

if [[ "$DO_FILES" == "1" ]]; then
  if [[ "$path_count" -eq 0 ]]; then
    log "WARN: backup_files=true, но paths пуст — пропуск файлов"
  fi
  for i in $(seq 0 $((path_count - 1))); do
    label="$(jq -r ".paths[$i].label // .paths[$i].path" "$MANIFEST_FILE")"
    root="$(jq -r ".paths[$i].path" "$MANIFEST_FILE")"
    slug="$(sanitize_slug "$(jq -r ".paths[$i].slug // .paths[$i].path" "$MANIFEST_FILE")")"

    if [[ "$root" == "~" || "$root" == "~/"* ]]; then
      root="${root/#\~/$HOME}"
    fi

    # Весь хостинг / указанный путь — одним снапшотом
    if [[ "$root" == "$HOME" || "$root" == "$HOME/" ]]; then
      log "Хостинг: один бэкап всего аккаунта ${HOME} (restic сам режет на pack/chunks, upload throttled)"
      backup_one_tree "$HOME" "home" "home"
    else
      [[ -z "$slug" || "$slug" =~ ^_+$ ]] && slug="$(sanitize_slug "$(basename "$root")")"
      [[ -z "$slug" || "$slug" =~ ^_+$ ]] && slug="files"
      backup_one_tree "$root" "$label" "$slug"
    fi
  done

  log "restic forget/prune"
  restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 6 --prune || log "WARN: prune failed"
else
  log "Файлы пропущены (backup_files=false)"
fi

# --- 2) Отдельные дампы БД (из манифеста панели или discovery) ---
if [[ "$DO_DBS" == "1" ]]; then
  if [[ "$db_count" -eq 0 ]]; then
    log "databases пуст — поиск конфигов через backup-databases.sh"
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -f "$SCRIPT_DIR/backup-databases.sh" ]]; then
      set +e
      # BACKAPER_RUN_LOG уже в env — heartbeat дампов пишется туда же
      bash "$SCRIPT_DIR/backup-databases.sh"
      db_ec=$?
      set -e
      if [[ "$db_ec" -ne 0 ]]; then
        log "WARN: backup-databases.sh exit ${db_ec}"
      fi
    else
      log "WARN: backup-databases.sh не найден — дампы пропущены"
    fi
  else
  for i in $(seq 0 $((db_count - 1))); do
    label="$(jq -r ".databases[$i].label // .databases[$i].name" "$MANIFEST_FILE")"
    db_host="$(jq -r ".databases[$i].host" "$MANIFEST_FILE")"
    db_name="$(jq -r ".databases[$i].name" "$MANIFEST_FILE")"
    db_user="$(jq -r ".databases[$i].user" "$MANIFEST_FILE")"
    db_pass="$(jq -r ".databases[$i].password" "$MANIFEST_FILE")"
    db_slug="$(sanitize_slug "$db_name")"

    log "=== DB: ${label} (${db_name}) ==="

    if command -v mariadb-dump >/dev/null 2>&1; then
      dump_bin=(mariadb-dump)
    else
      dump_bin=(mysqldump)
    fi
    mysql_args=(-h "$db_host" -u "$db_user" --password="$db_pass")

    dump_sql="${TMP_DIR}/${db_slug}.sql"
    dump_gz="${dump_sql}.gz"
    log "mysqldump → ${RCLONE_REMOTE}:${CLOUD_PREFIX}/${db_slug}/${TIMESTAMP}.sql.gz"

    if ! run_timeout 1800 "${dump_bin[@]}" "${mysql_args[@]}" \
      --single-transaction --routines --triggers --max-allowed-packet=512M \
      "$db_name" > "$dump_sql" 2>/dev/null; then
      log "ERROR: mysqldump failed for ${label} — skip"
      rm -f "$dump_sql" "$dump_gz"
      continue
    fi

    gzip -cf "$dump_sql" > "$dump_gz"
    log_size "db" "$db_slug" "$dump_gz" "no"
    if ! run_timeout 1800 rclone copyto "$dump_gz" "${RCLONE_REMOTE}:${CLOUD_PREFIX}/${db_slug}/${TIMESTAMP}.sql.gz"; then
      log "ERROR: rclone upload failed for ${label} — skip"
      rm -f "$dump_sql" "$dump_gz"
      continue
    fi
    log_size "db" "$db_slug" "$dump_gz" "yes"
    rm -f "$dump_sql" "$dump_gz"
    log "OK db: ${label}"
  done
  fi
else
  log "Базы пропущены (backup_databases=false)"
fi

log "BACKUP_COMPLETE"
