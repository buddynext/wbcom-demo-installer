---
title: WP-CLI commands
---

Every wizard action is available from WP-CLI. Commands run as the first administrator unless you pass `--user`.

| Command | What it does |
|---|---|
| `wp wbcom-demo status` | Theme, supported products, starting points and the demo content on the site. |
| `wp wbcom-demo build [--recipe=<id>] [--include=<ids>] [--keep-look] [--dry-run]` | Build a demo. Recipes: community, learning, events, jobs, directory, services, everything. |
| `wp wbcom-demo finish [--mode=keep\|all] [--keep=<provider:type,...>] [--dry-run]` | Finish the demo. `--keep` keeps the listed types (custom mode). |
| `wp wbcom-demo resume` | Continue a build or finish that stopped part way. |
| `wp wbcom-demo verify` | Show the report of the last finish; exits with an error if anything was left. |

Product ids: buddynext, mediaverse, jetonomy, learnomy, eventonomy, careerboard, listora, sellservices, mediashield, roadmap, gamification.
