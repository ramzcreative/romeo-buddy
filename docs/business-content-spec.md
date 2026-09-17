# Business content — authors, related content, galleries, articles, projects and topics

Written 2026-09-14. Folding Publication and Portfolio into the Business site type ([`site-types-spec.md`](site-types-spec.md)) makes
these baseline stables content, per Gary:
- **on:** authors, related content (a standard on every build), a gallery block with a lightbox, and **Topics**, which
  replaces blog categories;
- **added but off:** articles and projects.

Every "today" claim in §2 was read in the code or the local DB on that date. **Status: draft, unbuilt; all decisions made
(§9).**

## 1. Goal

1. **Authors:** real public author profiles, credited on posts, with correct structured data. Never a CP username.
2. **Related content** on every detail page, and as a block, from manual picks topped up automatically.
3. **Galleries:** a gallery block with an accessible lightbox. Nothing like it exists today.
4. **Topics** is the one taxonomy for every section. It replaces `blogCategory`, whose pages are broken (B12).
5. **Articles and Projects** exist in every new site, switched off, and a single command switches each one on.
   Projects is renamed per site (e.g. "Work", "Programs") the way Events already is.
6. Every addition follows repo rules:
   - migrations use Craft's services;
   - SEO logic is gated on a field existing (dormant capability);
   - templates guard empty content;
   - list semantics survive Safari.

## 2. What we have today

| # | Claim | Where |
|---|---|---|
| B1 | **No authors anywhere.** Sections keep Craft's default one author per entry, no template shows an author, there's no author field or section, and user accounts have no custom fields or photo volume. The local DB's only user, `rzadmin`, has no full name. | `sections/*.yaml:7`; `users/users.yaml:3`; `users/fieldLayouts/*.yaml`; DB |
| B2 | **Security: article pages publish the CP username.** `buildArticle()` sets JSON-LD `author.name` from `$entry->getAuthor()->getName()`, and the SEO extension emits `<meta name="author">` the same way. `User::getName()` returns `fullName ?? username`, so any author account without a full name puts its **login username** in every blog post's source. Locally that's `rzadmin`. Every site on this seo module with articles is affected. | `StructuredDataBuilder.php:606`–`:611`; `SeoTwigExtension.php:162`; `vendor/.../elements/User.php:1607` |
| B3 | **Taxonomy is blog-only.** The `blogCategory` category group has `blog/category/{slug}` pages, and the blog index filters by it. Events and jobs have none. There are no tags, and no Entries-based taxonomy. | `categoryGroups/blogCategory--*.yaml:105`; `_sections/blog/index.twig:59`–`:84` |
| B4 | **Category pages are second-class:** not in the sitemap (entries only), no breadcrumbs (Entry only), and SEO tags use sitewide defaults because `scaffold.twig` passes `entry` only. `_category.twig` reads `category.postBuilder` and `category.intro`, which aren't on the group's layout, and its "latest" query isn't filtered by the category. | `SitemapGenerator.php:146`; `StructuredDataBuilder.php:383`; `scaffold.twig:19`, `:68`; `_category.twig:8`–`:53` |
| B5 | **No related content.** An unused `entries` field (all sources, max 9) exists. The blog detail's "View Other Blogs" is the 3 latest posts and **can include the post being read**. Events and jobs detail pages have nothing. | `fields/entries--*.yaml`; `_sections/blog/_detail.twig:8`–`:13`, `:70` |
| B6 | **Cross-section picking already exists:** `sourceEntry` (all sources) plus `ItemResolver`'s heading/intro/image chains, with per-section overrides. | `fields/sourceEntry--*.yaml:18`; `ItemResolver.php:158`–`:195`; `config/stables/items.php:26` |
| B7 | **The migrations for adding a section already exist:** events and jobs. Each creates its fields, adopts or creates the entry type (`setTabs()` before `setElements()`), creates the section, adds a **disabled** Entries source, and adds a `postType` option. `safeDown` reverses in order. Index pages (`/blog`, `/events`, `/jobs`) are ordinary `pages` entries holding a `posts` block, not routes. | `m260818_200000_addEventsSection.php:59`–`:358`; `m260821_100000_addJobsSection.php`; DB |
| B8 | **An element source `disabled` flag does more than hide the sidebar.** It also removes the section from relation-field source settings (`availableSources()`), and very likely from selector modals. It doesn't affect front-end routing. | `ElementSources.php:92`; `BaseRelationField.php:1838`–`:1841`; `ElementIndexesController.php:360`; `Entry.php:1336` |
| B9 | **An empty article section still advertises a feed.** `scaffold.twig` emits `<link rel="alternate">` for every article section with a URI prefix, and `feed.xml` returns 200 with an empty channel. The sitemap already skips empty sections. | `scaffold.twig:92`; `SeoResolver.php:1023`; `FeedGenerator.php:61`; `SitemapGenerator.php:71`–`:85` |
| B10 | **Article sections are config:** `seo.php` `sectionDefaults` plus the CP's `articleSections`, with `blog → BlogPosting` as the fallback. | `config/stables/seo.php:90`; `SeoResolver.php:834`–`:886` |
| B11 | **Router fallback is quiet:** `_sections/<handle>/_detail` → `<handle>/<handle>` → `_sections/default`, all `ignore missing`, and `_layouts` guards `entry.pageBuilder ?? null`. | `_router.twig:1`; `_layouts/index.twig:26` |
| B12 | **Blog category pages crash in dev.** `http://stables.test/blog/category/family-fun` returns **500**: `Variable "entry" does not exist in "_sections/blog/heroDetail" at line 26`. `_category.twig` includes `heroDetail`, which reads `entry`, but a category route passes only `category`. It also reads `category.intro` and `category.postBuilder` unguarded (neither is on the group's layout), and its post list is the 3 latest posts, **not that category's posts**. With `strict_variables` off in production it probably renders, but without the category's posts; that's unverified. romeo-buddy has the same `blogCategory` group and templates. | local `curl` 2026-09-14; `_category.twig:8`–`:53`; `heroDetail.twig:26`; romeo-buddy `config/project/categoryGroups/` |
| B13 | **No gallery or lightbox exists.** `_blocks/collage.twig` is an orphan: it has a template but no entry type. | grep; `_blocks/collage.twig` |

## 3. Step 0 — stop publishing usernames (B2)

This is independent of everything else and goes first.

- **`buildArticle()` and the meta tag:** use the authors field when the entry has one (§4.3). Otherwise use the user's
  **full name only**. With no full name, drop `author` and rely on `publisher` (the Organization), which is valid for
  Article/BlogPosting.
- **Never `getName()`,** anywhere in the seo module. Grep all of craft-modules for `getName()` on users in front-end
  output while there.
- **romeo-buddy:** check its production blog source for a username before and after (a read-only GET). Port only on
  Gary's yes.

## 4. Authors (on)

### 4.1 Content model

**Authors are entries, not Craft users:**
- Guest writers and staff shouldn't need CP accounts.
- A public profile shouldn't be tied to a login.
- Entries get URLs, SEO fields, the sitemap and breadcrumbs for free, which category-style profiles wouldn't (B4).

| Piece | Setting |
|---|---|
| Section `authors` | Channel, `authors/{slug}`, `_router.twig`, versioning on, element source **enabled** |
| Entry type `author` | **Profile** tab: Title (the name), `jobTitle` (plain text), `image` (photo; existing field), `excerpt` (short bio; existing field), `sameAs` (Table, one URL column: profiles elsewhere). **Content** tab: `postBuilder` (optional long bio). **SEO** tab: `seo`. |
| Field `postAuthors` | Entries field, source `section:authors`, max 3, card view, labelled "Authors". Added to `blogPost` (and `article`, §6) after Excerpt. The handle isn't `authors`: Craft reserves that word (an entry's own CP-user authors). |

- **Created by a migration** in the events/jobs pattern (B7): adopt existing, `setTabs()` then `setElements()`, and
  `safeDown` in reverse.
- **Not required.** A post with no author credits the Organization (§3).
- **Craft's own author** (the CP user) is untouched. It stays the "who created this" record.

### 4.2 Front end

- **`_sections/authors/_detail.twig`:** photo, name (`h1`), job title, bio, profile links (`ul role="list"`,
  `rel="me"`), then their posts across sections with `relatedTo({targetElement: author, field: 'postAuthors'})`,
  paginated via `_listing`.
- **Byline on blog/article detail:** "By Name, Name". Each name links to the profile, with a small photo that has
  `alt=""` because the name is next to it. It renders nothing when empty.
- **Blog cards:** unchanged (no byline) unless Gary wants one. See §9.

### 4.3 SEO, gated on the field

- **An entry whose layout has the `postAuthors` field** (handle from `seo.php` `authorField`, default `postAuthors`) outputs
  `author` as Person nodes. Each has:
  - `@id` `<profile url>#person`
  - `name`, `url`, `image`, `jobTitle`
  - `sameAs` from the table
- **The author's own page** is `ProfilePage`, with the Person node as `mainEntity` under the same `@id`, so the two link
  up.
- **`<meta name="author">`** lists the names.
- **RSS:** `<dc:creator>` per item. This adds the `dc` namespace to `FeedGenerator`.
- **A site without the field** gets §3's behaviour.

## 5. Related content (on)

### 5.1 Content model

| Piece | Setting |
|---|---|
| Field `relatedEntries` | Entries field, sources = the URL sections listed in `config/stables/related.php` (not `pageTemplates`, `authors`, `topics`), max 6, card view, labelled "Related". Added to `blogPost`, `article`, `event` and `job` on their Settings tab. |
| Block `related` | Components group, in every builder field. `blockHeading` plus `relatedEntries`. |
| Config `config/stables/related.php` | `field`, `topicsField`, the fill sections, `limit` (3), `fillFromSameSection` (true) |

The unused `entries` field (B5) is left alone. It carries no content, and renaming it would be churn.

### 5.2 How the list is built

A `RelatedContent` service in `stablestwigextensions`, exposed as `relatedEntries(entry, limit = null)`:
1. **Manual picks:** live, current site, in the editor's order.
2. **Topped up from shared topics**, when the entry's layout has the topics field and it has topics:
   `relatedTo({targetElement: topics, field: topicsField})` across the fill sections, newest first.
3. **Topped up from the same section**, newest first.
4. **The entry itself and anything already listed are always excluded.** That fixes B5's self-link.

- Each step runs only if the list isn't full yet. That's at most 3 queries, with images eager-loaded.
- The result is an array of entries; an empty array means render nothing.
- Every field read is guarded by `getFieldByHandle()`, like `ItemResolver`.

### 5.3 Rendering

- **`_partials/related.twig`:**
  - `<aside aria-labelledby>` with an `h2` ("Related", overridable by the caller);
  - a `ul role="list"` of cards;
  - each card has an image with `alt=""` (the title link is adjacent), a section label ("Event", "Article"), the title
    as the link, the date for dated sections, and the excerpt.
  - Headings, intros and images resolve through `ItemResolver`'s `sectionKeys`, so an event card can use its own
    fields (B6).
- **Detail pages:**
  - It replaces blog "View Other Blogs" (B5).
  - It's added to events and jobs detail, and to articles (§6).
- **The `related` block:** manual picks. With none, it tops up from the page's topics when the page has the field, otherwise
  it renders nothing (§3 of the blocks spec).
- **Caching:** `{% cache using key themeCacheKey('related-' ~ entry.id) %}` for logged-out visitors. Craft's element
  cache tags clear it when a listed entry is saved.
- **CSS:** `blocks/related.pcss` in `layer(components)`. Cards reuse the cards grid styles where they match.

### 5.4 As built (2026-09-16)

- **Two section lists in `related.php`:** `sections` (what editors can pick; the migration reads it once for the field's
  sources: blog, events, jobs, pages) and `fillSections` (what top-ups draw from: blog, events, jobs). Pages are
  pick-only, since "the newest pages" isn't related to anything.
- **`sectionCriteria`:** extra query params for the same-section top-up, so an event page fills with upcoming events,
  soonest first (`eventStart >= now`), and a job page with open jobs (`jobValidThrough` empty or `>= today`). The
  topics top-up doesn't apply them yet; revisit in 1.11.
- **`dateFields`:** the date a card shows (`blog` → `postDate`, `events` → `eventStart`); other sections show none.
- **Picks always show in full** (up to the field's 6); top-ups only fill to `limit`.
- **Field placement:** end of the Settings tab on `event` and `job`; `blogPost` has no Settings tab, so the end of its
  first tab.
- **The block** is offered in all four builder fields (Components in pageBuilder, General in postBuilder). Its top-up is
  topics only, from its page (followed up through a Container).
- **Card values** come from `ItemResolver::resolveEntry()` (Twig `entryData()`), the entry chains in `items.php`, so
  `sectionKeys` apply. Twig functions: `relatedEntries(entry, limit)`, `relatedForBlock(block)`, `relatedDate(entry)`.

## 5b. Gallery block and lightbox (on)

> **Content model superseded** by `business-blocks-spec.md` §5.2 (2026-09-17): a gallery is now an entry in the
> Galleries section (`galleriesCollection`), and the block and `project` pick one with `galleryEntry` instead of holding
> `galleryImages`. The block also has a `ticker` layout (§4.4). The markup and lightbox below are unchanged.

### Content model

| Piece | Setting |
|---|---|
| Field `galleryImages` | Assets field, the Images volume, images only, multiple, reorderable, card view |
| Block `gallery` | Media group, in every builder field. Own fields: `blockHeading`, `galleryImages`, `layoutGallery` (buttonbox: **Grid**, **Masonry**; Grid is the default) |
| Reuse | `galleryImages` is also on `project` (§6.4), rendered by the same partial |

- **Alt text and captions** come from the asset: Craft's native **Alt** field, and the Images volume's caption field if
  it has one. Check the Images volume layout shows Alt, and add it there if not. An editor sets alt once per image,
  not once per gallery.

### Markup (`_partials/gallery.twig`, used by the block and by projects)

- Renders nothing when there are no images.
- A `ul role="list"` of `li > figure`. Each figure holds `<a href="<large transform>">` wrapping the thumbnail (`srcset`,
  lazy except for the first block on the page), and a `figcaption` when there's a caption.
- **Without JS**, each link opens the large image. Nothing is hidden behind the script.

### Lightbox (`<gallery-lightbox>` Web Component, `Components/galleryLightbox.ts`, dynamically imported only when a gallery is on the page)

- **Native `<dialog>` with `showModal()`:** the rest of the page is inert, Escape closes it, and focus returns to the
  thumbnail that opened it. That gives a real modal with no focus-trap code to get wrong.
- **Controls:**
  - Previous / Next / Close as real `button`s with text labels;
  - arrow keys;
  - swipe on touch;
  - "3 of 12" as text, announced politely.
- **Image:** the large transform is loaded only on open, and neighbours are preloaded; alt and caption match the
  thumbnail. The dialog's accessible name is the gallery heading, or "Gallery".
- **Motion:** a fade and scale with the Base easing tokens, and none under `prefers-reduced-motion`.
- **Settings:** CSS custom properties (`--gallery-lightbox-backdrop`, `--gallery-columns`) with defaults. No data
  attributes.
- **Budget:** under 3 KB compressed and no library. Thumbnails reserve their aspect ratio, so there's no layout shift.

### As built (2026-09-16)

- **`galleryImages`** uses Craft's thumbnail view (`large`), not cards: editors are picking pictures.
- **Captions:** the Images volume has only Title and Alt, so no captions render yet. The partial reads a `caption` field
  on the asset, so adding one to the volume turns them on.
- **The dialog is rendered by Twig** inside `<gallery-lightbox>`, so its labels ("Previous", "Next", "Close",
  "{current} of {total}") are translatable; the component only wires it. Close gets focus on open.
- **Transforms:** `galleryGrid` (800×600 crop, shown 4:3), `galleryMasonry` (800 wide, fit), and `fullwidth` for the
  large image. When the block is first on the page, its first three images load eagerly.
- **The block** is offered in all four builder fields (Media in pageBuilder, General in postBuilder). Layout icons:
  `layout-gallery-grid.svg`, `layout-gallery-masonry.svg`, drawn to the layout icon rules.
- **Size:** the component is 0.87 KB gzipped, loaded only on a page with a gallery.

## 6. Topics (on), Articles and Projects (off)

### 6.1 What "off" means in stables

It can't just be the element source flag. That also removes the section from relation-field sources (B8), so a field
pointing at an off section couldn't pick from it. And an article section with no entries still advertises a feed (B9).

**Off:**
- the section and entry type exist;
- the element source is disabled (hidden in Entries);
- **its relation field isn't attached to any layout**;
- no `postType` option;
- no index page;
- not in `seo.php` `sectionDefaults`.

**On** is a console command, `php craft stablestwigextensions/content/enable <articles|projects>`. It's idempotent and prints what
it did:
1. enables the element source;
2. attaches `topics` (and `postAuthors` for articles) to the new entry type if the migration didn't already, **in place**
   (never rebuilding a layout: content is keyed by layout element uid);
3. adds the `postType` option, and creates a **disabled** index `pages` entry (`/articles`, `/projects`) with a `posts`
   block;
4. for Articles: prints the `seo.php` line to add (`articles → Article`). PHP config isn't rewritten by code;
5. `--name="Work" --uri=work` (optional): renames the section and entry type and sets the URI prefix, keeping the
   handle, the same way Events gets renamed per site. Handles stay `projects`/`project`, so templates, config and
   skills don't change.

The resulting project config YAML is committed like any content model change.

**Plus one seo fix for B9:** `getArticleFeeds()` skips sections with no live entries, using a cached count cleared on
entry save, so a feed link never points at an empty feed.

**As built (2026-09-16):**
- **Command route** is `stablestwigextensions/content/…` (the module's id), not `stables/content/…`.
- **The migration attaches** `postAuthors`, `relatedEntries` and `topics` to the new entry types, so `enable` reports
  those steps as already done. What it adds: the Entries source (Articles and Projects sit after Jobs, hidden), the
  `postType` option, a disabled index page with a posts block, and the section in `relatedEntries`' sources. It prints
  the `related.php` and (for Articles) `seo.php` lines to add by hand.
- **Rename:** `--name` renames the section, `--type-name` the entry type (default `--name`), `--uri` sets the URI prefix
  and the index page's slug. Handles don't change.
- **`attach-topics`** takes any entry type in a section with URLs and appends `topics` to its Settings tab.
- **B9 fix** (craft-modules seo): `SeoResolver::sectionHasLiveEntries()`, cached until any entry is saved; an empty
  article section gets no `<link rel="alternate">`, and its `feed.xml` 404s.
- **Down** drops the sections' saved source config too, so project config comes back clean.
- **Checked:** enable (twice, idempotent) for Articles and for Projects renamed "Work" at `/work`; `attach-topics`
  (and its refusal of a non-URL type); an article with takeaways (an empty row skipped), sources (a row without a title
  skipped), a review date, reading time and byline; `Article` JSON-LD with `citation` and a reviewed `dateModified`
  once `seo.php` names the section; a project with facts, services, gallery and related; the Work index and the topic
  page listing both; the empty-section feed; the deploy path and the down path; the hidden sources in the CP.

### 6.2 Articles

**How they differ from blog posts** (Gary): a blog post is friendly (tips, stories, updates). An article is serious and
built on facts, data and research. That difference is structural, not just tone, and it's what the layout carries:

| Piece | Setting |
|---|---|
| Section `articles` | Channel, `articles/{slug}`, `_router.twig`, versioning on |
| Entry type `article` | **Content:** Title, Alternative title, Excerpt, Image, `postAuthors`, `keyTakeaways`, `postBuilder`. **Sources:** `references`, `reviewedDate`. **Settings:** `relatedEntries` plus `topics`. **SEO.** |
| `keyTakeaways` | Table, one text column, max 5. Rendered above the body as a "Key takeaways" `aside` with a `ul role="list"`. Renders nothing when empty. |
| `references` | Table: Title, Publisher, URL, Date. Rendered after the body as a numbered "Sources" list (`ol`), each linking out with `rel="noopener"`. |
| `reviewedDate` | Date. Shown under the byline as "Reviewed <date>" when set; "Updated <date>" uses the entry's `dateUpdated` when it's later than `postDate`. |
| Templates | `_sections/articles/_detail.twig`, `index.twig`, `_card.twig`, following blog's, with byline, takeaways, sources and related. Reading time from the body's word count. |
| SEO (gated on the fields) | `Article` (not `BlogPosting`); `citation` from `references` as `CreativeWork` nodes (name, url, publisher, datePublished); `dateModified` from `reviewedDate` or `dateUpdated` |

A data/table block for charts and figures is a likely next block, but it isn't in this spec.

### 6.3 Topics (on) — replacing blog categories

Decided 2026-09-14 (Gary accepted the recommendation). **One taxonomy for every section.**
- `blogCategory` only works for the blog.
- Related content tops up by topic across sections (§5.2).
- Category pages miss the sitemap, breadcrumbs and their own SEO (B4), and they're broken (B12).
- **Topics are entries**, so topic pages get all of that for free.

| Piece | Setting |
|---|---|
| Section `topics` | **Structure**, 2 levels, `topics/{slug}`, `_router.twig`, element source **enabled** |
| Entry type `topic` | Title, Excerpt, Image, `pageBuilder`, SEO |
| Field `topics` | Entries field, source `section:topics`, no max, card view. **On `blogPost` by default**, where `blogCategories` was; `article` and `project` get it from their migrations. Events and jobs: `php craft stablestwigextensions/content/attach-topics <event\|job>` per site, in place. |
| Templates | `_sections/topics/_detail.twig`: the topic's own blocks, then everything tagged with it across sections, paginated via `_listing`, with a section filter. Breadcrumbs follow the structure. |
| Blog index filter | `_sections/blog/index.twig` and `_filters.twig` filter by topic (`relatedTo` on the `topics` field) instead of category, with the same GET parameter validation, and the `category` parameter renamed `topic`. Only topics that have blog posts are listed. |
| Blog detail sidebar | The "Categories" list becomes "Topics", linking to topic pages |
| Nav | `_sections/blog/nav.twig`'s category menu lists topics with blog posts |

**The move, in two phases** (the migration-order rule: never delete what a migration still needs to read, in the same
deploy):

1. **Phase 1, additive** (one migration + templates):
   - create `topics` and the `topics` field and attach it to `blogPost`;
   - create one topic per blog category (same title, slug, excerpt and image, nested the same way) and set each blog
     post's `topics` from its `blogCategories`, through `setFieldValue()` + `saveElement()`, never raw relation rows;
   - the migration **asserts its own outcome**: every post's topic slugs equal its old category slugs, or it throws;
   - templates switch to topics;
   - add one token redirect, `blog/category/<slug:[\w-]+>` → `/topics/<slug>` (301), through the redirects module;
   - **nothing is deleted**, and the category pages stay routable until phase 2 (they 301 anyway).
2. **Phase 2, destructive: two deploys** (found building it, 2026-09-16). Project config applies before content
   migrations, so a committed deletion of the group and field runs before any migration can check, copy or back up
   anything. Tested: a category added after phase 1 and a post given only categories were lost, and a nav link to a
   category was left dangling.
   - **2a, nothing deleted** (`m260916_200000_prepareCategoryRemoval`): takes `blogCategories` off `blogPost`, so no
     post gains a category after it ships; copies anything added since phase 1 (a new category, a post or draft with
     categories but no topics; a post that has topics keeps exactly those), reading the relations table so it works
     after project config has already taken the field off the layout; repoints nav nodes that link to a category;
     asserts nothing would be lost. Category pages still 301 through `_category.twig`.
   - **2b, delete** (`m260917_100000_removeBlogCategories`, its own deploy after 2a is verified): project config
     deletes the field and the group; the migration refuses to run without 2a, repoints any nav node linking to a blog
     category (trashed ones included) at the topic with the same slug, reports one with no topic, and deletes the
     field and group itself where project config didn't, after a backup. Deletes `_category.twig`; the phase 1 rule
     301s category URLs from then on.
   - **The backup runs in `up()`, before Craft's transaction.** Inside it, mysqldump waits on locks the migration holds
     and the deploy hangs (seen locally). Still take a backup before deploying 2b: on a normal deploy project config
     deletes before any migration runs.
   - **Checked, both deploys in real order** (old DB + new project config + `php craft up`), with drift between each:
     categories and posts caught up, nav nodes repointed, topics intact, `/blog/category/<slug>` 301s. Phase 1 and 2a
     replay cleanly on a site with no categories (a phase 1 check read `blogCategories` unguarded; fixed).

**Phase 1 as built (2026-09-16):**
- **Category pages 301 from `_category.twig` itself** (to the topic with the same slug, else 404). The redirects module
  only acts on a 404, and the category routes still resolve until phase 2, so the stored
  `blog/category/<slug:[\w-]+>` → `/topics/<slug>` rule takes over only once the group is gone.
- **Drafts are tagged too** (not revisions), so applying an older draft doesn't clear a post's topics. Tagged posts get
  a new `dateUpdated`.
- **`?category=` still filters the blog index,** as an alias for `?topic=`, so old filtered links keep working.
- **Topic page:** a header (parent link, h1, excerpt, subtopic links) unless the topic's first block is a hero, the
  topic's own blocks, then "Everything on {topic}" through `_listing`. The section filter shows only when more than one
  section has tagged entries; the parameter is checked against those. Cards are the shared `_partials/entryCard.twig`,
  which the Related list now uses too.
- **Checked:** the deploy path (old DB, then `php craft up`: project config first, the migration adopting it and still
  copying and tagging, no config drift); the down path; category 301s; `?topic=`/`?category=`; the nav variant; the
  topic page with an event temporarily tagged (filter, `?section=events`); Related topping up by topic across sections
  (1.8, end to end); BreadcrumbList; the topics sitemap.

**romeo-buddy isn't changed.** It has the same `blogCategory` group and templates (B12), but real content. Moving it is
a separate decision, and until then its category pages stay as they are. If it moves, it's the same two phases, with
production verified between them.

### 6.4 Projects

For work, case studies or programs on a service site. Renamed per site (§6.1).

| Piece | Setting |
|---|---|
| Section `projects` | Channel, `projects/{slug}`, `_router.twig`, versioning on |
| Entry type `project` | **Overview:** Title, Alternative title, Excerpt, Image, `projectClient` (plain text), `projectDate` (date), `projectLocation` (plain text), `projectServices` (Entries, `pages` section, max 5: links to the service pages it relates to). **Gallery:** `galleryImages` (§5b). **Content:** `pageBuilder`, where results go in a Stats block (blocks spec §5.1) and a testimonial goes in a Blockquote. **Settings:** `relatedEntries` plus `topics`. **SEO.** |
| Templates | `_sections/projects/_detail.twig`: hero, a facts list (`dl`: Client, Date, Location, Services, each shown only when set), gallery, blocks, related. `_card.twig`: image, client, title. Listed through the `posts` block like blog/events/jobs. |
| SEO | No article schema. Breadcrumbs and page SEO as for pages. |

Every project field is optional, and each renders nothing when empty.

## 7. Where things live and build order

The order across all three specs is set in [`theme-content-roadmap.md`](theme-content-roadmap.md): stored formats and content model first, screens second. The table below is this spec's detail.

| Step | What | Repo |
|---|---|---|
| 0 | §3 username fix | craft-modules `seo` |
| 1 | Authors: migration, templates, byline, §4.3 SEO | stables + craft-modules `seo` |
| 2 | Related: field, block, service, partial; replace "View Other Blogs"; events/jobs | stables |
| 2b | Gallery block + lightbox (§5b) | stables |
| 3 | Topics phase 1 (§6.3): section, field, category → topic copy with asserted outcome, blog templates, redirect | stables |
| 3b | Gary verifies on stables, then Topics phase 2 (§6.3) in its own commit/deploy | stables |
| 4 | Articles and Projects off: migrations, templates, `stables/content/enable` (with rename), `attach-topics`, B9 feed fix, article SEO | stables + craft-modules `seo` |
| — | Skills: `ramz-content-import` can set authors/topics/related; `ramz-page-scaffold` knows the `related` block; `page-patterns.md` gains it | stables, craft-modules |

**Skills as built (2026-09-16):** the content-import action gained a `relations` key (Entries field handle → slugs,
page or block, resolved within the field's sources, nothing created; `content-import-spec.md` "Relations"), verified
with an author, a topic and related picks on a blog post and a `related` block on a page. `ramz-content-import` reads
bylines, tags and related links into it; `ramz-page-scaffold` knows `related`, `gallery` and `stats`; `page-patterns.md`
lists all three.

## 8. Verification

| # | Check |
|---|---|
| C1 | **Step 0:** a blog post by `rzadmin` (no full name): no `rzadmin` anywhere in the page source (JSON-LD, meta, feed); with a full name set, the full name shows |
| C2 | **Authors:** the profile page renders with ProfilePage JSON-LD; a post with two authors shows both in the byline and as two Person nodes whose `@id`s match the profile pages; Rich Results Test passes |
| C3 | **Authors a11y:** byline links have the author name as their accessible name; VoiceOver reads the profile links as a list in Safari |
| C4 | **Related:** a blog post with 1 manual pick and no topics → the pick plus 2 latest blog posts, never itself; an event gets events; an empty block renders nothing |
| C5 | **Related cost:** at most 3 queries for the list (query log), and a cache hit on a second logged-out load |
| C6 | **Off:** a fresh install has the articles/projects sections with Entries sources hidden, no `postType` option, no feed link, and no sitemap entry |
| C7 | **Enable:** `enable articles` shows the source, adds the `postType` option and creates a disabled `/articles` page; a second run changes nothing; `attach-topics event` adds the field to events in place and existing event content still reads (content keys unchanged) |
| C8 | **Migrations:** `php craft up` on a restored DB applies them; `safeDown` reverses; project config YAML diff is additive only |
| C9 | **Topic page:** it lists a blog post, an event and a page tagged with it; the section filter works; it has breadcrumbs, is in the sitemap, and has its own SEO title |
| C10 | **Gallery:** no-JS links open large images; lightbox opens with focus inside, arrows/swipe move, Escape closes and focus returns to the thumbnail; VoiceOver reads alt, caption and "3 of 12"; reduced motion has no animation; CLS 0; the large image loads only on open |
| C11 | **Articles:** takeaways and sources render only when filled; JSON-LD is `Article` with `citation` nodes and `dateModified`; Rich Results Test passes |
| C12 | **Projects:** `enable projects --name="Work" --uri=work` → the CP shows "Work", the URL is `/work/<slug>`, handles unchanged; the facts list omits empty rows; the gallery renders through the shared partial |
| C13 | **Topics phase 1:** on a restored DB, `php craft up` → one topic per former category, nested the same; every blog post's topics match its old categories (the migration's own assertion, and a spot check in the CP); `/blog?topic=family-fun` lists the same posts `/blog?category=family-fun` did before; `/blog/category/family-fun` 301s to `/topics/family-fun`, which returns 200 and lists those posts; no 500 anywhere under `/blog` |
| C14 | **Topics phase 2:** after a backup, `php craft up` removes the field and group; blog posts, topics and the redirect are unaffected; the YAML diff removes only the category pieces |

## 9. Decisions and questions

Decided by Gary, 2026-09-14:
1. **Portfolio folds into Business:** Projects (renameable) and galleries are baseline.
2. **Articles are serious, fact-, data- and research-based;** blog posts are friendly (§6.2).
3. **Bylines on detail pages only.**
4. **Related heading:** the partial takes a heading, defaulting to "Related"; events and jobs pass their own.

5. **Blog categories are replaced by Topics** in stables, in two phases; Topics is on (§6.3). romeo-buddy is a
   separate decision.

No open questions remain.
