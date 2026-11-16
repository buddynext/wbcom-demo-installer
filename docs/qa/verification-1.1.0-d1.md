# 1.1.0 D1 verification: install Wbcom products from the wizard

Run 11 November 2026 on the lab (port 8930), WordPress 7.1.3, PHP 8.3, starting from `bare` (WordPress only, the
installer active, no Wbcom product or theme on disk) unless noted. Card 10483411874.

| Case | Result |
|---|---|
| Free key and item id in the catalog equal the ones each plugin ships in its main file (10 store products) | PASS: all 10 match; Product Roadmap and BuddyX come from WordPress.org |
| Store flow from the site: `activate_license` then `get_version` with the free key | PASS: licence valid, package on wbcomdesigns.com, version is the released one |
| `wp wbcom-demo install --all` on a bare site | PASS: 11 products installed and active in about a minute: BuddyNext 1.2.8, WPMediaVerse 2.6.3, Jetonomy 2.0.6, Learnomy 2.2.0, Eventonomy 1.9.1, WP Career Board 1.8.2, WB Listora 2.1.0, WP Sell Services 1.9.2, MediaShield 1.4.1, WB Gamification 1.6.7, Product Roadmap 1.8.1. GitHub already has Career Board 1.8.3 and Listora 2.1.1; the store serves the customer release, as intended |
| `wp wbcom-demo install buddyx` | PASS: BuddyX 5.2.0 from WordPress.org, active |
| `wp wbcom-demo build --recipe=jobs --install-missing --yes` on a bare site | PASS: installs BuddyNext, WPMediaVerse, Jetonomy, WP Career Board, WB Gamification, then builds 410 items in a fresh process |
| REST `/install` unknown id | PASS: 404 |
| REST `/install` as an editor | PASS: 403 |
| REST `/install` buddynext, mediaverse, jetonomy as the admin, then `/site` | PASS: 200 each; catalog shows the trio active, the rest not installed, Pro states; starting points community and everything appear |
| `DISALLOW_FILE_MODS` true | PASS: 403 with the message naming it; the product's checkbox is disabled (`can` false) |
| Every starting point uses the community set (owner, 8 November) | PASS: dry runs of learning, events, jobs, directory and services all include BuddyNext, WPMediaVerse and Jetonomy |
| Site check panel rendered in Node: bare site; trio active; a failed install; file changes not allowed | PASS: trio ticked by default and the button reads "Install and activate 3 products"; nothing ticked when active; the error shows on the product's row; disabled boxes and button when not allowed; BuddyX offered when the theme is not a Wbcom theme |

Gates: PHPCS clean, PHPStan level 6 clean, docs lint 0/0, POT regenerated.

Not yet verified: browser check of the panel at 1440 and 390 in light and dark (Playwright browser held by another
session).
