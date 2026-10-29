#!/usr/bin/env bash
# Print every page URL in a site's sitemaps (the redirect baseline before a cutover).
# Usage: bin/url-list.sh https://example.com > urls.txt
set -euo pipefail

site="${1:?usage: $0 https://example.com}"
curl -fsS "$site/sitemap_index.xml" | grep -o '<loc>[^<]*' | sed 's/<loc>//' | while read -r map; do
	curl -fsS "$map" | grep -o '<loc>[^<]*' | sed 's/<loc>//' | grep -viE '\.(jpe?g|png|webp|gif)$' || true
done | sort -u
