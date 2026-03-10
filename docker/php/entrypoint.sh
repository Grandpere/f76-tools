#!/bin/sh
set -eu

APP_VAR_DIR="/var/www/html/var"

mkdir -p \
  "${APP_VAR_DIR}/log" \
  "${APP_VAR_DIR}/cache" \
  "${APP_VAR_DIR}/sessions" \
  "${APP_VAR_DIR}/data/roadmap_uploads"

if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data "${APP_VAR_DIR}" || true
  chmod -R ug+rwX "${APP_VAR_DIR}" || true
fi

exec "$@"
