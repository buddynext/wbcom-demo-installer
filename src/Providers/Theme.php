<?php
/**
 * Theme adapter: logo, site icon, front page and menu. The only code that writes these during a demo.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\Registry;
use WbcomDemo\Core\Snapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Works with BuddyX, BuddyX Pro, Reign and any classic theme with nav menu locations.
 * Every option it changes goes through Snapshot so "Remove everything" can put it back.
 */
final class Theme extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'theme';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return self::theme_family();
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return __( 'Logo, home page and menu', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 1000;
	}

	/**
	 * Which adapter applies.
	 */
	public static function theme_family(): string {
		$template = get_template();
		return match ( true ) {
			str_starts_with( $template, 'buddyx-pro' ), 'buddyxpro' === $template => 'BuddyX Pro',
			str_starts_with( $template, 'buddyx' ) => 'BuddyX',
			str_starts_with( $template, 'reign' ) => 'Reign',
			wp_is_block_theme() => __( 'Block theme', 'wbcom-demo-installer' ),
			default => __( 'Classic theme', 'wbcom-demo-installer' ),
		};
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'attachment' => array( 2, 'setup', __( 'logo and site icon', 'wbcom-demo-installer' ) ),
			'menu'       => array( 1, 'setup', __( 'menu', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'logo', 'home', 'menu' );
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		match ( $step ) {
			'logo'  => $this->logo( $ctx ),
			'home'  => $this->home( $ctx ),
			'menu'  => $this->menu( $ctx ),
			default => null,
		};
	}

	/**
	 * Logo and site icon from the brand pack.
	 *
	 * @param Context $ctx Context.
	 */
	private function logo( Context $ctx ): void {
		$admin = (int) get_current_user_id();
		$logo  = $ctx->attachment( 'brand/site-logo.png', $admin, $this->id(), 'setup' );
		if ( $logo && current_theme_supports( 'custom-logo' ) ) {
			Snapshot::write( 'theme_mod:custom_logo', $logo );
			// The logo carries the name, so the site title beside it repeats it and crowds the menu. BuddyX shows
			// both unless its "Display Site Title and Tagline" setting is off; BuddyX Pro and Reign already hide it.
			if ( 'BuddyX' === self::theme_family() ) {
				Snapshot::write( 'theme_mod:display_header_text', false );
			}
		}
		$icon = $ctx->attachment( 'brand/site-icon.png', $admin, $this->id(), 'setup' );
		if ( $icon ) {
			Snapshot::write( 'site_icon', $icon );
		}
	}

	/**
	 * Front page: the first included product that offers one.
	 *
	 * @param Context $ctx Context.
	 */
	private function home( Context $ctx ): void {
		foreach ( $this->included( $ctx ) as $p ) {
			$page = $p->front_page();
			if ( $page ) {
				Snapshot::write( 'show_on_front', 'page' );
				Snapshot::write( 'page_on_front', $page );
				return;
			}
		}
	}

	/**
	 * One menu with every included product's pages, assigned to the theme's primary and mobile locations.
	 *
	 * @param Context $ctx Context.
	 */
	private function menu( Context $ctx ): void {
		$locations = $this->locations();
		if ( ! $locations ) {
			return;
		}
		$name = __( 'Community menu', 'wbcom-demo-installer' );
		$i    = 2;
		while ( wp_get_nav_menu_object( $name ) ) {
			/* translators: %d: number */
			$name = sprintf( __( 'Community menu %d', 'wbcom-demo-installer' ), $i++ );
		}
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return;
		}
		$ctx->record( $this->id(), 'menu', (int) $menu_id, 'setup' );
		// Themes hook wp_update_nav_menu_item and call get_current_screen() unguarded (BuddyX Pro menu
		// icons, card 10480406536), which is undefined in a REST request. Loading the screen API makes it return null.
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		$pos = 1;
		foreach ( $this->included( $ctx ) as $p ) {
			foreach ( $p->menu_items() as $item ) {
				$args = array(
					'menu-item-title'    => $item['title'],
					'menu-item-status'   => 'publish',
					'menu-item-position' => $pos++,
				);
				if ( ! empty( $item['page_id'] ) ) {
					$args += array(
						'menu-item-object'    => 'page',
						'menu-item-object-id' => (int) $item['page_id'],
						'menu-item-type'      => 'post_type',
					);
				} else {
					$args += array(
						'menu-item-url'  => $item['url'],
						'menu-item-type' => 'custom',
					);
				}
				wp_update_nav_menu_item( (int) $menu_id, 0, $args );
			}
		}
		$assigned = (array) get_theme_mod( 'nav_menu_locations', array() );
		foreach ( $locations as $loc ) {
			$assigned[ $loc ] = (int) $menu_id;
		}
		Snapshot::write( 'theme_mod:nav_menu_locations', $assigned );
	}

	/**
	 * Primary and mobile menu locations of the active theme.
	 *
	 * @return string[]
	 */
	private function locations(): array {
		$out = array();
		// Match on the slug only: labels such as "Primary - Logged in users" contain words the
		// exclusions would catch.
		foreach ( array_keys( get_registered_nav_menus() ) as $slug ) {
			if ( preg_match( '/primary|main|mobile|header|^menu-1/i', $slug ) && ! preg_match( '/footer|panel|social|user_menu|community/i', $slug ) ) {
				$out[] = $slug;
			}
		}
		return (array) apply_filters( 'wbcom_demo_menu_locations', $out, get_template() );
	}

	/**
	 * Included providers in build order.
	 *
	 * @param Context $ctx Context.
	 * @return Provider[]
	 */
	private function included( Context $ctx ): array {
		return array_filter( Registry::all(), static fn( Provider $p ) => $ctx->includes( $p->id() ) && $p->available() );
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		if ( 'attachment' === $row->object_type ) {
			if ( get_post( $id ) ) {
				wp_delete_attachment( $id, true );
			}
			return true;
		}
		if ( 'menu' === $row->object_type ) {
			if ( wp_get_nav_menu_object( $id ) ) {
				wp_delete_nav_menu( $id );
			}
			return true;
		}
		if ( 'page' === $row->object_type ) {
			if ( get_post( $id ) ) {
				wp_delete_post( $id, true );
			}
			return true;
		}
		return true;
	}
}
