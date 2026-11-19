# Theme onboarding and the demo catalog

Status: plan, 19 Nov 2026. Owner direction: "dedicated BuddyX and BuddyX Pro onboarding after Reign", and "more demos and variations in future, so plan and organise first".

## Goal

One installer drives the first-run experience for every Wbcom theme: Reign now, then BuddyX and BuddyX Pro, then any later theme. To add a demo, a variation or a film, you add one entry to the installer. No theme release is needed.

## Where we are (1.2.0)

- **Demo data:** `content/demos.php` holds every demo. Each entry has an id, a group, a theme family, a purpose, `requires`, `optional`, a preview and a `film` per theme family. The `wbcom_demo_demos` filter can change the list.
- **Public surface for themes:**
  - `wbcom_demo_status()` returns the status, `demo_id`, `demo_label`, `built_at`, `finished_at` and `samples_left`.
  - The `wbcom_demo_return` filter sets where the done screens send the owner.
  - `&demo=<id>` deep links.
  - The actions `wbcom_demo_built` and `wbcom_demo_finished`.
- **The gap:** themes hard-code what the installer already knows. Reign 8.1.1 hard-codes:
  - the seven site tiles,
  - their film ids and lengths,
  - the take-it-live film (`inc/Admin/class-reign-setup-card.php`).

  So a new demo, a re-recorded film or a changed length means a Reign release. BuddyX and BuddyX Pro would copy the same hard-coding.

## Plan

### A. A read API for theme tiles

`wbcom_demo_list()` returns the demos for the active theme in gallery order. For each demo it gives:
- `id`, `name`, `desc` and `purpose`
- `featured`
- `state` (ready, fixable, blocked or conflict)
- `image` and `preview`
- `film` (`id` and `length`, or empty)
- `url` (the `&demo=` deep link)

It also returns the `go_live` film. The data comes from `Demos::overview()`, which the gallery already uses, so the theme's tiles and the installer's gallery always match.

A theme renders its tiles from this list. It keeps its own list only as a fallback for installer 1.1.x, and drops that fallback once 1.2.0 is out.

The take-it-live film moves out of `Page.php` into the demo data, so there is one source for every film.

### B. One theme contract, documented once

The developer guide gets a "Theme onboarding" page. It lists what every theme's setup card does:

| Card shows | Comes from |
|---|---|
| Card state: build, building, take it live, live, hidden | `wbcom_demo_status()` |
| Site tiles | `wbcom_demo_list()` |
| Build button | each item's `url` |
| Way back to the theme's screen | `wbcom_demo_return` |
| Films | the list |

Reign is the reference build. BuddyX and BuddyX Pro copy it.

Theme-specific code inside the installer stays limited to `Providers\Theme`: layout, menu locations, footer areas, page meta. All of it is behind the existing filters.

### C. Room for more demos and variations

- **A new demo** is one entry in `content/demos.php`, plus a card image in `media/demos/<id>.webp`, plus an optional film per theme family.
- **A variation** is the same products with different sample content or a different look, for example a fitness community and an alumni community. Each variation is an entry with:
  - `variant_of: <parent id>`
  - `pack: <content pack id>`, read through the existing `wbcom_demo_content` filter
  - optionally `style`, a theme style preset

  The gallery keeps one top card per purpose and shows that card's variations as a choice on it, so the gallery does not grow into a wall of near-identical cards. `&demo=` accepts variation ids.
- **A catalog check:** `wp wbcom-demo demos --validate` checks every entry, and local CI runs it so a broken entry fails before release. The checks:
  - ids are unique,
  - every `requires` id is in the catalog,
  - every `variant_of` points to a real demo,
  - films are well formed,
  - the card image exists,
  - each purpose has at most one `ours` demo.

### D. Films per theme

Today:
- Reign has seven films.
- BuddyX and BuddyX Pro have only their all-in-one film.

The data already holds a film per theme family. The BuddyX and BuddyX Pro purpose films go on the video slate, each recorded on its own theme. Until then, those cards show no film rather than a Reign film, because the look would not match.

### E. First-run UX cleanups

- **Site check on a fresh site shows three next-step buttons:** the yellow-box Install, the bottom "Install and activate N products", and Continue.
  - Proposal: remove the yellow-box button, keep its text pointing to the list, and keep one install action.

## Staging

| Release | Contents |
|---|---|
| 1.2.0 (in QA) | Done: status helper, return filter, `&demo=`, films, A (`wbcom_demo_list()`), B (developer guide "Theme onboarding"), E (one install button). Reign 8.1.1 uses status, return and `&demo=`; its tiles and films still come from its own list and should move to `wbcom_demo_list()`. |
| 1.3.0 | C (variations and catalog check), BuddyX and BuddyX Pro setup cards copied from Reign's, reading `wbcom_demo_list()`. |
| Later | D (BuddyX and BuddyX Pro purpose films), more demos as entries. |
