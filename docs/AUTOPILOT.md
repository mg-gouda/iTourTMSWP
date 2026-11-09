# Autopilot queue

Work that needs **no decision from the owner**. The scheduled task "iTourTMS autopilot" takes the first unchecked item, finishes it (tested, lint clean, committed, pushed), ticks it here with the commit hash, and stops. One item per run.

Never on autopilot (owner decides): Tour Package / Excursion / Transfer functionality, payments and checkout, cancellation rules, B2B, Traveler importer, anything on staging or the live site, licence server URL/key.

## Queue
- [ ] **Accessibility pass (WCAG 2.2 AA).** Keyboard-only walk through: home, booking widget (all 3 models), slider, contact form, mobile menu, Theme Options, Shield. Fix focus order, focus visibility, target sizes (24×24 px), labels, contrast. Use Lighthouse/axe only if the permission allows it; otherwise manual checks in the browser.
- [ ] **First-run setup wizard (theme).** After activation, a short Appearance → Theme Options "Get started" panel: pick a design preset, upload logo, contact details, create the main menu from existing pages. Skippable, shown once.
- [ ] **ThemeForest package check.** Run `bin/package.sh`, check both zips install on a clean site, demo content imports, docs open offline, Theme Check passes. Fix what fails.

## Done
- (autopilot adds items here with the commit hash)
- Shield on Nginx: copyable rules on the Hardening tab, masking via ITOUR_SHIELD_NGINX (dbdd57f)
- Shield: blocked IPs skip cached pages (dbdd57f)
- Shield manual chapter + readme.txt (commit "docs(shield): manual chapter + readme")
- Arabic, admin side: theme 362/366, core 111/116, forms, slider, Shield 275/279 (untranslated = brand names, URLs, author). RTL margin fix in Theme Options and Shield (commits 066ae1e, d721067 and "feat(i18n): Arabic theme options + manual")
