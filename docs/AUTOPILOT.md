# Autopilot queue

Work that needs **no decision from the owner**. The scheduled task "iTourTMS autopilot" takes the first unchecked item, finishes it (tested, lint clean, committed, pushed), ticks it here with the commit hash, and stops. One item per run.

Never on autopilot (owner decides): Tour Package / Excursion / Transfer functionality, payments and checkout, cancellation rules, B2B, Traveler importer, anything on staging or the live site, licence server URL/key.

## Queue
- (empty) Owner-led work in progress: Excursions stage 2 (booking), then the Rehlla plugins of D-025 (need the live server's SSH host/user from the owner).

## Superseded
- **Demo content file** (done another way, see Done). Original note kept for the image question:
- **Demo content file.** ThemeForest buyers expect a one-click or WXR import. Export the demo pages, menus, Theme Options and booking-widget/slider/form examples (from `tools/demo-content.php`) as `demo/wp-itourtms-demo.xml` + an options JSON, add them to the ThemeForest bundle, and test the import on a clean site.
  - **Owner question (autopilot, 2026-11-09): demo images.** Repo bundles no images ("no third-party images" in licensing/README.txt). The slider example (and any hero/why-book photo) needs images, and a WXR import downloads attachments from a public URL (the dev site is localhost). Pick one:
    - **A (recommended):** ship the demo without slider/photos now (pages, menus, booking widget, contact form, Theme Options JSON); add the slider example later.
    - **B:** self-made placeholder images (flat plum shapes + caption) hosted in this public GitHub repo (`demo/images/`, raw.githubusercontent.com URLs in the WXR). Works offline-free for buyers only while the repo stays public.
    - **C:** you supply licensed travel photos (Unsplash-licence or own) and a public host for them.
  - Tech notes found (no decision needed): slider block stores attachment IDs, which the WordPress Importer does not remap, so B/C also need a small `import_end` remap of `itour/slider` image IDs. Theme Options `page`/`menu`/`image` fields hold IDs too, so the options JSON must store slugs and map them back on import.

## Done
- (autopilot adds items here with the commit hash)
- Demo content: one click under iTour → Overview instead of a WXR file (no WordPress Importer, no options JSON, no ID remapping, works offline). Home (hero, booking widget, why-book, call to action), Tours, Day trips, Transfers, About, Contact (contact form), main menu, front page. Remove trashes only `_itour_demo` pages and restores the previous front page and header menu. No images (option A); slider example waits for licensed photos. Tested on a clean install of the ThemeForest bundle (commit "feat(core): one-click demo content")
- First-run setup (theme): "Quick setup" tab first in Theme Options until finished or skipped (design preset, logo, phone/email/WhatsApp, main menu built from published top-level pages); one-time notice on Themes after activation; Arabic strings. Named "Quick setup", not "Get started", because the manual already has a "Getting started" tab (commit "feat(theme): quick setup wizard")
- Shield on Nginx: copyable rules on the Hardening tab, masking via ITOUR_SHIELD_NGINX (dbdd57f)
- Shield: blocked IPs skip cached pages (dbdd57f)
- Shield manual chapter + readme.txt (commit "docs(shield): manual chapter + readme")
- Arabic, admin side: theme 362/366, core 111/116, forms, slider, Shield 275/279 (untranslated = brand names, URLs, author). RTL margin fix in Theme Options and Shield (commits 066ae1e, d721067 and "feat(i18n): Arabic theme options + manual")
- Accessibility pass (WCAG 2.2 AA): keyboard walk of header, booking widget, slider, form, mobile menu, Theme Options, Shield. Fixed: slider dots 9 px → 24 px tap area (2.5.8), hero/call-to-action buttons had no link (unreachable by keyboard), top-bar contacts min 24 px. Verified: visible focus everywhere, skip link, menu Escape, form errors (aria-invalid + described), combobox ARIA, RTL. Lighthouse/axe not run (npx blocked) (commit "fix(a11y): …")
- ThemeForest package check: both zips build; clean WordPress install of theme + child + 7 plugins activates with zero PHP errors; docs open offline; Theme Check PASS. Fixed: 8 empty placeholder plugins were shipped in the bundle, now skipped (commit "build: skip placeholder plugins in release")
