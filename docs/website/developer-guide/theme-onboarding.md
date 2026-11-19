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
`featured`, `state`, `image`, `preview`, `film` (`id` and `length`, or empty), `url`, `needs` and `optional`.

- Show the `featured` demos as tiles: one per purpose (community, learning, events, jobs, directory, services, shop),
  with the demo for a plugin the owner already runs in its purpose's place.
- Add the theme's all-in-one demo (`group` `theme`, id ending in `-buddynext`) as the "everything" tile if you want one.
- The tile's button links to `url`. The wizard opens on that demo: its review step when the demo's plugins are active,
  otherwise a "Set up <demo>" step showing that one card with Set up and use this demo focused (one click installs what
  it needs), the other demos behind "Choose a different demo". Nothing installs until the owner clicks.
- `state` is worked out for the current user, so call `wbcom_demo_list()` during the owner's admin request (under WP-CLI,
  run `wp_set_current_user()` first). `ready`: everything is active, "Ready to build". `fixable`: the wizard installs
  or switches on what is missing in one click. `blocked`: something cannot be set up from here, either a paid plugin
  or add-on to upload, or this account or host does not allow installing plugins; the wizard card says which.
  `conflict`: the site runs another community plugin, so this demo does not fit.
- `needs` lists what is still missing, each as `label`, `kind` (`plugin`, `addon` for a Reign add-on from the owner's
  Wbcom account, or `theme`), `action` and `url`. Show it on the tile so the owner is never
  lost, above all for paid items:

  | `action` | Tell the owner |
  |---|---|
  | `install` | "Installed for you" (free; the wizard sets it up in one click) |
  | `upload` | `kind` `addon`: "Paid add-on: download it from your Wbcom account, then upload it under Plugins > Add New > Upload Plugin". Otherwise: "Paid: get it, then upload it under Plugins > Add New > Upload Plugin". Link `url` as Get it. |
  | `ask` | "Ask an administrator to add it" (this account or host cannot install plugins) |

  `optional` lists paid add-ons that improve the demo but are not required, in the same shape.
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

The URL must be on this site (WordPress's allowed redirect hosts); anything else falls back to the dashboard. Return
both keys: a filter that sets only `url` keeps the label Go to dashboard.

## Reacting to builds

The actions `wbcom_demo_built` (build id) and `wbcom_demo_finished` (the finish report) fire when a build or a finish
ends, for example to clear a cached card.

## Fitting the demo to the theme

How the demo's pages sit in the theme (menu locations, footer areas, full-width pages) goes through the filters
`wbcom_demo_menu_locations`, `wbcom_demo_footer_areas` and `wbcom_demo_page_layout`, not through theme checks inside
the installer.
