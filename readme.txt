=== Wbcom Demo Installer ===
Contributors: wbcomdesigns, vapvarun
Tags: demo content, sample data, community, setup wizard, buddynext
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.0
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

When you are ready, Finish demo removes the sample people and everything they made and keeps your settings, pages, menus, spaces, categories and plans. You can also choose per product what stays, or remove everything and put back your previous home page, menu and logo. A short report shows what was removed and what is still on the site.

Supported products: BuddyNext, WPMediaVerse, Jetonomy, Learnomy, Eventonomy, WP Career Board, WB Listora, WP Sell Services, MediaShield, Product Roadmap, WB Gamification and Wbcom Essential (with their Pro add-ons active); BuddyPress, BuddyBoss Platform and PeepSo; WooCommerce, Dokan, WCFM Marketplace, WC Vendors, LearnDash, Tutor LMS, LifterLMS, Sensei LMS, GeoDirectory and WP Job Manager, with the Reign add-on for each. Themes: Reign, BuddyX, BuddyX Pro or any theme. Paid plugins (Reign add-ons, LearnDash, BuddyBoss Platform, PeepSo, Pro add-ons) are bought, installed and licensed by you; the installer sets up the free ones.

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

= 1.2.0 - November 2026 =

Demos that look finished, link straight to one demo, and show how each is set up.

* New      - Watch how on each demo card plays the film of that demo being set up on your theme, and the ready screen links the film on taking it live.
* New      - A link with &demo=<id> opens Set up your site on that one demo, ready to set up in one click, so a theme's Build this site button lands on the demo the owner chose.
* New      - On a site without BuddyX Pro or Reign, the BuddyX demos install the free BuddyX theme from WordPress.org and switch it on last.
* New      - Try the demo installs User Switching, so Explore as signs you in as the sample person.
* New      - Every Learnomy course has an end-of-course quiz, already taken and graded by sample students.
* Improve  - Home pages open with a full-width hero, About and Join are styled, and the demo has a footer with its own menu.
* Improve  - Community demos open on the community: hero, numbers and members lead the home page.
* Improve  - The demo menu groups pages under Community, Explore and About, with the demo's own product (Courses on a course site) top-level.
* Fix      - A course, events, jobs, directory, services or store demo no longer gets the BuddyNext logo; the header keeps the site name.
* Improve  - Events run over the next eight weeks with one recent past event, and they are approved so RSVPs land.
* Improve  - Services demo orders are paid with real dates, and two sellers reach the Rising level.
* Improve  - Course lesson URLs no longer end in the course id.
* Improve  - Marketplace demos (Dokan, WCFM, WC Vendors) get a community about the shops: store groups, a feed of restocks, styling and care tips, and customer photos.
* Improve  - Product photos are square so product cards fill without letterboxing, and the online class sits in a Digital downloads category.
* Improve  - WC Vendors shops get a banner on the demo home page (and in the shop header with WC Vendors Pro), and sample stores use real city-centre addresses that maps can find.
* Improve  - Course, directory and services reviews are approved, dated and removed with the demo.
* Improve  - WPMediaVerse albums hold their owner's photos, and photos carry real tags and categories.
* Improve  - The site check says Activate for installed plugins and no longer shows the Pro line on free plugins.
* Improve  - The site check has one install button, and Continue stays secondary until a product is active.
* Improve  - A demo that needs a paid add-on or plugin names each one with where to get it, an Upload plugin button and Check again, in the wizard and in WP-CLI.
* Improve  - wp wbcom-demo build --demo=<id> --install-missing installs the demo's free plugins, then builds.
* Improve  - A demo card being set up stays in sight and counts its plugins as they install.
* Fix      - Building again after Start my real site no longer creates a second home page, menu, listing or event.
* Fix      - A demo login already taken on the site no longer shifts sample content to the wrong people.
* Fix      - Remove everything restores a site name that contains &, ' or ".
* Fix      - A stopped Remove everything that is resumed finishes as Remove everything and puts every setting back.
* Fix      - Start my real site keeps the pictures on the pages and categories it keeps.
* Fix      - The kept home page no longer shows the sample's numbers after Start my real site.
* Fix      - Start my real site removes the Try the demo page and its menu links, which only switched into sample people.
* Improve  - Start my real site, Remove everything and Try a different demo go straight to the confirm step, with the acknowledgement above the list of what is removed.
* Fix      - Jobs demo salaries use Career Board's own pay periods.
* Fix      - Every item of a large content pack is built, not only the first ones.
* Fix      - On phones the ready screen's footer no longer covers the finish buttons.
* Fix      - Try the demo tour text and badges pass WCAG AA contrast on every theme.
* Fix      - Try the demo buttons and the chosen tour tab use the theme's own button colours, so on Reign they are white on blue like every other button.
* Fix      - A WP-CLI build no longer carries one step's sample date into the next step.
* Fix      - Plugins installed from WP-CLI finish their first-run setup before the build, and Reign add-ons switch on after the plugins they need.
* Dev      - wbcom_demo_status() and wbcom_demo_list() (with each demo's needs: install, upload or ask) let a theme show the demo's progress and its own site tiles, and the wbcom_demo_return filter brings the owner back to the theme's screen.
* Dev      - New guide for Product Roadmap on Try the demo, a local CI script and a table lock.

= 1.1.1 - November 2026 =

Pick a demo by what your site is for, set it up in one click, and remove it again from the same screen.

* New      - Demo gallery: the Starting point step asks what your site is for and shows one demo each for community, learning, events, jobs, directory, services and shop.
* New      - A Reign add-on you uploaded puts its demo first, marked Your add-on, and a plugin you already run takes its purpose's card.
* New      - Set up and use this demo installs, updates or switches on the free plugins a demo needs and lists the paid ones to upload with a Get it link.
* New      - See every combination lists every theme demo for Reign, BuddyX and BuddyX Pro, every Reign add-on demo and the starting points.
* New      - The ready screen names your demo and offers Start my real site, Remove everything and Try a different demo, each confirmed before anything is removed.
* New      - Live preview on every demo card opens the same demo on our sandbox.
* Improve  - Each LMS and marketplace demo has its own home page hero.
* Improve  - Demo card images load from wbcomdesigns.com, which keeps the plugin small.
* Improve  - BuddyPress demos hide the toolbar from logged-out visitors.
* Fix      - Removing a demo now also removes the BuddyPress group photos.
* Fix      - BuddyBoss Platform sites are no longer treated as BuddyPress sites, so BuddyBoss demos are offered.
* Fix      - Demos built on BuddyNext now include WPMediaVerse, Jetonomy and WB Gamification content when BuddyNext is already active.
* Fix      - A site running an older BuddyPress, BuddyBoss or BuddyNext gets demos that update it, never a second community plugin.
* Dev      - WP-CLI: wp wbcom-demo demos lists the demos, wp wbcom-demo build --demo=<id> builds one, and wp wbcom-demo install accepts every product the demos use.

= 1.1.0 - November 2026 =

Set up a demo with Reign and its add-ons, describe your site to AI, and give visitors a guided tour of it.

* New      - Sample content for BuddyPress and BuddyBoss Platform: member photos, groups, friendships and a mixed, dated feed.
* New      - Sample content for PeepSo: a dated stream of member posts and link shares with comments and reactions.
* New      - Sample content for WooCommerce: a store with product photos, sale prices, stock and reviews.
* New      - Sample content for Dokan: sample vendors become stores that sell the sample products.
* New      - Sample content for LearnDash: courses with lessons, topics, photos and students part way through.
* New      - Sample content for WP Job Manager: job listings from sample companies with logos and salaries.
* New      - Sample content for GeoDirectory: places with photos, map pins, opening hours and reviews.
* New      - Sample content for TutorLMS and LifterLMS: courses with lessons, instructors and students making progress.
* New      - Sample content for Sensei LMS: courses with modules, lessons, teachers, students making progress and Reign course ratings.
* New      - Sample content for WCFM Marketplace and WC Vendors: sample vendors become stores that sell the sample products.
* New      - A designed home page for every combination: a hero, the site's numbers, each product's own sections (places and map, courses, jobs, stores, events, services, discussions) and the members with their activity, then a call to action.
* New      - The home page uses Wbcom Essential's hero, stats, call to action, members, activity and groups blocks, so every piece is edited like any block.
* New      - Reign add-ons are offered BuddyPress for their members, profiles, groups and activity when the site has no community plugin.
* New      - Try the demo page with role cards, a guided tour and one-click switching between sample accounts.
* New      - Describe your site in one sentence and get three ready ideas through the AI connector set up in Settings > Connectors.
* New      - About, Join, FAQ and Blog pages built with Wbcom Essential.
* New      - Install and activate the free Wbcom products from the site check.
* New      - The site check installs the free plugin each active Reign add-on is built for, and every starting point can install the product it needs.
* New      - Demo data entry under Tools, and your own site name and tagline on the Review step.
* Improve  - After Remove everything, Try a different demo goes straight back to the site check to build another starting point.
* Improve  - The demo is built on the latest releases: an older Wbcom product, Reign or Reign add-on is ticked on the site check and updated in place, settings kept (Reign through its licence, as Dashboard > Updates does).
* Improve  - Free Wbcom products with a public repository install from their latest GitHub release, with the store as the fallback.
* Improve  - Wbcom Essential is ticked on the site check, as its blocks build the home page and the regular pages.
* Improve  - The site check says when a Reign add-on still needs its plugin, even when other products are ready.
* Improve  - Sites without BuddyNext open on their community, shop or directory home page and show the site name in the header.
* Improve  - A site that already runs BuddyPress, BuddyBoss or PeepSo is no longer offered the BuddyNext set by default.
* Improve  - Keep my setup leaves the kept pages, menu and categories removable until you choose Keep them as mine.
* Improve  - The site check warns when PHP memory is too low for a build.
* Improve  - A failed build step can be retried from the wizard.
* Improve  - Back and Continue stay in view on long wizard steps.
* Fix      - A product too old for the installer is no longer listed on Review and then left out of the build without a word.
* Fix      - Every sample forum discussion has replies that answer it, and tags that fit it.
* Fix      - BuddyPress replies show real mentions instead of placeholder text.
* Fix      - Review counts spaces and sub-spaces together, as the spaces directory shows them.
* Fix      - Remove everything leaves a WooCommerce store exactly as it was: no Cart page revision, no store-is-live note, and a fresh site stays marked fresh.
* Fix      - Learnomy in-app notifications from the demo go to sample people only, never the site's admin.
* Fix      - Remove everything no longer leaves sample groups behind on a fresh BuddyPress site.
* Fix      - A plugin the wizard installs that finishes its own setup only on an admin page, such as GeoDirectory, now builds its demo without errors.
* Fix      - Remove everything puts back settings that other plugins also change, such as GeoDirectory's and the theme's menu locations, instead of reporting them as kept.
* Fix      - The Finish screen names every item in plain words, and member photos are removed with their sample people.
* Fix      - Removing sample product categories also clears them from the WooCommerce analytics lookup.
* Fix      - A Try the demo link opened after switching accounts returns to the tour instead of acting as the wrong person.
* Security - Learnomy sample settings never switch a site in live payment mode to the test gateway.
* Dev      - WP-CLI commands ideas, install and adopt, plus build --add, --describe, --idea, --name and --tagline.
* Dev      - REST routes POST /ideas and POST /add, and the provider methods full_width_archives(), settings(), home(), offers_home() and community().
* Compat   - Ships with Wbcom Essential 4.7.2 and Reign 8.1.0. Install both updates together.

= 1.0.0 - November 2026 =

First release.

* New      - Set up your site wizard under Appearance with site check, starting points, product choice, home page and menu, review and live progress.
* New      - Finish demo with keep setup, choose per product, and remove everything modes, a dry run and a verify report.
* New      - Sample content for BuddyNext, WPMediaVerse, Jetonomy, Learnomy, Eventonomy, WP Career Board, WB Listora, WP Sell Services, MediaShield, Product Roadmap and WB Gamification, free or with Pro.
* New      - Each product's setup is finished and its best display settings applied, and product pages are shown without sidebars on BuddyX, BuddyX Pro and Reign.
* New      - A site that already has members, blog posts or comments needs your confirmation before a build.
* New      - Building again after keeping your setup reuses the kept spaces and categories.
* Dev      - WP-CLI commands status, build, finish, resume and verify, the wbcom_demo_register action and the wbcom_demo_content and wbcom_demo_page_layout filters.
