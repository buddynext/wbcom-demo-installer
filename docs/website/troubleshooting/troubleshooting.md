---
title: Troubleshooting
---

## The site check says the site already has members or content

The demo is made for a fresh site. Use a staging copy or a new install if you can. To build here anyway, tick the
confirmation on the Review step. From WP-CLI, add `--yes` to `wp wbcom-demo build`.

## The site check warns about PHP memory

With every Wbcom product active, WordPress needs about 165 MB before the demo adds anything, so with less than 256 MB
a build step can stop. Ask your host to raise `memory_limit` to 256 MB, or add `define( 'WP_MEMORY_LIMIT', '256M' );`
to `wp-config.php`. From WP-CLI, run with `WP_CLI_PHP_ARGS="-d memory_limit=512M" wp wbcom-demo build`. If a step
does stop, the build skips that product's remaining part, says so, and finishes the rest.

## A product is shown as not supported

Its version is older than the one the demo was tested with. Update the product, then open the wizard again.

## The site check says demo content from another product is still on the site

That product added its own demo data earlier. Remove it from that product first, so the two demos do not mix.

## The build stopped or I closed the tab

Open **Appearance > Set up your site** again. The build continues from the step where it stopped. From WP-CLI, run
`wp wbcom-demo resume`.

## A product was deactivated before Finish

Its sample content waits until you activate the product again; everything else is removed. Reactivate it and run
Finish again to complete.

## The Finish report lists items that could not be removed

Usually the owner changed or deleted them after the build. Open the item from the report and remove it by hand, or
run `wp wbcom-demo verify` to see what is left.

## I see a reminder about payments or a Terms page

These are real owner tasks, not demo problems. Connect a payment method and map your own Terms page in that
product's settings.
