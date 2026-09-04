#!/usr/bin/env bash
set -Eeuo pipefail

APP_PATH="${APP_PATH:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"

cd "$APP_PATH"

if [[ ! -f .env ]]; then
    echo "ERROR: .env bulunamadı. .env.production.example dosyasını kopyalayıp değerleri doldurun." >&2
    exit 1
fi

"$PHP_BIN" artisan down --render=errors::503 --retry=60 || true
trap '"$PHP_BIN" artisan up || true' EXIT

"$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
"$NPM_BIN" ci
"$NPM_BIN" run build

"$PHP_BIN" artisan storage:link || true
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true

"$PHP_BIN" artisan up
trap - EXIT

echo "ASYA deployment tamamlandı: $APP_PATH"
