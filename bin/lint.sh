#!/usr/bin/env bash
# Run the same checks as CI locally, in Docker (no PHP needed on the machine).
# Usage: bin/lint.sh            → syntax + WPCS + PHPStan
#        bin/lint.sh fix        → auto-fix WPCS issues
set -euo pipefail

cd "$(dirname "$0")/.."
run() { docker run --rm -u "$(id -u):$(id -g)" -v "$PWD:/app" -w /app composer:2 "$@"; }

[ -d vendor ] || run composer install --no-interaction --no-progress
if [ "${1:-}" = fix ]; then
	run composer lint:fix || true
	exit 0
fi
run sh -c "find plugins theme -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null"
run composer lint
run composer analyse
