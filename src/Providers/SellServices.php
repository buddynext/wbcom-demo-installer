<?php
/**
 * WP Sell Services: freelancers selling services with packages, orders from buyers and reviews.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Sell Services provider. Uses VendorService, ServiceManager, the standalone order provider,
 * OrderService and ReviewService.
 */
final class SellServices extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'sellservices';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WP Sell Services';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'WPSS_VERSION' ) && class_exists( '\WPSellServices\Services\ServiceManager' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WPSS_VERSION' ) ? (string) WPSS_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.9.2';
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
		return 60;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'WPSS_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: services */
			__( '%d services from sample freelancers with packages, completed orders from buyers and reviews', 'wbcom-demo-installer' ),
			count( $this->content()['services'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'wpss_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		return array(
			'wpss_setup_wizard_completed' => time(),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'term'       => array( self::missing_terms( array( 'wpss_service_category' => array_column( $this->content()['services'], 2 ) ) ), 'setup', __( 'service categories', 'wbcom-demo-installer' ) ),
			'service'    => array( count( $this->content()['services'] ), 'sample', __( 'services', 'wbcom-demo-installer' ) ),
			'order'      => array( $this->order_count(), 'sample', __( 'orders', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $this->content()['services'] ), 'sample', __( 'service images', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * Orders orders() places: one or two per service from the buyers, never by the service's own seller.
	 */
	private function order_count(): int {
		$buyers = People::indexes( 'buyer' );
		$n      = 0;
		foreach ( array_values( $this->content()['services'] ) as $k => $s ) {
			for ( $o = 0; $o < 1 + $k % 2; $o++ ) {
				$buyer = $buyers[ ( $k + $o ) % max( 1, count( $buyers ) ) ] ?? null;
				$n    += ( null === $buyer || $buyer === $s[0] ) ? 0 : 1;
			}
		}
		return $n;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'vendors', 'orders' );
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		'orders' === $step ? $this->orders( $ctx ) : $this->vendors( $ctx );
	}

	/**
	 * WP Sell Services keeps its own copy of the site title (general > platform name, set on activation) and shows it
	 * on marketplace screens. When the build gave the site the owner's own name and that copy still holds the old
	 * title, follow the new name; a platform name the owner chose stays untouched (issue #4).
	 *
	 * @param Context $ctx Context.
	 */
	private function platform_name( Context $ctx ): void {
		$old = \WbcomDemo\Core\Snapshot::before( 'blogname' );
		if ( ! $ctx->state->get( 'own_name' ) || ! is_string( $old ) ) {
			return;
		}
		$general = get_option( 'wpss_general' );
		if ( ! is_array( $general ) || ( $general['platform_name'] ?? '' ) !== $old ) {
			return;
		}
		$general['platform_name'] = (string) get_option( 'blogname' );
		\WbcomDemo\Core\Snapshot::write( 'wpss_general', $general );
	}

	/**
	 * Vendors and their services.
	 *
	 * @param Context $ctx Context.
	 */
	private function vendors( Context $ctx ): void {
		$this->platform_name( $ctx );
		$vendors  = new \WPSellServices\Services\VendorService();
		$services = new \WPSellServices\Services\ServiceManager();
		$done     = array();
		foreach ( $this->content()['services'] as $i => $s ) {
			list( $who, $title, $cat, $base, $desc, $image ) = $s;
			$vendor = $ctx->person( $who );
			if ( ! isset( $done[ $vendor ] ) ) {
				$vendors->register( $vendor, array( 'status' => 'active' ) );
				$p = $ctx->profile( $vendor );
				if ( method_exists( $vendors, 'update_profile' ) ) {
					$vendors->update_profile(
						$vendor,
						array(
							'tagline' => $p['headline'] ?? '',
							'bio'     => sprintf( '%s based in %s.', $p['job'] ?? '', $p['location'] ?? '' ),
							'country' => substr( (string) ( $p['location'] ?? '' ), -2 ),
						)
					);
				}
				$done[ $vendor ] = true;
			}
			$term = $this->ensure_term( $cat, 'wpss_service_category', $ctx );
			// Sell Services publishes only marketplace-ready services, and that includes an image.
			$thumb = $ctx->attachment( $image, $vendor, $this->id() );
			\WbcomDemo\Core\Clock::set( $ctx->date( $i, count( $this->content()['services'] ), 70 ) );
			$id = $services->create(
				array(
					'title'      => $title,
					'content'    => wpautop( $desc ),
					'excerpt'    => $desc,
					'author'     => $vendor,
					'categories' => $term ? array( $term ) : array(),
					'packages'   => array(
						array(
							'name'          => 'Basic',
							'description'   => 'Everything you need to get started.',
							'price'         => $base,
							'delivery_days' => 3,
							'revisions'     => 1,
						),
						array(
							'name'          => 'Standard',
							'description'   => 'The popular choice.',
							'price'         => $base * 2,
							'delivery_days' => 5,
							'revisions'     => 3,
						),
						array(
							'name'          => 'Premium',
							'description'   => 'The full package with priority support.',
							'price'         => $base * 3,
							'delivery_days' => 7,
							'revisions'     => 5,
						),
					),
					'gallery'    => $thumb ? array( $thumb ) : array(),
					'status'     => 'publish',
				)
			);
			\WbcomDemo\Core\Clock::reset();
			if ( ! $id ) {
				$ctx->warn( $this->id(), 'service ' . $title, 'not created' );
				continue;
			}
			if ( 'publish' !== get_post_status( $id ) ) {
				$why = function_exists( 'wpss_get_service_publish_errors' ) ? wpss_get_service_publish_errors( (int) $id ) : array();
				$ctx->warn( $this->id(), 'service ' . $title, 'saved as ' . get_post_status( $id ) . ( $why ? ': ' . implode( ' ', $why ) : '' ) );
			}
			$date = $ctx->date( $i, count( $this->content()['services'] ), 70 );
			wp_update_post(
				array(
					'ID'            => $id,
					'post_date'     => get_date_from_gmt( $date ),
					'post_date_gmt' => $date,
					'edit_date'     => true,
				)
			);
			$ctx->record( $this->id(), 'service', (int) $id, 'sample', array( 'vendor' => $vendor ) );
		}
	}

	/**
	 * Buyers order services; most orders are completed and reviewed.
	 *
	 * @param Context $ctx Context.
	 */
	private function orders( Context $ctx ): void {
		if ( ! class_exists( '\WPSellServices\Integrations\Standalone\StandaloneOrderProvider' ) ) {
			return;
		}
		$buyers   = $ctx->people( 'buyer' );
		$provider = new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider();
		$orders   = new \WPSellServices\Services\OrderService();
		$reviews  = new \WPSellServices\Services\ReviewService();
		$texts    = array( 'Clear communication and delivered early. Would hire again.', 'Exactly what we needed, thank you!', 'Great quality and very patient with my changes.' );
		$was      = get_current_user_id();
		foreach ( Ledger::ids( $this->id(), 'service' ) as $k => $service_id ) {
			for ( $o = 0; $o < 1 + $k % 2; $o++ ) {
				$buyer = $buyers[ ( $k + $o ) % max( 1, count( $buyers ) ) ] ?? 0;
				if ( ! $buyer || (int) get_post_field( 'post_author', $service_id ) === $buyer ) {
					continue;
				}
				wp_set_current_user( $buyer );
				$order = $provider->create_order(
					array(
						'service_id'     => $service_id,
						'package_id'     => 0,
						'customer_id'    => $buyer,
						'payment_method' => 'manual',
						'subtotal'       => (float) get_post_meta( $service_id, '_wpss_starting_price', true ),
					)
				);
				if ( ! $order ) {
					$ctx->warn( $this->id(), 'order', 'not created' );
					continue;
				}
				$order_id = (int) $order->id;
				$ctx->record( $this->id(), 'order', $order_id, 'sample', array( 'buyer' => $buyer ) );
				// The natural path an order takes: paid, brief received, worked on, delivered, accepted.
				wp_set_current_user( $was );
				foreach ( array( 'pending_requirements', 'in_progress', 'pending_approval', 'completed' ) as $status ) {
					if ( ! $orders->update_status( $order_id, $status ) ) {
						$ctx->warn( $this->id(), 'order status ' . $status, 'refused' );
						break;
					}
				}
				wp_set_current_user( $buyer );
				if ( 0 === $o ) {
					$review = $reviews->create(
						$order_id,
						$buyer,
						array(
							'rating'  => 5 - $k % 2,
							'content' => $texts[ $k % count( $texts ) ],
						)
					);
					if ( ! $review ) {
						$ctx->warn( $this->id(), 'review', 'not created' );
					}
				}
			}
		}
		wp_set_current_user( $was );
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		switch ( $row->object_type ) {
			case 'term':
				return self::remove_unused_term( $id, 'wpss_service_category' );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'service':
				return self::delete_post( $id );
			case 'order':
				// Orders are removed with the buyer (Sell Services cascades orders, reviews and wallet rows on user delete).
				return true;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'term' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$url = function_exists( 'wpss_get_page_url' ) ? (string) wpss_get_page_url( 'services_page' ) : '';
		return $url
			? array(
				array(
					'title' => __( 'Services', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
			)
			: array();
	}
}
