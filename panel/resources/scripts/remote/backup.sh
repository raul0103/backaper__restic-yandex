#!/usr/bin/env bash
# Backaper backup:
#   1) restic snapshot — файлы проекта с исключениями
#   2) rclone — дамп БД в {cloud}/databases/{db_name}/{date}.sql.gz
#   3) rclone — архив проекта в {cloud}/projects/{project}/{date}.tar.gz
set -euo pipefail

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
MANIFEST_FILE="$(mktemp)"
TMP_DIR="${BACKAPER_ROOT}/tmp/backup-$$"
mkdir -p "$TMP_DIR"

cleanup() { rm -f "$MANIFEST_FILE"; rm -rf "$TMP_DIR"; }
trap cleanup EXIT

log() { echo "[backup] $(date -Is) $*"; }

file_bytes() {
  stat -c%s "$1" 2>/dev/null || stat -f%z "$1" 2>/dev/null || echo 0
}

log_size() {
  local type="$1"
  local name="$2"
  local path="$3"
  local uploaded="${4:-no}"
  local bytes
  bytes="$(file_bytes "$path")"
  log "SIZE type=${type} name=${name} bytes=${bytes} uploaded=${uploaded}"
}

log_cloud_quota() {
  log "=== Yandex Disk (${RCLONE_REMOTE}) ==="
  if ! rclone about "${RCLONE_REMOTE}:" 2>&1 | sed 's/^/[backup]   /'; then
    log "WARN: could not read cloud quota (rclone about failed)"
  fi
}

sanitize_slug() {
  echo "$1" | tr ' /:' '___' | tr -cd 'a-zA-Z0-9._-' | cut -c1-120
}

if [[ -n "${BACKAPER_MANIFEST_B64:-}" ]]; then
  echo "$BACKAPER_MANIFEST_B64" | base64 -d > "$MANIFEST_FILE"
elif [[ -n "${BACKAPER_MANIFEST:-}" && -f "$BACKAPER_MANIFEST" ]]; then
  cp "$BACKAPER_MANIFEST" "$MANIFEST_FILE"
else
  echo "BACKAPER_MANIFEST_B64 required" >&2
  exit 1
fi

ENV_FILE="$BACKAPER_ROOT/backaper.env"
[[ -f "$ENV_FILE" ]] || ENV_FILE="$BACKAPER_ROOT/restic.env"
# shellcheck source=/dev/null
. "$ENV_FILE"

export RESTIC_REPOSITORY RESTIC_PASSWORD
RCLONE_REMOTE="$(jq -r '.rclone_remote' "$MANIFEST_FILE")"
CLOUD_PREFIX="$(jq -r '.cloud_prefix' "$MANIFEST_FILE")"
RCLONE_REMOTE="${RCLONE_REMOTE:-${BACKAPER_RCLONE_REMOTE:-}}"
CLOUD_PREFIX="${CLOUD_PREFIX:-${BACKAPER_CLOUD_PREFIX:-}}"
TIMESTAMP="$(date +%Y-%m-%d_%H-%M-%S)"

if [[ -z "$RCLONE_REMOTE" || -z "$CLOUD_PREFIX" ]]; then
  log "ERROR: rclone_remote and cloud_prefix required"
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  JQ_BIN="$HOME/bin/jq"
  curl -fsSL "https://github.com/jqlang/jq/releases/download/jq-1.7.1/jq-linux-amd64" -o "$JQ_BIN"
  chmod +x "$JQ_BIN"
  export PATH="$HOME/bin:$PATH"
fi

project_count="$(jq '.projects | length' "$MANIFEST_FILE")"
log "Projects: $project_count | cloud: ${RCLONE_REMOTE}:${CLOUD_PREFIX}"
log_cloud_quota

for i in $(seq 0 $((project_count - 1))); do
  name="$(jq -r ".projects[$i].name" "$MANIFEST_FILE")"
  slug="$(jq -r ".projects[$i].slug" "$MANIFEST_FILE")"
  root="$(jq -r ".projects[$i].root_path" "$MANIFEST_FILE")"
  session_table="$(jq -r ".projects[$i].session_table" "$MANIFEST_FILE")"
  db_host="$(jq -r ".projects[$i].database.host" "$MANIFEST_FILE")"
  db_name="$(jq -r ".projects[$i].database.name" "$MANIFEST_FILE")"
  db_user="$(jq -r ".projects[$i].database.user" "$MANIFEST_FILE")"
  db_pass="$(jq -r ".projects[$i].database.password" "$MANIFEST_FILE")"

  slug="$(sanitize_slug "${slug:-$name}")"
  db_slug="$(sanitize_slug "$db_name")"

  log "=== ${name} (${root}) ==="

  if [[ ! -d "$root" ]]; then
    log "SKIP: path missing"
    continue
  fi

  if command -v mariadb >/dev/null 2>&1; then
    mysql_bin=(mariadb)
  else
    mysql_bin=(mysql)
  fi
  if command -v mariadb-dump >/dev/null 2>&1; then
    dump_bin=(mariadb-dump)
  else
    dump_bin=(mysqldump)
  fi
  # MariaDB 11+ ignores MYSQL_PWD for mysqldump; pass credentials explicitly.
  mysql_args=(-h "$db_host" -u "$db_user" --password="$db_pass")

  mysql_exec() {
    "${mysql_bin[@]}" "${mysql_args[@]}" "$db_name" -N -e "$1"
  }

  log "TRUNCATE ${session_table}"
  mysql_exec "TRUNCATE TABLE \`${session_table}\`;" 2>/dev/null || log "WARN: session truncate failed"

  # --- DB dump → rclone: .../databases/{db}/{timestamp}.sql.gz ---
  dump_sql="${TMP_DIR}/${db_slug}.sql"
  dump_gz="${dump_sql}.gz"
  log "mysqldump → ${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/${db_slug}/${TIMESTAMP}.sql.gz"
  "${dump_bin[@]}" "${mysql_args[@]}" \
    --single-transaction --routines --triggers "$db_name" > "$dump_sql"
  gzip -cf "$dump_sql" > "$dump_gz"
  log_size "db" "$db_slug" "$dump_gz" "no"
  rclone copyto "$dump_gz" "${RCLONE_REMOTE}:${CLOUD_PREFIX}/databases/${db_slug}/${TIMESTAMP}.sql.gz"
  log_size "db" "$db_slug" "$dump_gz" "yes"
  rm -f "$dump_sql" "$dump_gz"

  # --- restic: snapshot файлов проекта (без дампа в папке) ---
  exclude_args=()
  while IFS= read -r ex; do
    [[ -z "$ex" ]] && continue
    exclude_args+=(--exclude "$ex")
  done < <(jq -r ".projects[$i].exclusions[]?" "$MANIFEST_FILE")

  log "restic backup ${root}"
  restic backup "$root" \
    "${exclude_args[@]}" \
    --tag "project:${slug}" \
    --host "$(hostname -s 2>/dev/null || hostname)"

  # --- rclone: архив проекта по дате → .../projects/{slug}/{timestamp}.tar.gz ---
  tar_excludes=()
  while IFS= read -r ex; do
    [[ -z "$ex" ]] && continue
    clean="${ex//\*\*/}"
    clean="${clean#/}"
    clean="${clean%/}"
    [[ -n "$clean" ]] && tar_excludes+=(--exclude="$clean")
  done < <(jq -r ".projects[$i].exclusions[]?" "$MANIFEST_FILE")

  project_tar="${TMP_DIR}/${slug}.tar.gz"
  parent="$(dirname "$root")"
  base="$(basename "$root")"
  log "tar → ${RCLONE_REMOTE}:${CLOUD_PREFIX}/projects/${slug}/${TIMESTAMP}.tar.gz"
  tar -czf "$project_tar" -C "$parent" "${tar_excludes[@]}" "$base"
  log_size "tar" "$slug" "$project_tar" "no"
  rclone copyto "$project_tar" "${RCLONE_REMOTE}:${CLOUD_PREFIX}/projects/${slug}/${TIMESTAMP}.tar.gz"
  log_size "tar" "$slug" "$project_tar" "yes"
  rm -f "$project_tar"

  log "OK: ${name}"
done

log "restic forget/prune"
restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 6 --prune || log "WARN: prune failed"

log "BACKUP_COMPLETE"
