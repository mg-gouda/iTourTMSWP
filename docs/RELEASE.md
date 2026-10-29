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

## ThemeForest additions
- The main file includes documentation (`documentation/index.html`, built from the same source as the in-dashboard manual), a child theme and licensing.
- The sales page must state the item is 100% GPL; WordPress.org requires this of authors who sell themes elsewhere.
- Still to do before the first ThemeForest submission: run the Envato Theme Check plugin, add a demo-content XML export, and set up a live preview site.
