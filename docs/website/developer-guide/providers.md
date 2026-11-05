---
title: Providers, hooks and filters
---

Each product's sample content comes from a provider class. A provider can live in this plugin or in the product
itself, and a third-party plugin (WooCommerce, LearnDash, any other) is added the same way: one provider class and
one content file. Nothing in the engine, the wizard, Finish or the REST and WP-CLI commands changes.

## Register a provider

```php
add_action( 'wbcom_demo_register', function ( $registry ) {
	$registry->add( new My_Product_Demo_Provider() ); // extends WbcomDemo\Core\Provider
} );
```

Registering a provider with the id of a built-in one replaces it.

## What a provider declares

| Method | Required | Purpose |
|---|---|---|
| `id()` | yes | Short slug, also the name of its content file (`content/<id>.php`). |
| `label()`, `summary()`, `icon()` | yes / yes / no | Name, one-line summary and icon on the wizard card. |
| `available()` | yes | True when the product is active (check a class or constant). |
| `version()`, `min_version()` | no | The wizard marks the product "not supported" below the tested minimum. |
| `pro_label()` | no | "Pro add-on active" when the product's Pro is present. |
| `plan()` | yes | `object_type => [count, 'sample'\|'setup', label]`, shown on Review and in the Finish choices. Counts must equal what a build creates on this site: count terms with `missing_terms()`, the same rule `ensure_term()` builds with. |
| `steps()`, `run()` | yes | Small steps, each finishing well inside one request (chunk long lists). |
| `remove()` | yes | Delete one recorded object through the product's own delete function. |
| `settings()` | no | The product's best demo settings and its setup-complete flag (see below). |
| `activation_redirects()` | no | The one-time "go to my setup wizard" redirects the product sets on activation; cleared when the owner opens this wizard and on build. |
| `menu_items()`, `front_page()` | no | Pages for the demo menu and the home page; menu pages get the theme's no-sidebar layout. |
| `handover()`, `late_types()`, `verify()` | no | Kept items handed to the owner, removal order, extra checks after Finish. |
| `order()` | no | Build order; a provider that needs another product's content runs after it. |

## Content

Sample content lives in `content/<id>.php`, which returns an array the provider reads with `$this->content()`. Keep
text, people and media references there, never in the class. Authors are indexes into the shared cast
(`content/people.php`), so the same 20 people appear across every product.

The `wbcom_demo_content` filter receives that array and the provider id. An add-on, a niche content pack or a
generated pack can replace or extend the content without touching the provider, as long as it keeps the same shape.

## Settings

`settings()` returns the options that present the product at its best, applied through the snapshot before its
content is built:

```php
public function settings(): array {
	return array(
		'myproduct_setup_complete'                => true,              // the flag its own wizard's Skip or Finish sets
		'myproduct_settings'                      => array( 'layout' => 'grid' ), // merged into an array option
		'transient:myproduct_notice_pending'      => null,              // a one-time notice transient, cleared only
	);
}
```

Set a setup-complete flag only when the demo already does what the wizard would (pages, categories); never to hide a
step the product still needs. "Keep my setup" keeps these values; "Remove everything" restores every value the owner
has not changed since.

## Context helpers

| Helper | Use |
|---|---|
| `$ctx->people( $role )`, `$ctx->person( $i, $role )` | Sample people; roles: moderator, instructor, organizer, vendor, candidate, employer, reviewer, student, listing_owner, attendee, buyer. |
| `$ctx->date( $i, $n, $days )`, `$ctx->after( $date, $seq )` | Dates spread over past weeks; wrap creates that another product bridges in `Clock::set()`. |
| `$ctx->record( $provider, $type, $id, $kind )` | Record every created object in the ledger. |
| `$ctx->attachment( $rel, $author, $provider )`, `$ctx->media( $rel )` | Media from `media/`. |
| `$ctx->set()`, `$ctx->get()` | Pass ids between steps. |
| `$this->ensure_term( $name, $taxonomy, $ctx )`, `self::remove_unused_term( $id, $taxonomy )` | Create and record a term only when the site lacks it; remove it on Finish only while nothing uses it. |
| `$ctx->warn( $provider, $what, $error )` | Report a non-fatal problem to the wizard. |

## Rules

- Product APIs only: services, models, REST routes as the right user, core WordPress functions. No raw SQL on
  product tables.
- Create as the sample person a product credits (switch the current user), never as the site owner.
- Never touch existing users or content; a taken login is skipped, never adopted.
- Mail is quiet while a step runs and mail to sample people is always dropped.
- A product bug found while writing a provider is filed on that product's board and worked around here, with a
  comment naming the card.

## Done means

A new provider ships with: its content file, a `plan()` whose counts match what is created, a lab matrix case for
the product alone and inside its starting point, build with no warnings, Finish with nothing left, Remove everything
matching the site from before the build, and a browser check of every page it adds to the menu at 1440 and 390.

## Hooks and filters

| Hook | Type | Use |
|---|---|---|
| `wbcom_demo_register` | action | Register or replace providers. |
| `wbcom_demo_content` | filter | Replace or extend one provider's sample content. |
| `wbcom_demo_recipes` | filter | Add or change starting points. |
| `wbcom_demo_page_layout` | filter | Page meta that removes the sidebar on a product page for another theme. |
| `wbcom_demo_menu_locations` | filter | Theme menu locations the demo menu is assigned to. |
| `wbcom_demo_quiet_filters` | filter | Product notification filters switched off while building. |
| `wbcom_demo_provider_supported` | filter | Allow or block a provider for the active product version. |
| `wbcom_demo_other_demos` | filter | Report other demo data that would mix with this one. |
| `wbcom_demo_built` | action | Fires after a build, with the build id. |
| `wbcom_demo_finished` | action | Fires after a finish, with the report. |
