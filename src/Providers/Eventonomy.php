<?php
/**
 * Eventonomy: upcoming and past events, venues, organizers and RSVPs.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Eventonomy provider. Uses Eventonomy services and repositories.
 */
final class Eventonomy extends Provider {

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'eventonomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Eventonomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'EVENTONOMY_VERSION' ) && function_exists( 'evnm' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'EVENTONOMY_VERSION' ) ? (string) EVENTONOMY_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.9.1';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'cal';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 45;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'EVENTONOMY_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$past = count( array_filter( $this->content(), static fn( $e ) => $e[7] < 0 ) );
		return sprintf(
			/* translators: 1: upcoming events, 2: past events */
			__( '%1$d upcoming and %2$d past events, online and in person, with venues, organizers and RSVPs', 'wbcom-demo-installer' ),
			count( $this->content() ) - $past,
			$past
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'evnm_show_wizard_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		return array(
			'evnm_settings' => array( 'setup_complete' => true ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$venues     = array();
		$organizers = array();
		$cats       = array();
		foreach ( $this->content() as $e ) {
			if ( ! $e[10] && '' !== $e[2] ) {
				$venues[ $e[2] ] = true;
			}
			$organizers[ $e[6] ] = true;
			$cats[]              = $e[1];
		}
		// One venue per place and one organizer per sample person, as Eventonomy creates them.
		return array(
			'category'   => array( self::missing_terms( array( 'evnm_event_category' => $cats ) ), 'setup', __( 'event categories', 'wbcom-demo-installer' ) ),
			'venue'      => array( count( $venues ), 'setup', __( 'venues', 'wbcom-demo-installer' ) ),
			'organizer'  => array( count( $organizers ), 'setup', __( 'organizers', 'wbcom-demo-installer' ) ),
			'event'      => array( count( $this->content() ), 'sample', __( 'events', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $this->content() ), 'sample', __( 'event images', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'events:0', 'events:5', 'rsvps' );
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		if ( 'rsvps' === $step ) {
			$this->rsvps( $ctx );
			return;
		}
		$offset = (int) substr( $step, 7 );
		$svc    = evnm( \Eventonomy\Services\EventService::class );
		$repo   = evnm( \Eventonomy\Contracts\EventRepositoryInterface::class );
		$tz     = wp_timezone_string();
		foreach ( array_slice( $this->content(), $offset, 5, true ) as $i => $e ) {
			$i = (int) $i; // content/eventonomy.php is a list.
			list( $title, $cat, $venue, $address, $city, $country, $who, $days, $time, $desc, $virtual, $image ) = $e;
			$organizer = $ctx->person( $who );
			if ( function_exists( 'evnm_grant_organizer_role' ) ) {
				evnm_grant_organizer_role( $organizer );
			}
			list( $start, $end ) = explode( '-', $time );
			$day                 = gmdate( 'Y-m-d', strtotime( ( $days >= 0 ? '+' : '' ) . $days . ' days' ) );
			$input               = array(
				'title'       => $title,
				'content'     => wpautop( $desc ),
				'start_local' => $day . 'T' . $start,
				'end_local'   => $day . 'T' . $end,
				'timezone'    => $tz ? $tz : 'UTC',
				'status'      => 'published',
				'visibility'  => 'public',
				'organizer'   => array( 'name' => (string) ( $ctx->profile( $organizer )['name'] ?? '' ) ),
				'is_virtual'  => $virtual ? 1 : 0,
			);
			$input['settings']   = array();
			// Eventonomy reads the cover from settings.image (a URL), the same field its importers fill.
			$photo = $ctx->attachment( $image, $organizer, $this->id() );
			if ( $photo ) {
				$input['settings']['image'] = (string) wp_get_attachment_url( $photo );
			}
			if ( $virtual ) {
				$input['settings']['join_url'] = 'https://meet.example.com/' . sanitize_title( $title );
			} else {
				$input['city']    = $city;
				$input['country'] = $country;
				$input['venue']   = array(
					'name'    => $venue,
					'address' => $address . ', ' . $city,
				);
			}
			$was = get_current_user_id();
			wp_set_current_user( $organizer );
			\WbcomDemo\Core\Clock::set( $ctx->date( $i, 12, 50 ) );
			$row = $svc->create(
				$input,
				array(
					'user_id' => $organizer,
					'source'  => 'demo',
				)
			);
			wp_set_current_user( $was );
			\WbcomDemo\Core\Clock::reset();
			if ( is_wp_error( $row ) || empty( $row['id'] ) ) {
				$ctx->warn( $this->id(), 'event ' . $title, is_wp_error( $row ) ? $row : 'not created' );
				continue;
			}
			$id = (int) $row['id'];
			$repo->update( $id, array( 'created_at' => $ctx->date( $i, 12, 50 ) ) );
			if ( ! empty( $row['venue_id'] ) ) {
				$ctx->record( $this->id(), 'venue', (int) $row['venue_id'], 'setup' );
			}
			if ( ! empty( $row['organizer_id'] ) ) {
				$ctx->record( $this->id(), 'organizer', (int) $row['organizer_id'], 'setup' );
			}
			$term = $this->ensure_term( $cat, 'evnm_event_category', $ctx, '', 'category' );
			if ( $term ) {
				wp_set_object_terms( $id, array( $term ), 'evnm_event_category' );
			}
			$ctx->record( $this->id(), 'event', $id, 'sample', array( 'organizer' => $organizer ) );
		}
	}

	/**
	 * Attendees RSVP to each event.
	 *
	 * @param Context $ctx Context.
	 */
	private function rsvps( Context $ctx ): void {
		$svc       = evnm( \Eventonomy\Services\RsvpService::class );
		$attendees = array_values( array_unique( array_merge( $ctx->people( 'attendee' ), $ctx->people( 'organizer' ) ) ) );
		foreach ( Ledger::ids( $this->id(), 'event' ) as $k => $event_id ) {
			$n   = 3 + ( $k * 3 ) % 7;
			$max = min( $n, count( $attendees ) );
			for ( $a = 0; $a < $max; $a++ ) {
				$uid = $attendees[ ( $k + $a ) % count( $attendees ) ];
				\WbcomDemo\Core\Clock::set( $ctx->date( $k * 9 + $a, 120, 45 ) );
				$was = get_current_user_id();
				wp_set_current_user( $uid );
				$res = $svc->rsvp(
					array(
						'event_id'     => $event_id,
						'user_id'      => $uid,
						'status'       => 0 === $a % 4 ? 'maybe' : 'going',
						'guests_count' => 0,
					),
					array( 'user_id' => $uid )
				);
				wp_set_current_user( $was );
				if ( is_wp_error( $res ) ) {
					$ctx->warn( $this->id(), 'rsvp', $res );
				}
			}
		}
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		switch ( $row->object_type ) {
			case 'event':
				$res = evnm( \Eventonomy\Services\EventService::class )->delete( $id, array( 'user_id' => get_current_user_id() ) );
				return ! is_wp_error( $res ) || ! evnm( \Eventonomy\Contracts\EventRepositoryInterface::class )->find( $id );
			case 'venue':
				$repo = evnm( \Eventonomy\Contracts\VenueRepositoryInterface::class );
				if ( 0 === (int) $repo->usage_count( $id ) ) {
					$repo->delete( $id );
				}
				return true;
			case 'organizer':
				$repo = evnm( \Eventonomy\Contracts\OrganizerRepositoryInterface::class );
				if ( 0 === (int) $repo->usage_count( $id ) ) {
					$repo->delete( $id );
				}
				return true;
			case 'category':
				return self::remove_unused_term( $id, 'evnm_event_category' );
			case 'attachment':
				return self::delete_post( $id, true );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'venue', 'organizer', 'category' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		return function_exists( 'evnm_events_page_url' ) && evnm_events_page_url()
			? array(
				array(
					'title' => __( 'Events', 'wbcom-demo-installer' ),
					'url'   => evnm_events_page_url(),
				),
			)
			: array();
	}
}
