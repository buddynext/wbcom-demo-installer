---
title: Demo problems and fixes (Troubleshooting)
---

# Demo problems and fixes (Troubleshooting)

**Also called:** demo import not working, demo import failed, demo stuck, demo error, import stopped, cannot remove demo.

## What this does

This page lists the messages and problems you may meet while building or removing a demo. Each one says what it means and what to do. Find the one that matches what you see on screen.

## You'll know it worked when

The site check shows no warnings, the build finishes with "your demo is ready", or the Finish report says nothing was left behind.

## Good to know

### The site check says the site already has members or content

The demo is made for a new site. Use a staging copy or a new install if you can. To build here anyway, tick the confirmation on the **Review** step.

### The site check warns about PHP memory

PHP memory is the working space your server gives WordPress. With every Wbcom product active, WordPress needs about 165 MB before the demo adds anything. With less than 256 MB, a build step can stop.

- Ask your host to raise the PHP memory limit to 256 MB.
- Or add `define( 'WP_MEMORY_LIMIT', '256M' );` to your `wp-config.php` file.

If a step does stop, the build skips the rest of that product's part. It tells you so and finishes everything else.

### A product is shown as not supported

Its version is older than the one the demo was tested with. Update the product, then open the wizard again.

### The site check says demo content from another product is still on the site

That product added its own demo data earlier. Remove it in that product first, so the two demos do not mix.

### The build stopped, or I closed the tab

Open **Appearance > Set up your site** again. The build carries on from the step where it stopped.

### A product was switched off before I removed the demo

Its sample content waits until you activate the product again. Everything else is removed. Activate the product and run **Finish demo** again.

### The Finish report lists items that could not be removed

Usually someone changed or deleted those items after the build. Open each item from the report and remove it by hand.

### I see a reminder about payments or a Terms page

These are real tasks for your site, not demo problems. Connect a payment method and choose your own Terms page in that product's settings.

### Still stuck?

Open a support ticket at [wbcomdesigns.com/support](https://wbcomdesigns.com/support/#submit-ticket-section). Please include:

1. The installer version, shown under **Plugins**, and what the **Site check** step shows. A screenshot of that step is enough.
2. Your theme's system status. On Reign, open **Reign Settings > Tools**. On other themes, open **Tools > Site Health > Info** and choose **Copy site info to clipboard**.
3. Which demo or starting point you chose, the step where it stopped, and the exact message on screen.

## For developers

- To build on a site that already has content, add `--yes` to `wp wbcom-demo build`.
- To give WP-CLI more memory, run `WP_CLI_PHP_ARGS="-d memory_limit=512M" wp wbcom-demo build`.
- To continue a build or removal that stopped, run `wp wbcom-demo resume`.
- To see what is left after a removal, run `wp wbcom-demo verify`.

See [WP-CLI commands](../developer-guide/wp-cli.md) for every command.
