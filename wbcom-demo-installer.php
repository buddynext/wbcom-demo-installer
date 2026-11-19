<?php
/**
 * Plugin Name:       Wbcom Demo Installer
 * Plugin URI:        https://github.com/buddynext/wbcom-demo-installer
 * Description:       Set up a believable demo across your Wbcom products from one screen, then remove the samples and keep your setup.
 * Version:           1.2.0
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

define( 'WBCOM_DEMO_VERSION', '1.2.0' );
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

/**
 * Where the demo stands, for a theme's or plugin's own screens: status (idle, building, built, finishing or
 * finished), demo_id (gallery demo id), demo_label, built_at and finished_at (UTC), samples_left (sample items
 * still on the site; 0 = taken live). Read this, not the wbcom_demo_state option.
 *
 * @return array{status:string,demo_id:string,demo_label:string,built_at:string,finished_at:string,samples_left:int}
 */
function wbcom_demo_status(): array {
	return \WbcomDemo\Core\State::summary();
}

/**
 * The demos for the active theme, for a theme's own setup screen: featured cards first, each with id, name, desc,
 * group, purpose, featured, state (ready, fixable, blocked or conflict), image, preview, film (id, length) and url (opens
 * the wizard on it); plus go_live, the film on taking a demo site live. Render tiles from this, not a copied list.
 *
 * @return array{demos:array<int,array<string,mixed>>,go_live:array{id:string,length:string}}
 */
function wbcom_demo_list(): array {
	return \WbcomDemo\Core\Demos::listing();
}
