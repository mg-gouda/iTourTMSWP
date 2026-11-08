# Autopilot queue

Work that needs **no decision from the owner**. The scheduled task "iTourTMS autopilot" takes the first unchecked item, finishes it (tested, lint clean, committed, pushed), ticks it here with the commit hash, and stops. One item per run.

Never on autopilot (owner decides): Tour Package / Excursion / Transfer functionality, payments and checkout, cancellation rules, B2B, Traveler importer, anything on staging or the live site, licence server URL/key.

## Queue
- [ ] **Arabic, admin side.** Translate the remaining strings of `wp-itourtms` (Theme Options, manual), `itour-core`, `itour-forms`, `itour-slider`, `itour-shield` and the product plugins' option tabs into `tools/translations/ar.json`, then `python3 tools/build-po.py ar && bin/i18n.sh mo`. Check an Arabic wp-admin screen of each in the browser (Settings → General → Arabic, switch back to English after).
- [ ] **Shield manual + readme.** Add a "Shield" chapter to the built-in manual (via the `wp_itourtms_manual` filter, like `itour-core/src/Manual.php`) and a `readme.txt` for the plugin: what each tab does, the lock-out recovery constant, Nginx limits.
- [ ] **Shield on Nginx.** When the server isn't Apache, show the equivalent Nginx rules (blocked files, uploads PHP, masked paths) on the Hardening / Login & paths tabs as copyable text.
- [ ] **Shield: blocked IPs on cached pages.** Write the blocked-IP list into the cache config so `cache-serve.php` refuses those IPs too (cheap check, no WordPress needed).
- [ ] **Accessibility pass (WCAG 2.2 AA).** Keyboard-only walk through: home, booking widget (all 3 models), slider, contact form, mobile menu, Theme Options, Shield. Fix focus order, focus visibility, target sizes (24×24 px), labels, contrast. Use Lighthouse/axe only if the permission allows it; otherwise manual checks in the browser.
- [ ] **First-run setup wizard (theme).** After activation, a short Appearance → Theme Options "Get started" panel: pick a design preset, upload logo, contact details, create the main menu from existing pages. Skippable, shown once.
- [ ] **ThemeForest package check.** Run `bin/package.sh`, check both zips install on a clean site, demo content imports, docs open offline, Theme Check passes. Fix what fails.

## Done
- (autopilot adds items here with the commit hash)
