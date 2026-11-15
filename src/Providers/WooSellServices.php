<?php
/**
 * Woo Sell Services: a "Try the demo" guide for a site that already has its services and orders. Builds nothing.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Guide-only provider (no sample content), so the wizard does not offer it.
 */
final class WooSellServices extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'woosellservices';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return __( 'Woo Sell Services', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'WOO_SELL_PLUGIN_VERSION' ) && function_exists( 'wc_get_page_permalink' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WOO_SELL_PLUGIN_VERSION' ) ? (string) WOO_SELL_PLUGIN_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return __( 'A guided tour of the services, orders and conversations already on this site', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array();
	}

	/**
	 * Nothing to build.
	 *
	 * @param Context $ctx Build context.
	 * @return string[]
	 */
	public function steps( Context $ctx ): array {
		return array();
	}

	/**
	 * Nothing to build.
	 *
	 * @param string  $step Step key.
	 * @param Context $ctx  Build context.
	 */
	public function run( string $step, Context $ctx ): void {}

	/**
	 * Nothing recorded, so nothing to remove.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		$t = $this->content()['guide'] ?? array();
		if ( ! $t || ! $this->available() ) {
			return array();
		}
		// The best-selling service, so the order step opens a real product page.
		$top   = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 1,
				'orderby' => 'popularity',
			)
		);
		$shop  = (string) wc_get_page_permalink( 'shop' );
		$admin = static fn( string $path ): string => admin_url( $path );
		return array(
			'name'         => $t['name'],
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => array(
				array(
					'key'    => 'customer',
					'user'   => People::user_id( 'buyer' ),
					'label'  => $t['roles']['customer'][0],
					'points' => $t['roles']['customer'][1],
				),
			),
			'member'       => self::guide_steps(
				$t['member'],
				array(
					'browse' => $shop,
					'order'  => $top ? (string) get_permalink( $top[0]->get_id() ) : null,
					'track'  => (string) wc_get_endpoint_url( 'service-orders', '', wc_get_page_permalink( 'myaccount' ) ),
				),
				array(
					'browse' => array(
						'public' => true,
						'as'     => 'customer',
					),
					'order'  => array(
						'public'    => true,
						'as'        => 'customer',
						'highlight' => true,
					),
					'track'  => array( 'as' => 'customer' ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'orders'        => $admin( 'admin.php?page=wc-orders' ),
					'conversations' => $admin( 'admin.php?page=wss-conversations' ),
					'services'      => $admin( 'edit.php?post_type=product' ),
					'settings'      => $admin( 'admin.php?page=woo-sell-services' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}
}
