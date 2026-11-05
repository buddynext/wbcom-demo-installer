<?php
/**
 * Bootstrap: wires quiet mode, the admin wizard, REST routes and WP-CLI.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/** Capability every screen, route and command requires. */
	public const CAP = 'manage_options';

	/**
	 * Hook everything up.
	 */
	public static function boot(): void {
		Core\Ledger::maybe_upgrade();
		Core\QuietMode::init();

		if ( is_admin() ) {
			Admin\Page::init();
		}
		add_action( 'rest_api_init', array( Rest\Controller::class, 'register' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'wbcom-demo', Cli\Command::class );
		}
	}
}
