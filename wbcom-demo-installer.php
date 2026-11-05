<?php
/**
 * Plugin Name:       Wbcom Demo Installer
 * Plugin URI:        https://github.com/buddynext/wbcom-demo-installer
 * Description:       Set up a believable demo across your Wbcom products from one screen, then remove the samples and keep your setup.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Wbcom Designs
 * Author URI:        https://wbcomdesigns.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wbcom-demo-installer
 * Domain Path:       /languages
 *
 * @package WbcomDemo
 */

defined( 'ABSPATH' ) || exit;

define( 'WBCOM_DEMO_VERSION', '1.0.0' );
define( 'WBCOM_DEMO_FILE', __FILE__ );
define( 'WBCOM_DEMO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBCOM_DEMO_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( string $class_name ): void {
		if ( ! str_starts_with( $class_name, 'WbcomDemo\\' ) ) {
			return;
		}
		$file = WBCOM_DEMO_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, 10 ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'WbcomDemo\\Core\\Ledger', 'install' ) );
// Before products that redirect to their own setup wizard from plugins_loaded; see Admin\Page::clear_redirects().
add_action( 'plugins_loaded', array( 'WbcomDemo\\Admin\\Page', 'clear_redirects' ), 0 );
add_action( 'plugins_loaded', array( 'WbcomDemo\\Plugin', 'boot' ), 20 );
