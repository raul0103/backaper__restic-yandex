#!/usr/bin/env bash
# Совместимость: вызывает install.sh
exec "$(dirname "$0")/install.sh" "$@"
