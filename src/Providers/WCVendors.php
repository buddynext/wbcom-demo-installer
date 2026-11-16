<?php
/**
 * WC Vendors: sample people with the vendor role become shops (name, about, address, phone), and the WooCommerce
 * sample products are shared out among them.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * WC Vendors provider. A shop is made through WC Vendors' own wcv_set_primary_vendor_role() and its
 * Vendors_Settings object (which also makes the shop URL from the name), and a product belongs to the vendor who
 * authors it, as WC Vendors' product "Vendor" box saves it. So the vendors list, shop pages and "Sold by" lines
 * read them natively. The free plugin has no shop banner or logo (Pro does); the list shows the person's photo.
 */
final class WCVendors extends Provider {

	/**
	 * User meta WC Vendors keeps for a shop; cleared if the person outlives the demo.
	 */
	private const STORE_META = array( 'pv_shop_name', 'pv_shop_slug', 'pv_shop_description', 'pv_seller_info', '_wcv_vendor_status', '_wcv_store_phone', '_wcv_store_address1', '_wcv_store_city', '_wcv_store_postcode', '_wcv_store_country', '_wcv_store_state' );

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'wcvendors';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WC Vendors';
	}

	/**
	 * Active and installed: WC Vendors adds its vendor role and pages on the first wp-admin request after
	 * activation, so a site activated only from WP-CLI has no vendor role yet.
	 */
	public function available(): bool {
		return class_exists( 'WCV_Vendors' ) && class_exists( 'Vendors_Settings' ) && function_exists( 'wcv_set_primary_vendor_role' ) && class_exists( 'WooCommerce' ) && null !== get_role( 'vendor' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WCV_VERSION' ) ? (string) WCV_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '2.5.0';
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
		return 76; // After WooCommerce, whose products the shops sell.
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'wcvendors_activation_redirect' );
	}

	/**
	 * Show each shop's about text above its products (off by default), and mark WC Vendors' setup wizard as done:
	 * its pages are made on activation and the demo gives the vendors their shops, so the wizard's start-up prompt
	 * would only get in the way.
	 */
	public function settings(): array {
		return array(
			'wcvendors_display_shop_description' => 'yes',
			'wcvendors_wizard_complete'          => true,
		);
	}

	/**
	 * Shops this build makes: one per store in the content, as long as there are vendors for them.
	 */
	private function store_count(): int {
		return min( count( $this->content()['stores'] ), count( People::indexes( 'vendor' ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: shops */
			__( 'A marketplace of %d vendor shops with addresses and phone numbers, selling the sample products', 'wbcom-demo-installer' ),
			$this->store_count()
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'store' => array( $this->store_count(), 'sample', __( 'vendor shops', 'wbcom-demo-installer' ) ),
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
			WooCommerce::share_products( $ctx, Ledger::ids( $this->id(), 'store' ) );
		}
	}

	/**
	 * Turn the vendors into shops.
	 *
	 * @param Context $ctx Context.
	 */
	private function stores( Context $ctx ): void {
		$vendors = $ctx->people( 'vendor' );
		foreach ( array_slice( $this->content()['stores'], 0, $this->store_count() ) as $i => $row ) {
			list( $name, , $about, $street, $city, $state, $zip, $country, $phone ) = $row;
			$user = get_userdata( (int) $vendors[ $i ] );
			if ( ! $user ) {
				continue;
			}
			wcv_set_primary_vendor_role( $user );
			$shop = new \Vendors_Settings( $user->ID, false );
			if ( ! $shop->is_valid() ) {
				$ctx->warn( $this->id(), 'shop ' . $name, __( 'WC Vendors did not make the vendor.', 'wbcom-demo-installer' ) );
				continue;
			}
			$ctx->record( $this->id(), 'store', (int) $user->ID );
			$shop->shop_name        = $name;
			$shop->shop_description = $about;
			$shop->seller_info      = $about;
			$shop->store_phone      = $phone;
			$shop->store_address    = $street;
			$shop->store_city       = $city;
			$shop->store_state      = $state;
			$shop->store_postcode   = $zip;
			$shop->store_country    = $country;
			$saved                  = $shop->save();
			if ( is_wp_error( $saved ) ) {
				$ctx->warn( $this->id(), 'shop ' . $name, $saved );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id   = (int) $row->object_id;
		$user = 'store' === $row->object_type ? get_userdata( $id ) : false;
		if ( $user ) { // Normally the sample person is removed too; clean up if they stay.
			$user->set_role( (string) get_option( 'default_role', 'subscriber' ) );
			foreach ( self::STORE_META as $key ) {
				delete_user_meta( $id, $key );
			}
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$page = (int) get_option( 'wcvendors_vendors_page_id' );
		return $page && 'publish' === get_post_status( $page )
			? array(
				array(
					'title' => __( 'Stores', 'wbcom-demo-installer' ),
					'url'   => (string) get_permalink( $page ),
				),
			)
			: array();
	}

	/**
	 * The "Try the demo" text lives in content/integrations.php, alongside the other marketplaces.
	 */
	public function guide(): array {
		return ( new Integration( 'wcvendors' ) )->guide();
	}
}
