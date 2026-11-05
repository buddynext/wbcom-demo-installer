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

	/** [ vendor index, title, category, base price, description ]. */
	private const SERVICES = array(
		array( 0, 'I will design a clear, friendly onboarding flow for your app', 'Design', 300, 'Research-led onboarding design: I review your current flow, interview two of your users and deliver Figma screens your developers can build straight away.' ),
		array( 3, 'I will draw a custom illustration set for your brand', 'Illustration', 220, 'A set of six illustrations in one consistent style, delivered as SVG and PNG, ready for your site, app and social posts.' ),
		array( 8, 'I will rewrite your product copy in plain words', 'Writing', 150, 'Up to ten screens or one landing page, rewritten so people understand it on first read. Includes a short voice guide.' ),
		array( 13, 'I will design a logo and simple brand kit', 'Branding', 350, 'Three logo directions, one refined final logo, colours and type pairings, and a one-page brand guide.' ),
		array( 7, 'I will set up a fast, reliable Go API for your product', 'Development', 600, 'A small, well-tested REST API in Go with docs, deployment notes and a week of support after handover.' ),
		array( 17, 'I will run sound and AV for your meetup or conference', 'Events', 250, 'Mics, speakers, mixing and recording for events up to 300 people. Setup and teardown included.' ),
	);

	public function id(): string {
		return 'sellservices';
	}

	public function label(): string {
		return 'WP Sell Services';
	}

	public function available(): bool {
		return defined( 'WPSS_VERSION' ) && class_exists( '\WPSellServices\Services\ServiceManager' );
	}

	public function version(): string {
		return defined( 'WPSS_VERSION' ) ? (string) WPSS_VERSION : '';
	}

	public function min_version(): string {
		return '1.9.2';
	}

	public function icon(): string {
		return 'store';
	}

	public function order(): int {
		return 60;
	}

	public function pro_label(): string {
		return defined( 'WPSS_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	public function summary(): string {
		return sprintf(
			/* translators: %d: services */
			__( '%d services from sample freelancers with packages, completed orders from buyers and reviews', 'wbcom-demo-installer' ),
			count( self::SERVICES )
		);
	}

	public function plan(): array {
		return array(
			'term'    => array( 0, 'setup', __( 'service categories', 'wbcom-demo-installer' ) ),
			'service' => array( count( self::SERVICES ), 'sample', __( 'services', 'wbcom-demo-installer' ) ),
			'order'   => array( 0, 'sample', __( 'orders', 'wbcom-demo-installer' ) ),
		);
	}

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
	 * Vendors and their services.
	 *
	 * @param Context $ctx Context.
	 */
	private function vendors( Context $ctx ): void {
		$vendors  = new \WPSellServices\Services\VendorService();
		$services = new \WPSellServices\Services\ServiceManager();
		$done     = array();
		foreach ( self::SERVICES as $i => $s ) {
			list( $who, $title, $cat, $base, $desc ) = $s;
			$vendor = $ctx->person( $who );
			if ( ! isset( $done[ $vendor ] ) ) {
				$vendors->register( $vendor, array( 'status' => 'active' ) );
				$p = $ctx->profile( $vendor );
				if ( method_exists( $vendors, 'update_profile' ) ) {
					$vendors->update_profile(
						$vendor,
						array(
							'tagline'  => $p['headline'] ?? '',
							'bio'      => sprintf( '%s based in %s.', $p['job'] ?? '', $p['location'] ?? '' ),
							'country'  => substr( (string) ( $p['location'] ?? '' ), -2 ),
						)
					);
				}
				$done[ $vendor ] = true;
			}
			$term = term_exists( $cat, 'wpss_service_category' );
			if ( ! $term ) {
				$term = wp_insert_term( $cat, 'wpss_service_category' );
				if ( ! is_wp_error( $term ) ) {
					$ctx->record( $this->id(), 'term', (int) $term['term_id'], 'setup', array( 'taxonomy' => 'wpss_service_category' ) );
				}
			}
			\WbcomDemo\Core\Clock::set( $ctx->date( $i, count( self::SERVICES ), 70 ) );
			$id = $services->create(
				array(
					'title'      => $title,
					'content'    => wpautop( $desc ),
					'excerpt'    => $desc,
					'author'     => $vendor,
					'categories' => is_array( $term ) ? array( (int) $term['term_id'] ) : array(),
					'packages'   => array(
						array( 'name' => 'Basic', 'description' => 'Everything you need to get started.', 'price' => $base, 'delivery_days' => 3, 'revisions' => 1 ),
						array( 'name' => 'Standard', 'description' => 'The popular choice.', 'price' => $base * 2, 'delivery_days' => 5, 'revisions' => 3 ),
						array( 'name' => 'Premium', 'description' => 'The full package with priority support.', 'price' => $base * 3, 'delivery_days' => 7, 'revisions' => 5 ),
					),
					'status'     => 'publish',
				)
			);
			\WbcomDemo\Core\Clock::reset();
			if ( ! $id ) {
				$ctx->warn( $this->id(), 'service ' . $title, 'not created' );
				continue;
			}
			$date = $ctx->date( $i, count( self::SERVICES ), 70 );
			wp_update_post(
				array(
					'ID'            => $id,
					'post_date'     => get_date_from_gmt( $date ),
					'post_date_gmt' => $date,
					'edit_date'     => true,
				)
			);
			$thumb = $ctx->attachment( 'photos/' . array( 'tech-workspace.jpg', 'art-gallery.jpg', 'developer-desk.jpg', 'creative-studio-portrait.jpg', 'neon-light.jpg', 'festival-friends.jpg' )[ $i % 6 ], $vendor, $this->id() );
			if ( $thumb ) {
				set_post_thumbnail( (int) $id, $thumb );
			}
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
				$term = get_term( $id, 'wpss_service_category' );
				if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
					wp_delete_term( $id, 'wpss_service_category' );
				}
				return true;
			case 'attachment':
				if ( get_post( $id ) ) {
					wp_delete_attachment( $id, true );
				}
				return true;
			case 'service':
				if ( get_post( $id ) ) {
					wp_delete_post( $id, true );
				}
				return true;
			case 'order':
				// Orders are removed with the buyer (Sell Services cascades orders, reviews and wallet rows on user delete).
				return true;
		}
		return true;
	}

	public function late_types(): array {
		return array( 'term' );
	}

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
