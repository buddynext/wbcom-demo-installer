# Possible Bugs replication, 1.1.0 branch

Replicated 10 November 2026 on the lab (`~/work-artifacts/keep/demo-installer-lab`, port 8930): branch `1.1.0` at
627bf09, release zips BuddyNext 1.2.8, WPMediaVerse 2.6.3, Learnomy 2.2.0, WB Listora 2.1.0, WP Career Board 1.8.2,
all 11 products and 10 Pro add-ons for the memory case, BuddyX / BuddyX Pro, PHP 8.3.

| Card | Result | Evidence | Fix lives in |
|---|---|---|---|
| 10483229454 REST build accepts any recipe | Reproduced | `rest_do_request` POST /build recipe=doesnotexist: 200, state recipe doesnotexist, status building | Engine::start_build (validate against Recipes), REST arg enum |
| 10483229409 Lock released by non-owner | Confirmed in code | Engine.php:215-233: timestamp-only lock, get-then-update take-over, unconditional delete in finally | Engine::step (token lock, delete only own token) |
| 10483229354 Live check misses real content | Reproduced | Hello world + comment deleted, one real post published: live_signals() = [] | Engine::live_signals (count posts/comments other than the install defaults by id) |
| 10483229259 Learnomy::remove always true | Reproduced | pre_delete_attachment false: remove() true, attachment still exists | Learnomy::remove (return whether the object is gone), same check in other providers |
| 10483229216 Corrupted snapshot reports Success | Reproduced | wbcom_demo_snapshot=garbage: 4 provider warnings, `Success: Demo built: 21 items` | Snapshot (coerce non-array), Engine/CLI/wizard (built with errors) |
| 10483229166 Person removal trashes owner content | Reproduced | owner post authored by a flagged sample person: post trash after People::remove | People::remove (reassign to the admin running Finish) |
| 10483229119 Snapshot loose equality | Reproduced | demo wrote true, owner set a string: restored to original, kept [] | Snapshot::restore (strict compare of normalised values) |
| 10483372883 Finish says nothing left while content remains | Reproduced | custom finish keeping Learnomy: "Nothing from the demo was left behind", 56 ledger rows, status built | Finisher::complete + verify + wizard report heading |
| 10483372792 Partial finish hands setup over too early | Reproduced | same run: 4 course categories dropped from ledger; later Remove everything leaves Design, Development, Community Building, Wellbeing | Finisher::complete (hand over setup only when no sample rows remain) |
| 10483229309 Rebuild after Keep duplicates menu | Reproduced | build, finish keep, build: "Community menu" and "Community menu 2"; reused categories not recorded | Theme menu (reuse the kept menu), Learnomy categories (record reuse as kept) |
| 10483229501 Resume says Nothing to resume | Reproduced | Learnomy off, finish all (leftovers), Learnomy on: `Nothing to resume`, status built | Cli resume + wizard (offer to continue a finish with waiting rows) |
| 10483334519 Hashtags from background jobs not recorded | Reproduced | build one step per request with Action Scheduler between steps: 38 tags, 0 in ledger | BuddyNext::hashtag_slugs (decide newness before the first post step) |
| 10483334566 Wizard stops after one failed request | Confirmed in code | wizard.js:285-309: any rejected /step ends the loop, no retry or button. Browser repro pending (browser in use by another session) | wizard.js loop (retry with backoff, Try again button) |
| 10483229033 Product rows left after Remove everything | Reproduced | after remove: mvs_transactions 40 (user 0, upload), lrn_audit_log 26, bn_rate_limits 20; report says nothing left | Product side (delete-user cleanup); installer: report known side-effect tables |
| 10488092598 Listora reviews pending | Reproduced | 18 of 18 pending; Listora reviews.auto_approve is off by default | Listora provider: approve own sample reviews via the moderator REST status, keep the owner setting |
| 10487106723 WP-CLI out of memory at 128M | Reproduced | 22 plugins: fatal at 128M in jetonomy-pro boot, fine at 192M; boot peak 165 MB | Installer: site check warns under 256M, docs; boot memory is a product-side note |
| 10488498295 Pro add-ons add nothing | Confirmed in code | providers check Pro only for the label; no Pro pages or content | Feature for 1.1.0: Pro steps per provider |
| 10483387455 Manage demo data screen | Feature request | not a defect | 1.1.0 UX scope |
| 10488805254 (Bugs) Career Board industry | Reproduced | Crumb, Paperplane Studio, Roastworks: empty _wcb_industry | CareerBoard provider: registered slugs |
