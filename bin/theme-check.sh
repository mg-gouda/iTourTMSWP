#!/usr/bin/env bash
# Run the WordPress.org Theme Check plugin on the dev site against a theme (default wp-itourtms).
set -euo pipefail

cd "$(dirname "$0")/.."
docker cp tools/theme-check.php itourwp-wp:/var/www/html/theme-check-run.php >/dev/null
docker exec itourwp-wp chown www-data: /var/www/html/theme-check-run.php
bin/dev.sh wp plugin is-installed theme-check || bin/dev.sh wp plugin install theme-check --activate >/dev/null
bin/dev.sh wp eval-file theme-check-run.php "${1:-wp-itourtms}"
docker exec itourwp-wp rm -f /var/www/html/theme-check-run.php
