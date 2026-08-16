#!/bin/sh
set -eu

base_url="${1:-http://127.0.0.1:8080}"

php artisan migrate:status --no-interaction >/dev/null
curl --fail --silent --show-error "${base_url%/}/up" >/dev/null
curl --fail --silent --show-error "${base_url%/}/" >/dev/null

echo "Release smoke checks passed."
