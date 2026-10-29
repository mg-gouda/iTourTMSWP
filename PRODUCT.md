# Product

Adapted from iTourTMS's PRODUCT.md (same product, WordPress edition).

## Register

product. The iTour admin screens are app UI inside wp-admin. The public `itour` theme is a **brand** surface; override the register per task when working on it.

## Users
Staff at an Egyptian destination management company (DMC) or travel agency: reservations agents, contracting managers, traffic/ops dispatchers, excursion sellers and accountants. They work in it all day across branches (Cairo, Hurghada, Luxor …), often under time pressure: a flight lands, a driver is missing, a hotel needs a rooming list now. Secondary users: B2B partners (tour operators, travel agents) in their portal, and travellers on the public site.

## Product Purpose
One WordPress install runs the whole DMC operation: tours and excursions, transfers, hotel contracts and reservations, Nile cruises, tour-ops files, the B2B portal, the B2C website and the accounting behind them. Each module is a separate plugin, so a company installs only what it uses. Success: staff finish a task without leaving WordPress or reaching for a spreadsheet, and numbers agree across modules.

## Brand Personality
Bold, branded and premium, but still fast and legible for heavy daily use. Confidence over decoration. Inside wp-admin, the iTour screens follow WordPress's own visual language (it's the user's daily tool) and add brand only at key moments.

## Anti-references
- Generic SaaS templates: gradient hero metrics, identical icon-card grids.
- Legacy travel ERPs: grey 2005 forms, everything crammed into tabs, tables with no hierarchy.
- WordPress plugin clutter: upsell banners, nag notices, custom admin skins that fight core wp-admin.

## Design Principles
1. **Brand carries, data leads.** The working surface stays dense and scannable.
2. **Show the consequence.** Prices, branches, statuses and money owed are visible where a decision is made.
3. **Built for the rush.** The common action is one obvious step; errors say what to do next.
4. **One system, one language.** A pattern learned in one module works the same in every other. All modules use the core admin UI kit.

## Accessibility & Inclusion
- WCAG 2.2 AA: contrast, keyboard operation, visible focus, labelled controls.
- Arabic (RTL) is a supported locale; use logical properties.
- Respect `prefers-reduced-motion`.
