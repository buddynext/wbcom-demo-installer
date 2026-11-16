---
title: WP-CLI commands
---

Every wizard action is available from WP-CLI. Commands run as the first administrator unless you pass `--user`.

| Command | What it does |
|---|---|
| `wp wbcom-demo status` | Theme, supported products, starting points and the demo content on the site. |
| `wp wbcom-demo build [--recipe=<id>] [--include=<ids>] [--keep-look] [--dry-run] [--install-missing] [--name=<name>] [--tagline=<text>]` | Build a demo. Recipes: community, learning, events, jobs, directory, services, everything. `--install-missing` first installs the free products the starting point uses, then builds. `--name` and `--tagline` set the site name and tagline (restored by Remove everything). `--describe="<text>" [--idea=<n>]` asks the AI connector for ideas and builds one with text written for it. `--demo=<id>` builds a gallery demo (from `wp wbcom-demo demos`) once everything it needs is active. |
| `wp wbcom-demo demos [--format=table\|json]` | The demos in the Starting point gallery for the active theme, with each one's state (ready, fixable, blocked, conflict) and what it still needs. |
| `wp wbcom-demo finish [--mode=keep\|all] [--keep=<provider:type,...>] [--dry-run]` | Finish the demo. `--keep` keeps the listed types (custom mode). |
| `wp wbcom-demo install [<ids>...] [--all]` | Install and activate the free products a demo uses (Wbcom products, BuddyPress, WooCommerce, the LMS and marketplace plugins, or `buddyx`, the free theme), the latest release from GitHub, wbcomdesigns.com or WordPress.org. Switches on an uploaded paid plugin (BuddyBoss Platform, PeepSo, LearnDash) that is installed but not active. |
| `wp wbcom-demo ideas "<description>" [--format=json]` | Three site ideas from a sentence, through the AI connector in Settings > Connectors. |
| `wp wbcom-demo resume` | Continue a build or finish that stopped part way, or a finish that left items waiting for a product or failed. |
| `wp wbcom-demo verify` | Show the report of the last finish: removed, kept by choice, waiting, failed. Exits with code 1 when something you chose was not removed. |
| `wp wbcom-demo adopt` | Keep what the demo set up (settings, pages, menus, categories) as yours and end the demo. Only after the sample content is removed. |

`build` exits with an error when a product could not finish its part, and lists items that could not be created.

Product ids: buddynext, mediaverse, jetonomy, learnomy, eventonomy, careerboard, listora, sellservices, mediashield, roadmap, gamification.
