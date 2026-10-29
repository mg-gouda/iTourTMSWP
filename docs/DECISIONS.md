# Decisions log

Newest first. Status is **Decided** or **Open**; an open decision shows the recommendation, and the owner picks.

| ID | Decision | Status | Choice / recommendation | Why |
|---|---|---|---|---|
| D-010 | Rehlla's own plugins (`rehlla-transfers`, `rehlla-ai-mode`, `rehlla-languages`, `travel-b2b`) | Decided | Kept out of this public repo; used as references when porting into `itour-traffic`, `itour-ai`, `itour-b2b` | This repo is public and generic; those plugins hold Rehlla-specific code |
| D-009 | Repo visibility | Decided | Public (`mg-gouda/iTourTMSWP`) | Owner's choice. No hostnames, keys or credentials are ever committed; deploy targets live in a git-ignored `.deploy.env` |
| D-008 | Tenancy | Open | One WordPress install per company, with branches inside it. Multisite is not supported at first | Matches how DMCs host sites; much simpler than iTourTMS's multi-company tables |
| D-007 | Third-party plugins on Rehlla after cutover | Open | **Drop:** WPBakery, Elementor, Traveler code / Option Tree / Export / Layout Essential / Social Login, Disable Gutenberg, Advanced Editor Tools, Duplicate Page, WP Bulk Delete. **Keep for now:** WooCommerce (see D-005), Yoast SEO, Contact Form 7 and MC4WP (replace later with `itour-b2c` enquiries and newsletter) | Traveler and the page builders cause most of the clutter and weight |
| D-006 | Currency switching (FOX Currency Switcher) | Open | Replace it with `itour-core` currencies and daily rates: prices are stored in the base currency and shown in the guest's currency, and payment is taken in a currency the gateway supports | iTourTMS already models `Currency` and `CurrencyRate`; one fewer paid plugin |
| D-005 | Cart, checkout and payments | Open | Use **WooCommerce** for cart, checkout, payment gateways and orders, behind a thin `itour-core` booking contract so it can be swapped out later | Dozens of ready gateways (Paymob, Stripe, PayPal, cash) and familiar order admin. A native checkout is months of work |
| D-004 | Theme type | Open | **Classic PHP theme** with a small CSS token system and vanilla JS | Full control of markup and speed, easy RTL, booking UI needs PHP templates anyway; block themes add editor weight we don't need |
| D-003 | Data storage | Decided | Custom tables `{prefix}itour_*` per module, versioned by the core migrator. Public product pages also have a CPT post (URL, SEO, media) linked by `post_id` | iTourTMS data is relational (seasons, rates, allotments); postmeta would be slow and messy |
| D-002 | First module | Decided | `itour-crm` (tours and excursions) | It's what Rehlla.com sells today |
| D-001 | Approach | Decided | Native PHP port of iTourTMS. Each module is a separate plugin needing `itour-core`; it's a product for any DMC, and Rehlla.com is the first customer | Owner's choice: runs on any WordPress host, no Node/Postgres server needed |

## Conventions (decided)
- PHP 8.1+, WordPress 6.5+ (for the `Requires Plugins` header), tested on WordPress 7.x.
- Namespaces `ITour\Core`, `ITour\Crm`, … with PSR-4 autoloading from each plugin's `src/`. The plugins ship without a Composer runtime.
- Text domains match the plugin slugs. All strings are translatable, and the layout is RTL-ready.
- Hooks are named `itour/{module}/{event}`; options and tables use the `itour_` prefix.
- WordPress Coding Standards (WPCS 3), plus PHPStan at level 6 with WordPress stubs. Both run in CI on every push.
