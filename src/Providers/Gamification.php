<?php
/**
 * WB Gamification: a backdated points history so the leaderboard, levels and badges look lived-in.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Gamification provider. Uses ImportService (idempotent, backdated) and MemberData::purge.
 */
final class Gamification extends Provider {

	/** Actions the history is drawn from, with a weight for how often each happens. */
	private const ACTIONS = array(
		'bn_post_created'      => 5,
		'bn_comment_created'   => 8,
		'bn_reaction_received' => 10,
		'bn_followed'          => 3,
		'bn_space_joined'      => 2,
		'bn_poll_voted'        => 1,
	);

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'gamification';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WB Gamification';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'WB_GAM_VERSION' ) && class_exists( '\WBGam\Engine\ImportService' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WB_GAM_VERSION' ) ? (string) WB_GAM_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.6.7';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'trophy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 90;
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return __( 'Points history, levels, earned badges and kudos between the sample people, so the leaderboard and profiles have a real spread', 'wbcom-demo-installer' );
	}

	/** Badges each role earns (badge ids from WB Gamification's default set). */
	private const ROLE_BADGES = array(
		'member'     => array( 'first_post', 'first_comment', 'first_friend' ),
		'moderator'  => array( 'community_voice', 'top_contributor' ),
		'instructor' => array( 'mentor', 'content_creator' ),
		'organizer'  => array( 'event_host', 'team_player' ),
		'vendor'     => array( 'prolific_writer' ),
		'reviewer'   => array( 'engaged_reader', 'comment_champion' ),
		'student'    => array( 'active_member' ),
		'attendee'   => array( 'social_connector' ),
	);

	/** Kudos messages. */
	private const KUDOS = array(
		'Thanks for the feedback on my design, it really helped.',
		'Your answer saved me hours. Thank you!',
		'Great event, thanks for hosting.',
		'Love how you welcome every new member.',
		'Thanks for the course, I finally get it.',
	);

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		// The wizard's own Skip sets only the flag; the pending redirect option would still send the admin there.
		return array(
			'wb_gam_wizard_complete'        => true,
			'wb_gam_pending_setup_redirect' => null,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'member_points' => array( 20, 'sample', __( 'points histories', 'wbcom-demo-installer' ) ),
			'badges'        => array( 20, 'sample', __( 'badge sets', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'points:0', 'points:10', 'badges' );
	}

	/**
	 * Ten people's history per step. Busier people get more events.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		if ( 'badges' === $step ) {
			$this->badges( $ctx );
			return;
		}
		$offset  = (int) substr( $step, 7 );
		$actions = array();
		foreach ( self::ACTIONS as $action => $weight ) {
			$actions = array_merge( $actions, array_fill( 0, $weight, $action ) );
		}
		foreach ( array_slice( $ctx->people(), $offset, 10, true ) as $i => $uid ) {
			$events = 8 + ( ( $i * 37 ) % 53 );
			$rows   = array();
			for ( $e = 0; $e < $events; $e++ ) {
				$rows[] = array(
					'action_id'   => $actions[ ( $i * 13 + $e * 7 ) % count( $actions ) ],
					'user_id'     => $uid,
					'occurred_at' => gmdate( 'c', strtotime( $ctx->date( $e, $events, 60 ) . ' UTC' ) ),
					'source_key'  => 'wbcom-demo:' . $ctx->build_id() . ':' . $uid . ':' . $e,
				);
			}
			\WBGam\Engine\ImportService::ingest( $rows, true );
			$ctx->record( $this->id(), 'member_points', $uid );
		}
	}

	/**
	 * Earned badges by role, then kudos between people.
	 *
	 * @param Context $ctx Context.
	 */
	private function badges( Context $ctx ): void {
		if ( class_exists( '\WBGam\Engine\BadgeEngine' ) ) {
			foreach ( $ctx->people() as $i => $uid ) {
				$roles = (array) ( $ctx->profile( $uid )['roles'] ?? array( 'member' ) );
				$n     = 0;
				foreach ( $roles as $role ) {
					foreach ( self::ROLE_BADGES[ $role ] ?? array() as $badge ) {
						// Busier people earn more of their role badges.
						if ( $n < 2 + $i % 5 ) {
							\WBGam\Engine\BadgeEngine::award_badge( $uid, $badge, $ctx->date( $i * 3 + $n, 70, 55 ) );
							++$n;
						}
					}
				}
				$ctx->record( $this->id(), 'badges', $uid );
			}
		}
		if ( class_exists( '\WBGam\Engine\KudosEngine' ) ) {
			$people = $ctx->people();
			$count  = count( $people );
			foreach ( $people as $i => $giver ) {
				$to  = $people[ ( $i + 3 ) % $count ];
				$res = \WBGam\Engine\KudosEngine::send( $giver, $to, self::KUDOS[ $i % count( self::KUDOS ) ] );
				if ( is_wp_error( $res ) ) {
					$ctx->warn( $this->id(), 'kudos', $res );
				}
			}
		}
	}

	/**
	 * Purge one sample person's points, badges and streaks.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		if ( is_callable( array( '\WBGam\Engine\MemberData', 'purge' ) ) ) {
			\WBGam\Engine\MemberData::purge( (int) $row->object_id );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$page = (int) get_option( 'wb_gam_hub_page_id' );
		return $page && 'publish' === get_post_status( $page )
			? array(
				array(
					'title' => __( 'Leaderboard', 'wbcom-demo-installer' ),
					// The hub opens on the viewer's own stats, which are empty for the site owner; open its
					// leaderboard panel so the menu shows the community ranking.
					'url'   => add_query_arg( 'panel', 'leaderboard', get_permalink( $page ) ),
				),
			)
			: array();
	}
}
