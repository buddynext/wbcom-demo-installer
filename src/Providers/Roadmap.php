<?php
/**
 * Product Roadmap: a public roadmap for the community with statuses, categories and member votes.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Roadmap provider. Items are a post type; votes go through Roadmap's own API.
 */
final class Roadmap extends Provider {

	private const STATUSES = array(
		'planned'      => 'Planned',
		'in-progress'  => 'In Progress',
		'completed'    => 'Completed',
		'under-review' => 'Under Review',
	);

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'roadmap';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Product Roadmap';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'ROADMAP_VERSION' ) && post_type_exists( 'roadmap_item' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'ROADMAP_VERSION' ) ? (string) ROADMAP_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '1.8.1';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'clock';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 80;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'ROADMAP_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: items */
			__( 'A community roadmap with %d items across planned, in progress and completed, voted on by members', 'wbcom-demo-installer' ),
			count( $this->content()['items'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'roadmap_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		$settings = array(
			'roadmap_wizard_completed'       => true,
			'roadmap_setup_notice_dismissed' => true,
		);
		// The roadmap heading names the product; its own wizard fills it from the site title.
		if ( '' === (string) get_option( 'roadmap_product_name', '' ) ) {
			$settings['roadmap_product_name'] = get_bloginfo( 'name' );
		}
		return $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array(
			'term' => array(
				self::missing_terms(
					array(
						'roadmap_status'   => array_map( static fn( $s ) => self::STATUSES[ $s ], array_column( $this->content()['items'], 1 ) ),
						'roadmap_category' => array_column( $this->content()['items'], 2 ),
					)
				),
				'setup',
				__( 'roadmap statuses and categories', 'wbcom-demo-installer' ),
			),
			'page' => array( 1, 'setup', __( 'roadmap page', 'wbcom-demo-installer' ) ),
			'item' => array( count( $this->content()['items'] ), 'sample', __( 'roadmap items', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array( 'items', 'votes' );
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		'votes' === $step ? $this->votes( $ctx ) : $this->items( $ctx );
	}

	/**
	 * Roadmap items.
	 *
	 * @param Context $ctx Context.
	 */
	private function items( Context $ctx ): void {
		$page = (int) get_option( 'roadmap_page_id' );
		if ( ! $page || 'publish' !== get_post_status( $page ) ) {
			$page = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => __( 'Roadmap', 'wbcom-demo-installer' ),
					'post_content' => '[roadmap product="main"]',
				),
				true
			);
			if ( ! is_wp_error( $page ) ) {
				$ctx->record( $this->id(), 'page', (int) $page, 'setup' );
				\WbcomDemo\Core\Snapshot::write( 'roadmap_page_id', (int) $page );
			}
		}
		$author = $ctx->person( 0, 'moderator' );
		foreach ( $this->content()['items'] as $i => $item ) {
			list( $title, $status, $cat, $desc ) = $item;
			$id                                  = wp_insert_post(
				array(
					'post_type'    => 'roadmap_item',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => wpautop( $desc ),
					'post_author'  => $author,
					'post_date'    => get_date_from_gmt( $ctx->date( $i, count( $this->content()['items'] ), 75 ) ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'item ' . $title, $id );
				continue;
			}
			wp_set_object_terms( $id, $this->ensure_term( self::STATUSES[ $status ], 'roadmap_status', $ctx, $status ), 'roadmap_status' );
			wp_set_object_terms( $id, $this->ensure_term( $cat, 'roadmap_category', $ctx, sanitize_title( $cat ) ), 'roadmap_category' );
			update_post_meta( $id, '_roadmap_priority', array( 'high', 'medium', 'low' )[ $i % 3 ] );
			update_post_meta(
				$id,
				'_roadmap_progress',
				array(
					'completed'   => 100,
					'in-progress' => 50,
				)[ $status ] ?? 0
			);
			$ctx->record( $this->id(), 'item', (int) $id );
		}
	}

	/**
	 * Members vote through Roadmap's API.
	 *
	 * @param Context $ctx Context.
	 */
	private function votes( Context $ctx ): void {
		if ( ! class_exists( '\Roadmap_API' ) ) {
			return;
		}
		$api    = \Roadmap_API::get_instance();
		$people = $ctx->people();
		$was    = get_current_user_id();
		foreach ( Ledger::ids( $this->id(), 'item' ) as $k => $item_id ) {
			for ( $v = 0; $v < 3 + ( $k * 5 ) % 11; $v++ ) {
				wp_set_current_user( $people[ ( $k + $v * 3 ) % count( $people ) ] );
				$req = new \WP_REST_Request( 'POST', '/roadmap/v1/vote' );
				$req->set_param( 'item_id', $item_id );
				$req->set_param( 'vote_value', 1 );
				$api->submit_vote( $req );
			}
			$api->update_vote_count( $item_id );
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
		if ( 'term' === $row->object_type ) {
			return self::remove_unused_term( $id, (string) ( $row->meta['taxonomy'] ?? '' ) );
		}
		if ( ! self::delete_post( $id ) ) {
			return false;
		}
		// Product Roadmap keeps votes when an item is deleted (card filed). Remove only this item's votes.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'roadmap_votes', array( 'item_id' => $id ), array( '%d' ) );
		// Roadmap Pro logs each vote in its analytics table; drop the events for this item so the
		// owner's analytics do not count demo votes for items that no longer exist (same card).
		if ( defined( 'ROADMAP_PRO_VERSION' ) || class_exists( '\\Roadmap_Pro_Analytics' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'roadmap_analytics', array( 'item_id' => $id ), array( '%d' ) );
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
		$page = (int) get_option( 'roadmap_page_id' );
		return $page && 'publish' === get_post_status( $page )
			? array(
				array(
					'title'   => __( 'Roadmap', 'wbcom-demo-installer' ),
					'page_id' => $page,
				),
			)
			: array();
	}
}
