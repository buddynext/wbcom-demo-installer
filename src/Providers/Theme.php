<?php
/**
 * Theme adapter: logo, site icon, front page and menu. The only code that writes these during a demo.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Guide;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\Registry;
use WbcomDemo\Core\Snapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Works with BuddyX, BuddyX Pro, Reign and any classic theme with nav menu locations.
 * Every option it changes goes through Snapshot so "Remove everything" can put it back.
 */
final class Theme extends Provider {

	/** Post meta on the designed home page: its layout and the core-block parts Wbcom Essential blocks replace. */
	private const HOME_META = '_wbcom_demo_home';

	/** The Wbcom Essential blocks the wizard puts on the designed home page. */
	public const ESSENTIAL = array( 'wbcom-essential/hero', 'wbcom-essential/stats-counter', 'wbcom-essential/cta' );

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
		// BuddyX and BuddyX Pro also get the light-text logo for dark mode.
		$dark   = in_array( self::theme_family(), array( 'BuddyX', 'BuddyX Pro' ), true );
		$home   = (bool) array_filter( Registry::all(), static fn( Provider $p ): bool => $p->available() && $p->offers_home() );
		$plan   = array(
			'menu' => array( 1, 'setup', __( 'menu', 'wbcom-demo-installer' ) ),
			'page' => $home ? array( 2, 'setup', __( 'home page and Try the demo page', 'wbcom-demo-installer' ) ) : array( 1, 'setup', __( 'Try the demo page', 'wbcom-demo-installer' ) ),
		);
		$brand  = self::branded() ? ( $dark ? 3 : 2 ) : 0;
		$photos = $brand + ( $home ? 1 : 0 );
		if ( $photos ) {
			$label = $brand ? ( $home ? __( 'logo, site icon and home page photo', 'wbcom-demo-installer' ) : __( 'logo and site icon', 'wbcom-demo-installer' ) ) : __( 'home page photo', 'wbcom-demo-installer' );
			$plan  = array( 'attachment' => array( $photos, 'setup', $label ) ) + $plan;
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'logo', 'home', 'guide', 'menu' );
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
			'home'  => $this->home_step( $ctx ),
			'guide' => $this->guide_step( $ctx ),
			'menu'  => $this->menu( $ctx ),
			'pages' => $this->pages( $ctx ),
			default => null,
		};
	}

	/**
	 * Whether the BuddyNext brand pack fits this site: BuddyNext is active.
	 */
	public static function branded(): bool {
		$p = Registry::get( 'buddynext' );
		return $p && $p->available();
	}

	/**
	 * Logo and site icon from the brand pack.
	 *
	 * @param Context $ctx Context.
	 */
	private function logo( Context $ctx ): void {
		// The brand pack is the BuddyNext wordmark and icon: on a site without BuddyNext (BuddyPress, PeepSo, a
		// shop, a directory) it would brand the owner's site as a product they do not use, so the header keeps the
		// site name instead.
		if ( ! self::branded() ) {
			return;
		}
		$admin = (int) get_current_user_id();
		// The sample logo is a wordmark; an owner who typed their own site name sees that name in the header instead.
		$logo = $ctx->state->get( 'own_name' ) ? 0 : $ctx->attachment( 'brand/site-logo.png', $admin, $this->id(), 'setup' );
		if ( $logo && current_theme_supports( 'custom-logo' ) ) {
			Snapshot::write( 'theme_mod:custom_logo', $logo );
			// The logo carries the name, so the site title beside it repeats it and crowds the menu. BuddyX shows
			// both unless "Display Site Title and Tagline" is off; BuddyX Pro and Reign already hide it. That core
			// checkbox stores header_textcolor 'blank' (BuddyX then hides the text visually, still read by screen
			// readers), so write that: the owner sees the box unticked and can tick it to bring the title back.
			if ( 'BuddyX' === self::theme_family() ) {
				Snapshot::write( 'theme_mod:header_textcolor', 'blank' );
			}
			// Both BuddyX themes swap to dark_mode_logo (a URL) in dark mode; without it an owner's old dark logo, or the
			// navy wordmark on a near-black header, would show there.
			if ( in_array( self::theme_family(), array( 'BuddyX', 'BuddyX Pro' ), true ) ) {
				$dark = $ctx->attachment( 'brand/site-logo-dark.png', $admin, $this->id(), 'setup' );
				if ( $dark ) {
					Snapshot::write( 'theme_mod:dark_mode_logo', (string) wp_get_attachment_url( $dark ) );
				}
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
	private function home_step( Context $ctx ): void {
		$page = $this->designed_home( $ctx );
		foreach ( $page ? array() : $this->included( $ctx ) as $p ) {
			$page = $p->front_page();
			if ( $page ) {
				break;
			}
		}
		if ( $page ) {
			Snapshot::write( 'show_on_front', 'page' );
			Snapshot::write( 'page_on_front', $page );
		}
	}

	/**
	 * The designed home page: a hero from the site's main product, its numbers, one section per product, the
	 * community's members, and a closing call to action, in core and product blocks the owner can edit.
	 * Full width. Returns the page id, or 0 when no included product offers a part of it.
	 *
	 * @param Context $ctx Context.
	 */
	private function designed_home( Context $ctx ): int {
		$hero     = array();
		$fallback = array();
		$stats    = array();
		$crowd    = array();
		$sections = array();
		$people   = array();
		foreach ( $this->included( $ctx ) as $p ) {
			$part = $p->home( $ctx );
			if ( ! $part ) {
				continue;
			}
			if ( ! empty( $part['hero'] ) ) {
				if ( $p->community() ) {
					$fallback = $fallback ? $fallback : $part['hero'];
				} else {
					$hero = $hero ? $hero : $part['hero'];
				}
			}
			if ( $p->community() ) {
				$crowd = array_merge( $crowd, (array) ( $part['stats'] ?? array() ) );
			} else {
				$stats = array_merge( $stats, (array) ( $part['stats'] ?? array() ) );
			}
			if ( $p->community() ) {
				$people = array_merge( $people, (array) ( $part['sections'] ?? array() ) );
			} else {
				$sections = array_merge( $sections, (array) ( $part['sections'] ?? array() ) );
			}
		}
		if ( ! $sections && ! $people ) {
			return 0;
		}
		$hero   = $hero ? $hero : $fallback;
		$stats  = array_merge( $stats, $crowd ); // The product's numbers lead, the community's follow.
		$admin  = (int) get_current_user_id();
		$layout = array();
		$parts  = array();
		$body   = '';
		if ( $hero ) {
			$inner          = Blocks::heading( (string) $hero['title'], 1 ) . Blocks::paragraph( (string) $hero['text'] ) . ( ! empty( $hero['buttons'] ) ? Blocks::buttons( $hero['buttons'] ) : '' );
			$photo          = ! empty( $hero['image'] ) ? $ctx->attachment( (string) $hero['image'], $admin, $this->id(), 'setup' ) : 0;
			$parts['hero']  = $photo ? Blocks::cover( $photo, $inner ) : Blocks::section( $inner );
			$layout['hero'] = array(
				'title'   => (string) $hero['title'],
				'text'    => (string) $hero['text'],
				'buttons' => array_slice( $hero['buttons'], 0, 2 ),
				'image'   => $photo ? array(
					'id'  => $photo,
					'url' => (string) wp_get_attachment_image_url( $photo, 'full' ),
					'alt' => '',
				) : null,
			);
			$body          .= $parts['hero'];
		}
		if ( $stats ) {
			// The four that matter most: products list theirs first, the community's follow.
			$stats           = array_slice( $stats, 0, 4 );
			$cols            = array_map( static fn( array $s ): string => Blocks::heading( number_format_i18n( (int) $s[0] ), 3 ) . Blocks::paragraph( (string) $s[1] ), $stats );
			$parts['stats']  = Blocks::section( Blocks::columns( $cols ) );
			$layout['stats'] = array_map( static fn( array $s ): array => array( (int) $s[0], (string) $s[1] ), $stats );
			$body           .= $parts['stats'];
		}
		foreach ( array_merge( $sections, $people ) as $section ) {
			$body .= Blocks::section( (string) $section );
		}
		if ( $hero && ! empty( $hero['buttons'] ) ) {
			$cta           = ! empty( $hero['cta'] ) ? $hero['cta'] : $hero['buttons'][0];
			$heading       = (string) ( $hero['closing'] ?? $hero['title'] );
			$parts['cta']  = Blocks::section( Blocks::heading( $heading ) . Blocks::buttons( array( $cta ) ) );
			$layout['cta'] = array( $heading, $cta );
			$body         .= $parts['cta'];
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Home', 'wbcom-demo-installer' ),
				'post_name'    => 'home',
				'post_content' => $body,
				'post_author'  => $admin,
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			$ctx->warn( $this->id(), 'home page', $id );
			return 0;
		}
		$ctx->record( $this->id(), 'page', (int) $id, 'setup' );
		self::no_sidebar( (int) $id );
		// The hero, numbers and closing are core blocks here (valid on every path, WP-CLI too). When Wbcom Essential
		// is active the wizard swaps them for its hero, stats counter and call to action, built by those blocks' own
		// code in the browser so the editor accepts them (see home_upgrade()).
		update_post_meta(
			(int) $id,
			self::HOME_META,
			array(
				'layout' => $layout,
				'parts'  => $parts,
			)
		);
		return (int) $id;
	}

	/**
	 * The designed home page still waiting for its Wbcom Essential blocks, for the wizard: page id, the core-block
	 * parts to replace and what goes in their place. Empty when done, not ours, or Wbcom Essential is not active.
	 *
	 * @return array<string,mixed>
	 */
	public static function home_upgrade(): array {
		$id   = (int) get_option( 'page_on_front' );
		$data = $id ? get_post_meta( $id, self::HOME_META, true ) : '';
		if ( ! is_array( $data ) || empty( $data['parts'] ) ) {
			return array();
		}
		$registry = \WP_Block_Type_Registry::get_instance();
		foreach ( self::ESSENTIAL as $block ) {
			if ( ! $registry->is_registered( $block ) ) {
				return array();
			}
		}
		$content = (string) get_post_field( 'post_content', $id );
		$parts   = array_filter( (array) $data['parts'], static fn( $markup ): bool => is_string( $markup ) && str_contains( $content, $markup ) );
		return $parts ? array(
			'id'     => $id,
			'parts'  => $parts,
			'layout' => $data['layout'],
		) : array();
	}

	/**
	 * The "Try the demo" page (Core\Guide): member and site-owner steps for every product in the demo, with
	 * one-click account switching. Made whenever an included product offers a guide.
	 *
	 * @param Context $ctx Context.
	 */
	private function guide_step( Context $ctx ): void {
		$offers = array_filter( $this->included( $ctx ), static fn( Provider $p ) => (bool) $p->guide() );
		if ( ! $offers ) {
			return;
		}
		$id = self::guide_page();
		if ( $id ) {
			$ctx->record( $this->id(), 'page', $id, 'setup' );
		}
	}

	/**
	 * Id of the guide page, made when missing (also used by `wp wbcom-demo guide` on a site built earlier).
	 */
	public static function guide_page(): int {
		$found = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'meta_key'    => '_wbcom_demo_guide', // phpcs:ignore WordPress.DB.SlowDBQuery -- one page, read once per build.
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
		if ( $found ) {
			return (int) $found[0];
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Try the demo', 'wbcom-demo-installer' ),
				'post_name'    => 'try-the-demo',
				'post_content' => Guide::page_content(),
				'post_author'  => (int) get_current_user_id(),
				'meta_input'   => array( '_wbcom_demo_guide' => 1 ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		self::no_sidebar( (int) $id );
		self::hide_title( (int) $id );
		return (int) $id;
	}

	/**
	 * Hide the page title on Reign: the guide page opens with its own headline, so the title would repeat it.
	 *
	 * @param int $id Page id.
	 */
	public static function hide_title( int $id ): void {
		if ( 'Reign' !== self::theme_family() ) {
			return;
		}
		$data                          = (array) get_post_meta( $id, 'reign_wbcom_metabox_data', true );
		$data['layout']['site_layout'] = 'full_width';
		$data['layout']['title_style'] = 'hidden'; // Reign 8.1+ hides the page title per page this way.
		Snapshot::write( 'post_meta:' . $id . ':reign_wbcom_metabox_data', $data );
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
		$name = __( 'Demo menu', 'wbcom-demo-installer' );
		$i    = 2;
		while ( wp_get_nav_menu_object( $name ) ) {
			/* translators: %d: number */
			$name = sprintf( __( 'Demo menu %d', 'wbcom-demo-installer' ), $i++ );
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
		$pos   = 1;
		$guide = array_map( 'intval', Ledger::ids( $this->id(), 'page' ) );
		if ( $guide ) {
			// First, so a visitor sees how to try the demo before anything else.
			wp_update_nav_menu_item(
				(int) $menu_id,
				0,
				array(
					'menu-item-title'     => __( 'Try the demo', 'wbcom-demo-installer' ),
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $pos++,
					'menu-item-object'    => 'page',
					'menu-item-object-id' => max( $guide ),
					'menu-item-type'      => 'post_type',
				)
			);
		}
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
	 * Product pages (forums, media, leaderboard, roadmap...) are apps that bring their own columns, so they
	 * get the theme's no-sidebar layout. Pages whose owner already chose a layout are left alone.
	 *
	 * @param Context $ctx Context.
	 */
	private function pages( Context $ctx ): void {
		// BuddyX offers its own demo pages on every admin screen; with this demo built, answer "No thanks" for the owner.
		if ( str_starts_with( get_template(), 'buddyx' ) ) {
			Snapshot::write( 'buddyx_demo_setup_state', 'dismissed' );
		}
		foreach ( $this->included( $ctx ) as $p ) {
			// Reign keeps a layout per archive type and falls back to a right sidebar with the default widgets.
			if ( 'Reign' === self::theme_family() ) {
				foreach ( $p->full_width_archives() as $type ) {
					Snapshot::write( 'theme_mod:reign_' . sanitize_key( $type ) . '_archive_layout', 'full_width' );
					Snapshot::write( 'theme_mod:reign_' . sanitize_key( $type ) . '_single_layout', 'full_width' );
				}
			}
			$ids = $p->layout_pages();
			foreach ( $p->menu_items() as $item ) {
				$ids[] = ! empty( $item['page_id'] ) ? (int) $item['page_id'] : url_to_postid( strtok( (string) ( $item['url'] ?? '' ), '?' ) );
			}
			foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
				if ( $id && 'page' === get_post_type( $id ) ) {
					self::no_sidebar( $id );
				}
			}
		}
	}

	/**
	 * Give one page the active theme's layout without sidebars: the "Page No Sidebar" template on
	 * BuddyX and BuddyX Pro, the full width layout in Reign's page settings. Other themes are left as they are.
	 *
	 * @param int $id Page id.
	 */
	public static function no_sidebar( int $id ): void {
		$meta     = array();
		$template = 'page-templates/full-width-container.php';
		if ( isset( wp_get_theme()->get_page_templates()[ $template ] ) ) {
			if ( in_array( (string) get_post_meta( $id, '_wp_page_template', true ), array( '', 'default' ), true ) ) {
				$meta['_wp_page_template'] = $template;
			}
		} elseif ( 'Reign' === self::theme_family() ) {
			$data = get_post_meta( $id, 'reign_wbcom_metabox_data', true );
			$data = is_array( $data ) ? $data : array();
			if ( empty( $data['layout']['site_layout'] ) ) {
				$data['layout']['site_layout']    = 'full_width';
				$meta['reign_wbcom_metabox_data'] = $data;
			}
		}
		/**
		 * Page meta that gives one product page the active theme's layout without sidebars. Another theme
		 * adds its own page setting here; every value is written through the snapshot and restored on
		 * Remove everything.
		 *
		 * @param array<string,mixed> $meta     Meta key => value; empty when the theme is not known.
		 * @param int                 $id       Page id.
		 * @param string              $template Active theme template slug.
		 */
		foreach ( (array) apply_filters( 'wbcom_demo_page_layout', $meta, $id, get_template() ) as $key => $value ) {
			Snapshot::write( 'post_meta:' . $id . ':' . $key, $value );
		}
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
			return self::delete_post( $id, true );
		}
		if ( 'menu' === $row->object_type ) {
			if ( wp_get_nav_menu_object( $id ) ) {
				wp_delete_nav_menu( $id );
			}
			return ! wp_get_nav_menu_object( $id );
		}
		if ( 'page' === $row->object_type ) {
			return self::delete_post( $id );
		}
		return true;
	}
}
