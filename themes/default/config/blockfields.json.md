# `blockfields.json` — why each of this theme's rules exists

JSON has no comments, so the reasons live here. Rules describe what this site's block templates don't render, in its own field handles, and replace Base's rule for the same unit;
a theme whose templates differ sets its own units in `themes/<handle>/config/blockfields.json`. How levels combine,
and the rule tiers: `config/stables/blockfields.php`'s docblock. Moved here from that file unchanged (stables
docs/romeo-buddy-port-plan.md, P1); `comingSoon` is this site's own field; `itemIcon` was renamed from `iconPicker` by m260917_230000.

| Rule | Why |
|---|---|
| `cards.itemFields.hidden`: `subheading`, `text` | The card layouts don't render them. |
| `slider.itemFields.hidden`: `text`, `itemIcon`, `comingSoon` | No slider layout renders them. |
| `slider.ownFields.perLayout`: `sliderNav` on `hero` | Only the hero layout draws a nav; the others have their own arrows and pagination. |
| `imageText.itemFields.hidden`: `subheading`, `itemIcon`, `comingSoon` | The image + text layouts don't render them. |
| `banner.itemFields.hidden`: `preheading`, `subheading`, `itemIcon`, `comingSoon`, `text` | The banner is a heading, intro, image and buttons. |
| `spotlight.itemFields.hidden`: `preheading`, `itemIcon`, `comingSoon`, `text` | The spotlight doesn't render them. |

Update this file in the same commit as the rule.
| `gallery.available`: false | The shared gallery template renders a gallery picked from the Galleries section, which this site gets in the port's phase P7. Offered again then. |
