# 1.1.0 batch 1 verification (Batches A, B, C1-C3)

Run 10-11 November 2026 on the lab (`~/work-artifacts/keep/demo-installer-lab`, port 8930): branch `1.1.0`, WordPress
7.1.3, PHP 8.3, release zips BuddyNext 1.2.8, WPMediaVerse 2.6.3, Jetonomy 2.0.6, Learnomy 2.2.0, Eventonomy 1.9.1,
WP Career Board 1.8.2, WB Listora 2.1.0, WP Sell Services 1.9.2, MediaShield 1.4.1, Product Roadmap 1.8.1,
WB Gamification 1.6.7, BuddyX 5.2.0. Starting snapshots: `all-ready` (all free products active) and `t110-built`
(Everything built with this code: 565 items, no warnings). `perstep.sh` runs the engine one step per PHP process, the
way the wizard does, with `AS=1` running Action Scheduler between steps.

## Lab cases

| Plan row | Card | Case | Result |
|---|---|---|---|
| A1 | 10483229166 | Owner post credited to a sample person, Remove everything | PASS: post stays, published, author is the admin |
| A2 | 10483229119 | Demo writes true, owner sets "yes"; control option untouched | PASS: "yes" kept and listed in kept_options; control restored (deleted) |
| A3 | 10483229216 | `wbcom_demo_snapshot` = "garbage", build Community | PASS: `Demo built: 373 items`, no warnings |
| A4 | 10483229354 | Fresh site; then default post and comment deleted, one real post | PASS: `[]`, then `["1 blog post"]` |
| A5 | 10483372792 | Custom finish keeping Learnomy, then Remove everything | PASS: setup still listed after the first finish; 0 course categories, 0 courses after the second |
| A6 | 10483229309 | Menu created by a build | PASS: "Demo menu" |
| B1 | 10483334566 | Request dies (exit 255) inside the menu step, one step per request | PASS: build ends `built`; error "BuddyX: a step stopped the request twice..."; lock released |
| B1 | 10483334566 | Request dies while removing one row during Remove everything | PASS: that row reported as 1 failed, the other 371 removed |
| B2 | 10483229409 | Fresh lock held by another token; stale lock | PASS: step returns busy and the foreign lock stays; stale lock taken over and released |
| B4 | 10483229259 | Attachment deletes blocked (`pre_delete_attachment`), Remove everything | PASS: 36 failed, verify exit 1, rows stay in the ledger; after unblocking, `resume` removes them, nothing left |
| B4 | 10483229259 | Learnomy enrolments (Learnomy keeps ended rows as history) | PASS: ended enrolment counts as removed; 0 enrolment rows after the people go |
| B5 | 10483372883 | Custom finish keeping Learnomy | PASS: "Everything you chose was removed. The items you kept are still tracked." |
| B6 | 10483229501 | Learnomy off, Remove everything, Learnomy on, `resume` | PASS: 60 waiting, "Waiting for: Learnomy"; resume removes 60, nothing left |
| B7 | 10483229454 | REST build with recipe=doesnotexist | PASS: HTTP 400 |
| B8 | 10483229033 | Remove everything | PASS: report names BuddyNext, WPMediaVerse and Learnomy logs; "Nothing from the demo was left behind" with ledger 0 |
| C1 | 10488092598 | Everything build | PASS: 18 of 18 reviews approved; `auto_approve` still off |
| C2 | 10488805254 | Everything build | PASS: Crumb technology, Paperplane Studio design, Roastworks retail |
| C3 | 10483334519 | Community build one step per request with Action Scheduler between steps | PASS: 38 of 38 hashtags recorded (was 0); 0 hashtag rows after Remove everything |

## Wizard states (rendered in Node from `assets/js/wizard.js`)

| View | State | Shows |
|---|---|---|
| Ready | built | Your site is ready |
| Ready | built with problems | Your demo is built, with problems (problem list open) |
| Ready | setup only | The sample content is gone, Keep them as mine |
| Ready | waiting for Learnomy | Continue removing |
| Report | nothing left | Your site is ready for real members, nothing left behind, product logs named |
| Report | kept by choice | Done: everything you chose was removed, Kept by your choice row |
| Report | waiting for Learnomy | Almost done, Continue removing |
| Any | three failed /step requests | Error with a Try again button |

## Gates

PHPCS (`phpcs.xml.dist`) clean; PHPStan level 6 clean; docs lint 0 errors 0 warnings; POT regenerated.

## Not yet verified

Browser check of the wizard at 1440 and 390 in light and dark (Ready, Report, Try again, Continue removing, Keep them
as mine, Home page step note): the Playwright browser was held by another session. Cards stay in Bugs until this
passes and the close-out comments are posted.
