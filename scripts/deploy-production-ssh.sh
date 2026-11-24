#!/usr/bin/env bash
# Mac wrapper: SSH to production and run deploy-production.sh on the server.
#
# Usage (from Mac, repo root or any cwd):
#   ./scripts/deploy-production-ssh.sh
#   MYSQL_PASSWORD='…' ./scripts/deploy-production-ssh.sh
#
# Passes through env vars the remote script understands:
#   MYSQL_PASSWORD, MYSQL_USER, MYSQL_HOST, SKIP_GIT, SKIP_BUILD, SKIP_MIGRATE,
#   VERIFY_URL, APP_ROOT, PHP_BIN, MYSQL_BIN_DIR

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REMOTE_SCRIPT="${SCRIPT_DIR}/deploy-production.sh"
SSH_HOST="${SSH_HOST:-45.129.39.202}"
SSH_PORT="${SSH_PORT:-50214}"
SSH_USER="${SSH_USER:-root}"
REMOTE_APP_ROOT="${REMOTE_APP_ROOT:-/www/wwwroot2/dastgheib}"

[[ -f "${REMOTE_SCRIPT}" ]] || {
  printf 'missing %s\n' "${REMOTE_SCRIPT}" >&2
  exit 1
}

REMOTE_ENV=""
for key in MYSQL_PASSWORD MYSQL_USER MYSQL_HOST SKIP_GIT SKIP_BUILD SKIP_MIGRATE VERIFY_URL APP_ROOT PHP_BIN MYSQL_BIN_DIR; do
  if [[ -n "${!key-}" ]]; then
    REMOTE_ENV+="${key}=$(printf '%q' "${!key}") "
  fi
done

printf 'Deploying via %s@%s -p %s → %s\n' "${SSH_USER}" "${SSH_HOST}" "${SSH_PORT}" "${REMOTE_APP_ROOT}"

# Prefer on-server copy if present (after first pull); otherwise pipe this repo's script.
if ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
  "test -x ${REMOTE_APP_ROOT}/scripts/deploy-production.sh"; then
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "${REMOTE_ENV}bash ${REMOTE_APP_ROOT}/scripts/deploy-production.sh"
else
  ssh -p "${SSH_PORT}" "${SSH_USER}@${SSH_HOST}" \
    "${REMOTE_ENV}bash -s" < "${REMOTE_SCRIPT}"
fi
