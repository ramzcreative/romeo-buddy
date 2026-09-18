# Destination — a site type for DMO and tourism sites

Written 2026-09-18. **Status: draft, nothing built.** Every "today" claim in §2 was read in the code on that date and
is cited. Decisions still open are in §10.

Scope, decided with Gary before writing: **the content model and its starter set only**, and **editor-managed
partner records first, a CRM import later**. Partner search and map, the partner templates, the events↔partner
templates, the deals front end, and the trip planner (saved items and itineraries) each become their own spec once
this one is settled. §6 and §7 describe the boundaries
those later specs have to meet, so the fields exist from day one, but nothing in them is built here.

Companion to [`site-types-spec.md`](site-types-spec.md) (the type mechanism and starter sets),
[`business-content-spec.md`](business-content-spec.md) (the Business baseline this adds to) and
[`../../craft-modules/docs/recurring-events-spec.md`](../../craft-modules/docs/recurring-events-spec.md) (events, shipped).

## 1. Goal

A DMO site — a convention and visitors bureau, a tourism board, a chamber — is a Business site plus three things:

1. **Partners:** the restaurants, hotels, shops and attractions in the destination, each with its own page,
   browsable by category and searchable by attribute. The DMO calls the organisation a **partner** (some say member);
   the record on the site is its **listing**. One partner can hold several listings — a hotel with a dining listing
   and a meeting-venue listing — which §6 has to account for even though the set models one.
2. **A calendar that belongs to the destination, not to the DMO.** Events happen *at* partners, most of them repeat,
   and many are submitted by the partners themselves.
3. **Inspiration content that points at both:** deals, regions or neighborhoods, and trip ideas — the last of
   which is the trip planner's own spec (§3.3).

None of that belongs in stables. A trades or church site would never use it, and stables is the boilerplate every
client site branches from. So:

- **stables gains nothing.** No new section, field, block or template.
- **The content model is a starter set,** applied when a DMO site is created.
- **Templates live in a Destination theme.**
- **Code lives in `craft-modules`,** dormant unless the site has the fields (the pattern the `seo` module already uses).

**What this is not:**
- A CRM integration. §6 designs the boundary; it builds nothing.
- A replacement for Topics. Topics stays editorial (§3.3).
- A booking engine, a pass platform or a UGC gallery. Those are third-party products a site embeds.

### Why this is its own type

Decided by Gary 2026-09-18: Destination is its own type, tied to nothing else.

`site-types-spec.md` §1 lists tourism under Business, and for a tour operator or a hotel that's right. A DMO is
different in kind: most of its content is **other organisations' records**, browsed by category and filtered by
attribute, and eventually owned by an external system. That's the same reason Directory is its own type — and a DMO
can't share Directory's set, which is the sports and member model.

## 2. What exists today

| # | Claim | Where |
|---|---|---|
| D1 | **Four site types.** `SITE_TYPES` is `business`, `catalog`, `directory`, `custom`. An unknown value reads as `custom` with a logged warning, so an older site that meets a `destination` theme degrades quietly rather than erroring. | craft-modules `ThemeRegistry.php:50`–`:55`, `:456`–`:466` |
| D2 | **A set may create 11 field classes:** Plain Text, CKEditor, Assets, Number, Date, Lightswitch, Dropdown, Button Box, Entries, Matrix, Link — each with an allowlist of settings keys. Anything else is refused by name. | `StarterSet.php:38`–`:50`, `:155` |
| D3 | **A field that already exists is reused, whatever its class,** as long as the class matches; the allowlist is only consulted when the field has to be created. So a set may name baseline fields (`seo`, `topics`, `pageBuilder`, `address`) that a set could never create itself. | `StarterSet.php:141`–`:155` |
| D4 | **A set is looked up by site type:** `fetchStarterSet()` refuses a type outside `SITE_TYPES` and reads `starter-sets/<siteType>.json`. One type, one set. `business.json` (empty by design) and `directory.json` (a stub) are in the library. | `LibraryClient.php:256`–`:263`; `theme-library/starter-sets/` |
| D5 | **A set creates channel or structure sections,** with `uriFormat`, `template` and `maxLevels` as its only section keys; `maxLevels` applies to structures only. A section whose URL prefix the site already serves is refused before any write. | `StarterSet.php:57`, `:585`, `:592`, `:197` |
| D6 | ~~**Every section a set creates has URLs.**~~ **Fixed 2026-09-18** (craft-modules 1.133.0): a section spec may say `"hasUrls": false`, and gets no URI and no template — which is what a taxonomy is. Absent still means `true`. | `StarterSet.php` |
| D7 | **Topics is an entries taxonomy.** The `topics` section is a Structure, 2 levels, `topics/{slug}`; the `topics` field is an Entries field sourced at it, cards, no maximum. Craft category groups were removed from stables. | `config/project/sections/topics--d61277de….yaml`; `fields/topics--….yaml`; business-content-spec §6.3 |
| D8 | **Events carry their location as text.** The `event` entry type has `eventStart`, `eventEnd`, `eventRepeat`, `eventLocationName`, `eventAddress`, `eventPrice`, `eventUrl`, `eventIsOnline`. Nothing relates an event to another entry. | `config/project/fields/` |
| D9 | **Recurrence is built.** `eventdates` writes `{{%eventdates_occurrences}}` on save, `.occurring({from, before, to})` joins it, and the listing, `.ics` (a real `RRULE`) and `Event` JSON-LD all read those rows. | craft-modules `modules/eventdates/CLAUDE.md`; `seo/services/IcsGenerator.php`; `StructuredDataBuilder.php:1479` |
| D10 | **The events filter is dates only:** presets plus a from–to range, as a plain GET form. No category, area or attribute filter. | `themes/_base/templates/_sections/events/_filters.twig` |
| D11 | **LocalBusiness structured data is one sitewide entity,** built from the SEO settings (`@id` ends `/#localbusiness`), with a Business Type picker over the schema.org LocalBusiness subtree. Nothing emits a `LocalBusiness` or `Place` node *per entry*. | `StructuredDataBuilder.php:1862`–`:1880`; `seo/data/local-business-types.php`; `services/LocalBusinessTypes.php` |
| D12 | **The Business baseline already has reusable fields:** `address`, `phone`, `email`, `sameAs`, `image`, `excerpt`, `pageBuilder`, `relatedEntries`, `topics`, `seo`. | `config/project/fields/` |

**One consequence to notice before §3.** D3 is why the destination set creates far fewer fields than it names. (D6
was the other; it is fixed.)

## 3. The content model

Additive on the Business baseline, per `site-types-spec.md` §5.4 condition 2. Nothing is removed or replaced.

### 3.1 Sections

| Section | Type | URLs | Why |
|---|---|---|---|
| `partners` | Channel | `listing/{slug}` | The partner records. One page each, so each gets SEO, breadcrumbs and the sitemap. |
| `partnerCategories` | Structure, 3 levels | **none** | What visitors browse by: Dining › Cuisine › Italian. A filter taxonomy, not pages. |
| `partnerAttributes` | Structure, 2 levels | **none** | Filter values: pet friendly, free parking, wheelchair accessible. Labelled **“Amenities”** by default — the handle follows the CRM, the label is what a visitor would say, and it's per site like the Partners label. |
| `regions` | Structure, 2 levels | **none** | Neighborhoods, towns or districts. A filter. |
| `cuisines` | Structure, 1 level | **none** | Italian, Mexican, Seafood. Its own axis, not an amenity — the real build filters on it separately and the CRM syncs it down its own pipe. |
| `eventCategories` | Structure, 1 level | **none** | Music, Festival, Family. The calendar's own axis — §7's "filter by category" had nothing to filter on before this. |
| `dealCategories` | Structure, 1 level | **none** | Offers browse by kind too. |
| `deals` | Channel | `deals/{slug}` | Offers, each tied to a partner and dated. |

**Six of the eight have no URLs, and that is the correction that matters.** The first draft gave categories
`things-to-do/{slug}` and regions `regions/{slug}`. Reading a real Tempest DMO build settled it: its template tree has
detail templates for partners, events, deals, blog, news, press, pages and microsites — and **none for categories,
attributes, regions or cuisines**. Those are filter values. `/things-to-do/`, `/eat-drink/` and `/places-to-stay/` on a
real DMO site are ordinary `pages` entries whose page builder embeds a filtered slice of the directory, which is also
why the set now ships blocks (§3.3).

Giving the category section `things-to-do/{slug}` was wrong twice over: it squats on the prefix the DMO wants for its
own page, and a 3-level structure with a flat `{slug}` throws the hierarchy away.

**This needed `StarterSet` to support sections without URLs** (D6) — for six of the eight, not the one the first
draft treated as a single-section nicety. Built 2026-09-18.

**URL prefixes are still a per-site decision.** A set that collides with a prefix the site already serves is refused
(D5), and DMOs differ. `listing/` is the default because it matches the niche convention — the real sites use
`/directory/{slug}` or `/listing/{slug}`, flat, with the browse pages elsewhere. `site-launcher` should let them be
changed at creation (§10 Q1).

### 3.2 Fields

**Reused from the baseline** (D3, D12), created only if missing: `image`, `excerpt`, `address`, `phone`, `email`,
`sameAs`, `pageBuilder`, `relatedEntries`, `topics`, `seo`.

**Created by the set:**

| Field | Class | On | Notes |
|---|---|---|---|
| `partnerCategory` | Entries | partner | Sources `partnerCategories`. Cards. The primary browse axis. |
| `partnerAttributes` | Entries | partner | Sources `partnerAttributes`. No maximum. Filter facets. |
| `partnerRegion` | Entries, max 1 | partner | Sources `regions`. |
| `partnerLat`, `partnerLng` | Number, 6 decimals | partner | Map and distance. Two plain numbers rather than a map field, because no map plugin is assumed. |
| `partnerHours` | Matrix (`partnerHoursRow`) | partner | Rows of day + opens + closes. Feeds `openingHoursSpecification` when §6's JSON-LD arrives. |
| `partnerPriceRange` | Dropdown | partner | `$`–`$$$$`. |
| `partnerBookingUrl` | Link | partner | "Book now" / "Order online". |
| `partnerBusinessType` | Dropdown | partner | A short list of schema.org LocalBusiness subtypes (Restaurant, Hotel, Museum, Park, Store…), so per-listing JSON-LD can be specific (§10 Q3). |
| `partnerCuisine` | Entries | partner | Sources `cuisines`. Restaurants are the biggest category on most destination sites. |
| `eventCategory` | Entries | event | Sources `eventCategories`. |
| `dealCategory` | Entries | deal | Sources `dealCategories`. |
| `partnerStatus` | Dropdown | partner | `active` / `non-support` / `lapsed`. **CRM-owned.** Whether a partner is in good standing decides whether and how their listing appears — and *non-support* is a real state: listed, not paying, usually with less detail. Craft's enabled/disabled can't express it, and it throws away the reason a listing went dark. |
| `partnerTier` | Dropdown | partner | `basic` / `enhanced` / `premium`. **CRM-owned**, because enhanced placement is sold rather than chosen. Replaces the `listingFeatured` lightswitch the first draft had as editor-owned — that was backwards. |
| `partnerMeetingSpaces`, `partnerLargestRoom`, `partnerTotalSqFt` | Number | partner | Meeting capacity. Any partner can also be a meeting venue. |
| `partnerVenueType` | Dropdown | partner | Hotel, conference centre, unique venue, outdoor. |
| `crmAccountId`, `crmListingId` | Plain Text | partner, category, attribute, cuisine | Two, not one. Simpleview models Accounts holding Listings: the listing id is what an import matches on, the account id is what groups "all listings from this partner" — and it's where status and tier come from, because they belong to the organisation rather than to one of its records. Empty until an import exists (§6). |
| `dealStart`, `dealEnd` | Date | deal | |
| `dealPartner` | Entries, max 1 | deal | Sources `partners`. |
| `dealUrl` | Link | deal | |
| `dealTerms` | CKEditor | deal | Fine print. |
| `eventVenue` | Entries, max 1 | event | Sources `partners`. Named for the role, not the entity — an event’s venue is a partner. The link Gary asked for: added with the set so it exists from the start, used by templates in a later spec (§7). |

**Entry types:** `partner`, `partnerCategory`, `partnerAttribute`, `region`, `cuisine`, `eventCategory`,
`dealCategory`, `deal`, plus the nested `partnerHoursRow`.

**A taxonomy per content type, not one shared list.** Decided 2026-09-18 from the real build, whose CP carries Blog
Categories, Event Categories, Press Categories, Deals, Partner Categories, Partner Attributes, Cuisine Categories,
GeoCodes and four separate Jobs taxonomies — nothing shared between types. The shared editorial `topics` from the
Business baseline stays; everything a DMO browses by gets its own axis, so an import maps one CRM taxonomy to one
section rather than collapsing several into one.

**Topics is kept because it is the only taxonomy with URLs** (`topics/{slug}`, from the Business baseline). The six
new ones are filter values with no pages, which is right for a destination browsing thousands of partners — but a
smaller site that wants real category pages with their own SEO has topics sitting there already, and doesn't have to
model a second thing to get them.

**Press and news are a scope question this raises and doesn't answer.** The real build has `pressDirectory` and
`newsDirectory` as sections separate from the blog, each with its own taxonomy. Neither is in this spec. If they come
in, their taxonomies come with them.

**Meeting fields ship with the set, the planner pages don't.** The real build filters partners by venue size and
venue type alongside category and attribute, and a real DMO's nav has a `/planners/` section. So capacity is a partner
field, which makes it a content-model decision now; the planner pages, the RFP form and lead routing are a later
spec. Fields are cheap today and a migration on a live DMO's partner records is not. Each page-owning type carries `seo`, `pageBuilder` and `topics`, the way the
Business types do, so a category page can have real content above its listing grid.

### 3.3 What deliberately stays out

- **Blocks are NOT empty.** The first draft copied `"blocks": {}` from `directory.json`, where it was accurate
  because that set adds nothing. Here it was wrong: the intent pages are ordinary `pages` entries, so the set has to
  ship the block that puts a filtered directory on one. Corrected 2026-09-18; which blocks is a decision of its own
  (§10 Q9).
- **Topics is not reused for partner categories.** Editors own Topics; a CRM will own categories (§6). Sharing one
  section would mean an import renaming or removing entries editors rely on, topic pages listing hundreds of
  partners, related content topping up with partners, and every deep category becoming a thin indexed page.
- **No map field and no search engine.** Coordinates are two numbers; how they're searched and drawn is a later spec.
- **No Meeting Spaces or RFP module.** Convention-side content is real DMO work, but it's a second set of decisions
  and no client has asked yet.
- **No event submission flow.** §7.
- **Itineraries.** Moved out 2026-09-18: they belong with the trip planner / experience builder, which is its own
  spec. A real DMO site has a `savedItems` feature — visitors collect partners and events into a personal trip —
  and an itinerary is the editorial version of the same idea. Designing one without the other produces two
  overlapping models.

## 4. The starter set

`theme-library/starter-sets/destination.json`, the same schema as `requirements.json` plus the creation keys
(`site-types-spec.md` §5.4). Shape, abridged:

```json
{
  "siteType": "destination",
  "description": "A destination marketing site: partner listings browsable by category, attribute and region, plus deals. Editor-managed; a CRM import is a later, separate piece that fills crmId.",
  "blocks": {},
  "sections": {
    "partners": {
      "name": "Partners",
      "type": "channel",
      "uriFormat": "listing/{slug}",
      "template": "_router.twig",
      "entryTypes": {
        "partner": {
          "image": { "type": "craft\\fields\\Assets", "name": "Image", "tab": "Content" },
          "excerpt": { "type": "craft\\fields\\PlainText", "name": "Excerpt", "tab": "Content" },
          "partnerCategory": { "type": "craft\\fields\\Entries", "name": "Categories", "tab": "Content", "sources": ["partnerCategories"], "settings": { "viewMode": "cards" } },
          "crmId": { "type": "craft\\fields\\PlainText", "name": "CRM ID", "instructions": "Set by an import. Leave empty when partners are managed here.", "tab": "Settings" }
        }
      }
    }
  }
}
```

**How it behaves,** all of it existing `StarterSet` behaviour rather than anything new:
- **Planned before written.** Refusals (a handle that exists with a different class, a URL prefix in use, a field class
  outside the allowlist) stop the whole set.
- **Idempotent.** Re-running changes nothing.
- **Additive.** Existing baseline types gain fields in place; nothing is rebuilt, removed or made required.
- **Applied at creation**, by site-launcher after the theme, or with `--consent` on a site that already has content.
- **Self-checking:** after the set and a Destination theme are applied,
  `php craft theme-designer/requirements/check <theme>` must report nothing missing.

**Honest limit, per `site-types-spec.md` §5.4 condition 4:** no DMO client exists yet, so this set is **written from
Gary's Simpleview and Tempest experience and from what DMO RFPs ask for, not from a delivered site.** It ships marked
`STUB`, like `directory.json`, and is rewritten from the first real DMO build. The demo site built for bidding is what
proves it.

## 5. Where each piece lives

| Piece | Home | Active when |
|---|---|---|
| Sections, entry types, fields | `starter-sets/destination.json` | The site was created as Destination |
| Partner and deal templates; the browse pages; the map | A Destination theme, `_sections/<handle>/` | That theme is active |
| Partner search and filtering | `craft-modules`, later spec | The site has `partners` |
| Per-listing `LocalBusiness`/`Place` JSON-LD | `craft-modules` `seo` | The entry's layout has the fields (the dormant-capability pattern) |
| CRM import | `craft-modules`, own module, later spec | Configured with credentials |
| stables | — | Never |

## 6. The CRM import boundary (designed, not built)

A DMO that has a CRM (iDSS, Simpleview or another) will want listings to come from it. This spec doesn't build that,
but the model has to be importable later without rebuilding it. Four rules:

1. **The CRM ids are the join.** Every partner, category, attribute and cuisine has them. Empty means "managed here".
   An import matches on the listing id, never on title or slug, and reads status and tier from the account.
2. **Ownership is per field, and written down.** An import owns the record fields: title, address, phone, email, URL,
   coordinates, hours, categories, attributes, cuisines — and `partnerStatus` and `partnerTier`, which are facts about
   the membership rather than about the content. Editors keep the editorial ones: `pageBuilder`, `topics`, `excerpt`,
   `seo`. An import must never write an editor-owned field — that's the mistake that makes staff stop trusting a
   synced site.

   **Documented isn't enforced.** Once an import is configured, the CRM-owned fields should be read-only in the CP
   with a "Managed in <CRM>" note, or a staffer will edit one and lose it on the next sync — which is the exact
   failure this rule exists to prevent.
3. **An import never deletes.** A listing that leaves the CRM is disabled, so its URL, SEO and inbound links can be
   handled deliberately.
4. **Turning it on is a one-way, deliberate step per site.** Once the CRM owns categories, editors don't also
   hand-manage them.

**Which CRM comes first is decided by the first DMO client**, not now. Whatever is built is written from that CRM's
published API documentation.

## 7. Events and partners (the field now, the behaviour later)

`eventVenue` ships with the set so no retrofit is needed. What a later spec builds on it:

- An event's detail page links to its venue and inherits the venue's address and coordinates when its own are empty.
- A listing's page shows its upcoming events, through `.occurring({from: today})` (D9).
- A calendar map places events at their venue's coordinates.
- `Event` JSON-LD takes `location` from the venue rather than the text fields (D8).
- Filtering the calendar by category, region or attribute (D10) — useful to every site with events, not just DMOs.
  `eventCategories` (§3.1) is what it filters on; before that taxonomy existed there was nothing to filter by.
- Partner-submitted events: a front-end form creating disabled entries, with moderation.

## 8. SEO, ADA and performance

**SEO.** Now that categories, attributes and regions have no URLs, the thin-page risk the first draft worried about
mostly evaporates: there is no auto-generated page per leaf category to be thin. The browse pages are hand-written
`pages` entries, so a DMO makes one for each intent worth ranking for and gives it real content. Two things follow:
the set must not be read as "taxonomies can never have pages" — a region that deserves one gets a `pages` entry — and
a partner has exactly one URL, so the duplication risk is gone by construction.

**Per-listing structured data.** D11 means the sitewide `LocalBusiness` node can't describe a partner. A per-entry
node, gated on the listing fields, is `seo` module work with its own spec — and it's a real differentiator, because
a listing with hours, price range and geo is exactly what local results reward.

**ADA.** A filter UI copies the events filter: a plain GET form that works without JavaScript (D10). Any map must have
a list of the same results as its equal, keyboard-reachable alternative, never a map-only view.

**Performance.** Relation filters over thousands of listings are the load. The later search spec has to measure with
a realistic count (the events work measured at 36,878 rows), eager-load relations on listing pages, and key every
cached fragment with `themeCacheKey()`.

## 9. Verification

Nothing here is built, so this is what a build would have to prove.

| # | Check |
|---|---|
| V1 | On a fresh stables, `starter-kit/set destination --dry-run` lists every operation and no refusals |
| V2 | Applying it creates the six sections, their types and fields; running it again changes nothing |
| V3 | Baseline fields (`seo`, `topics`, `pageBuilder`, `address`) are reused, not duplicated, and `event` gains `eventVenue` **in place** — existing events keep their content |
| V4 | A set whose `uriFormat` collides with an existing section is refused before any write |
| V5 | A partner and a deal render, get breadcrumbs and appear in the sitemap; a category, attribute and region have no URL and appear nowhere in it |
| V6 | `theme-designer/requirements/check` against a Destination theme reports nothing missing |
| V7 | Applying it on a site with existing entries refuses without `--consent` |
| V8 | With the set applied and no Destination theme, the site still works: the generic router renders the new sections without errors |

## 10. Decisions and open questions

### Decided (Gary, 2026-09-18)

- **Destination is a fifth site type.** Not a second Directory set, and not tied to any existing type: it's its own
  thing. `SITE_TYPES` gains `destination` (D1), and the library readers that rebuild rows from fixed fields
  (`LibraryClient`, site-launcher `themeLibrary.js`) gain it too. "One type, one set" (D4) stays as it is.
- **Sections without URLs get support in `StarterSet`.** Gary is making that change. It now carries three sections,
  not one — categories, attributes, regions, cuisines and the two new category axes are all taxonomies. D6 describes the code before it.
- **"Partners", not "listings", for the section and the fields.** It is the right word for most DMOs, so the rest
  change a label rather than everyone changing it. This also matches the real Tempest build field for field:
  `partnerHours`, `partnerPriceRange`, `partnerCuisineType`, `partnerAmenities`.
- **`listing/{slug}`** for the partner URL. Flat, and the niche convention.
- **Itineraries move to their own spec**, with the trip planner they belong to.
- **The set ships blocks.** `"blocks": {}` was copied from a stub where it was true; here the browse pages can't
  exist without one.
- **`partnerStatus` and `partnerTier`, both CRM-owned**, replacing the editor-owned `listingFeatured`.
- **Two CRM ids**, account and listing.
- **Meeting venue fields ship now**; the planner pages, RFP form and lead routing are a later spec. Q7 is answered.
- **Cuisine is its own taxonomy.**
- **`partnerAttributes` in code, “Amenities” as the default label.** The handle follows the CRM and the real build;
  the word an editor reads is the one a visitor would use, and it's per site like the Partners label. “Attributes”
  is also the wider word — it holds “locally owned” or “open year round”, which no one would call an amenity.
- **A taxonomy per content type**: `eventCategories` and `dealCategories` join the set, matching the real build.
  `topics` stays shared for editorial relations only.

### Open

- **Q1 — URL prefixes.** Now only two: `listing/` and `deals/`. Should site-launcher let them be set at creation,
   or is editing the JSON before applying enough?
- **Q9 — Which blocks the set ships.** At minimum a directory grid (category, region, attributes, count, layout),
   since that block is what builds every browse page. An events grid and a deals grid follow the same argument; a
   map is probably later.
- **Q3 — `listingBusinessType`.** A curated Dropdown (about 20 common types), or the full LocalBusiness subtree the
   SEO settings already offer (D11)? The full tree is hundreds of options in an editor's face.
- **Q4 — Hours.** A Matrix of day rows, as above, or one Plain Text line per listing? The Matrix is what
   structured data needs; the text field is what small DMOs actually maintain.
- **Q6 — Deals in v1?** Half-answered: itineraries are out (§3.3). Deals stay for now, on the grounds that they're
   partner-submitted content a DMO expects, and the set is additive so being slightly wrong is cheap.
- **Q8 — What proves the set.** I'd propose: the demo site built for bidding, with real listings copied from a public
   destination site, is what the set is rewritten from before it's used on a paying client.
