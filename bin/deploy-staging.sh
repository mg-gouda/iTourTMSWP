#!/usr/bin/env bash
# Copy the iTour theme and plugins to a staging WordPress over SSH.
#
# Settings come from .deploy.env (git-ignored; see .deploy.env.example):
#   ITOUR_SSH       user@host
#   ITOUR_SSH_PORT  SSH port (default 22)
#   ITOUR_SSH_KEY   path to the private key (optional)
#   ITOUR_WP_PATH   WordPress root on the server, e.g. /home/site/public_html/staging
#   ITOUR_OWNER     file owner on the server, e.g. site:site (optional)
#
# Usage: bin/deploy-staging.sh [plugin-or-theme ...]   (default: everything)
# It refuses any path that doesn't contain "staging", so it can't touch a live site by mistake.
set -euo pipefail

cd "$(dirname "$0")/.."
[ -f .deploy.env ] || { echo "Missing .deploy.env (copy .deploy.env.example)." >&2; exit 1; }
# shellcheck disable=SC1091
. ./.deploy.env

: "${ITOUR_SSH:?set ITOUR_SSH}" "${ITOUR_WP_PATH:?set ITOUR_WP_PATH}"
case "$ITOUR_WP_PATH" in
	*staging*) ;;
	*) echo "Refusing: ITOUR_WP_PATH must be a staging site." >&2; exit 1 ;;
esac

ssh_cmd=(ssh -p "${ITOUR_SSH_PORT:-22}")
[ -n "${ITOUR_SSH_KEY:-}" ] && ssh_cmd+=(-i "$ITOUR_SSH_KEY")

targets=("$@")
[ ${#targets[@]} -eq 0 ] && targets=(theme/* plugins/*)

for src in "${targets[@]}"; do
	src="${src%/}"
	[ -d "$src" ] || { [ -d "plugins/$src" ] && src="plugins/$src"; } || true
	[ -d "$src" ] || { echo "Skip $src (not found)"; continue; }
	case "$src" in
		theme/*) dest="$ITOUR_WP_PATH/wp-content/themes/$(basename "$src")" ;;
		*)       dest="$ITOUR_WP_PATH/wp-content/plugins/$(basename "$src")" ;;
	esac
	echo "→ $src"
	rsync -az --delete --exclude '.DS_Store' -e "${ssh_cmd[*]}" "$src/" "$ITOUR_SSH:$dest/"
	[ -n "${ITOUR_OWNER:-}" ] && "${ssh_cmd[@]}" "$ITOUR_SSH" "chown -R '$ITOUR_OWNER' '$dest'"
done
echo "Deployed to staging."
