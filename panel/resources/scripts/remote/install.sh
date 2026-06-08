#!/usr/bin/env bash
# Backaper: установка restic + rclone + init репозитория на удалённом сервере
# Запуск (из панели по SSH или вручную):
#   env BACKAPER_RCLONE_REMOTE=yandex \
#       BACKAPER_RESTIC_PASSWORD='secret' \
#       BACKAPER_RESTIC_REPOSITORY='rclone:yandex:restic-repo/my-server' \
#       BACKAPER_CLOUD_PREFIX='backaper/my-server' \
#       bash install.sh
set -euo pipefail

BACKAPER_ROOT="${BACKAPER_ROOT:-$HOME/backaper}"
BIN_DIR="${BIN_DIR:-$HOME/bin}"
RESTIC_VERSION="${RESTIC_VERSION:-0.16.4}"
ARCH="${ARCH:-linux-amd64}"

mkdir -p "$BACKAPER_ROOT"/{scripts,logs,tmp} "$BIN_DIR"
export PATH="$BIN_DIR:$PATH"

log() { echo "[install] $*"; }

install_restic() {
  if command -v restic >/dev/null 2>&1; then
    log "restic: $(restic version | head -1)"
    return
  fi
  log "Installing restic ${RESTIC_VERSION}..."
  tmp="$(mktemp)"
  curl -fsSL "https://github.com/restic/restic/releases/download/v${RESTIC_VERSION}/restic_${RESTIC_VERSION}_${ARCH}.bz2" -o "${tmp}.bz2"
  bunzip2 -f "${tmp}.bz2"
  chmod +x "$tmp"
  mv "$tmp" "$BIN_DIR/restic"
}

install_rclone() {
  if command -v rclone >/dev/null 2>&1; then
    log "rclone: $(rclone version | head -1)"
    return
  fi
  log "Installing rclone..."
  tmpdir="$(mktemp -d)"
  curl -fsSL "https://downloads.rclone.org/rclone-current-${ARCH}.zip" -o "$tmpdir/rclone.zip"
  unzip -q "$tmpdir/rclone.zip" -d "$tmpdir"
  install -m 755 "$tmpdir"/rclone-*-linux-amd64/rclone "$BIN_DIR/rclone"
  rm -rf "$tmpdir"
}

write_rclone_config() {
  : "${BACKAPER_RCLONE_REMOTE:?BACKAPER_RCLONE_REMOTE required}"

  token_file="${BACKAPER_RCLONE_TOKEN_FILE:-$BACKAPER_ROOT/rclone-token.json}"

  if [[ -f "$token_file" ]]; then
    token="$(tr -d '\n' < "$token_file")"
    if rclone config show "${BACKAPER_RCLONE_REMOTE}" >/dev/null 2>&1; then
      log "Updating rclone remote [${BACKAPER_RCLONE_REMOTE}] token..."
      rclone config update "${BACKAPER_RCLONE_REMOTE}" token "$token" --non-interactive
    else
      log "Creating rclone remote [${BACKAPER_RCLONE_REMOTE}]..."
      rclone config create "${BACKAPER_RCLONE_REMOTE}" yandex config_token "$token" --non-interactive
    fi
    if ! rclone lsd "${BACKAPER_RCLONE_REMOTE}:" >/dev/null 2>&1; then
      echo "rclone remote [${BACKAPER_RCLONE_REMOTE}] unreachable — check OAuth token" >&2
      exit 1
    fi
    log "rclone remote [${BACKAPER_RCLONE_REMOTE}] OK"
    return
  fi

  if rclone lsd "${BACKAPER_RCLONE_REMOTE}:" >/dev/null 2>&1; then
    log "rclone remote [${BACKAPER_RCLONE_REMOTE}] already works (no token file uploaded)"
    return
  fi

  echo "rclone remote [${BACKAPER_RCLONE_REMOTE}] not configured." >&2
  echo "Put OAuth token in ${token_file} and run install again." >&2
  exit 1
}

write_backaper_env() {
  : "${BACKAPER_RESTIC_REPOSITORY:?}"
  : "${BACKAPER_RESTIC_PASSWORD:?}"
  : "${BACKAPER_RCLONE_REMOTE:?}"
  : "${BACKAPER_CLOUD_PREFIX:?}"

  cat > "$BACKAPER_ROOT/backaper.env" <<EOF
export RESTIC_REPOSITORY='${BACKAPER_RESTIC_REPOSITORY}'
export RESTIC_PASSWORD='${BACKAPER_RESTIC_PASSWORD}'
export BACKAPER_RCLONE_REMOTE='${BACKAPER_RCLONE_REMOTE}'
export BACKAPER_CLOUD_PREFIX='${BACKAPER_CLOUD_PREFIX}'
export PATH="$BIN_DIR:\$PATH"
EOF
  chmod 600 "$BACKAPER_ROOT/backaper.env"

  # совместимость со старым именем
  ln -sf backaper.env "$BACKAPER_ROOT/restic.env"
  log "backaper.env written (cloud: ${BACKAPER_CLOUD_PREFIX})"
}

init_restic() {
  # shellcheck source=/dev/null
  . "$BACKAPER_ROOT/backaper.env"
  if restic snapshots >/dev/null 2>&1; then
    log "restic repository already initialized"
    return
  fi
  log "restic init → ${RESTIC_REPOSITORY}"
  restic init
}

install_restic
install_rclone
write_rclone_config
write_backaper_env
init_restic

log "SETUP_COMPLETE"
