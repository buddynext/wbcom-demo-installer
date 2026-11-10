# Wbcom Demo Installer - working guide

Admin-only plugin that builds believable demo content across Wbcom products from one wizard
(Appearance > Set up your site) and removes it again on Finish demo. WP-CLI (`wp wbcom-demo`) runs the
same step engine.

## Rules

- **Scope:** this repo only. Never edit a product plugin or theme. A product problem found while
  building a provider is filed as a card in that product's Basecamp Bugs column; the provider works
  around it here and says so in a comment that names the card.
- **Providers use product APIs only:** services, models, REST routes (`rest_do_request` as the right
  user) and core WP functions. No raw SQL on product tables. The two narrow exceptions are documented
  in code: BuddyNext hashtag rows by exact id, Product Roadmap votes for our own item ids.
- **Seeder content is cherry-picked, never called:** product demo seeders are read for good content
  only. Content lives in `content/*.php` and media in `media/`.
- **Everything created is recorded** in the ledger (`wp_wbcom_demo_ledger`) as `sample` or `setup`.
  Finish removes ledger rows newest first through each product's own delete. Never delete by name,
  email, domain or "unused" status.
- **Never touch existing users or content.** Sample people use `demo.<login>` logins and
  `@demo-people.invalid` addresses; a taken login is skipped, never adopted. Never author demo items as
  the admin; switch to the sample person when a product credits the current user.
- **Fresh demonstration site:** a site with other members, blog posts or comments (`Engine::live_signals()`)
  builds only with explicit consent (wizard checkbox, REST `consent`, WP-CLI `--yes`). No large-site work.
- **Review counts are exact:** `plan()` reports what a build creates on this site; reuse the build's own rule
  (`missing_terms()` / `ensure_term()`, `People::indexes()`), never a separate estimate.
- **Quiet mode:** all mail is reported as handled while a step runs, and mail to sample people is
  dropped always. In-app notifications go only to sample people.
- **Dates:** use `Context::date()` / `after()`, and set `Clock::set()` around any create that another
  product bridges (BuddyNext feed posts) so the bridge gets the same date.

## Layout

- `src/Core/` engine: `Engine` (build steps, status), `Finisher` (selection, removal order, verify),
  `Ledger`, `State`, `Context`, `Clock`, `QuietMode`, `Snapshot` (theme/options restore), `Registry`,
  `Recipes`, `Provider` (contract), `Installer` (free products and the BuddyX theme through the store's free-key
  channel or WordPress.org).
- `src/Providers/` one class per product, plus `People` (shared cast) and `Theme` (logo, front page,
  menu).
- `src/Admin/` wizard page and notice; `src/Rest/Controller.php` routes under `wbcom-demo/v1`;
  `src/Cli/Command.php`.
- `assets/` wizard JS/CSS (tokens `--wdi-*`, logical properties, `-rtl.css` copies).

## Adding a provider

Extend `Core\Provider`: `id`, `label`, `available`, `version`/`min_version`, `summary`, `plan`
(type => [count, kind, label]), `steps`, `run`, `remove`, optional `menu_items`, `front_page`,
`late_types`, `handover`, `verify`, `settings` (the product's best-presentation options and its setup-complete flag,
applied through `Snapshot` before its content so Remove everything restores them). Register in `Registry::builtin()` or from the product via the
`wbcom_demo_register` action. Each step must finish well inside one request. Content lives in
`content/<id>.php` and is read with `$this->content()` (filter `wbcom_demo_content`), never kept in the class.
Third-party plugins are added the same way. Full guide: `docs/website/developer-guide/providers.md`; plan for
Wave 2 and Wave 3: `docs/roadmap.md`. Work in progress for 1.1.0: start at `docs/plans/1.1.0-handoff.md`.

## Checks before a release

- `phpcs --standard=phpcs.xml.dist` clean; PHPStan level 6 with `phpstan.neon.dist` clean.
- `wp i18n make-pot` with no warnings; regenerate `languages/wbcom-demo-installer.pot`.
- Lab matrix (`~/work-artifacts/keep/demo-installer-lab/matrix.sh`): every case PASS, plus the extra
  rows in `docs/testing.md`, plus browser checks of the wizard at 1440 and 390 in light and dark.
- `bin/build-release.sh` builds `dist/wbcom-demo-installer-<version>.zip`.
