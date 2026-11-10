<?php
/**
 * WB Listora: places run by the sample people, with hours, locations, photos and reviews.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Listora provider. Listings are a post type; meta and locations go through Listora's helpers,
 * reviews through its REST route.
 */
final class Listora extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'listora';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WB Listora';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'WB_LISTORA_VERSION' ) && post_type_exists( 'listora_listing' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WB_LISTORA_VERSION' ) ? (string) WB_LISTORA_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '2.1.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'map';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 55;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'WB_LISTORA_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: listings */
			__( '%d places run by the sample people, with photos, hours, map locations and reviews', 'wbcom-demo-installer' ),
			count( $this->content()['listings'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'wb_listora_show_wizard_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		// Same two flags as the plugin's own mark_setup_complete(), which it calls once a directory has listings.
		$settings = array(
			'wb_listora_setup_complete'                 => '1',
			'wb_listora_settings'                       => array( 'setup_complete' => true ),
			'transient:wb_listora_pages_review_pending' => null,
		);
		// Listora ships with no map tile source, so its maps draw nothing until the owner picks one. The demo sets
		// OpenStreetMap's standard tiles with their required credit so the Directory and listing maps show; Remove
		// everything puts the owner's blank values back. A live site should move to its own or a paid tile provider.
		if ( function_exists( 'wb_listora_get_setting' ) && '' === trim( (string) wb_listora_get_setting( 'map_tile_url', '' ) ) ) {
			$settings['wb_listora_settings'] += array(
				'map_provider'         => 'osm',
				'map_tile_url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
				'map_tile_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			);
		}
		return $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'term'       => array(
				self::missing_terms(
					array(
						'listora_listing_cat'      => array_column( $this->content()['listings'], 2 ),
						'listora_listing_feature'  => array_merge( array(), ...array_map( static fn( $l ) => (array) $l[11], $this->content()['listings'] ) ),
						// Listora files each listing under its city and country (Term_Helper::set_location_terms()).
						'listora_listing_location' => array_merge(
							array_column( $this->content()['listings'], 4 ),
							class_exists( '\WBListora\Core\Countries' ) ? array_map( static fn( $c ) => (string) \WBListora\Core\Countries::name( $c ), array_column( $this->content()['listings'], 5 ) ) : array()
						),
					)
				),
				'setup',
				__( 'listing categories, features and locations', 'wbcom-demo-installer' ),
			),
			'listing'    => array( count( $this->content()['listings'] ), 'sample', __( 'listings', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $this->content()['listings'] ), 'sample', __( 'listing photos', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'listings', 'reviews' );
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		'reviews' === $step ? $this->reviews( $ctx ) : $this->listings( $ctx );
	}

	/**
	 * Listings.
	 *
	 * @param Context $ctx Context.
	 */
	private function listings( Context $ctx ): void {
		$type = 'place';
		if ( class_exists( '\WBListora\Core\Listing_Type_Registry' ) ) {
			\WBListora\Core\Listing_Type_Registry::instance()->install_default( $type );
		}
		add_filter( 'wb_listora_should_send_notification', '__return_false' );
		foreach ( $this->content()['listings'] as $i => $l ) {
			list( $who, $title, $cat, $street, $city, $country, $lat, $lng, $price, $photo, $desc, $features ) = $l;
			$owner = $ctx->person( $who );
			$id    = wp_insert_post(
				array(
					'post_type'    => 'listora_listing',
					'post_status'  => 'draft',
					'post_title'   => $title,
					'post_content' => wpautop( $desc ),
					'post_author'  => $owner,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'listing ' . $title, $id );
				continue;
			}
			$ctx->record( $this->id(), 'listing', (int) $id );
			wp_set_object_terms( $id, $type, 'listora_listing_type' );
			wp_set_object_terms( $id, array( $this->ensure_term( $cat, 'listora_listing_cat', $ctx ) ), 'listora_listing_cat' );
			$feature_ids = array();
			foreach ( $features as $f ) {
				$feature_ids[] = $this->ensure_term( $f, 'listora_listing_feature', $ctx );
			}
			wp_set_object_terms( $id, array_filter( $feature_ids ), 'listora_listing_feature' );
			$address = array(
				'address'     => $street . ', ' . $city,
				'lat'         => $lat,
				'lng'         => $lng,
				'city'        => $city,
				'state'       => '',
				'country'     => $country,
				'postal_code' => '',
			);
			$ctx->watch_terms( $this->id(), array( 'listora_listing_location' ), static fn() => \WBListora\ImportExport\Term_Helper::set_location_terms( (int) $id, $address ) );
			$hours = array();
			for ( $d = 0; $d < 7; $d++ ) {
				$hours[] = 0 === $d ? array(
					'day'    => 0,
					'closed' => true,
				) : array(
					'day'   => $d,
					'open'  => '08:00',
					'close' => 5 <= $d ? '22:00' : '18:00',
				);
			}
			foreach ( array(
				'address'        => $address,
				'phone'          => '+00 555 01' . sprintf( '%02d', $i ),
				'website'        => 'https://' . sanitize_title( $title ) . '.example',
				'price_range'    => $price,
				'business_hours' => $hours,
			) as $key => $value ) {
				\WBListora\Core\Meta_Handler::set_value( (int) $id, $key, $value );
			}
			$thumb = $ctx->attachment( 'photos/' . $photo, $owner, $this->id() );
			if ( $thumb ) {
				set_post_thumbnail( (int) $id, $thumb );
			}
			if ( 0 === $i % 3 ) {
				update_post_meta( $id, '_listora_is_featured', true );
			}
			$date = $ctx->date( $i, count( $this->content()['listings'] ), 80 );
			\WbcomDemo\Core\Clock::set( $date );
			wp_update_post(
				array(
					'ID'            => $id,
					'post_status'   => 'publish',
					'post_date'     => get_date_from_gmt( $date ),
					'post_date_gmt' => $date,
					'edit_date'     => true,
				)
			);
			\WbcomDemo\Core\Clock::reset();
		}
		remove_filter( 'wb_listora_should_send_notification', '__return_false' );
	}

	/**
	 * Reviewers review through Listora's REST route.
	 *
	 * @param Context $ctx Context.
	 */
	private function reviews( Context $ctx ): void {
		$reviewers = array_values( array_unique( array_merge( $ctx->people( 'reviewer' ), $ctx->people( 'buyer' ) ) ) );
		$was       = get_current_user_id();
		add_filter( 'wb_listora_should_send_notification', '__return_false' );
		foreach ( Ledger::ids( $this->id(), 'listing' ) as $k => $listing_id ) {
			$owner = (int) get_post_field( 'post_author', $listing_id );
			for ( $r = 0; $r < 2 + $k % 3; $r++ ) {
				$uid = $reviewers[ ( $k * 2 + $r ) % max( 1, count( $reviewers ) ) ] ?? 0;
				if ( ! $uid || $uid === $owner ) {
					continue;
				}
				$review = $this->content()['reviews'][ ( $k + $r ) % count( $this->content()['reviews'] ) ];
				wp_set_current_user( $uid );
				$req = new \WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing_id . '/reviews' );
				$req->set_param( 'overall_rating', $review[0] );
				$req->set_param( 'title', $review[1] );
				$req->set_param( 'content', $review[2] );
				$res = rest_do_request( $req );
				if ( $res->is_error() ) {
					$ctx->warn( $this->id(), 'review', $res->as_error() );
					continue;
				}
				$data = (array) $res->get_data();
				if ( 'pending' === ( $data['status'] ?? '' ) && ! empty( $data['id'] ) ) {
					// Listora holds new reviews for moderation by default, which is right for real reviews. A demo
					// listing with only pending reviews reads "No reviews yet", so the admin approves our own sample
					// reviews through Listora's moderator route; the owner's moderation setting is not changed.
					wp_set_current_user( $was );
					$approve = new \WP_REST_Request( 'PUT', '/listora/v1/reviews/' . (int) $data['id'] );
					$approve->set_param( 'status', 'approved' );
					$done = rest_do_request( $approve );
					if ( $done->is_error() ) {
						$ctx->warn( $this->id(), 'review', $done->as_error() );
					}
				}
			}
		}
		remove_filter( 'wb_listora_should_send_notification', '__return_false' );
		wp_set_current_user( $was );
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		if ( 'term' === $row->object_type ) {
			return self::remove_unused_term( $id, (string) ( $row->meta['taxonomy'] ?? '' ) );
		}
		if ( 'attachment' === $row->object_type ) {
			return self::delete_post( $id, true );
		}
		return self::delete_post( $id );
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
		$url = function_exists( 'wb_listora_get_page_url' ) ? (string) wb_listora_get_page_url( 'directory' ) : '';
		return $url
			? array(
				array(
					'title' => __( 'Directory', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
			)
			: array();
	}
}
