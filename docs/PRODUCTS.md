# Product rules (owner, 9 Nov 2026)

Owner's answers to the 14 product questions, plus two later rules. Decision D-024. Build from this.

## Shared by all products
- **Ages:** infant 0–1, child 2–11, adult 12+.
- **Pax brackets (tours + excursions):** price per person is set per bracket of travellers in the booking (e.g. 1, 2–3, 4–6, 7–10, 11+). The more travellers, the lower the price per person, because fixed costs (guide, vehicle) are shared. The bracket comes from the booking's total travellers.
- **Seasons (tours + excursions):** prices can change by date range.
- **Supplements (tours + excursions):** per-person charges added automatically when the travel dates include a set date, e.g. Christmas Gala Meal, New Year's Gala Meal, Gosto Gala. Each one has a name, date or date range, and adult / child / infant price.
- **Location Tree (built, `itour-core`, own admin menu):** destination → zone → hotel / airport / port / meeting point, set up once and used by every product. Table `itour_locations` (`parent_id` tree, `lat`/`lng`/`place_id` ready for maps and Google Places).
- **Hotel and airport list (excursions + transfers):** comes from the Location Tree; every hotel and airport sits in a zone. Google Places autocomplete can find addresses that aren't on the list (owner supplies the API key).
- **Payments (WooCommerce, D-005):** card online, cash on arrival / pay the driver, bank transfer, deposit now and the rest later.
- **Cancellation:** one site-wide table of tiers that applies to all products. Each tier has a travel date range, days before travel, and fee %. Example: 1 Dec–10 Jan, 30+ days 0%, 15–29 days 50%, under 15 days 100%.
- **B2B:** comes later, after B2C launches. Partner net prices go in a later phase; the data model leaves room for them now.

## Tour Packages (`itour-tours`)
- **Pricing:**
  - Per person, by season and by pax bracket.
  - Hotel class options: 3★, 4★, 5★.
  - Room supplements: single, triple.
- **Dates:** set per package:
  - Fixed departures with a seat limit (sells out automatically), or
  - Any date on request.
- **Page content:**
  - Day-by-day itinerary: title, places, meals and hotel for each day.
  - Includes and excludes lists.
  - Map with several locations, taken from the places the itinerary visits.
  - FAQ and gallery.
  - Categories, tour language and suitability (e.g. families, wheelchair access).
- **Optional extras:** paid add-ons the customer can tick, for packages only (e.g. a balloon ride or a sound and light show). Priced per person or per booking.
- **Booking:** set per package. Options are instant booking with full payment, instant booking with a deposit, or an enquiry.

## Excursions (`itour-excursions`)
- **Pricing:**
  - Adult, child and infant prices.
  - Shared and private versions.
  - Pax brackets and seasons.
- **Schedule:**
  - Days of the week it runs, with start times.
  - Cut-off: how many hours before the start bookings close.
  - Daily seat limit.
  - Blocked dates.
- **Pick-up:** each excursion allows one or more of:
  - Hotel list by zone: the zone sets the pick-up time and an optional extra price.
  - The customer typing their hotel name.
  - A meeting point.
- **Add-ons:** each excursion lists its own paid extras (lunch, tickets, guide language, photos), priced per person or per booking.

## Transfers (`itour-transfers`)
- **Pricing:**
  - Zone to zone, per vehicle.
  - Pick-up and drop-off are chosen from the hotel and airport list, or with Google Places; the place maps to its zone.
- **Vehicles:** types with a passenger and luggage limit (e.g. sedan 3, van 7, minibus 14, bus 28).
- **Extras:**
  - Paid child seats.
  - Extra luggage fee.
  - Return-trip discount %.
- **Rules:**
  - Minimum hours before pick-up.
  - Flight number required for airport trips.
  - Free waiting time shown to the customer, e.g. 60 min at airports and 15 min at hotels.

## Open points (small, can default)
- **Pax brackets:** the bracket edges are set per product. The default is 1 / 2–3 / 4–6 / 7–10 / 11+.
- **Infants:** they count towards the bracket and vehicle size but pay the infant price, which defaults to 0.
- **Supplements:** each one is a per-person charge. It is compulsory when the stay covers its date.
