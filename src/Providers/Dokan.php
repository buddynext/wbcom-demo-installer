<?php
/**
 * Dokan: sample people with the vendor role become stores (name, address, phone, photo, banner, about), and
 * the WooCommerce sample products are shared out among them. Replaces the guide-only integration of the same
 * id and keeps its "Try the demo" text.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Dokan provider. Vendors are made through Dokan's own dokan_user_update_to_seller() and Vendor object, so the
 * store list, store pages and "Sold by" lines read them as Dokan does. Dokan needs WooCommerce, so the
 * WooCommerce provider (which runs first) supplies the products.
 */
final class Dokan extends Provider {

	/**
	 * User meta Dokan keeps for a store; cleared if the person outlives the demo.
	 */
	private const STORE_META = array( 'dokan_profile_settings', 'dokan_store_name', 'dokan_enable_selling', 'dokan_publishing', 'dokan_feature_seller' );

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'dokan';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Dokan';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return function_exists( 'dokan' ) && function_exists( 'dokan_user_update_to_seller' ) && class_exists( 'WooCommerce' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'DOKAN_PLUGIN_VERSION' ) ? (string) DOKAN_PLUGIN_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '3.0.0';
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
	 * Stores this build makes: one per store in the content, as long as there are vendors for them.
	 */
	private function store_count(): int {
		return min( count( $this->content()['stores'] ), count( People::indexes( 'vendor' ) ) );
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
		$n = $this->store_count();
		return array(
			'store'      => array( $n, 'sample', __( 'stores', 'wbcom-demo-installer' ) ),
			'attachment' => array( $n, 'sample', __( 'store banners', 'wbcom-demo-installer' ) ),
		);
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
			$this->stores( $ctx );
		} elseif ( 'products' === $step ) {
			$this->products( $ctx );
		}
	}

	/**
	 * Turn the vendors into stores.
	 *
	 * @param Context $ctx Context.
	 */
	private function stores( Context $ctx ): void {
		$vendors = $ctx->people( 'vendor' );
		foreach ( array_slice( $this->content()['stores'], 0, $this->store_count() ) as $i => $row ) {
			list( $name, $slug, $about, $street, $city, $state, $zip, $country, $phone, $banner ) = $row;
			$user = get_userdata( (int) $vendors[ $i ] );
			if ( ! $user ) {
				continue;
			}
			$address = array(
				'street_1' => $street,
				'street_2' => '',
				'city'     => $city,
				'zip'      => $zip,
				'country'  => $country,
				'state'    => $state,
			);
			dokan_user_update_to_seller(
				$user,
				array(
					'fname'    => $user->first_name,
					'lname'    => $user->last_name,
					'shopurl'  => $slug,
					'shopname' => $name,
					'phone'    => $phone,
					'address'  => $address,
				)
			);
			$vendor = dokan()->vendor->get( $user->ID );
			if ( ! $vendor || ! $vendor->get_id() ) {
				$ctx->warn( $this->id(), 'store ' . $name, __( 'Dokan did not make the store.', 'wbcom-demo-installer' ) );
				continue;
			}
			$ctx->record( $this->id(), 'store', (int) $user->ID );
			$photo = (int) get_user_meta( $user->ID, People::AVATAR, true );
			if ( $photo ) {
				$vendor->set_gravatar_id( $photo );
			}
			$cover = $ctx->attachment( $banner, (int) $user->ID, $this->id() );
			if ( $cover ) {
				$vendor->set_banner_id( $cover );
			}
			$vendor->set_show_email( 'no' );
			$vendor->save();
			$vendor->make_active();
			// The About tab reads the biography from the profile settings, which have no setter.
			$settings                     = (array) get_user_meta( $user->ID, 'dokan_profile_settings', true );
			$settings['vendor_biography'] = wpautop( $about );
			update_user_meta( $user->ID, 'dokan_profile_settings', $settings );
		}
	}

	/**
	 * Give each store the WooCommerce sample products of its category.
	 *
	 * @param Context $ctx Context.
	 */
	private function products( Context $ctx ): void {
		WooCommerce::share_products( $ctx, Ledger::ids( $this->id(), 'store' ) );
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
					foreach ( self::STORE_META as $key ) {
						delete_user_meta( $id, $key );
					}
				}
				return true;
			case 'attachment':
				return self::delete_post( $id, true );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$pages = (array) get_option( 'dokan_pages', array() );
		$url   = ! empty( $pages['store_listing'] ) ? (string) get_permalink( (int) $pages['store_listing'] ) : '';
		return $url
			? array(
				array(
					'title' => __( 'Stores', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
			)
			: array();
	}

	/**
	 * The "Try the demo" text stays in content/integrations.php, shared with the guide-only integration.
	 */
	public function guide(): array {
		return ( new Integration( 'dokan' ) )->guide();
	}
}
