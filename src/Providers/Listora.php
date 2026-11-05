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

	/** [ owner index, title, category, address, city, country, lat, lng, price, photo, description, features ]. */
	private const LISTINGS = array(
		array( 7, 'Roastworks Coffee Bar', 'Cafe', 'Oranienstraße 21', 'Berlin', 'DE', 52.5009, 13.4220, '$$', 'coffee-cheers.jpg', 'A small roastery bar with single-origin pour-overs, a quiet back room for laptops and cinnamon buns on weekends.', array( 'WiFi', 'Takeout', 'Outdoor Seating' ) ),
		array( 3, 'Kissa Hanabi', 'Cafe', 'Shimogyo-ku 12', 'Kyoto', 'JP', 35.0036, 135.7681, '$', 'matcha-latte.jpg', 'A ten-seat tea room with hand-whisked matcha, wagashi from the shop next door and a record player that never stops.', array( 'Credit Cards', 'Takeout' ) ),
		array( 13, 'Mercado Criativo', 'Coworking', 'Rua de Cedofeita 80', 'Porto', 'PT', 41.1517, -8.6176, '$$', 'creative-studio-portrait.jpg', 'Desks, a print studio and a shared kitchen for freelancers. Day passes available, members get the good chairs.', array( 'WiFi', 'Wheelchair Accessible' ) ),
		array( 17, 'The Sound Room', 'Venue', 'Rainbow Street 5', 'Amman', 'JO', 31.9522, 35.9254, '$$', 'festival-friends.jpg', 'A 120-person room for talks and small gigs, with a proper sound system and the best falafel stand outside.', array( 'Live Music', 'Wheelchair Accessible' ) ),
		array( 9, 'Hanok Lens Studio', 'Studio', 'Bukchon-ro 37', 'Seoul', 'KR', 37.5826, 126.9837, '$$$', 'vintage-camera.jpg', 'A rentable photo studio in a renovated hanok, with daylight from three sides and a darkroom for film.', array( 'Reservations', 'Credit Cards' ) ),
		array( 7, 'Kiez Bakery', 'Bakery', 'Weserstraße 50', 'Berlin', 'DE', 52.4855, 13.4373, '$', 'chocolate-truffles.jpg', 'Sourdough every morning, chocolate truffles every afternoon and a queue that moves faster than it looks.', array( 'Takeout', 'Credit Cards' ) ),
	);

	/** Review pool: [ rating, title, text ]. */
	private const REVIEWS = array(
		array( 5, 'My favourite spot in town', 'Friendly people, great coffee and quiet enough to get work done.' ),
		array( 4, 'Lovely, gets busy', 'Really good. Go early on weekends, it fills up fast.' ),
		array( 5, 'Worth the trip', 'We came across the city for this and would do it again.' ),
		array( 4, 'Great for small groups', 'Booked it for our team meetup, everything was easy.' ),
		array( 5, 'A real gem', 'The kind of place you want to keep secret and tell everyone about.' ),
	);

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
			count( self::LISTINGS )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'term'       => array( 0, 'setup', __( 'listing categories and features', 'wbcom-demo-installer' ) ),
			'listing'    => array( count( self::LISTINGS ), 'sample', __( 'listings', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( self::LISTINGS ), 'sample', __( 'listing photos', 'wbcom-demo-installer' ) ),
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
	 * A term id, created (and recorded) when missing.
	 *
	 * @param string  $name     Name.
	 * @param string  $taxonomy Taxonomy.
	 * @param Context $ctx      Context.
	 */
	private function term( string $name, string $taxonomy, Context $ctx ): int {
		$term = term_exists( $name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $term ) ) {
				return 0;
			}
			$ctx->record( $this->id(), 'term', (int) $term['term_id'], 'setup', array( 'taxonomy' => $taxonomy ) );
		}
		return (int) $term['term_id'];
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
		foreach ( self::LISTINGS as $i => $l ) {
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
			wp_set_object_terms( $id, array( $this->term( $cat, 'listora_listing_cat', $ctx ) ), 'listora_listing_cat' );
			$feature_ids = array();
			foreach ( $features as $f ) {
				$feature_ids[] = $this->term( $f, 'listora_listing_feature', $ctx );
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
			$date = $ctx->date( $i, count( self::LISTINGS ), 80 );
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
				$review = self::REVIEWS[ ( $k + $r ) % count( self::REVIEWS ) ];
				wp_set_current_user( $uid );
				$req = new \WP_REST_Request( 'POST', '/listora/v1/listings/' . $listing_id . '/reviews' );
				$req->set_param( 'overall_rating', $review[0] );
				$req->set_param( 'title', $review[1] );
				$req->set_param( 'content', $review[2] );
				$res = rest_do_request( $req );
				if ( $res->is_error() ) {
					$ctx->warn( $this->id(), 'review', $res->as_error() );
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
			$tax  = (string) ( $row->meta['taxonomy'] ?? '' );
			$term = $tax ? get_term( $id, $tax ) : null;
			if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
				wp_delete_term( $id, $tax );
			}
			return true;
		}
		if ( 'attachment' === $row->object_type ) {
			if ( get_post( $id ) ) {
				wp_delete_attachment( $id, true );
			}
			return true;
		}
		if ( get_post( $id ) ) {
			wp_delete_post( $id, true );
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
