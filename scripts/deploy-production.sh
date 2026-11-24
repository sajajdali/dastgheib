#!/usr/bin/env bash
# Production deploy for dastgheib (run ON the server).
#
# Typical Mac SSH wrapper (also see scripts/deploy-production-ssh.sh):
#   ssh -p 50214 root@45.129.39.202 'bash -s' < scripts/deploy-production.sh
#   # or, after this file is on the server:
#   ssh -p 50214 root@45.129.39.202 'bash /www/wwwroot2/dastgheib/scripts/deploy-production.sh'
#
# Optional env:
#   APP_ROOT          default /www/wwwroot2/dastgheib
#   VERIFY_URL        default https://demo.s8n.ir/
#   MYSQL_USER        default root
#   MYSQL_PASSWORD    MySQL password (empty = socket/auth plugin default)
#   MYSQL_HOST        default localhost
#   MYSQL_BIN_DIR     e.g. /www/server/mysql/bin
#   PHP_BIN           e.g. /www/server/php/83/bin/php
#   SKIP_GIT=1        skip git pull
#   SKIP_BUILD=1      skip frontend build (still copies dist if present)
#   SKIP_MIGRATE=1    skip artisan migrations
#
# Safety: never drops databases or deletes tenant/app data.

set -euo pipefail

APP_ROOT="${APP_ROOT:-/www/wwwroot2/dastgheib}"
VERIFY_URL="${VERIFY_URL:-https://demo.s8n.ir/}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_HOST="${MYSQL_HOST:-localhost}"
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_ROOT="${APP_ROOT}/backups"
DUMP_DIR="${BACKUP_ROOT}/db-${STAMP}"
ZIP_PATH="${BACKUP_ROOT}/db-${STAMP}.zip"

log() { printf '[deploy] %s\n' "$*"; }
die() { printf '[deploy] ERROR: %s\n' "$*" >&2; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || die "missing command: $1"
}

resolve_mysql_bins() {
  local candidates=()
  if [[ -n "${MYSQL_BIN_DIR:-}" ]]; then
    candidates+=("${MYSQL_BIN_DIR}")
  fi
  candidates+=(
    /www/server/mysql/bin
    /usr/local/mysql/bin
    /usr/bin
  )
  local dir
  for dir in "${candidates[@]}"; do
    if [[ -x "${dir}/mysql" && -x "${dir}/mysqldump" ]]; then
      MYSQL_CLI="${dir}/mysql"
      MYSQLDUMP_CLI="${dir}/mysqldump"
      return 0
    fi
  done
  require_cmd mysql
  require_cmd mysqldump
  MYSQL_CLI="$(command -v mysql)"
  MYSQLDUMP_CLI="$(command -v mysqldump)"
}

resolve_php() {
  if [[ -n "${PHP_BIN:-}" && -x "${PHP_BIN}" ]]; then
    return 0
  fi
  local candidates=(
    /www/server/php/83/bin/php
    /www/server/php/82/bin/php
    /www/server/php/81/bin/php
  )
  local p
  for p in "${candidates[@]}"; do
    if [[ -x "$p" ]]; then
      PHP_BIN="$p"
      return 0
    fi
  done
  require_cmd php
  PHP_BIN="$(command -v php)"
}

run_mysql() {
  if [[ -n "${MYSQL_PASSWORD}" ]]; then
    MYSQL_PWD="${MYSQL_PASSWORD}" "$@"
  else
    "$@"
  fi
}

backup_all_databases() {
  log "Backing up all MySQL databases → ${ZIP_PATH}"
  mkdir -p "${DUMP_DIR}"

  local -a base_args=(-h "${MYSQL_HOST}" -u "${MYSQL_USER}" --single-transaction --routines --triggers --events --default-character-set=utf8mb4)
  local dbs
  dbs="$(
    run_mysql "${MYSQL_CLI}" "${base_args[@]}" -N -e \
      "SHOW DATABASES WHERE \`Database\` NOT IN ('information_schema','performance_schema','sys');"
  )"
  [[ -n "${dbs}" ]] || die "no databases returned by SHOW DATABASES"

  local db count=0
  while IFS= read -r db; do
    [[ -n "${db}" ]] || continue
    log "  dumping ${db}"
    run_mysql "${MYSQLDUMP_CLI}" "${base_args[@]}" --databases "${db}" \
      > "${DUMP_DIR}/${db}.sql"
    count=$((count + 1))
  done <<< "${dbs}"

  [[ "${count}" -gt 0 ]] || die "dumped zero databases"

  (
    cd "${BACKUP_ROOT}"
    zip -r -q "$(basename "${ZIP_PATH}")" "$(basename "${DUMP_DIR}")"
  )

  [[ -f "${ZIP_PATH}" ]] || die "zip not created: ${ZIP_PATH}"
  local zip_size
  zip_size="$(wc -c < "${ZIP_PATH}" | tr -d ' ')"
  [[ "${zip_size}" -gt 0 ]] || die "backup zip is empty: ${ZIP_PATH}"

  log "Backup OK: ${ZIP_PATH} (${zip_size} bytes, ${count} databases)"
  # Keep the unzipped dump dir for quick inspect; zip is the gate for continuing.
}

git_pull() {
  if [[ "${SKIP_GIT:-0}" == "1" ]]; then
    log "SKIP_GIT=1 — skipping git pull"
    return 0
  fi
  require_cmd git
  log "git pull origin main in ${APP_ROOT}"
  cd "${APP_ROOT}"
  git pull origin main
  log "HEAD: $(git rev-parse HEAD)"
}

frontend_build_and_publish() {
  cd "${APP_ROOT}"
  local frontend_dir=""
  if [[ -f "${APP_ROOT}/Frontend/package.json" ]]; then
    frontend_dir="${APP_ROOT}/Frontend"
  elif [[ -f "${APP_ROOT}/package.json" ]]; then
    frontend_dir="${APP_ROOT}"
  else
    die "no Frontend/package.json or root package.json found"
  fi

  local dist_dir="${frontend_dir}/dist"
  if [[ "${SKIP_BUILD:-0}" != "1" ]]; then
    require_cmd npm
    log "Building frontend in ${frontend_dir}"
    (
      cd "${frontend_dir}"
      npm run build
    )
  else
    log "SKIP_BUILD=1 — using existing dist"
  fi

  [[ -f "${dist_dir}/index.html" ]] || die "missing ${dist_dir}/index.html"
  [[ -d "${dist_dir}/assets" ]] || die "missing ${dist_dir}/assets"

  local public_dir="${APP_ROOT}/backend/public"
  [[ -d "${public_dir}" ]] || die "missing ${public_dir}"

  log "Publishing dist → backend/public (index.html, app.html, assets/)"
  mkdir -p "${public_dir}/assets"
  cp "${dist_dir}/index.html" "${public_dir}/index.html"
  cp "${dist_dir}/index.html" "${public_dir}/app.html"
  # Merge/overwrite built assets; do not wipe other public files or storage.
  cp -a "${dist_dir}/assets/." "${public_dir}/assets/"
  log "Frontend publish OK"
}

run_migrations() {
  if [[ "${SKIP_MIGRATE:-0}" == "1" ]]; then
    log "SKIP_MIGRATE=1 — skipping migrations"
    return 0
  fi
  resolve_php
  local backend="${APP_ROOT}/backend"
  [[ -f "${backend}/artisan" ]] || die "missing ${backend}/artisan"

  log "Using PHP: ${PHP_BIN}"
  cd "${backend}"
  log "Central migrate (--force)"
  "${PHP_BIN}" artisan migrate --force
  log "Tenant migrate (--force) for all tenants"
  "${PHP_BIN}" artisan tenants:migrate --force
  log "Migrations OK"
}

verify_assets() {
  require_cmd curl
  require_cmd grep
  log "Verifying live assets via ${VERIFY_URL}"
  local html matches
  html="$(curl -sS --max-time 30 "${VERIFY_URL}")" \
    || die "curl failed for ${VERIFY_URL}"
  matches="$(printf '%s' "${html}" | grep -o 'assets/index-[^"]*' || true)"
  if [[ -z "${matches}" ]]; then
    die "no assets/index-* found in ${VERIFY_URL}"
  fi
  log "Live asset hashes:"
  printf '%s\n' "${matches}"
}

main() {
  [[ "$(id -u)" -eq 0 ]] || log "warning: not running as root (MySQL/file perms may fail)"
  [[ -d "${APP_ROOT}" ]] || die "APP_ROOT not found: ${APP_ROOT}"

  resolve_mysql_bins
  require_cmd zip

  backup_all_databases
  git_pull
  frontend_build_and_publish
  run_migrations
  verify_assets

  log "Deploy finished successfully."
}

main "$@"
