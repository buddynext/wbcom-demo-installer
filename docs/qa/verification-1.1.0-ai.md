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

## Pages and blog (Wbcom Essential 4.7.0)

| Case | Result |
|---|---|
| `wp wbcom-demo install pages` | PASS: Wbcom Essential 4.7.0 installed from the store with the preset key it ships, active |
| Community build, curated text | PASS: 393 items; ledger equals plan (4 pages, 3 categories before the build, 4 posts, 5 images); pages are core blocks (image, paragraph, columns, quote, heading, details, buttons, latest posts) plus Wbcom Essential post carousel and category grid, all rendered; menu gains About, Blog, FAQ |
| Remove everything | PASS: no pages, posts or categories of the demo left (Hello world and Uncategorized stay) |
| AI build "Ledgerly" (customer community for an accounting app), REST path, one step per request | PASS: 58 steps in 4 min 15 s, no warnings; pages "About the Hub", "Join the Hub", Blog with release notes; posts and categories about Ledgerly |
| Remove everything for that build | PASS: ledger 0, pages 0, posts 1 (Hello world), categories 1, hashtags 0, site name back |

Static Wbcom Essential blocks (hero, feature grid, FAQ accordion) are not written: their saved HTML comes from the
editor, and markup written on the server would be flagged as invalid when the owner opens the page.

## Regression matrix (11 November 2026)

`matrix.sh`, 23 cases (BuddyX, BuddyX Pro, Reign 8.0.9 branch and Twenty Twenty-Five; every starting point; each
product alone; Free and Pro together), with this 1.1.0 code. Each case: fresh install, build, checks, Keep my setup,
checks, Keep them as mine, then Remove everything from the built snapshot and a footprint compare.

First run: every case failed only the keep check, which still expected the 1.0 handover message. The check now
expects the 1.1.0 behaviour (owner, 10 November: kept setup stays tracked, "The items you kept are still tracked"),
then runs `adopt` and requires status idle with an empty ledger. Second run: **23 of 23 PASS** (build with no warnings,
no mail, no sample people left, Remove everything leaves nothing, footprint equal to before the build, no PHP errors).

Note: with every Pro add-on active the lab web server's 128 MB PHP limit fails page loads during the settle step
before a case starts (Jetonomy Pro and Learnomy Pro boot); that is card 10487106723's product-side boot memory, and the
site check now warns about it.

## Demo pages on three themes (11 November 2026)

Owner rule: every demo page must be production ready. Community build with Wbcom Essential 4.7.0; logged-out
screenshots at 1440 and 390 with the Playwright CLI on system Chrome (`--channel=chrome`, its own temporary profile).

| Page | BuddyX 5.2.0 | BuddyX Pro 5.2.0 | Reign 8.0.9 branch (e89122c) |
|---|---|---|---|
| About: wide photo (21:9), intro, three values, member quote, three posts in one row | PASS | PASS | PASS (also 390) |
| Join: photo of members, three steps, Create your account button | PASS | PASS | PASS (also 390) |
| FAQ: six questions that open and close | PASS | PASS | PASS |
| Blog: three category tiles with photos and post counts, six posts in two rows | PASS | PASS (also 390) | PASS |

Fixed on the way: Hello world showed as an empty card in the About carousel (blocks limited to the demo's categories);
the core latest-posts list stacked full-width images (now Wbcom Essential's post grid); category tiles showed letter
placeholders (each demo category gets a photo); titles hard to read on bright photos (dark overlay, wide tiles); four
posts left an orphan card (six posts); the About carousel's arrow covered a card badge (one-row grid); Join had no photo
and no full-width layout (photo plus `Provider::layout_pages()`); images had no alt text (every installer image now gets
one from its file name).

Media: `photos/rooftop-bar.jpg` carried a burned-in "cc-nc-nd" licence mark and was removed (BuddyNext and WPMediaVerse
content now use credited CC0 photos). 28 of the 43 library photos have no entry in `media/CREDITS.json`; they come from
WPMediaVerse's own `assets/demo-images` without credits. The pages and blog use only credited CC0 photos.

Product-side, not this plugin: logged out, WB Listora prints its "Log in to save listings" favourites dialog unstyled at
the bottom of every page where its styles are not loaded (seen on BuddyX Pro and Reign).
