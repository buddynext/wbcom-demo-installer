=== Wbcom Demo Installer ===
Contributors: wbcomdesigns, vapvarun
Tags: demo content, sample data, community, setup wizard, buddynext
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See your Wbcom products working with believable sample content in a few minutes, then remove the samples and keep your setup.

== Description ==

Wbcom Demo Installer sets up a believable demo of every Wbcom product you have active, from one screen under Appearance > Set up your site. It is made for a fresh site you use to show or try the products: a new install, a staging copy or a sandbox.

* One cast of 20 sample people is shared by every product, so the same person posts in the feed, teaches a course, runs an event and sells a service.
* Pick a starting point (Community, Learning community, Events community, Job board community, Directory, Services marketplace, or Everything I have), switch products on or off, choose whether to set your home page, menu and logo, and review exactly what will be created.
* Dates are spread over the last two months so feeds, forums and leaderboards read like a real community.
* Nothing is emailed. Sample people never receive mail, now or later.
* Each product's own setup is finished and its pages are shown without sidebars, so there are no setup wizards or reminders waiting after the build.
* Works with any mix of free products and Pro add-ons.
* Your own users and content are never changed. On a site that already has other members, blog posts or comments, the build waits for your explicit confirmation.

When you are ready, Finish demo removes the sample people and everything they made and keeps your settings, pages, menus, spaces, categories and plans. You can also choose per product what stays, or remove everything and put back your previous home page, menu and logo. A short report confirms nothing was left behind.

Supported products: BuddyNext, WPMediaVerse, Jetonomy, Learnomy, Eventonomy, WP Career Board, WB Listora, WP Sell Services, MediaShield, Product Roadmap and WB Gamification (with their Pro add-ons active), on BuddyX, BuddyX Pro, Reign or any theme.

= For developers =

Everything the wizard does is also available from WP-CLI:

* `wp wbcom-demo status`
* `wp wbcom-demo build --recipe=community [--include=buddynext,jetonomy] [--keep-look] [--dry-run] [--yes]`
* `wp wbcom-demo finish [--mode=keep|all] [--keep=learnomy:course] [--dry-run]`
* `wp wbcom-demo verify`

Products and add-ons can register or replace a provider on the `wbcom_demo_register` action, and replace sample content with the `wbcom_demo_content` filter.

== Frequently Asked Questions ==

= Can I run it on my live site? =

It is made for a fresh site. On a site people already use, the sample people would appear next to your real members until you finish the demo. If the site already has other members, blog posts or comments, the site check warns you and the build waits until you confirm.

= Will it change my existing users or posts? =

No. It only creates its own sample people and content, and Finish demo removes only what it created.

= Does it send email? =

No. All mail is held while it builds or finishes, and mail to sample people is never sent.

= What happens if I deactivate a product before finishing the demo? =

That product's sample content waits, and the sample people who made it stay, until you activate the product again and run Finish demo once more.

= Can I delete the plugin after finishing the demo? =

Yes. Nothing you kept depends on it. If sample content is still on the site, uninstalling keeps the plugin's record of it so reinstalling can still finish the demo.

== Changelog ==

= 1.0.0 - November 2026 =

First release.

* New      - Set up your site wizard under Appearance with site check, starting points, product choice, home page and menu, review and live progress.
* New      - Finish demo with keep setup, choose per product, and remove everything modes, a dry run and a verify report.
* New      - Sample content for BuddyNext, WPMediaVerse, Jetonomy, Learnomy, Eventonomy, WP Career Board, WB Listora, WP Sell Services, MediaShield, Product Roadmap and WB Gamification, free or with Pro.
* New      - Each product's setup is finished and its best display settings applied, and product pages are shown without sidebars on BuddyX, BuddyX Pro and Reign.
* New      - A site that already has members, blog posts or comments needs your confirmation before a build.
* New      - Building again after keeping your setup reuses the kept spaces and categories.
* Dev      - WP-CLI commands status, build, finish, resume and verify, the wbcom_demo_register action and the wbcom_demo_content and wbcom_demo_page_layout filters.
