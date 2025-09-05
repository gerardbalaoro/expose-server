#!/usr/bin/env bash
set -euo pipefail

: "${PORT:=8080}"
: "${DOMAIN:=localhost}"

if [[ "$#" -gt 0 ]]; then
  exec /src/expose-server serve "$@"
else
  exec /src/expose-server serve "${DOMAIN}" --port "${PORT}" --validateAuthTokens
fi