# `blockfields.json` — why each of Base's rules exists

JSON has no comments, so the reasons live here. Rules describe what **Base's own block templates** don't render; a theme
whose templates differ sets its own units in `themes/<handle>/config/blockfields.json`. How levels combine, and the rule
tiers: `config/stables/blockfields.php`'s docblock.

| Rule | Why |
|---|---|
| `*.itemFields.hidden` includes `video` (cards, slider, banner, stats, spotlight) | `video` is on the shared `item` type (m260917_120000) but only `imageText · hero` renders it. Un-hide it only once a block's template renders it. |
| `*.itemFields.hidden` includes `statPrefix` / `statNumber` / `statSuffix` | Only `stats` renders them. |
| `slider.itemFields.perLayout`: `itemIcon` on `hero` | Only the hero layout is designed around an icon per slide. |
| `slider.ownFields.perLayout`: `sliderNav` on `hero` | Only the hero layout draws a nav; the others have their own arrows and pagination. |
| `imageText.itemFields.perLayout`: `video` on `hero` | The hero layout is full-bleed background media; `default` and `show` set media beside the copy, where a playing video would fight the text. |
| `hero.ownFields.perLayout`: `heroParallax` on `standard`, `video` + `autoplay` on `video`, `backgroundRole` on `product` | Each is read by that one layout only: parallax moves the standard hero's background image, the video layout's background is the video, and only the product layout's two columns sit on a colour (the others are full-bleed media). |
| `video.ownFields.perLayout`: `autoplay` on `inline` | A modal video starts when its dialog opens, so there's nothing for autoplay to do. |
| `logos.ownFields.perLayout` / `gallery.ownFields.perLayout`: `tickerDirection` on `ticker` | Only a ticker moves. |
| `posts.ownFields.perLayout`: `showFilters` on `grid`, `postLimit` on `list` and `slider` | Grid is the section's whole listing with its own filters and pagination; list and slider show the latest few, with no filters. |
| `stats.itemFields.hidden` | The label is the heading, the detail the intro, an icon optional; nothing else applies. |
| `imageText.ownFields.perLayout` / `slider.ownFields.perLayout`: `backgroundRole`, `backgroundImage` on every layout but `hero` | Both hero layouts are full-bleed media, so a block background can't show. |
| `nested.container.hidden`: `backgroundImage` | Inside a container a block keeps its colour but not its image — the container carries the image for the group (`_blocks/partials/background.twig`). |
| `nested.column.hidden`: `backgroundRole`, `backgroundImage` | Inside a Columns block's column a block shows neither; the columns block's colour sets the tone. |

`nested.<parent>.hidden` hides fields on any block directly inside that parent, wherever the parent sits. Unlike every
rule above, it's applied in the CP by script (`modules/stablestwigextensions/resources/js/blockNestedFields.js`), not
generated CSS: the CSS is kept one level deep on purpose (a selector reaching further also caught same-named fields at
other levels), and a block inside a column is two levels down, in a slideout, where no selector can tell which block
owns a field. The script reads each field's real owner from the page instead.

Eyebrows render wherever an item heading does, and icons render on cards (every layout), image + text (default,
show) and stats, so no rule hides `preheading` there or `itemIcon` on those blocks. `itemIcon` stays hidden on banner
and spotlight, whose templates don't render it. Update this file in the same commit as the rule.
