<?php
/**
 * WP-CLI: the same engine the wizard uses.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Cli;

use WbcomDemo\Core\Engine;
use WbcomDemo\Core\Finisher;
use WbcomDemo\Core\Installer;
use WbcomDemo\Core\Recipes;
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
	 * [--keep-look]
	 * : Do not change the home page, menu or logo.
	 *
	 * [--dry-run]
	 * : Show what would be created.
	 *
	 * [--yes]
	 * : Build even though the site already has members or content (the demo is made for a fresh site).
	 *
	 * [--install-missing]
	 * : First install and activate the free Wbcom products the starting point uses that are not active yet.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo build --recipe=community
	 *     wp wbcom-demo build --include=buddynext,jetonomy --keep-look
	 *
	 * @param array<int,string>         $args  Args.
	 * @param array<string,string|bool> $assoc Assoc args.
	 */
	public function build( $args, $assoc ): void {
		$this->as_admin();
		if ( isset( $assoc['install-missing'] ) ) {
			$recipe = (string) ( $assoc['recipe'] ?? 'community' );
			$all    = \WbcomDemo\Core\Recipes::all();
			if ( ! isset( $all[ $recipe ] ) ) {
				WP_CLI::error( sprintf( 'Unknown starting point "%s". Starting points: %s', $recipe, implode( ', ', array_keys( $all ) ) ) );
			}
			$want = isset( $assoc['include'] ) ? array_filter( array_map( 'trim', explode( ',', $assoc['include'] ) ) ) : array_merge( Installer::CORE, $all[ $recipe ]['use'], $all[ $recipe ]['needs'] );
			$this->install_ids( array_values( array_unique( $want ) ) );
			// New plugins boot on the next request, so the build runs in a fresh process.
			unset( $assoc['install-missing'] );
			$flags = '';
			foreach ( $assoc as $k => $v ) {
				// WP-CLI passes a bare flag as true.
				$flags .= ! is_string( $v ) || '' === $v ? " --$k" : ' --' . $k . '=' . escapeshellarg( $v );
			}
			WP_CLI::runcommand(
				'wbcom-demo build' . $flags,
				array(
					'launch'     => true,
					'exit_error' => true,
				)
			);
			return;
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
		$status = Engine::start_build( $recipe, $include, isset( $assoc['keep-look'] ) ? 'keep' : 'demo', isset( $assoc['yes'] ) );
		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() . ( 'wbcom_demo_live_site' === $status->get_error_code() ? ' Add --yes to build anyway.' : '' ) );
		}
		$this->run_steps( 'Building' );
		$status   = Engine::status();
		$problems = array_merge( (array) $status['errors'], (array) $status['warnings'] );
		foreach ( $problems as $problem ) {
			WP_CLI::warning( $problem );
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
	 * Install and activate free Wbcom products (and the free BuddyX theme) the same way the wizard does.
	 *
	 * Released versions only, from wbcomdesigns.com (the channel each plugin uses for its updates) or
	 * WordPress.org. Pro add-ons need a licence and are not installed here.
	 *
	 * ## OPTIONS
	 *
	 * [<ids>...]
	 * : Product ids: buddynext, mediaverse, jetonomy, learnomy, eventonomy, careerboard, listora, sellservices,
	 * mediashield, roadmap, gamification, or buddyx for the theme.
	 *
	 * [--all]
	 * : Every free product in the list.
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
		$ids = isset( $assoc['all'] ) ? array_values( array_diff( array_keys( Installer::catalog() ), array( 'buddyx' ) ) ) : array_map( 'sanitize_key', $args );
		if ( ! $ids ) {
			WP_CLI::error( 'Name the products to install, or pass --all.' );
		}
		$this->install_ids( $ids );
	}

	/**
	 * Install and activate each id that is not active yet; stop on the first failure.
	 *
	 * @param string[] $ids Catalog ids.
	 */
	private function install_ids( array $ids ): void {
		$states = array_column( Installer::overview(), 'state', 'id' );
		foreach ( $ids as $id ) {
			if ( ! isset( $states[ $id ] ) ) {
				WP_CLI::error( sprintf( '"%s" is not a product this installer can add. Products: %s', $id, implode( ', ', array_keys( Installer::catalog() ) ) ) );
			}
			if ( 'active' === $states[ $id ] ) {
				continue;
			}
			WP_CLI::log( sprintf( '%s %s...', 'inactive' === $states[ $id ] ? 'Activating' : 'Installing', $id ) );
			$done = Installer::install( $id );
			if ( is_wp_error( $done ) ) {
				WP_CLI::error( sprintf( '%s: %s', $id, $done->get_error_message() ) );
			}
			WP_CLI::log( sprintf( '%s is active.', $id ) );
		}
		WP_CLI::success( 'Products are ready.' );
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
			if ( ++$guard > 5000 ) {
				WP_CLI::error( 'Stopped: too many steps.' );
			}
		} while ( in_array( $status['status'], array( 'building', 'finishing' ), true ) );
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
