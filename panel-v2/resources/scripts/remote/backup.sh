#!/usr/bin/env bash
# Backaper v2:
#   1) restic — полный бэкап указанных путей (с исключениями)
#   2) rclone — отдельные дампы БД в {cloud}/databases/{db}/{date}.sql.gz
set -euo pipefail

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
MANIFEST_FILE="$(mktemp)"
TMP_DIR="${BACKAPER_ROOT}/tmp/backup-$$"
mkdir -p "$TMP_DIR"

cleanup() { rm -f "$MANIFEST_FILE"; rm -rf "$TMP_DIR"; }
trap cleanup EXIT

log() { echo "[backup] $(date -Is) $*"; }

# Построчный вывод в лог (иначе при redirect в файл restic «молчит» часами)
restic_run() {
  if command -v stdbuf >/dev/null 2>&1; then
    stdbuf -oL -eL restic "$@"
  else
    restic "$@"
  fi
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
RCLONE_REMOTE="$(jq -r '.rclone_remote' "$MANIFEST_FILE")"
CLOUD_PREFIX="$(jq -r '.cloud_prefix' "$MANIFEST_FILE")"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"

if [[ -z "$RCLONE_REMOTE" || -z "$CLOUD_PREFIX" ]]; then
  log "ERROR: rclone_remote and cloud_prefix required"
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  mkdir -p "$HOME/bin"
  curl -fsSL "https://github.com/jqlang/jq/releases/download/jq-1.7.1/jq-linux-amd64" -o "$HOME/bin/jq"
  chmod +x "$HOME/bin/jq"
  export PATH="$HOME/bin:$PATH"
fi

path_count="$(jq '.paths | length' "$MANIFEST_FILE")"
db_count="$(jq '.databases | length' "$MANIFEST_FILE")"
log "Paths: ${path_count} | Databases: ${db_count} | cloud: ${RCLONE_REMOTE}:${CLOUD_PREFIX}"

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

# --- 2) Отдельные дампы БД ---
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
  mysql_args=(-h "$db_host" -u "$db_user" --password="$db_pass" --connect-timeout=10)

  dump_sql="${TMP_DIR}/${db_slug}.sql"
  dump_gz="${dump_sql}.gz"
  log "mysqldump → ${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/${db_slug}/${TIMESTAMP}.sql.gz"

  if ! run_timeout 1800 "${dump_bin[@]}" "${mysql_args[@]}" \
    --single-transaction --routines --triggers --max-allowed-packet=512M \
    "$db_name" > "$dump_sql" 2>/dev/null; then
    log "ERROR: mysqldump failed for ${label} — skip"
    rm -f "$dump_sql" "$dump_gz"
    continue
  fi

  gzip -cf "$dump_sql" > "$dump_gz"
  log_size "db" "$db_slug" "$dump_gz" "no"
  if ! run_timeout 1800 rclone copyto "$dump_gz" "${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/${db_slug}/${TIMESTAMP}.sql.gz"; then
    log "ERROR: rclone upload failed for ${label} — skip"
    rm -f "$dump_sql" "$dump_gz"
    continue
  fi
  log_size "db" "$db_slug" "$dump_gz" "yes"
  rm -f "$dump_sql" "$dump_gz"
  log "OK db: ${label}"
done

log "restic forget/prune"
restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 6 --prune || log "WARN: prune failed"

log "BACKUP_COMPLETE"
