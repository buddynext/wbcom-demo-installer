<?php
/**
 * WP-CLI: the same engine the wizard uses.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Cli;

use WbcomDemo\Core\Engine;
use WbcomDemo\Core\Finisher;
use WbcomDemo\Core\Guide;
use WbcomDemo\Core\Installer;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Recipes;
use WbcomDemo\Providers\People;
use WbcomDemo\Providers\Theme;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Build, finish and verify demo content.
 */
final class Command {

	/**
	 * Show what is on the site and what a build would create.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function status( $args, $assoc ): void {
		$site   = Engine::site();
		$status = Engine::status();
		if ( 'json' === ( $assoc['format'] ?? '' ) ) {
			WP_CLI::line( (string) wp_json_encode( compact( 'site', 'status' ), JSON_PRETTY_PRINT ) );
			return;
		}
		WP_CLI::line( sprintf( 'Theme: %s %s (%s adapter)', $site['theme']['name'], $site['theme']['version'], $site['theme']['adapter'] ) );
		WP_CLI::line( sprintf( 'Demo status: %s', $status['status'] ) );
		WP_CLI::line( 'Starting points: ' . implode( ', ', array_keys( $site['recipes'] ) ) );
		$rows = array();
		foreach ( $site['products'] as $p ) {
			$rows[] = array(
				'id'        => $p['id'],
				'product'   => $p['label'],
				'version'   => $p['version'],
				'supported' => $p['supported'] ? 'yes' : 'no',
			);
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'product', 'version', 'supported' ) );
		if ( $status['ledger'] ) {
			WP_CLI::line( 'Demo content on the site:' );
			WP_CLI\Utils\format_items( 'table', $status['ledger'], array( 'product', 'label', 'kind', 'count' ) );
		}
	}

	/**
	 * Build a demo.
	 *
	 * ## OPTIONS
	 *
	 * [--recipe=<recipe>]
	 * : Starting point. Default: community, or the first available.
	 *
	 * [--include=<ids>]
	 * : Comma-separated product ids. Default: the starting point's products.
	 *
	 * [--demo=<id>]
	 * : A named demo from the Starting point gallery (wp wbcom-demo demos): builds exactly its products.
	 *
	 * [--keep-look]
	 * : Do not change the home page, menu or logo.
	 *
	 * [--dry-run]
	 * : Show what would be created.
	 *
	 * [--yes]
	 * : Build even though the site already has members or content (the demo is made for a fresh site).
	 *
	 * [--name=<name>]
	 * : Site name to use (restored by Remove everything). The header shows it instead of the sample logo.
	 *
	 * [--tagline=<tagline>]
	 * : Tagline to use (restored by Remove everything).
	 *
	 * [--describe=<text>]
	 * : Describe the site in a sentence: the AI connector set up in Settings > Connectors suggests ideas, the build
	 * uses one (see --idea), installs what it needs and writes the text for it.
	 *
	 * [--idea=<n>]
	 * : Which suggested idea to build with --describe (1 to 3).
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--brief=<json>]
	 * : Internal: the picked idea as JSON, passed on by --describe.
	 *
	 * [--add=<ids>]
	 * : Add these products to the demo that is already built (same ledger, so Finish removes them too).
	 *
	 * [--install-missing]
	 * : First install and activate what is missing: with --demo, what its gallery card sets up (free plugins, uploaded
	 * Reign add-ons, the free BuddyX theme last); otherwise the free Wbcom products the starting point uses.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo build --demo=events --install-missing --yes
	 *     wp wbcom-demo build --recipe=community
	 *     wp wbcom-demo build --include=buddynext,jetonomy --keep-look
	 *
	 * @param array<int,string>         $args  Args.
	 * @param array<string,string|bool> $assoc Assoc args.
	 */
	public function build( $args, $assoc ): void {
		$this->as_admin();
		if ( isset( $assoc['describe'] ) ) {
			$ideas = \WbcomDemo\Ai\Ideas::suggest( (string) $assoc['describe'] );
			if ( is_wp_error( $ideas ) ) {
				WP_CLI::error( $ideas->get_error_message() );
			}
			$pick = $ideas[ max( 0, (int) ( $assoc['idea'] ?? 1 ) - 1 ) ] ?? $ideas[0];
			WP_CLI::log( sprintf( 'Building "%s" (%s): %s', $pick['name'], $pick['recipe'], $pick['tagline'] ) );
			$this->install_ids( $pick['products'] );
			// New plugins boot on the next request, and the brief travels as JSON to the build in a fresh process.
			$flags = sprintf( ' --recipe=%s --include=%s --name=%s --tagline=%s --brief=%s', $pick['recipe'], implode( ',', $pick['products'] ), escapeshellarg( $pick['name'] ), escapeshellarg( $pick['tagline'] ), escapeshellarg( (string) wp_json_encode( $pick + array( 'description' => (string) $assoc['describe'] ) ) ) );
			foreach ( array( 'yes', 'keep-look' ) as $flag ) {
				$flags .= isset( $assoc[ $flag ] ) ? ' --' . $flag : '';
			}
			$this->relaunch( 'wbcom-demo build' . $flags );
			return;
		}
		if ( isset( $assoc['install-missing'] ) ) {
			if ( isset( $assoc['demo'] ) ) {
				// What the demo's card would set up: its free plugins, then Reign add-ons, the theme last.
				$demo = $this->demo( (string) $assoc['demo'] );
				if ( 'conflict' === $demo['state'] ) {
					WP_CLI::error( $this->needs( $demo ) );
				}
				$want = $demo['fix'];
				$late = static fn( string $t ): int => 'buddyx' === $t ? 2 : ( str_starts_with( $t, 'addon:' ) ? 1 : 0 );
				usort( $want, static fn( string $a, string $b ): int => $late( $a ) <=> $late( $b ) );
			} else {
				$recipe = (string) ( $assoc['recipe'] ?? 'community' );
				$all    = \WbcomDemo\Core\Recipes::all();
				if ( ! isset( $all[ $recipe ] ) ) {
					WP_CLI::error( sprintf( 'Unknown starting point "%s". Starting points: %s', $recipe, implode( ', ', array_keys( $all ) ) ) );
				}
				$want = isset( $assoc['include'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['include'] ) ) ) : array_merge( Installer::CORE, $all[ $recipe ]['use'], $all[ $recipe ]['needs'] );
			}
			$waits  = isset( $demo ) && 'blocked' === $demo['state'];
			$want   = array_values( array_unique( $want ) );
			$addons = array_values( array_filter( $want, static fn( string $id ): bool => str_starts_with( $id, 'addon:' ) ) );
			$theme  = array_values( array_intersect( $want, array( 'buddyx' ) ) );
			// Plugins first, then the Reign add-ons that need them, the theme last; only the last step says done.
			$this->install_ids( array_values( array_diff( $want, $addons, $theme ) ), ! $waits && ! $addons && ! $theme );
			if ( $addons ) {
				// WordPress checks an add-on's required plugins once per request: switch the add-ons on in a fresh one,
				// now that their plugins are active.
				$this->relaunch( 'wbcom-demo install ' . implode( ' ', $addons ) . ( $waits || $theme ? ' --quiet-done' : '' ) );
			}
			if ( $theme ) {
				$this->install_ids( $theme, ! $waits );
			}
			if ( $waits ) {
				// The free part is in; what is left is the owner's to upload, so stop here and say exactly what.
				WP_CLI::error( $this->needs( $demo, true ) );
			}
			// New plugins boot on the next request, so the build runs in a fresh process.
			unset( $assoc['install-missing'] );
			$flags = '';
			foreach ( $assoc as $k => $v ) {
				if ( '' === $v ) {
					continue;
				}
				// WP-CLI passes a bare flag as true; a default such as --idea's 1 arrives as a number and keeps its value.
				$flags .= true === $v ? " --$k" : ' --' . $k . '=' . escapeshellarg( (string) $v );
			}
			$this->relaunch( 'wbcom-demo build' . $flags );
			return;
		}
		if ( isset( $assoc['add'] ) ) {
			$status = Engine::add_products( array_filter( array_map( 'trim', explode( ',', (string) $assoc['add'] ) ) ) );
			if ( is_wp_error( $status ) ) {
				WP_CLI::error( $status->get_error_message() );
			}
			$this->run_steps( 'Adding' );
			$status = Engine::status();
			foreach ( array_merge( (array) $status['errors'], (array) $status['warnings'] ) as $problem ) {
				WP_CLI::warning( $problem );
			}
			WP_CLI::success( sprintf( 'Added to the demo: %d items in total now.', array_sum( array_column( $status['ledger'], 'count' ) ) ) );
			return;
		}
		$demo = isset( $assoc['demo'] ) ? $this->demo( (string) $assoc['demo'] ) : null;
		if ( $demo && 'ready' !== $demo['state'] ) {
			WP_CLI::error( $this->needs( $demo ) );
		}
		$recipes = Recipes::available();
		if ( ! $recipes ) {
			WP_CLI::error( 'No supported products are active.' );
		}
		$recipe = $assoc['recipe'] ?? ( isset( $recipes['community'] ) ? 'community' : array_key_first( $recipes ) );
		if ( ! isset( $recipes[ $recipe ] ) ) {
			WP_CLI::error( sprintf( 'Unknown or unavailable starting point "%s". Available: %s', $recipe, implode( ', ', array_keys( $recipes ) ) ) );
		}
		$include = isset( $assoc['include'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['include'] ) ) ) : $recipes[ $recipe ]['use'];
		if ( $demo ) {
			// A named demo from the Starting point gallery (checked ready above): exactly its products.
			$recipe  = 'everything';
			$include = $demo['products'];
		}
		if ( isset( $assoc['dry-run'] ) ) {
			foreach ( Engine::site()['products'] as $p ) {
				if ( in_array( $p['id'], $include, true ) ) {
					foreach ( $p['plan'] as $type => $row ) {
						WP_CLI::line( sprintf( '%-16s %5d %-7s %s', $p['label'], $row[0], $row[1], $row[2] ) );
					}
				}
			}
			return;
		}
		$memory = Engine::memory();
		if ( $memory['low'] ) {
			WP_CLI::warning( sprintf( 'PHP memory limit is %d MB; with all products active WordPress needs about 165 MB before a step starts. If a step stops, run WP-CLI with a higher limit: php -d memory_limit=512M $(which wp) wbcom-demo ...', (int) ( $memory['limit'] / MB_IN_BYTES ) ) );
		}
		// PeepSo switches itself off under WP-CLI (its constructor returns early), so its provider cannot run here.
		foreach ( (array) get_option( 'active_plugins', array() ) as $plugin ) {
			if ( str_ends_with( (string) $plugin, '/peepso.php' ) && ! class_exists( 'PeepSoActivity' ) ) {
				WP_CLI::warning( 'PeepSo does not load under WP-CLI, so its stream posts are left out. Build from Appearance > Set up your site, or the REST API, to include PeepSo.' );
			}
		}
		$status = Engine::start_build( $recipe, $include, isset( $assoc['keep-look'] ) ? 'keep' : 'demo', isset( $assoc['yes'] ), (string) ( $assoc['name'] ?? '' ), (string) ( $assoc['tagline'] ?? '' ), (array) json_decode( (string) ( $assoc['brief'] ?? '' ), true ), sanitize_key( (string) ( $assoc['demo'] ?? '' ) ) );
		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() . ( 'wbcom_demo_live_site' === $status->get_error_code() ? ' Add --yes to build anyway.' : '' ) );
		}
		$this->run_steps( 'Building' );
		$status   = Engine::status();
		$problems = array_merge( (array) $status['errors'], (array) $status['warnings'] );
		foreach ( $problems as $problem ) {
			WP_CLI::warning( $problem );
		}
		if ( ! empty( $status['reused'] ) ) {
			WP_CLI::log( 'Reused from an earlier demo, as they are: ' . implode( ', ', (array) $status['reused'] ) . '.' );
		}
		$items = array_sum( array_column( $status['ledger'], 'count' ) );
		if ( $status['errors'] ) {
			WP_CLI::error( sprintf( 'Demo built with problems: %d items, %d problems. Fix the cause, then run Finish and build again.', $items, count( $problems ) ) );
		}
		if ( $problems ) {
			WP_CLI::success( sprintf( 'Demo built: %d items, %d could not be created (listed above).', $items, count( $problems ) ) );
			return;
		}
		WP_CLI::success( sprintf( 'Demo built: %d items.', $items ) );
	}

	/**
	 * Finish the demo.
	 *
	 * ## OPTIONS
	 *
	 * [--mode=<mode>]
	 * : keep (keep setup, remove sample content), all (remove everything and restore the look).
	 * ---
	 * default: keep
	 * options:
	 *   - keep
	 *   - all
	 * ---
	 *
	 * [--keep=<keys>]
	 * : Comma-separated provider:type keys to keep (custom mode), e.g. learnomy:course.
	 *
	 * [--dry-run]
	 * : Show what would be removed and kept.
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function finish( $args, $assoc ): void {
		$this->as_admin();
		$mode   = $assoc['mode'] ?? 'keep';
		$custom = array();
		if ( ! empty( $assoc['keep'] ) ) {
			$keep = array_map( 'trim', explode( ',', $assoc['keep'] ) );
			$mode = 'custom';
			foreach ( Finisher::preview( 'keep' )['remove'] as $row ) {
				$custom[ $row['key'] ] = ! in_array( $row['key'], $keep, true );
			}
		}
		$preview = Finisher::preview( $mode, $custom );
		if ( isset( $assoc['dry-run'] ) ) {
			foreach ( array( 'remove', 'keep' ) as $side ) {
				WP_CLI::line( strtoupper( $side ) . ':' );
				WP_CLI\Utils\format_items( 'table', $preview[ $side ], array( 'product', 'label', 'kind', 'count' ) );
			}
			return;
		}
		$status = Finisher::start( $mode, $custom );
		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() );
		}
		$this->run_steps( 'Finishing' );
		$this->verify( array(), array() );
	}

	/**
	 * Continue a build or finish that stopped part way (closed browser, timeout).
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function resume( $args, $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI command signature.
		$this->as_admin();
		$status = Engine::status();
		if ( 'built' === $status['status'] && ! empty( $status['report']['left'] ) ) {
			// A finish ended with items waiting for a product or failed: try them again with the same choices.
			$started = Finisher::resume();
			if ( is_wp_error( $started ) ) {
				WP_CLI::error( $started->get_error_message() );
			}
			$status = Engine::status();
		}
		if ( ! in_array( $status['status'], array( 'building', 'finishing' ), true ) ) {
			WP_CLI::success( 'Nothing to resume.' );
			return;
		}
		$finishing = 'finishing' === $status['status'];
		$this->run_steps( $finishing ? 'Finishing' : 'Building' );
		if ( $finishing ) {
			$this->verify( array(), array() );
			return;
		}
		WP_CLI::success( 'Build resumed and completed.' );
	}

	/**
	 * Show the report of the last finish.
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function verify( $args, $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI command signature.
		$report = (array) Engine::status()['report'];
		if ( ! $report ) {
			WP_CLI::line( 'No finish has run yet.' );
			return;
		}
		WP_CLI::line( sprintf( 'Removed: %d  Not removed yet: %d  Waiting: %d  Failed: %d  Sample people left: %d', $report['removed'], $report['left'], $report['waiting'], $report['failed'], $report['people_left'] ) );
		if ( ! empty( $report['waiting_for'] ) ) {
			WP_CLI::line( 'Waiting for: ' . implode( ', ', (array) $report['waiting_for'] ) . ' (activate, then run: wp wbcom-demo resume)' );
		}
		$kept = (int) ( $report['kept_sample'] ?? 0 ) + (int) ( $report['kept_setup'] ?? 0 );
		if ( $kept ) {
			WP_CLI::line( sprintf( 'Kept by your choice: %d sample items, %d setup items. Remove them later with wp wbcom-demo finish, or keep them as yours with wp wbcom-demo adopt.', $report['kept_sample'] ?? 0, $report['kept_setup'] ?? 0 ) );
		}
		foreach ( (array) $report['issues'] as $issue ) {
			WP_CLI::warning( $issue );
		}
		foreach ( (array) ( $report['leftovers'] ?? array() ) as $note ) {
			WP_CLI::line( $note );
		}
		foreach ( (array) $report['kept_options'] as $name ) {
			WP_CLI::line( "Kept your change to: $name" );
		}
		if ( $report['left'] || $report['failed'] || $report['issues'] ) {
			WP_CLI::error( 'Not everything you chose was removed.', false );
			WP_CLI::halt( 1 );
		}
		if ( $kept ) {
			WP_CLI::success( 'Everything you chose was removed. The items you kept are still tracked.' );
			return;
		}
		WP_CLI::success( 'Nothing from the demo was left behind.' );
	}

	/**
	 * The demos on the Starting point gallery for the active theme, and what each still needs.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo demos
	 *     wp wbcom-demo build --demo=reign-learndash --yes
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function demos( $args, $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed -- WP-CLI command signature.
		$this->as_admin();
		$rows = array_map(
			static fn( array $d ): array => array(
				'id'    => $d['id'],
				'group' => $d['group'],
				'name'  => $d['name'],
				'state' => $d['state'],
				'needs' => implode( ', ', array_column( array_filter( $d['rows'], static fn( array $r ): bool => $r['required'] && 'active' !== $r['state'] ), 'label' ) ),
			),
			\WbcomDemo\Core\Demos::overview()
		);
		if ( ! $rows ) {
			WP_CLI::log( 'No named demos for this theme. Use wp wbcom-demo build --recipe=<starting point>.' );
			return;
		}
		WP_CLI\Utils\format_items( (string) ( $assoc['format'] ?? 'table' ), $rows, array( 'id', 'group', 'name', 'state', 'needs' ) );
	}

	/**
	 * Suggest three site ideas from a description, with the AI connector set up in Settings > Connectors.
	 *
	 * ## OPTIONS
	 *
	 * <description>
	 * : The site in a sentence or two: who it is for and what people do there.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo ideas "A yoga studio with weekly classes and a members forum"
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function ideas( $args, $assoc ): void {
		$this->as_admin();
		$ideas = \WbcomDemo\Ai\Ideas::suggest( (string) ( $args[0] ?? '' ) );
		if ( is_wp_error( $ideas ) ) {
			WP_CLI::error( $ideas->get_error_message() );
		}
		if ( 'json' === ( $assoc['format'] ?? '' ) ) {
			WP_CLI::line( (string) wp_json_encode( $ideas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return;
		}
		foreach ( $ideas as $n => $idea ) {
			WP_CLI::line( sprintf( '%d. %s (%s): %s', $n + 1, $idea['name'], $idea['recipe'], $idea['tagline'] ) );
			WP_CLI::line( '   Products: ' . implode( ', ', $idea['products'] ) . ( $idea['missing'] ? ' (installs ' . implode( ', ', $idea['missing'] ) . ')' : '' ) );
			foreach ( $idea['highlights'] as $h ) {
				WP_CLI::line( '   - ' . $h );
			}
		}
		WP_CLI::line( 'Build one: wp wbcom-demo build --describe="..." --idea=<n> --yes' );
	}

	/**
	 * Install and activate free Wbcom products (and the free BuddyX theme) the same way the wizard does.
	 *
	 * Released versions only, from wbcomdesigns.com (the channel each plugin uses for its updates) or
	 * WordPress.org. Pro add-ons need a licence and are not installed here.
	 *
	 * ## OPTIONS
	 *
	 * [<ids>...]
	 * : Product ids: buddynext, mediaverse, jetonomy, learnomy, eventonomy, careerboard, listora, sellservices,
	 * mediashield, roadmap, gamification, buddyx for the theme, or addon:<folder> to switch on an uploaded Reign add-on.
	 *
	 * [--all]
	 * : Every free product in the list.
	 *
	 * [--quiet-done]
	 * : Do not print the closing success line (used when another step follows).
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo install buddynext mediaverse jetonomy
	 *     wp wbcom-demo install --all
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function install( $args, $assoc ): void {
		$this->as_admin();
		// --all: every product the installer can download (not the theme, not the ones uploaded by hand).
		$ids = isset( $assoc['all'] ) ? array_keys( array_filter( Installer::catalog(), static fn( array $e, string $id ): bool => 'buddyx' !== $id && ( isset( $e['wporg'] ) || isset( $e['item_id'] ) ), ARRAY_FILTER_USE_BOTH ) ) : array_map( static fn( string $a ): string => str_starts_with( $a, 'addon:' ) ? 'addon:' . sanitize_key( substr( $a, 6 ) ) : sanitize_key( $a ), $args );
		if ( ! $ids ) {
			WP_CLI::error( 'Name the products to install, or pass --all.' );
		}
		$this->install_ids( $ids, ! isset( $assoc['quiet-done'] ) );
	}

	/**
	 * A gallery demo for the active theme, or stop with the ids there are.
	 *
	 * @param string $id Demo id.
	 * @return array<string,mixed>
	 */
	private function demo( string $id ): array {
		$all  = \WbcomDemo\Core\Demos::overview();
		$demo = current( array_filter( $all, static fn( array $d ): bool => $d['id'] === $id ) );
		if ( ! $demo ) {
			WP_CLI::error( sprintf( 'Unknown demo "%s" for this theme. Demos: %s', $id, implode( ', ', array_column( $all, 'id' ) ) ) );
		}
		return $demo;
	}

	/**
	 * What a demo still needs and who does each part: free items with --install-missing, paid ones uploaded by the
	 * owner (with where to download them), the rest by an administrator.
	 *
	 * @param array<string,mixed> $demo      Gallery demo.
	 * @param bool                $installed The free part was just installed.
	 */
	private function needs( array $demo, bool $installed = false ): string {
		if ( $demo['conflict'] ) {
			return sprintf( 'Demo "%s" is built on a different community plugin: this site runs %s.', $demo['name'], $demo['conflict'] );
		}
		$by    = static fn( string $action ): array => array_filter( $demo['needs'], static fn( array $n ): bool => $action === $n['action'] );
		$lines = array( sprintf( 'Demo "%s" is not ready yet.', $demo['name'] ) );
		if ( $by( 'install' ) && ! $installed ) {
			$lines[] = sprintf( 'Free, installed for you with --install-missing: %s.', implode( ', ', array_column( $by( 'install' ), 'label' ) ) );
		}
		if ( $by( 'upload' ) ) {
			$lines[] = 'Paid, upload these yourself under Plugins > Add New > Upload Plugin, then run this again:';
			foreach ( $by( 'upload' ) as $n ) {
				$lines[] = sprintf( '  - %s: download it from %s', $n['label'], $n['url'] );
			}
		}
		if ( $by( 'ask' ) ) {
			$lines[] = sprintf( 'This account or host cannot install plugins; ask an administrator to add: %s.', implode( ', ', array_column( $by( 'ask' ), 'label' ) ) );
		}
		return implode( "\n", $lines );
	}

	/**
	 * Install and activate each id that is not active yet; stop on the first failure.
	 *
	 * @param string[] $ids  Catalog ids.
	 * @param bool     $done Say so when all are in (not when the demo still waits for an upload).
	 */
	private function install_ids( array $ids, bool $done = true ): void {
		$catalog = Installer::catalog();
		$changed = false;
		foreach ( $ids as $id ) {
			if ( str_starts_with( $id, 'addon:' ) ) {
				// A Reign add-on the owner uploaded: switched on as the demo card does.
				WP_CLI::log( sprintf( 'Activating %s...', substr( $id, 6 ) ) );
				$result = Installer::install( $id );
				if ( is_wp_error( $result ) ) {
					WP_CLI::error( sprintf( '%s: %s', $id, $result->get_error_message() ) );
				}
				$changed = true;
				continue;
			}
			// The whole catalog, as the demo cards use it: the site check's list hides a Reign add-on's plugin
			// until the add-on is active.
			if ( ! isset( $catalog[ $id ] ) ) {
				WP_CLI::error( sprintf( '"%s" is not a product this installer can add. Products: %s', $id, implode( ', ', array_keys( $catalog ) ) ) );
			}
			$state = Installer::status( $id )['state'];
			if ( 'active' === $state ) {
				continue;
			}
			WP_CLI::log(
				sprintf(
					'%s %s...',
					array(
						'inactive' => 'Activating',
						'outdated' => 'Updating',
					)[ $state ] ?? 'Installing',
					$id
				)
			);
			$result = Installer::install( $id );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( sprintf( '%s: %s', $id, $result->get_error_message() ) );
			}
			$changed = true;
			WP_CLI::log( sprintf( '%s is active.', $id ) );
		}
		if ( $changed ) {
			$this->settle();
		}
		if ( $done ) {
			WP_CLI::success( 'Products are ready.' );
		}
	}

	/**
	 * Let the plugins just installed finish their first-run setup: several (BuddyPress among them) do it only on an
	 * admin page load, which WP-CLI never makes. One request to wp-admin as this administrator does it, as the
	 * wizard's background page load does. A host that blocks requests to itself gets told what to do instead.
	 */
	private function settle(): void {
		$user = get_current_user_id();
		if ( ! $user ) {
			WP_CLI::warning( 'No administrator to open wp-admin as, so some new plugins may not have finished their setup. Open any wp-admin page once in the browser, then run the build again.' );
			return;
		}
		$url      = admin_url( 'index.php' );
		$secure   = 'https' === wp_parse_url( $url, PHP_URL_SCHEME );
		$expiry   = time() + 5 * MINUTE_IN_SECONDS;
		$sessions = \WP_Session_Tokens::get_instance( $user );
		$token    = $sessions->create( $expiry ); // One short session for this request, ended right after it.
		$res      = wp_remote_get(
			$url,
			array(
				'timeout'     => 60,
				'redirection' => 0,
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				'cookies'     => array(
					new \WP_Http_Cookie(
						array(
							'name'  => $secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE,
							'value' => wp_generate_auth_cookie( $user, $expiry, $secure ? 'secure_auth' : 'auth', $token ),
						)
					),
					new \WP_Http_Cookie(
						array(
							'name'  => LOGGED_IN_COOKIE,
							'value' => wp_generate_auth_cookie( $user, $expiry, 'logged_in', $token ),
						)
					),
				),
			)
		);
		$sessions->destroy( $token );
		$code = (int) wp_remote_retrieve_response_code( $res );
		// A redirect is fine when it stays in wp-admin (a plugin sending the owner to its welcome screen); one to the
		// login page, or off the admin, means the page never ran.
		$to = (string) wp_remote_retrieve_header( $res, 'location' );
		$ok = ! is_wp_error( $res ) && ( ( $code >= 200 && $code < 300 ) || ( $code >= 300 && $code < 400 && str_starts_with( $to, admin_url() ) ) );
		if ( ! $ok ) {
			WP_CLI::warning( 'Could not open wp-admin from here, so some new plugins may not have finished their setup. Open any wp-admin page once in the browser, then run the build again.' );
		}
	}


	/**
	 * Keep what the demo left (settings, pages, menus, categories) as your own and end the demo.
	 *
	 * Only after the sample content is removed. The installer stops tracking these items, so it can be
	 * deleted afterwards.
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function adopt( $args, $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WP-CLI command signature.
		$this->as_admin();
		$status = Finisher::adopt();
		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() );
		}
		WP_CLI::success( 'The demo is finished. What it set up is yours now.' );
	}

	/**
	 * Add the "Try the demo" page to a site whose demo was built before the page existed.
	 *
	 * Lists how to try each product as a member and as the site owner, with one-click account switching
	 * (User Switching). Safe to run again: it reuses the page it made.
	 *
	 * ## OPTIONS
	 *
	 * [--menu=<menu>]
	 * : Menu (id, slug or name) to add the page to, as the first item. Skipped when it is already there.
	 *
	 * [--products=<ids>]
	 * : For a demo whose content was not built by the installer: provider ids to show, comma separated.
	 *
	 * [--accounts=<list>]
	 * : Accounts for the switch cards on such a demo, as provider:login pairs, comma separated, in the product's role order. Products not named keep the accounts they had.
	 *
	 * [--launch=<url>]
	 * : The template's launch link. Signed-out visitors (the public model site) get it on every button.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo guide --menu=primary
	 *     wp wbcom-demo guide --products=listora --accounts=listora:priya_nair --menu=primary-menu --launch=https://app.instawp.io/launch?s=wb-listora
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function guide( $args, $assoc ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WP-CLI command signature.
		$this->as_admin();
		if ( isset( $assoc['products'] ) || isset( $assoc['accounts'] ) || isset( $assoc['launch'] ) ) {
			$extra = (array) get_option( Guide::OPTION, array() );
			if ( isset( $assoc['launch'] ) ) {
				$extra['launch'] = esc_url_raw( (string) $assoc['launch'] );
			}
			if ( isset( $assoc['products'] ) ) {
				$extra['products'] = array_values( array_filter( array_map( 'trim', explode( ',', (string) $assoc['products'] ) ) ) );
			}
			// Each product named in --accounts gets exactly the accounts listed for it; other products keep theirs.
			$named = array();
			foreach ( array_filter( array_map( 'trim', explode( ',', (string) ( $assoc['accounts'] ?? '' ) ) ) ) as $pair ) {
				list( $provider, $login ) = array_pad( explode( ':', $pair, 2 ), 2, '' );
				$user                     = get_user_by( 'login', $login );
				if ( ! $user ) {
					WP_CLI::error( sprintf( 'No user "%s".', $login ) );
				}
				$named[ $provider ][] = (int) $user->ID;
			}
			if ( $named ) {
				$extra['accounts'] = array_merge( (array) ( $extra['accounts'] ?? array() ), $named );
			}
			update_option( Guide::OPTION, $extra, false );
		}
		if ( ! Guide::products() ) {
			WP_CLI::error( 'No product in this demo offers a guide yet.' );
		}
		$id = Theme::guide_page();
		if ( ! $id ) {
			WP_CLI::error( 'The page could not be made.' );
		}
		Ledger::add( 'guide', 'theme', 'page', $id, 'setup' );
		Theme::hide_title( $id );
		// BuddyNext sends a member who never finished onboarding to the onboarding screen on every page; the
		// people on the cards must open straight into their account.
		if ( class_exists( '\\BuddyNext\\Onboarding\\OnboardingService' ) ) {
			$onboarding = new \BuddyNext\Onboarding\OnboardingService();
			foreach ( Guide::account_ids() as $account ) {
				$onboarding->finish( (int) $account );
			}
		}
		// A page made by the first version holds only the shortcode: give it the tour block.
		if ( false !== strpos( (string) get_post_field( 'post_content', $id ), '[' . Guide::SHORTCODE . ']' ) ) {
			wp_update_post(
				array(
					'ID'           => $id,
					'post_content' => Guide::page_content(),
				)
			);
		}
		// Sample people made before 1.1.0 have no billing details (a checkout tried as them would ask for an address)
		// and see the WordPress toolbar on the site.
		foreach ( People::roster() as $person ) {
			$user = get_user_by( 'login', 'demo.' . $person['login'] );
			if ( ! $user ) {
				continue;
			}
			if ( ! get_user_meta( $user->ID, 'billing_address_1', true ) ) {
				People::billing( (int) $user->ID, $person );
			}
			update_user_meta( $user->ID, 'show_admin_bar_front', 'false' );
		}
		if ( ! empty( $assoc['menu'] ) ) {
			$menu = wp_get_nav_menu_object( $assoc['menu'] );
			if ( ! $menu ) {
				WP_CLI::error( sprintf( 'No menu "%s".', $assoc['menu'] ) );
			}
			$items = (array) wp_get_nav_menu_items( $menu->term_id );
			if ( ! in_array( $id, array_map( static fn( $i ) => (int) $i->object_id, $items ), true ) ) {
				// Shift the others down one place so the guide is first.
				foreach ( $items as $item ) {
					if ( ! (int) $item->menu_item_parent ) {
						wp_update_post(
							array(
								'ID'         => (int) $item->ID,
								'menu_order' => (int) $item->menu_order + 1,
							)
						);
					}
				}
				wp_update_nav_menu_item(
					(int) $menu->term_id,
					0,
					array(
						'menu-item-title'     => get_the_title( $id ),
						'menu-item-status'    => 'publish',
						'menu-item-position'  => 1,
						'menu-item-object'    => 'page',
						'menu-item-object-id' => $id,
						'menu-item-type'      => 'post_type',
					)
				);
			}
		}
		WP_CLI::success( (string) get_permalink( $id ) );
	}

	/**
	 * Loop the step engine with a progress bar.
	 *
	 * @param string $label Label.
	 */
	private function run_steps( string $label ): void {
		$guard = 0;
		do {
			$status = Engine::step();
			if ( ! empty( $status['current'] ) ) {
				WP_CLI::log( sprintf( '%s: %s (%d/%d)', $label, $status['current'], $status['done'], $status['total'] ) );
			}
			// A settings step turned on something that loads only on the next request (BuddyPress components):
			// carry on in a fresh process, as --install-missing does after installing plugins.
			$state = new \WbcomDemo\Core\State();
			if ( $state->get( 'reload' ) ) {
				$state->set( 'reload', false )->save();
				$this->relaunch( 'wbcom-demo resume' );
				return;
			}
			if ( ++$guard > 5000 ) {
				WP_CLI::error( 'Stopped: too many steps.' );
			}
		} while ( in_array( $status['status'], array( 'building', 'finishing' ), true ) );
	}

	/**
	 * Carry on in a fresh WP-CLI process (new plugins and components boot only on the next request). A site with a
	 * store, a marketplace and an LMS active needs about 200 MB just to boot, so the current memory limit is passed on.
	 *
	 * @param string $command WP-CLI command and its arguments, already escaped.
	 */
	private function relaunch( string $command ): void {
		// WP-CLI's own launch keeps --path, --url and --user and this terminal; --exec runs before WordPress and the
		// plugins load, so the child starts with this process's memory limit.
		$memory = (string) ini_get( 'memory_limit' );
		WP_CLI::runcommand(
			$command . ' --exec=' . escapeshellarg( 'ini_set( "memory_limit", ' . var_export( $memory, true ) . ' );' ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- a PHP literal for --exec.
			array(
				'launch'     => true,
				'exit_error' => true,
				'return'     => false,
			)
		);
	}

	/**
	 * Run as the first administrator when no --user is given.
	 */
	private function as_admin(): void {
		if ( get_current_user_id() ) {
			return;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
			)
		);
		if ( $admins ) {
			wp_set_current_user( $admins[0]->ID );
		}
	}
}
