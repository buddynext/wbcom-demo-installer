# 1.1.0 AI site builder verification

Run 11 November 2026 on the lab (port 8930), WordPress 7.1.3, PHP 8.3, all free products, BuddyX 5.2.0, the owner's
Anthropic connector in Settings > Connectors (snapshot `all-ready-ai`; `lab.sh keysave` / `keyload` keep the key across
restores, stored mode 600 and never printed).

| Case | Result |
|---|---|
| `Assistant::available()` with the connector; `overview()` | PASS: true; connectors Anthropic, Google, OpenAI; link to options-connectors.php |
| Ideas for "A running club in Kochi..." | PASS: 3 distinct valid ideas in about 18 s (events, learning, services), products only from the installer |
| Schema accepted by Anthropic | First run FAILED (minItems above 1 rejected); fixed by keeping only portable keywords, counts enforced in `Ideas::check()` |
| Text collection per product | PASS: people 134 texts / 1 request, BuddyNext 144 / 2, Jetonomy 220 / 3, Learnomy 76 / 2, others 1 each; slugs, enums, paths, times, links, keep lists never sent |
| Collection bugs found and fixed | `topics` in the shared skip list hid every Jetonomy topic (now People only); the email rule dropped texts with `{@N}` mention tokens (now whole addresses only) |
| Listora pack, one request | PASS: 33 of 33 texts rewritten in about 12 s, fitting each listing; addresses kept (they match the map coordinates) |
| `wp wbcom-demo build --describe="..." --idea=1 --yes` | PASS: "Kochi Dawn Runners", 397 items in 3 min 38 s, no warnings; spaces, posts, topics, events and bios about the club |
| Remove everything after that | FAILED first: 5 hashtags left (the AI added hashtags to captions that had none). Fixed: the check rejects added hashtags, and BuddyNext reads new tags from every text it posts |
| Wizard path over REST: /ideas, idea 2, /build with the brief, one step per request with Action Scheduler between steps | PASS: "Pune Yoga Circle", 55 steps in 4 min 16 s, no warnings or errors, 34 hashtags recorded; spaces, posts, topics and events about yoga in Pune; own name set, no sample logo |
| Remove everything for that build, one step per request | PASS: ledger 0, hashtags 0, sample people 0, site name back |
| Wizard screens in Node: no connector (admin, non-admin), connector set up, three idea cards, pick with a missing product, pick with everything active, Review | PASS: connect card for admins only; describe box naming the connector; recipe names and "will install"; missing product ticked with a banner; recipe and products filled; Review says the connector writes the text |

Known limit: WB Listora listings keep their original street and city because the map coordinates are fixed data.

Gates: PHPCS clean, PHPStan level 6 clean (the WordPress 7.0 AI client is newer than the stubs and is ignored by name),
docs lint 0/0, POT regenerated.

Not yet verified: browser check at 1440 and 390 in light and dark (Playwright browser held by another session).
