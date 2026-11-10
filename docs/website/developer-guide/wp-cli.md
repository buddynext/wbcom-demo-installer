---
title: WP-CLI commands
---

Every wizard action is available from WP-CLI. Commands run as the first administrator unless you pass `--user`.

| Command | What it does |
|---|---|
| `wp wbcom-demo status` | Theme, supported products, starting points and the demo content on the site. |
| `wp wbcom-demo build [--recipe=<id>] [--include=<ids>] [--keep-look] [--dry-run]` | Build a demo. Recipes: community, learning, events, jobs, directory, services, everything. |
| `wp wbcom-demo finish [--mode=keep\|all] [--keep=<provider:type,...>] [--dry-run]` | Finish the demo. `--keep` keeps the listed types (custom mode). |
| `wp wbcom-demo resume` | Continue a build or finish that stopped part way, or a finish that left items waiting for a product or failed. |
| `wp wbcom-demo verify` | Show the report of the last finish: removed, kept by choice, waiting, failed. Exits with code 1 when something you chose was not removed. |
| `wp wbcom-demo adopt` | Keep what the demo set up (settings, pages, menus, categories) as yours and end the demo. Only after the sample content is removed. |

`build` exits with an error when a product could not finish its part, and lists items that could not be created.

Product ids: buddynext, mediaverse, jetonomy, learnomy, eventonomy, careerboard, listora, sellservices, mediashield, roadmap, gamification.
