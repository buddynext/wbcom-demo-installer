# Test matrix

Every choice in the wizard is a buyer-facing path, so each one is tested end to end on the Docker lab: wizard in the
browser as the admin, the built site in the browser as a visitor and as a sample member, then WP-CLI and database
checks. A row is done only when all its checks pass at 1440px and 390px, light and dark where the theme supports it.

Lab: clean WordPress 7.x on PHP 8.3 (also 8.1 and 8.5 for the full-stack row), `WP_DEBUG` on, mail logged and never
sent. Released product zips only.

## A. Themes x starting points (build with defaults, then Finish with the default)

| # | Theme | Starting point | Products active |
|---|---|---|---|
| A1 | BuddyX 5.2.0 | Community | BuddyNext, WPMediaVerse, Jetonomy, Gamification |
| A2 | BuddyX Pro 5.2.0 | Community | same + Pro add-ons |
| A3 | Reign 8.0.9 | Community | same + Pro add-ons |
| A4 | BuddyX Pro | Learning community | + Learnomy (+Pro), MediaShield |
| A5 | Reign | Events community | + Eventonomy (+Pro) |
| A6 | BuddyX | Job board community | + WP Career Board (+Pro) |
| A7 | Reign | Directory | + WB Listora (+Pro) |
| A8 | BuddyX Pro | Services marketplace | + WP Sell Services (+Pro) |
| A9 | Reign | Everything I have | every Wave 1 product + Pro |
| A10 | Twenty Twenty-Five | Community | no Wbcom theme (generic adapter) |

Checks for every A row:
- Wizard: site check is accurate, only active starting points show, counts on Review match what is created.
- Build completes from the browser with no PHP notices in debug.log; progress survives a page reload mid-build.
- Visitor view: home page, menu and every product page linked from it show the sample content, no empty states,
  no broken images, dates spread over weeks, the same people appear across products.
- Member view (log in as a sample person): profile complete, feed, notifications bell, messages, course progress,
  RSVPs, applications or orders as that person's roles imply.
- Mail log empty after the build and after running pending scheduled jobs.
- Finish (default): zero sample people and sample items; settings, pages, menus, spaces, categories and plans still
  work in the browser; verify report all zero.

## B. Product choices

| # | Case | Expect |
|---|---|---|
| B1 | Each product alone (switch every other one off) | Builds and finishes cleanly on its own |
| B2 | BuddyNext without WPMediaVerse | Warning shown; no photo posts or messages; nothing fails |
| B3 | Jetonomy with BuddyNext | Discussions appear in the feed with discussion dates, not build time; Finish leaves no orphan feed posts |
| B4 | Gamification with others | Points and badges for sample people only; removed with them |
| B5 | Pro add-on inactive vs active | Pro extras only when the Pro add-on is active |
| B6 | Product version below the tested minimum | Shown as not supported, left out |

## C. Home page and menu

| # | Case | Expect |
|---|---|---|
| C1 | Use the community home page and menu | Front page, menu and logo set; snapshot saved |
| C2 | Keep my home page and menu | Front page, menu, logo untouched |
| C3 | Remove everything after C1 | Previous front page, menu and logo back |
| C4 | Owner changes the menu after C1, then Remove everything | Owner's menu kept, listed in the report |

## D. Finish modes

| # | Case | Expect |
|---|---|---|
| D1 | Keep setup, remove sample content | As A-row checks |
| D2 | Let me choose: keep courses only | Courses and their instructors kept, everything else gone |
| D3 | Remove everything | Database matches the bare snapshot (row counts and options), except the installer's own option |
| D4 | Finish interrupted (close tab mid-way), reopen | Resumes, verify all zero |
| D5 | A product deactivated before Finish | Its rows wait with a notice; rest removed; reactivate and Finish completes |

## E. Safety

| # | Case | Expect |
|---|---|---|
| E1 | Site already has 3 users and 4 posts | Unchanged after build and after every Finish mode (checksums) |
| E2 | A real user named like a sample person (`demo.alex.rivera`) | Slot skipped, real user untouched |
| E3 | Second build while a demo exists | Blocked with a message |
| E4 | Non-admin (editor) | No menu, notice, routes or CLI access |
| E5 | Two browser tabs press Build | One build only (lock) |

## F. WP-CLI parity

Every A row repeated once through `wp wbcom-demo build`, `finish`, `verify` with the same results as the wizard.

## Results: 0.1.0, 5 November 2026

Docker lab, WordPress 7.1.3, PHP 8.3 web, WP_DEBUG on, mail logged and never sent. Release zips:
BuddyNext 1.2.8, WPMediaVerse 2.6.3, Jetonomy 2.0.6, Learnomy 2.2.0, Eventonomy 1.9.1, WP Career Board 1.8.2,
WB Listora 2.1.0, WP Sell Services 1.9.2, MediaShield 1.4.1, Product Roadmap 1.8.1 (+ Pro 1.8.0),
WB Gamification 1.6.7, all Pro add-ons at the same versions; BuddyX and BuddyX Pro 5.2.0, Reign 8.0.9.

Automated with `matrix.sh` and `extra.sh` in the lab folder. Every A and B case runs: build, no warnings, no
mail (also after forcing all scheduled jobs), Finish keep with zero sample people and items left, then
Remove everything from the built snapshot with the site footprint matching the pre-build one (audit and
log tables the products keep by design excluded), and no PHP notices.

| Case | Theme | Starting point | Items | Result |
|---|---|---|---|---|
| A1 | buddyx | community | 306 | PASS |
| A4 | buddyx-pro | learning | 287 | PASS |
| A5 | reign-theme | events | 270 | PASS |
| A6 | buddyx | jobs | 241 | PASS |
| A7 | reign-theme | directory | 215 | PASS |
| A8 | buddyx-pro | services | 215 | PASS |
| A10 | twentytwentyfive | community | 305 | PASS |
| B1-buddynext | buddyx | everything | 148 | PASS |
| B1-wpmediaverse | buddyx | everything | 56 | PASS |
| B1-jetonomy | buddyx | everything | 61 | PASS |
| B1-learnomy | buddyx | everything | 63 | PASS |
| B1-eventonomy | buddyx | everything | 56 | PASS |
| B1-listora | buddyx | everything | 50 | PASS |
| B1-sellservices | buddyx | everything | 50 | PASS |
| B1-mediashield | buddyx | everything | 28 | PASS |
| B1-gamification | buddyx | everything | 63 | PASS |
| A2 | buddyx-pro | community | 306 | PASS |
| A3 | reign-theme | community | 306 | PASS |
| A9 | buddyx-pro | everything | 560 | PASS |
| B1-careerboard | buddyx | everything | 73 | PASS |
| B1-roadmap | buddyx | everything | 34 | PASS |

| Row | Result |
|---|---|
| C1 | PASS |
| C3 | PASS |
| C2 | PASS |
| D4-build | PASS |
| D4-finish | PASS |
| E5 | PASS |
| B6 | PASS |
| E1 | PASS |
| E2 | PASS |
| E3 | PASS |
| E4 | PASS |
| PHP-8.1 | PASS |
| PHP-8.4 | PASS |
| PHP-8.5 | PASS |

Browser checks (Playwright): wizard every step at 1440 and 390, light and dark; build and Finish run from the
wizard; home, profile, forums, courses, events, jobs, directory, services, media, roadmap and leaderboard
show sample content on BuddyX Pro and Reign; leaderboard spread, badges and kudos for sample people only.

Code checks: PHPCS (WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.1+) 0 errors 0 warnings;
PHPStan level 6 no errors; `wp i18n make-pot` no warnings.

Product cards filed from these runs: Product Roadmap votes and Pro analytics left after an item is deleted;
WP Career Board company HQ location link left after a company is deleted.
