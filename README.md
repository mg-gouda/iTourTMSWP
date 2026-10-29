# iTourTMS for WordPress

[iTourTMS](https://github.com/mg-gouda/iTourTMS) rebuilt natively for WordPress, for destination management companies and travel agencies. It's a clean theme plus one plugin per module, so a company installs only what it uses.

| Folder | What it is |
|---|---|
| `theme/itour` | The front-end theme: no page builder, no jQuery, RTL-ready |
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

## Development
```bash
bin/lint.sh              # PHP syntax, WordPress Coding Standards, PHPStan (runs in Docker)
bin/lint.sh fix          # auto-fix coding-standard issues
bin/deploy-staging.sh    # copy theme and plugins to the staging site in .deploy.env
```
CI runs the same checks on every push.

## Adding a module
A module plugin registers on `itour/register_modules` and extends `ITour\Core\Module`, declaring `slug()`, `name()`, `version()`, `boot()` and optionally `requires()`, `tables()` and `admin_pages()`. Changing `version()` makes the core migrator apply `tables()` with dbDelta on the next admin page load.

Author: Mohamed Gouda · https://wa.me/+201002805139 · GPL-2.0-or-later
