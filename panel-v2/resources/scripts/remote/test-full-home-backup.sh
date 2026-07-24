#!/usr/bin/env bash
# Совместимость: старое имя → backup-files.sh
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/backup-files.sh" "$@"
