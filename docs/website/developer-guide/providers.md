---
title: Providers, hooks and filters
---

Each product's sample content comes from a provider class. A product can ship its own provider and replace the built-in one.

## Register a provider

```php
add_action( 'wbcom_demo_register', function ( $registry ) {
	$registry->add( new My_Product_Demo_Provider() ); // extends WbcomDemo\Core\Provider
} );
```

A provider declares what it creates (`plan()`), runs small steps (`steps()`, `run()`), records every object it creates through the context, and removes each object through the product's own delete function (`remove()`).

## Hooks and filters

| Hook | Type | Use |
|---|---|---|
| `wbcom_demo_register` | action | Register or replace providers. |
| `wbcom_demo_recipes` | filter | Add or change starting points. |
| `wbcom_demo_quiet_filters` | filter | Product notification filters switched off while building. |
| `wbcom_demo_menu_locations` | filter | Theme menu locations the demo menu is assigned to. |
| `wbcom_demo_provider_supported` | filter | Allow or block a provider for the active product version. |
| `wbcom_demo_other_demos` | filter | Report other demo data that would mix with this one. |
| `wbcom_demo_built` | action | Fires after a build, with the build id. |
| `wbcom_demo_finished` | action | Fires after a finish, with the report. |
