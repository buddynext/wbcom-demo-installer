<?php
/**
 * WCFM Marketplace: sample people with the vendor role become stores (name, address, phone, photo, banner, about),
 * and the WooCommerce sample products are shared out among them. Adds a Stores page when the site has none.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * WCFM provider. WCFM has no function that makes a store, so a store is made the way its own "Add vendor" form
 * does it (the wcfm_vendor role plus the store meta that form saves), and products are given to stores the way
 * its product "Store" box does (post author plus the store's category list), so the store list, store pages and
 * "Sold by" lines read them natively. WCFM needs WooCommerce, whose provider runs first and supplies the products.
 */
final class WCFM extends Provider {

	/**
	 * User meta WCFM keeps for a store; cleared if the person outlives the demo.
	 */
	private const STORE_META = array( 'wcfmmp_profile_settings', 'store_name', 'wcfmmp_store_name', '_store_description', '_wcfmmp_shipping', '_wcfmmp_avg_review_rating', '_wcfmmp_profile_id', '_wcfmmp_unique_id', 'wcfm_register_on', 'wcfm_register_member', '_store_setup', '_wcfm_vendor_tax_migrated', '_wcfm_street_1', '_wcfm_street_2', '_wcfm_city', '_wcfm_zip', '_wcfm_country', '_wcfm_state' );

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'wcfm';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WCFM Marketplace';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return function_exists( 'wcfmmp_get_store_url' ) && function_exists( 'get_wcfm_url' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WCFMmp_VERSION' ) ? (string) WCFMmp_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '3.6.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'store';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 76; // After WooCommerce, whose products the stores sell.
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( '_wcfm_activation_redirect', '_wcfmmp_activation_redirect' );
	}

	/**
	 * Stores this build makes: one per store in the content, as long as there are vendors for them.
	 */
	private function store_count(): int {
		return min( count( $this->content()['stores'] ), count( People::indexes( 'vendor' ) ) );
	}

	/**
	 * The published page that lists the stores ([wcfm_stores]), 0 when the site has none. WCFM does not make one.
	 */
	public static function stores_page(): int {
		$pages = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				's'           => '[wcfm_stores',
				'numberposts' => 5,
			)
		);
		foreach ( $pages as $page ) {
			if ( has_shortcode( $page->post_content, 'wcfm_stores' ) ) {
				return (int) $page->ID;
			}
		}
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: stores */
			__( 'A marketplace of %d stores with photos, banners and addresses, selling the sample products', 'wbcom-demo-installer' ),
			$this->store_count()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$n    = $this->store_count();
		$plan = array(
			'store'      => array( $n, 'sample', __( 'stores', 'wbcom-demo-installer' ) ),
			'attachment' => array( $n, 'sample', __( 'store banners', 'wbcom-demo-installer' ) ),
		);
		if ( ! self::stores_page() ) {
			$plan['page'] = array( 1, 'setup', __( 'Stores page', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'stores', 'products' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $step See parent.
	 * @param Context $ctx  See parent.
	 */
	public function run( string $step, Context $ctx ): void {
		if ( 'stores' === $step ) {
			$this->page( $ctx );
			$this->stores( $ctx );
		} elseif ( 'products' === $step ) {
			$this->products( $ctx );
		}
	}

	/**
	 * The Stores page, when the site has none.
	 *
	 * @param Context $ctx Context.
	 */
	private function page( Context $ctx ): void {
		if ( self::stores_page() ) {
			return;
		}
		$page = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Stores', 'wbcom-demo-installer' ),
				'post_name'    => 'stores',
				'post_content' => '<!-- wp:shortcode -->[wcfm_stores has_map="no" has_radius="no"]<!-- /wp:shortcode -->',
			),
			true
		);
		if ( is_wp_error( $page ) ) {
			$ctx->warn( $this->id(), 'Stores page', $page );
			return;
		}
		$ctx->record( $this->id(), 'page', (int) $page, 'setup' );
	}

	/**
	 * Turn the vendors into stores, saving what WCFM's own "Add vendor" form saves.
	 *
	 * @param Context $ctx Context.
	 */
	private function stores( Context $ctx ): void {
		$vendors = $ctx->people( 'vendor' );
		foreach ( array_slice( $this->content()['stores'], 0, $this->store_count() ) as $i => $row ) {
			list( $name, , $about, $street, $city, $state, $zip, $country, $phone, $banner ) = $row;
			$user = get_userdata( (int) $vendors[ $i ] );
			if ( ! $user ) {
				continue;
			}
			// The store URL is /store/<nicename>; the nicename stays as it is, since the person's profile links and
			// @mentions elsewhere on the site use it too.
			$user->set_role( 'wcfm_vendor' );
			if ( ! in_array( 'wcfm_vendor', (array) get_userdata( $user->ID )->roles, true ) ) {
				$ctx->warn( $this->id(), 'store ' . $name, __( 'WCFM did not make the store.', 'wbcom-demo-installer' ) );
				continue;
			}
			$ctx->record( $this->id(), 'store', (int) $user->ID );
			$cover   = $ctx->attachment( $banner, (int) $user->ID, $this->id() );
			$address = array(
				'street_1' => $street,
				'street_2' => '',
				'city'     => $city,
				'zip'      => $zip,
				'country'  => $country,
				'state'    => $state,
			);
			update_user_meta(
				$user->ID,
				'wcfmmp_profile_settings',
				array(
					'store_name'       => $name,
					'store_email'      => $user->user_email,
					'phone'            => $phone,
					'address'          => $address,
					'gravatar'         => (int) get_user_meta( $user->ID, People::AVATAR, true ),
					'banner_type'      => 'single_img',
					'banner'           => $cover,
					'list_banner_type' => 'single_img',
					'list_banner'      => $cover,
					'shop_description' => wpautop( $about ),
					// Sample addresses end in .invalid and there is no map key on a fresh site.
					'store_hide_email' => 'yes',
					'store_hide_map'   => 'yes',
				)
			);
			// WCFM's own profile photo (its dashboard header and welcome box read it): the person's photo.
			global $wpdb;
			update_user_meta( $user->ID, $wpdb->get_blog_prefix() . 'user_avatar', (int) get_user_meta( $user->ID, People::AVATAR, true ) );
			update_user_meta( $user->ID, 'store_name', $name );
			update_user_meta( $user->ID, 'wcfmmp_store_name', $name );
			update_user_meta( $user->ID, '_store_description', wpautop( $about ) );
			foreach ( $address as $field => $value ) {
				update_user_meta( $user->ID, '_wcfm_' . $field, $value );
			}
			update_user_meta(
				$user->ID,
				'_wcfmmp_shipping',
				array(
					'_wcfmmp_user_shipping_enable' => 'yes',
					'_wcfmmp_user_shipping_type'   => 'by_zone',
				)
			);
			update_user_meta( $user->ID, '_wcfmmp_avg_review_rating', 0 );
			update_user_meta( $user->ID, '_wcfmmp_profile_id', $user->ID );
			update_user_meta( $user->ID, '_wcfmmp_unique_id', time() );
			update_user_meta( $user->ID, 'wcfm_register_on', strtotime( 'midnight', (int) strtotime( $ctx->date( $i, 12, 60 ) . ' UTC' ) ) );
			update_user_meta( $user->ID, 'wcfm_register_member', 'yes' );
			// The store is set up already, so the vendor is not sent to WCFM's store setup wizard on first visit.
			update_user_meta( $user->ID, '_store_setup', 'yes' );
		}
	}

	/**
	 * Give each store the WooCommerce sample products of its category and list those categories on the store, as
	 * WCFM's product "Store" box does.
	 *
	 * @param Context $ctx Context.
	 */
	private function products( Context $ctx ): void {
		global $WCFMmp; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		$wcfm = $WCFMmp->wcfmmp_vendor ?? null; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
		foreach ( WooCommerce::share_products( $ctx, Ledger::ids( $this->id(), 'store' ) ) as $product_id => $store ) {
			if ( ! $wcfm ) {
				continue;
			}
			$wcfm->wcfmmp_reset_vendor_taxonomy( $store, $product_id );
			foreach ( (array) wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) ) as $term ) {
				$wcfm->wcfmmp_save_vendor_taxonomy( $store, $product_id, (int) $term );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		switch ( $row->object_type ) {
			case 'store':
				$user = get_userdata( $id );
				if ( $user ) { // Normally the sample person is removed too; clean up if they stay.
					$user->set_role( (string) get_option( 'default_role', 'subscriber' ) );
					global $wpdb;
					foreach ( array_merge( self::STORE_META, array( $wpdb->get_blog_prefix() . 'user_avatar' ) ) as $key ) {
						delete_user_meta( $id, $key );
					}
				}
				return true;
			case 'attachment':
				return self::delete_post( $id, true );
			case 'page':
				return self::delete_post( $id );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$page = self::stores_page();
		return $page
			? array(
				array(
					'title' => __( 'Stores', 'wbcom-demo-installer' ),
					'url'   => (string) get_permalink( $page ),
				),
			)
			: array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function offers_home(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The marketplace's hero, store count and store cards. WCFM has no blocks, so the cards are core blocks; the
	 * call to action is WCFM's own vendor registration page.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		$page   = self::stores_page();
		$reg    = (int) get_option( 'wcfm_vendor_registration_page_id' );
		$sell   = $reg && 'publish' === get_post_status( $reg ) ? (string) get_permalink( $reg ) : '';
		$stores = array();
		foreach ( Ledger::ids( $this->id(), 'store' ) as $id ) {
			$profile  = (array) get_user_meta( (int) $id, 'wcfmmp_profile_settings', true );
			$stores[] = array(
				'name'   => (string) ( $profile['store_name'] ?? get_user_meta( (int) $id, 'store_name', true ) ),
				'url'    => (string) wcfmmp_get_store_url( (int) $id ),
				'about'  => (string) ( $profile['shop_description'] ?? '' ),
				'banner' => (int) ( $profile['banner'] ?? 0 ),
			);
		}
		return WooCommerce::marketplace_home( (array) ( $this->content()['home'] ?? array() ), $stores, $page ? (string) get_permalink( $page ) : '', $sell );
	}

	/**
	 * The "Try the demo" text lives in content/integrations.php, alongside the other marketplaces.
	 */
	public function guide(): array {
		return ( new Integration( 'wcfm' ) )->guide();
	}
}
