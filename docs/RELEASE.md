# Releasing WP iTourTMS

Build: `bin/dev.sh` (dev site running), then `bin/package.sh`.

| File | Goes to |
|---|---|
| `dist/wp-itourtms.zip` | WordPress.org theme upload; ThemeForest “Installable WordPress file” |
| `dist/themeforest/WP-iTourTMS.zip` | ThemeForest “Main file”: theme, child theme, plugins, documentation, licensing |

## Checklist before every release
1. Bump `Version` in `theme/wp-itourtms/style.css`, `WP_ITOURTMS_VERSION` in `functions.php`, `Stable tag` in `readme.txt`, and the manual's changelog section.
2. `bin/lint.sh`: syntax, WordPress Coding Standards, PHPStan must all pass.
3. `bin/theme-check.sh`: must print `PASS` (this is the WordPress.org Theme Check plugin).
4. Recapture the screenshot if the design changed: `bin/screenshot.sh http://localhost:8091/ theme/wp-itourtms/screenshot.png 1200,900`.
5. `bin/package.sh`, then install `dist/wp-itourtms.zip` on a clean WordPress and click through the home page, a post, a page, search, 404, comments and the phone menu.

## WordPress.org rules this theme follows
Source: <https://make.wordpress.org/themes/handbook/review/required/> (last updated 9 Jun 2026).
- GPLv2+, a copyright notice, and a resources list in `readme.txt`. No third-party assets are bundled.
- Name contains no “WordPress”, “Theme” or “Twenty”. The slug and text domain are `wp-itourtms`. The 4+ letter prefix is `wp_itourtms_`.
- No CPTs, custom blocks, shortcodes, roles or other plugin features; those live in the iTour plugins. The theme has patterns and block styles only.
- The theme recommends only WordPress.org plugins. The iTour plugins are not mentioned by the theme; they add their own manual sections through the `wp_itourtms_manual` filter when active.
- One admin page under Appearance, using core UI, the `edit_theme_options` capability, and its CSS enqueued only on its own screen. No activation redirect or popup, no demo import, no external calls, no tracking.
- No remote resources: the system font stack is used.
- Skip link, visible focus, keyboard-operable menus, and reduced motion are supported.
- Screenshot is 1200 × 900 (4:3), showing the theme itself.

## License (commercial edition)
- Before a ThemeForest release, register product slug `wp-itourtms` on the MG License Server and save its public key (`curl https://<server>/api/v1/pubkey`). Then build with `ITOUR_LICENSE_PUBKEY_FILE=/path/pubkey.pem bin/package.sh` and set `WP_ITOURTMS_LICENSE_SERVER` (wp-config, or default it in `inc/license.php` once the server URL is final).
- The WordPress.org zip leaves out `inc/license.php`, so it has no License tab, no external calls and no license wording.
- Dev testing: `tools/dev-license.php` makes a local key pair and test tokens; `tools/mock-license-server.py` answers heartbeats (`docker run -d --name itourwp-lic --network itourwp -v $PWD/tools/mock-license-server.py:/srv.py:ro python:3.12-alpine python -u /srv.py`, then `bin/dev.sh wp config set WP_ITOURTMS_LICENSE_SERVER http://itourwp-lic:8080`).

## ThemeForest additions
- The main file includes documentation (`documentation/index.html`, built from the same source as the in-dashboard manual), a child theme and licensing.
- The sales page must state the item is 100% GPL; WordPress.org requires this of authors who sell themes elsewhere.
- Still to do before the first ThemeForest submission: run the Envato Theme Check plugin, add a demo-content XML export, and set up a live preview site.
