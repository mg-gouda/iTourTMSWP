# Autopilot queue

Work that needs **no decision from the owner**. The scheduled task "iTourTMS autopilot" takes the first unchecked item, finishes it (tested, lint clean, committed, pushed), ticks it here with the commit hash, and stops. One item per run.

Never on autopilot (owner decides): Tour Package / Excursion / Transfer functionality, payments and checkout, cancellation rules, B2B, Traveler importer, anything on staging or the live site, licence server URL/key.

## Queue
- [ ] **Demo content file.** ThemeForest buyers expect a one-click or WXR import. Export the demo pages, menus, Theme Options and booking-widget/slider/form examples (from `tools/demo-content.php`) as `demo/wp-itourtms-demo.xml` + an options JSON, add them to the ThemeForest bundle, and test the import on a clean site.

## Done
- (autopilot adds items here with the commit hash)
- First-run setup (theme): "Quick setup" tab first in Theme Options until finished or skipped (design preset, logo, phone/email/WhatsApp, main menu built from published top-level pages); one-time notice on Themes after activation; Arabic strings. Named "Quick setup", not "Get started", because the manual already has a "Getting started" tab (commit "feat(theme): quick setup wizard")
- Shield on Nginx: copyable rules on the Hardening tab, masking via ITOUR_SHIELD_NGINX (dbdd57f)
- Shield: blocked IPs skip cached pages (dbdd57f)
- Shield manual chapter + readme.txt (commit "docs(shield): manual chapter + readme")
- Arabic, admin side: theme 362/366, core 111/116, forms, slider, Shield 275/279 (untranslated = brand names, URLs, author). RTL margin fix in Theme Options and Shield (commits 066ae1e, d721067 and "feat(i18n): Arabic theme options + manual")
- Accessibility pass (WCAG 2.2 AA): keyboard walk of header, booking widget, slider, form, mobile menu, Theme Options, Shield. Fixed: slider dots 9 px → 24 px tap area (2.5.8), hero/call-to-action buttons had no link (unreachable by keyboard), top-bar contacts min 24 px. Verified: visible focus everywhere, skip link, menu Escape, form errors (aria-invalid + described), combobox ARIA, RTL. Lighthouse/axe not run (npx blocked) (commit "fix(a11y): …")
- ThemeForest package check: both zips build; clean WordPress install of theme + child + 7 plugins activates with zero PHP errors; docs open offline; Theme Check PASS. Fixed: 8 empty placeholder plugins were shipped in the bundle, now skipped (commit "build: skip placeholder plugins in release")
