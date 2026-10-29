#!/usr/bin/env bash
# Local dev WordPress in Docker (plain docker, no Compose), with the repo's theme and plugins mounted live.
#   bin/dev.sh          start (first run installs WP + iTour Core/CRM + WP iTourTMS theme)  → http://localhost:8091
#   bin/dev.sh wp …     run WP-CLI
#   bin/dev.sh stop     stop containers (data kept in Docker volumes)
# Admin login is generated once into .dev.env (git-ignored).
set -euo pipefail

cd "$(dirname "$0")/.."
root="$PWD"
net=itourwp
db_env=(-e WORDPRESS_DB_HOST=itourwp-db -e WORDPRESS_DB_USER=wordpress -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress)

mounts=(-v itourwp-wp:/var/www/html)
for t in theme/*/; do
	t="${t%/}"
	mounts+=(-v "$root/$t:/var/www/html/wp-content/themes/$(basename "$t")")
done
for p in plugins/*/; do
	p="${p%/}"
	mounts+=(-v "$root/$p:/var/www/html/wp-content/plugins/$(basename "$p")")
done

wp() { docker run --rm -i --network "$net" --user 33:33 "${db_env[@]}" "${mounts[@]}" wordpress:cli-php8.3 wp "$@"; }

case "${1:-start}" in
	stop)
		docker stop itourwp-wp itourwp-db >/dev/null 2>&1 || true
		exit 0
		;;
	wp)
		shift
		wp "$@"
		exit 0
		;;
esac

docker network inspect "$net" >/dev/null 2>&1 || docker network create "$net" >/dev/null
if ! docker start itourwp-db >/dev/null 2>&1; then
	docker run -d --name itourwp-db --network "$net" --restart unless-stopped -v itourwp-db:/var/lib/mysql \
		-e MARIADB_DATABASE=wordpress -e MARIADB_USER=wordpress -e MARIADB_PASSWORD=wordpress -e MARIADB_RANDOM_ROOT_PASSWORD=1 \
		mariadb:11.4 >/dev/null
fi
if ! docker start itourwp-wp >/dev/null 2>&1; then
	docker run -d --name itourwp-wp --network "$net" --restart unless-stopped -p 8091:80 "${db_env[@]}" "${mounts[@]}" \
		-e WORDPRESS_DEBUG=1 \
		-e WORDPRESS_CONFIG_EXTRA="define( 'WP_ENVIRONMENT_TYPE', 'local' ); define( 'WP_DEBUG_LOG', true ); define( 'WP_DEVELOPMENT_MODE', 'theme' );" \
		wordpress:php8.3-apache >/dev/null
fi

until wp core version >/dev/null 2>&1 && wp db check >/dev/null 2>&1; do sleep 2; done

if ! wp core is-installed >/dev/null 2>&1; then
	[ -f .dev.env ] || printf 'DEV_ADMIN_USER=admin\nDEV_ADMIN_PASS=%s\n' "$(head -c 18 /dev/urandom | base64 | tr -d '/+=')" > .dev.env
	# shellcheck disable=SC1091
	. ./.dev.env
	wp core install --url=http://localhost:8091 --title="iTour Dev" --admin_user="$DEV_ADMIN_USER" \
		--admin_password="$DEV_ADMIN_PASS" --admin_email=dev@example.test --skip-email >/dev/null
	wp rewrite structure '/%postname%/' --hard >/dev/null
	wp theme activate wp-itourtms >/dev/null
	wp plugin activate itour-core itour-crm >/dev/null
	wp plugin delete akismet hello >/dev/null 2>&1 || true
fi
echo "iTour dev running at http://localhost:8091 (admin login in .dev.env)"
