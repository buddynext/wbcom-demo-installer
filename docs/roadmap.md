# Roadmap and readiness audit

Where the installer stands after Wave 1, and what Wave 2 (third-party plugins) and Wave 3 (an AI site builder)
need from it. Written 6 November 2026, updated for the 1.0.0 release and the 1.1.0 plan (10 November 2026).

## Wave 1: Wbcom products (release candidate)

Built: eleven product providers (BuddyNext, WPMediaVerse, Jetonomy, Learnomy, Eventonomy, WP Career Board, WB Listora,
WP Sell Services, MediaShield, Product Roadmap, WB Gamification), a shared cast of 20 people, theme adapters for
BuddyX, BuddyX Pro and Reign plus a generic one, seven starting points, three Finish modes, wizard, REST and WP-CLI.

After a build each product's setup is finished the way its own wizard finishes it, presentation settings are applied
(no-sidebar product pages, Learnomy fills the theme container, title hidden beside the logo on BuddyX) and every
change is restored by Remove everything. Test results: `docs/testing.md`; the team's manual test plan:
`docs/team-testing.md`. Lab scripts (matrix, extra cases, rebuild after keep) live outside the repo in
`~/work-artifacts/keep/demo-installer-lab/`.

Planning artifact: `docs/plans/site-blueprints.html` (open in a browser) holds the blueprint catalogue for what we can
offer after Waves 2 and 3, including prebuilt templates such as a multivendor community with Dokan, and a preview of
the admin wizard organised by wave. Published copy: https://claude.ai/artifact/CEV6Qo1UoSbJDhwarrFnUa

Release 1.0.0 checklist:

| Item | State |
|---|---|
| Review counts | Done. Every count equals what the build creates on the site (verified type by type against a full build). BuddyNext feed posts show its own count and say other products add theirs. |
| Real site consent | Done. Other members, blog posts or comments are named on the site check; Build waits for a ticked confirmation; REST needs `consent`, WP-CLI needs `--yes`. |
| Product setup wizards | Done. Activation redirects are cleared when the owner opens this wizard (Learnomy redirects from `plugins_loaded`, so this runs at priority 0) and each product's setup is completed on build. |
| Member view | Done. Profile, connections, media, discussions, events, messages and notifications walked as a sample person; in-app notifications between sample people are now created during the build, never to real users. |
| Mid-build reload | Done. Reloading the wizard during a build returns to the progress screen and the build completes. |
| Uninstall | Done. With demo content present the records stay so Finish still works after reinstalling; after Finish, uninstall leaves no table or option. |
| RTL | Done. The wizard mirrors; arrows flip in the RTL stylesheet. |
| Roadmap heading | Done. Product name set from the site title when the owner has not set one. |
| Customer docs | Done. FAQ and Troubleshooting added; docs lint 0 errors, 0 warnings. |
| Large existing site | Out of scope (owner, 6 November 2026): the installer is for a fresh demonstration site. |
| B2, B3 | After 1.0.0: automate BuddyNext without WPMediaVerse and Jetonomy discussions in the feed. |
| Multisite | After 1.0.0: decide per-site only or blocked on network admin. |
| Owner warnings left on purpose | Learnomy "no payment method" and Sell Services "no Terms page": real owner tasks. |

Notes for the product teams (worked around in this plugin; each team decides):

- Learnomy redirects to its setup wizard from its `plugins_loaded` callback, so the first admin page of any kind after
  activation is taken over; Learnomy, Eventonomy, WP Sell Services, Product Roadmap and WB Gamification redirect even
  when their setup is already complete.
- Jetonomy awards reputation for a vote only in its votes REST route, not in `Vote::cast()`, so other writers
  (importers, this installer) must award it themselves.
- Jetonomy's `jetonomy_notification_should_send` filter receives no recipient, so a caller cannot allow some
  notifications and hold others (BuddyNext's passes the notification data).

## 1.1.0 plan: customer usability first

Scope set by the owner on 10 November 2026: fix every confirmed bug, then make the installer able to build any demo a
customer needs with all its settings, from the wizard, WP-CLI or a described site (AI through WordPress core only).
Replication evidence for every bug card: `docs/qa/replication-1.1.0.md`. Order below is by what a customer feels,
not by where the code lives. Each group ships as one batch with its lab and browser checks.

### 1. Never hurt the owner's site (P1)

A demo tool that touches real content loses the customer's trust for good, so these go first.

| Card | What the customer sees | Fix |
|---|---|---|
| Person removal trashes owner content | A post the owner wrote, credited to a sample person, is trashed on Finish | `People::remove` reassigns to the admin running Finish |
| Snapshot restore overwrites owner change | A setting the owner changed after the build is put back on Remove everything | `Snapshot::restore` compares normalised values strictly |
| Live check misses real content | A site with real posts is treated as fresh once the default post is deleted | `live_signals()` counts posts and comments other than the install defaults by id |
| Partial finish hands setup over too early | Remove everything later leaves course categories behind | Hand setup over only when no sample rows remain |
| Rebuild after Keep duplicates menu | "Community menu 2" appears; kept categories leave the ledger | Reuse the kept menu; record reused terms as kept |

### 2. Tell the truth on screen (P1)

| Card | What the customer sees | Fix |
|---|---|---|
| Finish says nothing left behind | "Nothing from the demo was left behind" while 56 items remain | Report built from the ledger after the run: removed, kept, waiting |
| Build freezes after one failed request | The build screen stops with a server error and no way on | Retry with backoff, then a Try again button that resumes the queue |
| Corrupted snapshot still says Success | Warnings scroll by, then "Demo built" | Coerce a bad snapshot; finish as "Built with problems" listing each |
| Learnomy remove reports success on failure | Finish counts an item as removed when it is still there | Every provider returns whether the object is gone |
| Product rows left after Remove everything | Product tables keep rows the report never mentions | Report known side-effect tables by name; removal is each product team's call |
| Resume says Nothing to resume | A Finish waiting for a product cannot be continued | `resume` and the wizard continue a finish with waiting rows |

### 3. The demo looks finished (P1)

| Card | What the customer sees | Fix |
|---|---|---|
| Directory reviews pending | Listings say "No reviews yet" | Approve our own sample reviews through Listora's moderator route; the owner's setting stays |
| Career Board industry empty | Companies show no industry | Use Career Board's registered slugs |
| Hashtags not recorded | Tags made by BuddyNext background jobs stay after Remove everything | Decide which tags are new before the first feed step |
| Pro add-ons add nothing | Owners who paid for Pro see a Free-only demo | Pro steps per provider: the pages and sample items each Pro add-on brings, behind its own `available()` |

### 4. Easier to start and to clean up (UX)

| Item | What it gives the customer |
|---|---|
| Install missing Wbcom plugins from the wizard (card in Ready for Development) | Pick a starting point, see which products are missing, install and activate them with the owner's licence in one place, then build |
| Manage demo data screen | After Finish, a screen that lists what the demo left by product with counts, and removes all or chosen types; same Finisher, same ledger |
| Site check before Build | Memory under 256 MB, PHP and product versions, other members or content, all shown with what to do; WP-CLI at 128 MB is named as the cause, not a crash |
| Customise before Build | Site name, tagline, brand colour and how much content (small, standard, full) on the Review step; written through `Snapshot` so Remove everything restores them |
| Clear progress | Per product progress with counts, time left, and which product a warning came from |

### 5. WP-CLI matches the wizard

`wp wbcom-demo build` gets the same choices as the wizard: `--name --tagline --colour --size=small|standard|full`,
`--install-missing`, `--pro`, `--format=json` on `status`, `verify` and `finish`, a non-zero exit when a build or
finish ends with problems, and `wp wbcom-demo data list|remove` for the Manage demo data screen. Agencies script
client demos with it, so every option the wizard has must exist here.

### 6. AI site builder through WordPress core

Owner decision, 10 November 2026: AI uses only the connection WordPress core provides (`wp_supports_ai()`,
`wp_ai_client_prompt()`); the installer ships no AI service, key setting or adapters of its own. When core AI is not
available the AI option is hidden and every other path works as now.

1. The owner describes the site in one box. One seam (`src/Ai/Brief.php`) asks core for a **site brief** as JSON
   against a schema: identity, products and starting point, size, tone, language, niche words.
2. The brief becomes the normal **plan**; the Review step shows it with exact counts and the owner says yes. Nothing
   is written before that.
3. Optional **content packs**: core AI rewrites names, titles and text per provider in the shape of
   `content/<id>.php`, validated against that shape before step one; anything that fails validation falls back to
   our curated content for that provider, never a half pack.
4. The build runs through the same engine, ledger and Finish, so an AI demo is removable exactly like any other.

Code needs, carried over from the Wave 3 audit below: brief as build input, a schema per content file, pack
validation, a tagged `media/` manifest, site identity through `Snapshot`, chunking by item count in every provider.

### 7. Hardening (P3)

| Card | Fix |
|---|---|
| REST build accepts any recipe | Validate against `Recipes` in `start_build()` and as a REST enum |
| Lock released by non-owner | Token lock; delete only our own token |

### Product-side notes (for each product team, no change here)

- Jetonomy Pro needs about 165 MB just to boot with all products active.
- WPMediaVerse (`mvs_transactions`), Learnomy (`lrn_audit_log`) and BuddyNext (`bn_rate_limits`) keep rows for
  deleted users.

### Done means

Lab matrix plus a case for every card above, each one failing on 1.0.0; wizard at 1440 and 390 in light and dark;
WP-CLI options checked against the wizard; AI path checked with a core provider configured and with none.

## Readiness audit

| Area | State | Ready for |
|---|---|---|
| Provider contract (`Core\Provider`) | One base class; registry with `wbcom_demo_register`; a provider with a built-in id replaces it. | Wave 2 |
| Content | Every provider reads `content/<id>.php` through one loader and the `wbcom_demo_content` filter (6 November: moved the last in-class content into files). | Wave 2, Wave 3 content packs |
| Settings | `settings()` per provider, applied through `Snapshot` (options, array options merged, theme mods, page meta, one-time transients). | Wave 2 |
| Theme adapters | BuddyX, BuddyX Pro, Reign, generic; `wbcom_demo_page_layout` and `wbcom_demo_menu_locations` filters for other themes. Logo/title handling is still a `match` in `Theme`. | Wave 2 (Reign); a theme-adapter class per theme when a fourth theme family arrives |
| Ledger and Finish | Product-agnostic: `sample`/`setup` kinds, newest-first removal through each provider, handover, late types, verify. | Wave 2, Wave 3 |
| Engine | Queue of small steps, lock, resume, errors kept per provider. Step size is fixed per provider (chunking only where content is long). | Wave 2; Wave 3 needs chunking everywhere |
| Entry points | Wizard, REST (`wbcom-demo/v1`: site, build, step, finish/preview, finish), WP-CLI. Build input is recipe + products + look. | Wave 2; Wave 3 needs a brief input |
| Quality gates | PHPCS (WordPress-Extra, PHPCompatibility 8.1+), PHPStan 6, make-pot clean; lab matrix of 30+ cases. | All |

## Wave 2: third-party plugins

A third-party plugin is one provider plus one content file, nothing else. Per plugin:

1. Read its public API (services, REST, documented functions) and its own demo or onboarding code for what a
   finished setup looks like. Seeder content is cherry-picked into `content/<id>.php`, never called.
2. Write the provider (`docs/website/developer-guide/providers.md`): `available()` on its class or constant,
   `min_version()` at the tested release, `plan()`, chunked `steps()`, `remove()` through its own delete, `settings()`
   for its setup flag and best display options, `menu_items()` for its main page.
3. Add it to a starting point (`Recipes`, or a new one such as "Shop" or "LMS on Reign").
4. Lab: product alone, inside its starting point, with and without our products, on Reign and BuddyX; build with no
   warnings, Finish leaves nothing, Remove everything matches the site before the build; browser check of its pages.
5. A plugin bug found on the way is reported to its vendor, worked around in the provider, and noted in a comment.

Third-party providers ship inside this plugin (so the wizard can offer them) and stay inactive unless the plugin is
active. The list of Wave 2 plugins is the owner's call.

## Wave 3: AI site builder

Goal: the owner types what they need ("a running club with events, a forum and paid coaching courses") and gets a
site built from our products, with content that fits.

Flow:

1. Chat produces a **site brief**: name, tagline, niche and audience, language, which products and starting point,
   home page and menu, brand colour and logo, how much content (people, items).
2. The brief becomes a **plan**: products, counts, pages and settings, shown to the owner for a yes before anything
   is written. This is the same Review data the wizard shows, so `plan()` counts must be exact.
3. Content comes as a **content pack** per provider, in the same shape as `content/<id>.php`, supplied through the
   `wbcom_demo_content` filter. The pack is validated against that shape before the build starts.
4. The build runs through the same engine, ledger and Finish, so everything stays removable and the owner can keep
   only the setup.

What the code needs:

| Need | Change |
|---|---|
| Brief as input | `Engine::start_build()` and REST `build` accept a brief (identity, products, counts, content pack ids); stored in state for every step. |
| Content shape per provider | A short schema for each `content/<id>.php` (keys, field order, which fields are roster indexes or media names) so packs can be generated and validated. |
| Pack validation | Reject a pack that does not match before step one; sanitize every text through the product API as now. |
| Media by meaning | A manifest of `media/` with tags (subject, orientation) so generated content can pick fitting images; same-shape images per surface (no banners in photo grids). |
| Site identity | Write site title, tagline, brand colour (per theme adapter) through `Snapshot`, so Remove everything restores them. |
| Scale | Chunk every provider's steps by item count, not only Jetonomy and BuddyNext, so larger packs stay inside one request per step. |
| Real site, not demo | The `setup` kind already marks what stays; a brief can ask for setup only (no sample people). |
| Cast | Pack can supply its own people (names, bios, roles) in the shape of `content/people.php`. |

AI service: decided 10 November 2026, WordPress core AI only (see the 1.1.0 plan). Still open: how much content a brief
may ask for, and languages at launch.
