---
title: Theme onboarding
---

A theme can give a new owner a "Set up your site" card on its own screen: pick a kind of site, build it, take it
live. The installer does the work; the theme only shows where the owner stands and links in. Reign does this first,
and BuddyX and BuddyX Pro follow the same pattern.

Read everything through the functions below. Never copy the demo list, film ids or lengths into the theme, and never
read the `wbcom_demo_state` option: a new demo or film then shows on every theme without a theme release.

Check `function_exists()` before each call, since older installers do not have them.

## The card's state: `wbcom_demo_status()`

Returns `status`, `demo_id`, `demo_label`, `built_at`, `finished_at` (UTC, `Y-m-d H:i:s`) and `samples_left`.

| What the owner sees | When |
|---|---|
| Pick a site to build | `status` is `idle` |
| Building, with a link to watch it | `status` is `building` |
| Your demo is ready: try it, then take it live | `status` is `built` and `samples_left` is more than 0 |
| Live: hide the card, or show "your pages are yours" | `status` is `finished`, or `built` with `samples_left` 0 |
| Finishing | `status` is `finishing` |

`demo_label` names the site ("Learnomy courses"). Never label it from the recipe: every gallery build uses the same
recipe. After Remove everything the status is `idle` again, so the card offers a fresh build.

## The site tiles: `wbcom_demo_list()`

Returns `demos` and `go_live`. Each demo has `id`, `name`, `desc`, `group` (`ours`, `theme` or `addon`), `purpose`,
`featured`, `state`, `image`, `preview`, `film` (`id` and `length`, or empty) and `url`.

- Show the `featured` demos as tiles: one per purpose (community, learning, events, jobs, directory, services, shop),
  with the demo for a plugin the owner already runs in its purpose's place.
- Add the theme's all-in-one demo (`group` `theme`, id ending in `-buddynext`) as the "everything" tile if you want one.
- The tile's button links to `url`. The wizard opens on that demo: its review step when the demo's plugins are active,
  otherwise its card with Set up and use this demo ready.
- `state` lets the tile say "Ready to build" (`ready`) or "Installs N free plugins" (`fixable`); a `blocked` demo needs a
  paid upload first.
- `film` is the setup film recorded on this theme, when there is one. `go_live` is the film for taking a site live.
  Load YouTube only on click (`youtube-nocookie.com`).

## Links into the wizard

`wp-admin/themes.php?page=wbcom-demo&demo=<id>` opens the wizard on one demo. The id is a gallery demo id, a purpose
(opens that purpose's featured demo) or `everything` (the theme's all-in-one BuddyNext demo). Unknown ids, and a site
that already has a demo, ignore it.

## Bringing the owner back

The `wbcom_demo_return` filter sets where the wizard's done screens send the owner, by default the dashboard:

```php
add_filter( 'wbcom_demo_return', function () {
	return array(
		'url'   => admin_url( 'themes.php?page=my-theme-home' ),
		'label' => __( 'Back to My Theme Home', 'my-theme' ),
	);
} );
```

The URL must be on this site's admin; anything else falls back to the dashboard.

## Reacting to builds

The actions `wbcom_demo_built` (build id) and `wbcom_demo_finished` (the finish report) fire when a build or a finish
ends, for example to clear a cached card.

## Fitting the demo to the theme

How the demo's pages sit in the theme (menu locations, footer areas, full-width pages) goes through the filters
`wbcom_demo_menu_locations`, `wbcom_demo_footer_areas` and `wbcom_demo_page_layout`, not through theme checks inside
the installer.
