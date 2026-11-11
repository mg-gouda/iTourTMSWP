#!/usr/bin/env bash
# Build release packages (needs the dev site running: bin/dev.sh).
#   dist/wp-itourtms.zip               → upload to WordPress.org (no License tab: inc/license.php left out)
#   dist/themeforest/wp-itourtms.zip   → ThemeForest "Installable WordPress file" (with License tab)
#   dist/themeforest/WP-iTourTMS.zip   → ThemeForest "Main file": theme, child theme, plugins, documentation, licensing
# The dev public key (theme/wp-itourtms/inc/license-pubkey.pem) never ships. For a real release pass the
# MG License Server's key:  ITOUR_LICENSE_PUBKEY_FILE=/path/pubkey.pem bin/package.sh
# Also refreshes theme/wp-itourtms/languages/wp-itourtms.pot.
set -euo pipefail

cd "$(dirname "$0")/.."
root="$PWD"
ver="$(sed -n 's/^Version:[[:space:]]*//p' theme/wp-itourtms/style.css | tr -d '\r')"
rm -rf dist && mkdir -p dist/themeforest dist/stage

zipdir() { # zipdir <parent-dir> <folder> <out.zip> [excluded paths relative to parent…] ; EXTRA="src=dest" adds a file
	local parent="$1" folder="$2" out="$3"
	shift 3
	(cd "$parent" && python3 -I -c 'import os, sys, zipfile, pathlib
src, out, skip = pathlib.Path(sys.argv[1]), sys.argv[2], set(sys.argv[3:])
with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
    for p in sorted(src.rglob("*")):
        if p.is_file() and p.as_posix() not in skip and not any(part.startswith(".") for part in p.parts):
            z.write(p, p.as_posix())
    extra = os.environ.get("EXTRA", "")
    if extra:
        s, d = extra.split("=", 1)
        z.write(s, d)' "$folder" "$out" "$@")
}

bin/i18n.sh mo

echo "→ documentation"
docker cp tools/build-docs.php itourwp-wp:/var/www/html/build-docs.php >/dev/null
docker exec itourwp-wp chown www-data: /var/www/html/build-docs.php
bin/dev.sh wp eval-file build-docs.php /var/www/html/documentation.html >/dev/null
mkdir -p dist/stage/documentation
docker cp itourwp-wp:/var/www/html/documentation.html dist/stage/documentation/index.html >/dev/null
docker exec itourwp-wp rm -f /var/www/html/build-docs.php /var/www/html/documentation.html

echo "→ theme zips"
key=wp-itourtms/inc/license-pubkey.pem
zipdir theme wp-itourtms "$root/dist/wp-itourtms.zip" wp-itourtms/inc/license.php "$key"
if [ -n "${ITOUR_LICENSE_PUBKEY_FILE:-}" ]; then
	EXTRA="$(realpath "$ITOUR_LICENSE_PUBKEY_FILE")=$key" zipdir theme wp-itourtms "$root/dist/themeforest/wp-itourtms.zip" "$key"
else
	echo "  ! no ITOUR_LICENSE_PUBKEY_FILE: the ThemeForest build will need the key set in wp-config.php"
	zipdir theme wp-itourtms "$root/dist/themeforest/wp-itourtms.zip" "$key"
fi
zipdir theme wp-itourtms-child "$root/dist/stage/wp-itourtms-child.zip"
cp dist/themeforest/wp-itourtms.zip dist/stage/

echo "→ plugins"
mkdir -p dist/stage/plugins
# Plugins with a composer.json ship their vendor/ (e.g. iTour Travel Documents → mPDF): install it, no dev deps.
for c in plugins/*/composer.json; do
	d="$(dirname "$c")"
	docker run --rm -u "$(id -u):$(id -g)" -v "$root/$d:/app" -w /app composer:2 composer install --no-dev --no-interaction --no-progress --ignore-platform-req=ext-gd >/dev/null
done
for p in plugins/*/; do
	# Module placeholders (plugin header + empty Module class) aren't shipped until they do something.
	[ "$(find "$p" -name '*.php' | wc -l)" -gt 2 ] || { echo "  skip $(basename "$p") (placeholder)"; continue; }
	zipdir plugins "$(basename "$p")" "$root/dist/stage/plugins/$(basename "$p").zip"
done

echo "→ licensing"
mkdir -p dist/stage/licensing
cat > dist/stage/licensing/README.txt <<EOF
WP iTourTMS ${ver}, (C) 2026 Mohamed Gouda.
The theme, child theme and iTour plugins are 100% GPL: GNU General Public License v2 or later
(https://www.gnu.org/licenses/gpl-2.0.html). No third-party images are bundled.

Third-party code inside the iTour Travel Documents plugin (plugins/itour-documents.zip → vendor/):
- mPDF (https://mpdf.github.io), GPL-2.0-only
- FPDI by Setasign (https://www.setasign.com/fpdi), MIT
- DeepCopy by myclabs, MIT; random_compat by Paragon Initiative, MIT; PSR HTTP Message and PSR Log interfaces, MIT
- DejaVu Sans fonts (https://dejavu-fonts.github.io), Bitstream Vera / DejaVu free licence (see vendor/mpdf/mpdf/ttfonts/DejaVuinfo.txt)
EOF
cat > dist/stage/README.txt <<EOF
WP iTourTMS ${ver}

wp-itourtms.zip         the theme: Appearance → Themes → Add New Theme → Upload Theme
wp-itourtms-child.zip   optional child theme for your own changes
plugins/                optional iTourTMS modules: install iTour Core first, then the modules you need
documentation/          open index.html (also built in: Appearance → Theme Options)
licensing/              licence information
EOF
mv dist/stage dist/WP-iTourTMS
zipdir dist WP-iTourTMS "$root/dist/themeforest/WP-iTourTMS.zip"
rm -rf dist/WP-iTourTMS
ls -l dist dist/themeforest | sed 's/^/  /'
