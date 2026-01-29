#!/usr/bin/env bash
set -euo pipefail

errors=0

if rg -n "\?\.[A-Za-z_$][\\w$]*\s*=" resources/js; then
  echo "ERROR: Optional-chaining assignment on LHS is not allowed."
  errors=1
fi

if [ "$errors" -ne 0 ]; then
  exit 1
fi
