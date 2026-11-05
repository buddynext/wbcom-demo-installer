<?php
/**
 * WP-CLI: the same engine the wizard uses.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Cli;

use WbcomDemo\Core\Engine;
use WbcomDemo\Core\Finisher;
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
	 * ## EXAMPLES
	 *
	 *     wp wbcom-demo build --recipe=community
	 *     wp wbcom-demo build --include=buddynext,jetonomy --keep-look
	 *
	 * @param array<int,string>    $args  Args.
	 * @param array<string,string> $assoc Assoc args.
	 */
	public function build( $args, $assoc ): void {
		$this->as_admin();
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
		$status = Engine::start_build( $recipe, $include, isset( $assoc['keep-look'] ) ? 'keep' : 'demo' );
		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status->get_error_message() );
		}
		$this->run_steps( 'Building' );
		$status = Engine::status();
		foreach ( array_merge( (array) $status['errors'], (array) $status['warnings'] ) as $error ) {
			WP_CLI::warning( $error );
		}
		WP_CLI::success( sprintf( 'Demo built: %d items.', array_sum( array_column( $status['ledger'], 'count' ) ) ) );
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
		WP_CLI::line( sprintf( 'Removed: %d  Left: %d  Waiting: %d  Failed: %d  Sample people left: %d', $report['removed'], $report['left'], $report['waiting'], $report['failed'], $report['people_left'] ) );
		foreach ( (array) $report['issues'] as $issue ) {
			WP_CLI::warning( $issue );
		}
		foreach ( (array) $report['kept_options'] as $name ) {
			WP_CLI::line( "Kept your change to: $name" );
		}
		if ( $report['left'] || $report['failed'] || $report['issues'] ) {
			WP_CLI::error( 'Verify found leftovers.', false );
			return;
		}
		WP_CLI::success( 'Nothing from the demo was left behind.' );
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
