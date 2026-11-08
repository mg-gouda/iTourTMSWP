#!/usr/bin/env bash
# Translation files for the theme and every plugin, made with WP-CLI in the dev container.
#   bin/i18n.sh        → languages/<domain>.pot for each package (template for translators)
#   bin/i18n.sh mo     → also compile every languages/*.po into .mo + .l10n.php (what WordPress loads)
set -euo pipefail

cd "$(dirname "$0")/.."

pot() { # <container path> <repo folder> <domain> <package name>
	bin/dev.sh wp i18n make-pot "$1" "/var/www/html/$3.pot" --domain="$3" --package-name="$4" \
		--headers='{"Report-Msgid-Bugs-To":"https://github.com/mg-gouda/iTourTMSWP/issues"}' >/dev/null
	mkdir -p "$2/languages"
	docker cp "itourwp-wp:/var/www/html/$3.pot" "$2/languages/$3.pot" >/dev/null
	docker exec itourwp-wp rm -f "/var/www/html/$3.pot"
	echo "  $2/languages/$3.pot ($(grep -c '^msgid "' "$2/languages/$3.pot") strings)"
}

echo "→ translation templates"
pot wp-content/themes/wp-itourtms theme/wp-itourtms wp-itourtms "WP iTourTMS"
for p in plugins/*/; do
	slug=$(basename "$p")
	# Placeholder modules with only their plugin header have nothing to translate yet.
	[ "$(grep -rhoE "(__|_e|_n|_x)\(" --include=*.php "$p" | wc -l)" -gt 5 ] || continue
	pot "wp-content/plugins/$slug" "plugins/$slug" "$slug" "$(sed -n 's/^ \* Plugin Name: *//p' "$p/$slug.php")"
done

if [ "${1:-}" = mo ]; then
	echo "→ compiled translations"
	for po in theme/wp-itourtms/languages/*.po plugins/*/languages/*.po; do
		[ -f "$po" ] || continue
		dir=$(dirname "$po")
		case "$dir" in
			theme/*) mount="wp-content/themes/${dir#theme/}" ;;
			*) mount="wp-content/${dir}" ;;
		esac
		name=$(basename "$po" .po)
		docker cp "$po" "itourwp-wp:/var/www/html/$name.po" >/dev/null
		docker exec itourwp-wp chown www-data: "/var/www/html/$name.po"
		bin/dev.sh wp i18n make-mo "/var/www/html/$name.po" /var/www/html/ >/dev/null
		# .l10n.php loads faster, but Theme Check flags its control characters, so the theme ships .mo only.
		case "$dir" in theme/*) ;; *) bin/dev.sh wp i18n make-php "/var/www/html/$name.po" /var/www/html/ >/dev/null ;; esac
		docker cp "itourwp-wp:/var/www/html/$name.mo" "$dir/$name.mo" >/dev/null
		docker exec itourwp-wp test -f "/var/www/html/$name.l10n.php" && docker cp "itourwp-wp:/var/www/html/$name.l10n.php" "$dir/$name.l10n.php" >/dev/null
		docker exec itourwp-wp rm -f "/var/www/html/$name.po" "/var/www/html/$name.mo" "/var/www/html/$name.l10n.php"
		echo "  $dir/$name.mo + .l10n.php ($mount)"
	done
fi
