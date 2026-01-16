#!/usr/bin/env bash
set -euo pipefail

DEBOUNCE_SEC="${DEBOUNCE_SEC:-3}"
LOG_FILE="${LOG_FILE:-ci.log}"
LOCK_DIR=".ci.lock"

require() { command -v "$1" >/dev/null 2>&1 || { echo "Missing: $1"; exit 1; }; }
require git
require fswatch

git rev-parse --is-inside-work-tree >/dev/null

run_ci() {
  if mkdir "$LOCK_DIR" 2>/dev/null; then
    trap 'rmdir "$LOCK_DIR" 2>/dev/null || true' RETURN
    echo "==> $(date) running CI..."
    set +e
    ./ci.sh 2>&1 | tee "$LOG_FILE"
    set -e
    echo "==> $(date) CI finished"
  fi
}

# Fingerprint tracked file CONTENT in the working tree (robust, order-stable)
fingerprint_worktree() {
  # List tracked files with their blob-ish hash for current working tree content
  # This changes when you edit any tracked file (staged or not).
  git ls-files -z -- . ':!ci.log' \
    | xargs -0 -I{} sh -c 'git hash-object "{}" 2>/dev/null && printf "  %s\n" "{}"' \
    | shasum \
    | cut -d' ' -f1
}

echo "Watching repo (tracked-file content changes; debounce ${DEBOUNCE_SEC}s)"
echo "Log: ${LOG_FILE} | Stop: Ctrl+C"

last_fingerprint="$(fingerprint_worktree)"

fswatch -o . | while read -r _; do
  [ -d "$LOCK_DIR" ] && continue

  sleep "$DEBOUNCE_SEC"

  new_fingerprint="$(fingerprint_worktree)"
  [ "$new_fingerprint" = "$last_fingerprint" ] && continue

  last_fingerprint="$new_fingerprint"
  run_ci || true
done
