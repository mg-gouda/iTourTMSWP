# Module map: iTourTMS to WordPress plugins

Each iTourTMS module becomes one plugin. Every plugin needs `itour-core`. Some need other modules too; those go in the `Requires Plugins:` header, so WordPress enforces them.

| Plugin | iTourTMS source (routers / models / screens) | Requires | Notes |
|---|---|---|---|
| `itour-core` | `shared` routers; Company, Branch, User/Role/Permission, Country, Currency(+Rate), Language, PartnerTitle, Partner, Sequence, InstalledModule, ApiKey, Notification | — | Installed first. Holds the module registry, schema migrator, REST base, admin UI kit and money maths. Users and roles map onto WordPress users and capabilities. |
| `itour-tours` | Built from scratch (owner rule D-019); iTourTMS tour-ops packages as reference | core | **Tour packages**: itineraries, departures, prices, availability, booking. Booking-widget tab + Theme Options pages tab in place; functionality waits for the owner's rules |
| `itour-excursions` | Built from scratch; iTourTMS `crm` excursion models as reference | core | **Excursions / day trips**: programmes, pick-up, prices, availability, booking. Booking-widget tab + pages tab in place |
| `itour-transfers` | Built from scratch; iTourTMS `traffic` as reference | core | **Transfers**: zones, vehicles, prices, booking, later dispatch, drivers, fleet. Booking-widget tab + pages tab in place |
| `itour-crm` | `crm` (leads, opportunities, customers, activities, mailings) | core | Sales CRM only; excursions moved to `itour-excursions` |
| `itour-slider` | — | core | Image slider block (owner rule D-017) ✅ |
| `itour-forms` | — | core | Contact form block, spam traps, Enquiries screen, email (replaces Contact Form 7, D-018) ✅ |
| `itour-shield` | — | none (standalone) | Page cache, speed trims, hidden login, masked paths, firewall, login lockouts, 2FA, file scan, hardening, activity log (D-023) ✅ |
| `itour-contracting` | `contracting` (28 routers, 43 models incl. tour operators, markup, tariffs, API integrations, 40 screens) | core | Hotels and hotel contracts. |
| `itour-reservations` | `reservations` (10 routers, 14 models + hotel credit notes, 15 screens) | core, contracting | Hotel bookings, vouchers, cancellations. |
| `itour-nile-cruises` | `nile-cruises` (34 routers, 38 models, 36 screens) | core | Boats, cabins, itineraries, cruise contracts and bookings. |
| `itour-tour-ops` | `tour-ops` (16 routers, Ops* models, 25 screens) | core | Files, packages, quotations, flights, sightseeing, guidance, meals, P&L. |
| `itour-b2b` | `b2b-portal` + `partner` (25 routers, 8 models, 19 screens) | core | Partner portal: onboarding, users, markup, credit, rate sheets, bookings. It works with whichever product modules are active. |
| `itour-b2c` | `b2c-site` (10 routers, 10 models, 14 screens) | core | B2C markup rules, hero slides, testimonials, newsletter, enquiries. Public pages, blog and FAQ use WordPress itself. |
| `itour-finance` | `finance` (31 routers, 40 models, 96 screens) | core | Double-entry accounting. Other modules post to it through `itour/finance/post` hooks only, so none of them requires it. |

## Theme Options tab per module
When a module is finished it adds its own vertical tab to **Appearance → Theme Options → Options**. The tab holds its page choices (for example the tours listing, booking and checkout pages) and its front-end settings. It uses the `wp_itourtms_option_sections` filter with its own `store` option (for example `itour_crm_pages`), so the settings stay with the plugin if the theme changes. Field types: color, px, number, checkbox, text, select, font, page.

## Admin look
`itour-core` restyles all of wp-admin and the login page in the iTourTMS look (`Admin\Skin`, switch on iTour → Company). This lives in the plugin because restyling wp-admin is plugin territory for themes.

## How modules talk to each other
- **No direct class use across modules.** A module reaches another only through `itour-core` services, actions/filters (`itour/{module}/{event}`), or the REST API.
- **Optional integration:** a module checks `itour()->modules()->active( 'finance' )` and only then hooks in.
- **Bookings:** every product module creates bookings through the core booking contract (phase 2), so cart, checkout, B2B credit and finance treat tours, transfers, hotels and cruises the same way.
