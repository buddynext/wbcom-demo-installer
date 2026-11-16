# Team testing guide (1.0.0)

Shared with the team in Slack #dailymeeting on 6 November 2026. Report issues as replies in that thread.

## Setup for every case

- A new WordPress site each time (Docker, Local or InstaWP), never a live site.
- WordPress 6.5 or newer, PHP 8.1 or newer, WP_DEBUG on.
- Activate the theme and products first, then install the installer zip and open Appearance > Set up your site.

## Cases

| # | Theme | Products | What to check |
|---|---|---|---|
| 1 | BuddyX | All free | Starting point "Everything", full build, full walk |
| 2 | BuddyX Pro | Some free, some with Pro | Pro cards say "Pro add-on active", build, full walk |
| 3 | Reign | All with Pro | Full build, product pages without sidebar |
| 4 | Any | One product only (repeat per product) | Only that product's content is built |
| 5 | Any | Any | Site in use: add 2 users and 2 posts first |
| 6 | Any | Any | Reload the page in the middle of a build |
| 7 | Any | Any | Finish modes, and build again after keeping the setup |

## During the wizard

- Site check lists the theme and products correctly.
- Review shows a count per product; note them.
- Build shows live progress and ends with no red warnings. Screenshot any yellow note.

## After the build

- As admin: every page in the demo menu at desktop and phone width. Nothing empty or broken, no sidebar on product
  pages, title hidden beside the logo on BuddyX.
- wp-admin: no product setup wizard or "finish setup" notice. Expected warnings: Learnomy "no payment method" and
  Sell Services "no Terms page".
- What was built matches the Review counts.
- As a sample member: profile, connections, media, discussions, events, messages and notifications have content.

## Case 5: site in use

Site check warns about other members, posts or comments; Build stays disabled until the confirmation is ticked.

## Case 6: reload

The wizard returns to the progress screen and the build completes.

## Case 7: Finish demo

Choosing a mode shows a "Will remove" and "Will keep" list; check it before continuing.

- "Keep my setup, remove sample content": sample people and their content go; pages, menu, spaces and categories stay.
- Build again after that: kept spaces and categories are reused, no duplicates.
- "Let me choose": only the ticked items are removed.
- "Remove everything the demo added": the site matches how it was before the build (pages, menu, logo, settings).
- After Remove everything the report says nothing was left behind; deactivate and delete the installer.

## Reporting

Case number, theme, products, page URL, screenshot, expected vs actual. One reply per issue.
