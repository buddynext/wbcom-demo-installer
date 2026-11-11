<?php
/**
 * Appearance > Set up your site: the full-page wizard, the dashboard notice and plugin links.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Admin;

use WbcomDemo\Core\Engine;
use WbcomDemo\Core\State;
use WbcomDemo\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screens. Admins only.
 */
final class Page {

	public const SLUG    = 'wbcom-demo';
	private const NOTICE = 'wbcom_demo_notice_dismissed';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'clear_redirects' ), 0 );
		add_action( 'admin_init', array( self::class, 'takeover' ) );
		add_action( 'admin_init', array( self::class, 'to_demo_data' ) );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
		add_action( 'wp_ajax_wbcom_demo_dismiss', array( self::class, 'dismiss' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WBCOM_DEMO_FILE ), array( self::class, 'links' ) );
	}

	/**
	 * Wizard URL.
	 *
	 * @param string $view Optional view (finish).
	 */
	public static function url( string $view = '' ): string {
		$args = array( 'page' => self::SLUG );
		if ( $view ) {
			$args['view'] = $view;
		}
		return add_query_arg( $args, admin_url( 'themes.php' ) );
	}

	/**
	 * Asset version: the plugin version plus the file's change time, so a browser never keeps a stale copy
	 * after an update (or a hotfix that keeps the version).
	 *
	 * @param string $rel Path under the plugin folder.
	 */
	private static function ver( string $rel ): string {
		$time = @filemtime( WBCOM_DEMO_DIR . $rel ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file falls back to the version.
		return WBCOM_DEMO_VERSION . ( $time ? '.' . $time : '' );
	}

	/**
	 * Menu entry.
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'Set up your site', 'wbcom-demo-installer' ),
			__( 'Set up your site', 'wbcom-demo-installer' ),
			Plugin::CAP,
			self::SLUG,
			'__return_null'
		);
		// A permanent way back to what the demo left, after the wizard is closed (card 10483387455).
		add_management_page(
			__( 'Demo data', 'wbcom-demo-installer' ),
			__( 'Demo data', 'wbcom-demo-installer' ),
			Plugin::CAP,
			self::SLUG . '-data',
			'__return_null'
		);
	}

	/**
	 * Tools > Demo data opens the wizard's Finish screen, where the owner chooses what to remove.
	 */
	public static function to_demo_data(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
		if ( ! isset( $_GET['page'] ) || self::SLUG . '-data' !== $_GET['page'] || wp_doing_ajax() || ! current_user_can( Plugin::CAP ) ) {
			return;
		}
		wp_safe_redirect( self::url( 'data' ) );
		exit;
	}

	/**
	 * Plugins screen link.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Set up your site', 'wbcom-demo-installer' ) . '</a>' );
		return $links;
	}

	/**
	 * Products send the admin to their own setup wizard on the first admin page after activation. When that page
	 * is this wizard, the owner chose to set the site up here, so those one-time redirects are cleared first.
	 * Each product's own wizard stays available from its menu.
	 *
	 * Runs at plugins_loaded priority 0 (Learnomy redirects from its own plugins_loaded callback) with the
	 * built-in providers, and again at admin_init for providers that add-ons register.
	 */
	public static function clear_redirects(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
		if ( ! is_admin() || ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( self::SLUG, self::SLUG . '-data' ), true ) || wp_doing_ajax() || ! current_user_can( Plugin::CAP ) ) {
			return;
		}
		$providers = did_action( 'admin_init' ) || doing_action( 'admin_init' ) ? \WbcomDemo\Core\Registry::all() : \WbcomDemo\Core\Registry::builtin_providers();
		foreach ( $providers as $provider ) {
			$provider->clear_redirects();
		}
	}

	/**
	 * Render the wizard as a full-page takeover (no admin menus) and stop.
	 */
	public static function takeover(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
		if ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] || wp_doing_ajax() ) {
			return;
		}
		if ( ! current_user_can( Plugin::CAP ) ) {
			wp_die( esc_html__( 'Only site administrators can set up or remove demo content.', 'wbcom-demo-installer' ), 403 );
		}
		wp_enqueue_style( 'wbcom-demo', WBCOM_DEMO_URL . 'assets/css/wizard.css', array(), self::ver( 'assets/css/wizard.css' ) );
		wp_style_add_data( 'wbcom-demo', 'rtl', 'replace' );
		wp_enqueue_script( 'wbcom-demo', WBCOM_DEMO_URL . 'assets/js/wizard.js', array( 'wp-api-fetch', 'wp-i18n' ), self::ver( 'assets/js/wizard.js' ), true );
		wp_set_script_translations( 'wbcom-demo', 'wbcom-demo-installer' );
		wp_localize_script(
			'wbcom-demo',
			'wbcomDemo',
			array(
				'dashboard' => admin_url(),
				'home'      => home_url( '/' ),
				'customize' => admin_url( 'customize.php' ),
				'users'     => admin_url( 'user-new.php' ),
				'plugins'   => admin_url( 'plugins.php' ),
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'view'      => isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '',
			)
		);
		include WBCOM_DEMO_DIR . 'src/Admin/view.php';
		exit;
	}

	/**
	 * One-time dashboard notice after activation, until dismissed or a demo exists.
	 */
	public static function notice(): void {
		if ( ! current_user_can( Plugin::CAP ) || get_user_meta( get_current_user_id(), self::NOTICE, true ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'themes' ), true ) ) {
			return;
		}
		if ( State::IDLE !== ( new State() )->get( 'status' ) || ! Engine::site()['recipes'] ) {
			return;
		}
		wp_enqueue_style( 'wbcom-demo-notice', WBCOM_DEMO_URL . 'assets/css/notice.css', array(), self::ver( 'assets/css/notice.css' ) );
		wp_style_add_data( 'wbcom-demo-notice', 'rtl', 'replace' );
		wp_enqueue_script( 'wbcom-demo-notice', WBCOM_DEMO_URL . 'assets/js/notice.js', array(), self::ver( 'assets/js/notice.js' ), true );
		wp_localize_script(
			'wbcom-demo-notice',
			'wbcomDemoNotice',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'wbcom_demo_dismiss' ),
			)
		);
		?>
		<div class="notice wbcom-demo-notice" data-wbcom-demo-notice>
			<div class="wbcom-demo-notice__body">
				<p class="wbcom-demo-notice__title"><?php esc_html_e( 'See your community with sample content', 'wbcom-demo-installer' ); ?></p>
				<p><?php esc_html_e( 'Set up a believable demo of your Wbcom products with 20 sample people in a few minutes. Remove the samples whenever you like and keep your setup.', 'wbcom-demo-installer' ); ?></p>
				<p class="wbcom-demo-notice__actions">
					<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Set up your site', 'wbcom-demo-installer' ); ?></a>
					<button type="button" class="button-link" data-wbcom-demo-dismiss><?php esc_html_e( 'Not now', 'wbcom-demo-installer' ); ?></button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Dismiss the notice for this admin.
	 */
	public static function dismiss(): void {
		check_ajax_referer( 'wbcom_demo_dismiss' );
		if ( ! current_user_can( Plugin::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), self::NOTICE, 1 );
		wp_send_json_success();
	}
}
