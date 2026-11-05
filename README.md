# Wbcom Demo Installer

One place for a WordPress site owner to see their Wbcom products working with believable sample content, then
remove the samples and keep their setup.

- **Set up your site** (Appearance menu, admins only): pick a starting point, choose which installed products to
  include, review the exact counts, build. One shared cast of 20 sample people is used across every product, dates
  are spread over recent weeks, and nothing is emailed.
- **Finish demo**: keep your settings, pages, menus and structure, remove the sample people and content (or choose
  per product, or remove everything and restore your previous home page and menu). A verify report confirms nothing
  is left behind.
- Existing users and content are never changed.

Supports the Wbcom product family (BuddyNext, WPMediaVerse, Jetonomy, Learnomy, Eventonomy, WP Career Board,
WB Listora, WP Sell Services, MediaShield, Product Roadmap, WB Gamification and their Pro add-ons) with the BuddyX, BuddyX Pro and Reign themes. Third-party
plugins used by the Reign add-ons follow in a later release.

Requires WordPress 6.5+ and PHP 8.1+.

## WP-CLI

```
wp wbcom-demo status
wp wbcom-demo build --recipe=community [--include=buddynext,jetonomy] [--keep-look] [--dry-run]
wp wbcom-demo finish [--mode=keep|all] [--keep=learnomy:course] [--dry-run]
wp wbcom-demo resume
wp wbcom-demo verify
```

## License

GPL-2.0-or-later.

Docs: `docs/website/`. Developer guide: providers, hooks and WP-CLI.
