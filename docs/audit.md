# Phase 0 audit

Audited on 29 Oct 2026, read-only. Two sources:
1. **iTourTMS** (`mg-gouda/iTourTMS`, 222 commits): the feature set to port.
2. **Rehlla.com staging** (Traveler theme): the first site to migrate, and what the importer has to read.

## 1. Rehlla.com today (staging copy of live)

Platform: WordPress 7.1.3 on PHP 8.3, with the Traveler Childtheme on Traveler. Active plugins: Contact Form 7, Disable Gutenberg, Duplicate Page, Elementor, WPBakery (js_composer), MC4WP, Rehlla AI Mode, Rehlla Languages, Rehlla Transfers, Traveler Option Tree, Advanced Editor Tools, Travel B2B, Traveler code, Traveler Export, Traveler Layout Essential for Elementor, Traveler Social Login, FOX Currency Switcher, WooCommerce, Yoast SEO and WP Bulk Delete. There are also 24 Rehlla must-use plugins and a WebP must-use plugin.

### Content to migrate
| Type | Published | Notes |
|---|---|---|
| `st_tours` | 30 | Tour packages. Taxonomies: `st_tour_type`, `suitability`, `durations`, `languages`, `location` |
| `st_activity` | 15 | Excursions. Taxonomies: `attractions`, `activity-types`, `suitability`, `durations`, `languages`, `location` |
| `st_hotel` / `hotel_room` | 24 / 40 | Not sold today. Import only if hotels are wanted (phase 10) |
| `st_cars` | 4 | Replaced by `rehlla-transfers` and later `itour-traffic` |
| `location` | 22 | Destinations, nested set in `st_location_nested`, links in `st_location_relationships` (777) |
| `page` / `post` | 52 / 4 | Pages built with `[vc_*]` WPBakery shortcodes (81 `vc_column`, 32 `vc_row`) and `[rehlla_*]` shortcodes |
| `st_template_email` | 23 | Traveler email templates using `[st_email_*]` shortcodes |
| `st_order` / Woo orders | 16 | Order lines carry `_st_*` item meta (below) |

### Tour and activity data the importer reads
- **Prices and people:** `adult_price`, `child_price`, `infant_price`, `sale_price`, `min_price`, `min_people`, `max_people`, `discount*`, `discount_by_people_type`, `is_sale_schedule`, `sale_price_from/to`, `deposit_payment_*`, `extra_price`, `tour_price_by`.
- **Programme and content:** `tours_program` / `activity_program`, `*_include`, `*_exclude`, `*_highlight`, `*_faq`, `gallery`, `video`, `duration_day` / `duration`, `type_tour` / `type_activity`, `activity-time`.
- **Booking rules:** `tours_booking_period` / `activity_booking_period`, `st_allow_cancel`, `st_cancel_number_days`, `st_cancel_percent`, `st_booking_option_type`, `disable_*_name`, `hide_*_in_booking_form`.
- **Place:** `id_location`, `multi_location`, `address`, `map_lat/lng/zoom`.
- **SEO:** Yoast fields `_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`; translations via `_rl_lang_*`.
- **Availability:** `st_tour_availability` (43,704 rows) and `st_activity_availability` (40,140 rows). Columns: `post_id, check_in, check_out, starttime, count_starttime, number, price, adult_price, child_price, infant_price, status, groupday, number_booked, booking_period, is_base`. `check_in` is a unix timestamp per day.
- **Search cache tables:** `st_tours` (30), `st_activity` (15). These are derived and will not be migrated.

### Booking data
- Order item meta: `_st_check_in`, `_st_check_out`, `_st_starttime`, `_st_adult_number/price`, `_st_child_number/price`, `_st_infant_number/price`, `_st_guest_name`, `_st_guest_title`, `_st_extras`, `_st_extra_price`, `_st_total_price`, `_st_sale_price`, `_st_ori_price`, `_st_discount_*`, `_st_commission`, `_st_st_booking_id`, `_st_st_booking_post_type`, `_st_type_tour`, `_st_user_id`, and package fields `_st_package_*`. Transfers write `_rtf` and `_rtf_partner`, plus readable lines such as "Booking reference", "Vehicle" and "Passengers".
- `st_order_item_meta` (23 rows) mirrors bookings for Traveler's partner dashboard.
- Add-to-cart input (`do_add_to_cart`): `item_id`, `check_in`/`check_out` (d/m/Y), `adult_number`, `child_number`, `infant_number`, `starttime_tour`, `guest_name[]`, `guest_title[]`.

### Page templates in use
`template-home-modern.php` (22, mostly translations), `template-hotel-search.php` (12), `template-tour-search.php` (4), `template-activity-search.php` (4), plus checkout, confirm, payment-success, user, register, reset-password, partner-information, member-packages and blog templates. All others use the default template (68).

### Shortcodes to replace
- WPBakery `vc_*` → plain blocks or HTML.
- `st_*` email, partner and search shortcodes → `itour` templates.
- `rehlla_*`: `deals`, `about`, `contact`, `partner_join`, `partner_profile`, `footer`, `build_trip`, `offers`, `trust_bar`, `destinations`, `transfer_booking`, `transfer_track` → theme templates or module blocks.
- CF7 and MC4WP shortcodes can stay or be replaced (decision D-007).

## 2. iTourTMS feature inventory

Auto-generated from `prisma/schema.prisma`, `src/server/trpc/routers` and `src/app/(dashboard)`:

## AUTH MODELS (Auth.js v5) — 3 models, 0 enums
   AuthAccount, Session, VerificationToken
## COMPANY & TENANT — 3 models, 0 enums
   Company, CompanySetup, License
## USERS & RBAC — 7 models, 0 enums
   User, Branch, UserBranch, Role, Permission, RolePermission, UserRole
## REFERENCE DATA — 6 models, 0 enums
   Country, CountryState, Currency, CurrencyRate, Language, PartnerTitle
## PARTNER (Unified: Customer, Supplier, Tour Operator, Corporate) — 1 models, 0 enums
   Partner
## SEQUENCE GENERATOR — 1 models, 0 enums
   Sequence
## MODULE REGISTRY — 1 models, 0 enums
   InstalledModule
## FINANCE MODULE — Enums — 0 models, 26 enums
   
## CONTRACTING MODULE — Enums — 0 models, 10 enums
   
## FINANCE MODULE — Chart of Accounts — 3 models, 0 enums
   AccountGroup, AccountTag, FinAccount
## FINANCE MODULE — Journals — 1 models, 0 enums
   Journal
## FINANCE MODULE — Taxes — 3 models, 0 enums
   TaxGroup, Tax, TaxRepartitionLine
## FINANCE MODULE — Payment Terms — 2 models, 0 enums
   PaymentTerm, PaymentTermLine
## FINANCE MODULE — Moves (Journal Entries / Invoices / Bills) — 2 models, 0 enums
   Move, MoveLineItem
## FINANCE MODULE — Fiscal Positions — 3 models, 0 enums
   FiscalPosition, FiscalPositionTaxMap, FiscalPositionAccountMap
## FINANCE MODULE — Payments — 1 models, 0 enums
   Payment
## FINANCE MODULE — Reconciliation — 1 models, 0 enums
   PartialReconcile
## FINANCE MODULE — Bank Statements — 2 models, 0 enums
   BankStatement, BankStatementLine
## FINANCE MODULE — Batch Payments — 22 models, 0 enums
   BatchPayment, FiscalYear, FiscalPeriod, RecurringEntry, RecurringEntryLine, Budget, BudgetLine, AnalyticAccount, AccountAsset, AccountAssetLine, AccountLoan, LoanScheduleLine, TaxReturn, TaxReturnLine, AccountLockDate, AuditTrailEntry, WorkingFile, DeferredRevenue, DeferredRevenueSchedule, DeferredExpense, DeferredExpenseSchedule, UnrealizedCurrencyEntry
## CONTRACTING MODULE — Hotel Master Data — 29 models, 1 enums
   Destination, City, Zone, HotelAmenity, Hotel, HotelImage, HotelRoomType, RoomTypeOccupancy, ChildPolicy, HotelMealBasis, Contract, ContractSeason, ContractRoomType, ContractMealBasis, ContractBaseRate, ContractSupplement, ContractSpecialOffer, ContractOfferTier, ContractSeasonSpo, ContractSeasonSpoBtc, ContractSeasonSpoRoomSup, ContractSeasonSpoDate, ContractAllotment, ContractStopSale, ContractMarketingContribution, ContractChildPolicy, ContractCancellationPolicy, Market, ContractMarket
## TOUR OPERATOR — CONTRACT & HOTEL ASSIGNMENTS — 3 models, 0 enums
   TourOperator, ContractTourOperator, HotelTourOperator
## MARKUP & TARIFF — 2 models, 1 enums
   MarkupRule, Tariff
## SPECIAL MEALS (Gala Dinners) — 1 models, 1 enums
   ContractSpecialMeal
## RATE VERIFICATION (Booking Simulator) — 1 models, 0 enums
   RateVerification
## CONTRACT COPY LOG — 1 models, 0 enums
   ContractCopyLog
## CONTRACT AUDIT LOG — 1 models, 0 enums
   ContractAuditLog
## API KEYS — 1 models, 0 enums
   ApiKey
## API INTEGRATIONS — 4 models, 0 enums
   ApiIntegration, ApiIntegrationHotel, WebhookDelivery, IncomingWebhook
## RESERVATIONS MODULE — Enums — 0 models, 10 enums
   
## RESERVATIONS MODULE — Models — 14 models, 4 enums
   Guest, Booking, BookingRateChange, BookingDocument, BookingCurrencyLine, BookingRoom, BookingGuest, BookingPayment, Voucher, BookingTimeline, BookingDeadline, BookingSpecialRequest, BookingCommunication, Notification
## TRAFFIC & TRANSPORT MODULE — Enums — 0 models, 10 enums
   
## TRAFFIC & TRANSPORT MODULE — Shared Model: Airport — 1 models, 0 enums
   Airport
## TRAFFIC & TRANSPORT MODULE — Models — 21 models, 0 enums
   TtZone, TtVehicleType, TtVehicle, TtVehicleCompliance, TtDriver, TtDriverVehicle, TtRep, TtRepZone, TtPriceItem, TtPartnerPriceOverride, TtSupplierTripPrice, TtTrafficJob, TtTrafficFlight, TtTrafficAssignment, TtStatusChangeLog, TtNoShowEvidence, TtGuestBooking, TtGuestPayment, TtOperationalCost, TtSettings, TtPushToken
## PUBLIC SITE MODULE — 8 models, 7 enums
   PublicSiteBranding, HeroSlide, PublicPage, BlogPost, Faq, Testimonial, NewsletterSubscriber, ContactInquiry
## B2C MARKUP RULES — 2 models, 0 enums
   B2cMarkupRule, B2cMarkupTier
## B2B PORTAL — CREDIT MANAGEMENT — 1 models, 1 enums
   B2bCreditTransaction
## CRM & EXCURSION SALES — 29 models, 20 enums
   CrmLead, CrmSalesTeam, CrmMailing, CrmMailingRecipient, CrmOpportunity, CrmActivity, CrmCustomer, CrmSupplier, CrmExcursion, CrmExcursionProgram, CrmProgramItem, CrmExcursionAgeGroup, CrmExcursionAddon, CrmCostSheet, CrmCostComponent, CrmPickupLocation, CrmTransportTier, CrmSellingPrice, CrmBooking, CrmBookingItem, CrmProgramPlan, CrmProgramPlanItem, CrmProgramTourOperator, CrmPickupTime, CrmExcursionTicket, CrmExcursionBreakdown, CrmExcursionDispatch, CrmDispatchRun, CrmDispatchRunStop
## HOTEL CREDIT NOTES — Tracks money left with hotel after penalty-free cancel — 24 models, 13 enums
   HotelCreditNote, HotelCreditConsumption, OpsFile, OpsPackage, OpsPackageComponent, OpsQuotation, CreditOverrideRequest, OpsPnL, OpsFlightTicket, OpsFlightLeg, OpsFlightFareLine, OpsTransportDestination, OpsTransportRoute, OpsTransportRateSeason, OpsTransportRate, OpsSightseeingEntry, OpsSightseeingRateSeason, OpsGuidanceRate, OpsGuidanceRateSeason, OpsMealRate, OpsMealRateSeason, CoaTemplate, CoaTemplateGroup, CoaTemplateAccount
## NILE CRUISES MODULE — 38 models, 31 enums
   CruiseBoat, CruiseBoatAmenity, CruiseBoatImage, CruiseDeck, CruiseCabinCategory, CruiseCabin, CabinOutOfOrder, CruiseType, CruiseItinerary, CruiseItineraryDay, CruiseItineraryDayExcursion, CruiseCancellationPolicy, CruiseCancellationPolicyTier, CruiseContract, CruiseSeason, CruiseBaseRate, CruiseSupplement, CruiseChildPolicy, CruiseContractEmbarkDay, CruiseContractMarket, CruiseContractTourOperator, CruiseOffer, CruiseGalaMeal, CruiseStopSale, CruiseDeparturePattern, CruiseDeparture, CruiseAllotment, CruiseBooking, CruiseBookingCabinLine, CruisePassenger, CabinAssignment, CruiseBookingPayment, CruiseSpecialRequest, CruiseBookingCommunication, CruiseBookingAmendment, CruiseVoucher, CruiseManifest, CruiseManifestAmendment
## B2B PARTNER PORTAL — 7 models, 3 enums
   PartnerMarkupRule, PartnerProductBooking, PartnerProductMarkup, PartnerInvite, PartnerAuditEvent, PartnerTermsVersion, ContractSpecialOfferPartner

## tRPC routers
- b2b-portal (15): credit, index, markup, onboarding, partner-user, portal-access, product-booking, product-markup, rate-sheet, reports, reservation, search, tour-operator, travel-agent, voucher
- b2c-site (10): b2c-markup, blog-post, branding, contact-inquiry, faq, hero-slide, index, newsletter, public-page, testimonial
- contracting (28): audit-log, cancellation-policy, child-policy, contract-allotment, contract-base-rate, contract-child-policy, contract-meal-basis, contract-room-type, contract-season, contract-supplement, contract, destination, hotel, index, market, marketing-contribution, markup-rule, meal-basis, rate-calculator, rate-verification, reports, room-type, season-spo-btc, season-spo, special-meal, special-offer, tariff, tour-operator
- crm (21): activity, addon, age-group, booking, cost-sheet, customer, excursion-breakdown, excursion-dispatch, excursion-ticket, excursion, index, lead, mailing, opportunity, pickup-location, pickup-time-sheet, program-plan, program, sales-team, selling-price, supplier
- finance (31): account, analytic, asset, audit-trail, bank-statement, batch-payment, budget, coa-template, currency, deferred-expense, deferred-revenue, fiscal-position, index, inter-branch, journal, loan, lock-date, move, partner, payment-term, payment, period, reconciliation, recurring-entry, report-by-branch, report, review, tax-return, tax, unrealized-currency, working-file
- nile-cruises (34): allotment, amendment, base-rate, boat, booking-payment, booking, cabin-assignment, cabin-category, cabin, cancellation-policy, child-policy, communication, contract, cruise-type, dashboard, deck, departure, embark-day, gala-meal, index, itinerary, manifest, markup, offer, passenger, reports, search, season, settings, special-request, stop-sale, supplement, template, voucher
- partner (10): booking, credit, dashboard, index, markup, products, rate-sheet, reports, search, team
- reservations (10): booking-payment, booking, communication, deadline, guest, hotel-credit, index, reports, special-request, voucher
- shared (9): admin, analytics, api-integration, branch, giata, notification, settings, setup, user
- tour-ops (16): calculator, component, credit-override, dispatch, file, flight-ticket, guidance, index, lookup, meals, package, pnl, quotation, reports, sightseeing, transport
- traffic (18): airport, dispatch, driver, guest-booking, index, operational-cost, partner-price-override, price-item, rep, reports, settings, supplier-trip-price, traffic-assignment, traffic-flight, traffic-job, vehicle-type, vehicle, zone

## Dashboard screens
- admin: 6 pages
- analytics: 1 pages
- b2b-portal: 19 pages
- b2c-site: 14 pages
- contracting: 40 pages
- crm: 31 pages
- dashboard: 1 pages
- finance: 96 pages
- help: 2 pages
- nile-cruises: 36 pages
- profile: 1 pages
- reservations: 15 pages
- settings: 1 pages
- tour-ops: 25 pages
- traffic: 32 pages

## 3. Public URLs on rehlla.com (from the Yoast sitemaps, 2026-10-29)

These must keep working after cutover, either at the same URL or with a 301.

- `post-sitemap.xml`: 6 URLs
- `page-sitemap.xml`: 16 URLs
- `st_activity-sitemap.xml`: 15 URLs
- `st_tours-sitemap.xml`: 30 URLs
- `location-sitemap.xml`: 24 URLs
- `product_cat-sitemap.xml`: 1 URLs
- `rl-sitemap-de.xml`: 91 URLs
- `rl-sitemap-fr.xml`: 91 URLs
- `rl-sitemap-it.xml`: 91 URLs
- `rl-sitemap-es.xml`: 91 URLs
- `rl-sitemap-pl.xml`: 40 URLs
- `rl-sitemap-ru.xml`: 40 URLs

Full list: run `bin/url-list.sh https://rehlla.com` before cutover and keep it as the redirect baseline.
