# `blockfields.json` — why each of Base's rules exists

JSON has no comments, so the reasons live here. Rules describe what **this site's Base block templates** don't render;
a theme whose templates differ sets its own units in `themes/<handle>/config/blockfields.json`. How levels combine,
and the rule tiers: `config/stables/blockfields.php`'s docblock. Moved here from that file unchanged (stables
docs/romeo-buddy-port-plan.md, P1); the field handles are this site's own (`iconPicker`, `comingSoon`).

| Rule | Why |
|---|---|
| `cards.itemFields.hidden`: `subheading`, `text` | The card layouts don't render them. |
| `slider.itemFields.hidden`: `text`, `iconPicker`, `comingSoon` | No slider layout renders them. |
| `slider.ownFields.perLayout`: `sliderNav` on `hero` | Only the hero layout draws a nav; the others have their own arrows and pagination. |
| `imageText.itemFields.hidden`: `subheading`, `iconPicker`, `comingSoon` | The image + text layouts don't render them. |
| `banner.itemFields.hidden`: `preheading`, `subheading`, `iconPicker`, `comingSoon`, `text` | The banner is a heading, intro, image and buttons. |
| `spotlight.itemFields.hidden`: `preheading`, `iconPicker`, `comingSoon`, `text` | The spotlight doesn't render them. |

Update this file in the same commit as the rule.
