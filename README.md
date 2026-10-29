# iTourTMS for WordPress

[iTourTMS](https://github.com/mg-gouda/iTourTMS) rebuilt natively for WordPress, for destination management companies and travel agencies. It's a clean theme plus one plugin per module, so a company installs only what it uses.

| Folder | What it is |
|---|---|
| `theme/wp-itourtms` | **WP iTourTMS** theme: block editor + theme.json, no page builder, no jQuery, RTL-ready, built-in manual (Appearance → WP iTourTMS) |
| `theme/wp-itourtms-child` | Child theme for site-specific changes |
| `plugins/itour-core` | Required foundation: module registry, migrations, admin menu, shared services |
| `plugins/itour-crm` | Tours and excursions (first module) |
| `plugins/itour-traffic` | Transfers and fleet |
| `plugins/itour-contracting` | Hotels and contracts |
| `plugins/itour-reservations` | Hotel bookings (needs Contracting) |
| `plugins/itour-nile-cruises` | Nile cruises |
| `plugins/itour-tour-ops` | Files, packages, quotations |
| `plugins/itour-b2b` | Partner portal |
| `plugins/itour-b2c` | Public-site extras |
| `plugins/itour-finance` | Accounting |

Needs WordPress 6.5+ and PHP 8.1+. Install **iTour Core** first; WordPress then lets you activate any module whose requirements are met.

## Docs
- [Roadmap](docs/ROADMAP.md)
- [Module map](docs/MODULES.md)
- [Decisions](docs/DECISIONS.md)
- [Phase 0 audit](docs/audit.md)
- [Releasing (WordPress.org + ThemeForest)](docs/RELEASE.md)

## Development
```bash
bin/dev.sh               # local WordPress at http://localhost:8091 (Docker; login in .dev.env)
bin/dev.sh wp plugin list   # WP-CLI against the dev site
bin/dev.sh stop          # stop it
bin/lint.sh              # PHP syntax, WordPress Coding Standards, PHPStan (runs in Docker)
bin/lint.sh fix          # auto-fix coding-standard issues
bin/theme-check.sh       # WordPress.org Theme Check on the dev site
bin/package.sh           # build dist/ zips for WordPress.org and ThemeForest
bin/deploy-staging.sh    # copy theme and plugins to the staging site in .deploy.env
```
CI runs the same checks on every push.

## Adding a module
A module plugin registers on `itour/register_modules` and extends `ITour\Core\Module`, declaring `slug()`, `name()`, `version()`, `boot()` and optionally `requires()`, `tables()` and `admin_pages()`. Changing `version()` makes the core migrator apply `tables()` with dbDelta on the next admin page load.

Author: Mohamed Gouda · https://wa.me/+201002805139 · GPL-2.0-or-later
