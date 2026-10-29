# iTourTMS for WordPress: roadmap

The goal is iTourTMS rebuilt natively in PHP for WordPress, as a product any DMC can install. It ships as one clean theme (`itour`), one required core plugin (`itour-core`), and one plugin per iTourTMS module, each installable on its own. Rehlla.com is the first site to move over, from the Traveler theme.

Source of truth for features: the iTourTMS repo (`mg-gouda/iTourTMS`, Next.js + Prisma, ~250 models). The module map is in [MODULES.md](MODULES.md) and the starting audit in [audit.md](audit.md).

| # | Phase | Delivers | Done when |
|---|---|---|---|
| 0 | Audit | iTourTMS module inventory; Rehlla/Traveler data audit | `docs/audit.md`, `docs/MODULES.md` ✅ |
| 1 | Foundations | Repo, coding standards (WPCS + PHPStan), CI lint, staging deploy script, plugin and theme skeletons, module registry | Lint passes in CI ✅ (this commit) |
| 2 | `itour-core` | Company settings, branches, roles and capabilities (iTourTMS RBAC), reference data (countries, currencies and rates, languages, titles), unified Partner, sequences, schema migrator, admin UI kit (list tables, forms, notices), REST namespace `itour/v1`, money maths (bcmath), audit log, email templates | Two modules can install, migrate and share partners and currencies |
| 3 | `itour` theme | Design tokens, header/footer, home with search, listing and single-product templates, account pages, RTL (Arabic), WCAG 2.2 AA, performance budget (LCP < 2.5 s on 4G, CSS < 40 KB, no jQuery on the front end) | Lighthouse ≥ 95 on the home, listing and product pages |
| 4 | `itour-crm` (first module) | Excursions and tours catalogue, programmes, age groups, add-ons, pickup locations and times, cost sheets, selling prices, availability and departures, B2C booking, bookings and booking items, customers; later leads, opportunities, activities, mailings, dispatch | A guest can find and book an excursion end to end on staging |
| 5 | Checkout and payments | Cart, checkout, payment gateways, vouchers, booking emails (see decision D-005) | Paid test booking with voucher and emails |
| 6 | Traveler importer | WP-CLI importer: `st_tours`, `st_activity`, their meta, taxonomies, the `st_*_availability` tables and images into `itour-crm`; old URLs 301 to the new ones | Rehlla staging fully imported, with no broken URLs |
| 7 | `itour-traffic` | Zones, vehicle types, airports, price items, partner price overrides, B2C transfer booking (replaces `rehlla-transfers`), then jobs, flights, dispatch, drivers, reps, fleet and costs | Transfer booking parity with `rehlla-transfers` |
| 8 | `itour-b2b` | Partner portal: onboarding, partner users, markup rules, credit, rate sheets, product bookings, reports (replaces `travel-b2b`) | Partner agent books at their net rate on credit |
| 9 | Rehlla cutover | Staging parity checklist, AI Mode ported as `itour-ai`, languages, SEO, launch on live, rollback plan | Rehlla.com runs on `itour` with Traveler, WPBakery and Elementor removed |
| 10 | `itour-contracting` + `itour-reservations` | Hotels, contracts, seasons, rates, supplements, offers, allotments, stop sales, markets, tariffs; hotel bookings, rooming, vouchers, cancellations | Hotel booking priced from a contract |
| 11 | `itour-nile-cruises`, `itour-tour-ops` | Boats, cabins, itineraries, departures, cruise bookings; ops files, packages, quotations, P&L | Cruise and package bookings |
| 12 | `itour-finance` | Chart of accounts, journals, moves, payments, reconciliation, taxes, reports; other modules post to it through hooks | Bookings post balanced entries |
| 13 | Productisation | Setup wizard, licensing, update server, Arabic translation, user docs, demo data | First outside DMC installs it |

## Rules that apply in every phase
- Each module plugin works with only `itour-core` (plus any modules it declares as required) and adds nothing to the front end unless it is used.
- Data lives in custom tables (`{prefix}itour_*`), with WordPress posts only where WordPress features (permalinks, SEO, media, revisions) are wanted, such as public product pages.
- No page builders, no jQuery on the front end, and no third-party plugin is required.
- Every plugin and theme header has `Author: Mohamed Gouda` and `Author URI: https://wa.me/+201002805139`.
- Work goes to staging first; the live site only changes when the owner says "deploy" or "live".
