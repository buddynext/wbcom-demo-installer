# 1.1.0 D2-D4 verification: Demo data entry, memory check, name and tagline

Run 11 November 2026 on the lab (port 8930), WordPress 7.1.3, PHP 8.3, all free products active, BuddyX 5.2.0.

| Plan row | Card | Case | Result |
|---|---|---|---|
| D2 | 10483387455 | Tools > Demo data as the admin | PASS: 302 to Appearance > Set up your site with view=data |
| D2 | 10483387455 | Same, right after products were activated (WB Gamification set its one-time setup redirect) | PASS after the fix in this batch: redirects are cleared on this page too, so it no longer lands on the WB Gamification setup screen |
| D2 | 10483387455 | Wizard with view=data and nothing tracked (Node render) | PASS: "No demo data on this site" with Set up a demo |
| D2 | 10483387455 | Wizard with view=data and a demo on the site | Same route as Finish demo: opens the Finish screen (Let me choose covers chosen types, in rounds) |
| D3 | 10487106723 | memory_limit 128M | PASS: memory() low=true; site check shows the warning with the fix (Node render); WP-CLI build prints a warning naming WP_CLI_PHP_ARGS |
| D3 | 10487106723 | memory_limit 512M | PASS: no warning |
| D4 | | `build --name="Riverside Runners" --tagline="Run together, every Sunday"` | PASS: name and tagline set; no sample logo and no hidden title (header_textcolor untouched); site icon set |
| D4 | | Remove everything after that build | PASS: "My Community" and the empty tagline back; icon removed |
| D4 | | Review step fields (Node render) | PASS: prefilled with the current name and tagline; only changed values are sent |

Gates: PHPCS clean, PHPStan level 6 clean, docs lint 0/0, POT regenerated.

Not yet verified: browser check at 1440 and 390 in light and dark (Playwright browser held by another session).
