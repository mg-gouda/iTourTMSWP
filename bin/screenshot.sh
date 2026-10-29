#!/usr/bin/env bash
# Capture a page with headless Chrome (fresh profile, no cache).
# Usage: bin/screenshot.sh <url> <out.png> [width,height]
#   bin/screenshot.sh http://localhost:8091/ theme/wp-itourtms/screenshot.png 1200,900   ← theme screenshot (WordPress.org: 1200×900, 4:3)
set -euo pipefail

profile="$(mktemp -d)"
trap 'rm -rf "$profile"' EXIT
google-chrome --headless=new --disable-gpu --no-sandbox --hide-scrollbars --user-data-dir="$profile" \
	--window-size="${3:-1200,900}" --virtual-time-budget=3000 --screenshot="$2" "$1" >/dev/null 2>&1
echo "Saved $2"
