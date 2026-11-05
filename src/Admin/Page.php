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

	public const SLUG   = 'wbcom-demo';
	private const NOTICE = 'wbcom_demo_notice_dismissed';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'takeover' ) );
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
		wp_enqueue_style( 'wbcom-demo', WBCOM_DEMO_URL . 'assets/css/wizard.css', array(), WBCOM_DEMO_VERSION );
		wp_style_add_data( 'wbcom-demo', 'rtl', 'replace' );
		wp_enqueue_script( 'wbcom-demo', WBCOM_DEMO_URL . 'assets/js/wizard.js', array( 'wp-api-fetch', 'wp-i18n' ), WBCOM_DEMO_VERSION, true );
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
		wp_enqueue_style( 'wbcom-demo-notice', WBCOM_DEMO_URL . 'assets/css/notice.css', array(), WBCOM_DEMO_VERSION );
		wp_style_add_data( 'wbcom-demo-notice', 'rtl', 'replace' );
		wp_enqueue_script( 'wbcom-demo-notice', WBCOM_DEMO_URL . 'assets/js/notice.js', array(), WBCOM_DEMO_VERSION, true );
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
