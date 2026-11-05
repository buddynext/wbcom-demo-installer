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

	public function id(): string {
		return 'gamification';
	}

	public function label(): string {
		return 'WB Gamification';
	}

	public function available(): bool {
		return defined( 'WB_GAM_VERSION' ) && class_exists( '\WBGam\Engine\ImportService' );
	}

	public function version(): string {
		return defined( 'WB_GAM_VERSION' ) ? (string) WB_GAM_VERSION : '';
	}

	public function min_version(): string {
		return '1.6.7';
	}

	public function icon(): string {
		return 'trophy';
	}

	public function order(): int {
		return 90;
	}

	public function summary(): string {
		return __( 'Points history, levels and badges for every sample person, so the leaderboard has a real spread', 'wbcom-demo-installer' );
	}

	public function plan(): array {
		return array( 'member_points' => array( 20, 'sample', __( 'points histories', 'wbcom-demo-installer' ) ) );
	}

	public function steps( Context $ctx ): array {
		return array( 'points:0', 'points:10' );
	}

	/**
	 * Ten people's history per step. Busier people get more events.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
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
	 * Purge one sample person's points, badges and streaks.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		if ( class_exists( '\WBGam\Engine\MemberData' ) && method_exists( '\WBGam\Engine\MemberData', 'purge' ) ) {
			\WBGam\Engine\MemberData::purge( (int) $row->object_id );
		}
		return true;
	}

	public function menu_items(): array {
		$page = (int) get_option( 'wb_gam_hub_page_id' );
		return $page && 'publish' === get_post_status( $page )
			? array(
				array(
					'title'   => __( 'Leaderboard', 'wbcom-demo-installer' ),
					'page_id' => $page,
				),
			)
			: array();
	}
}
