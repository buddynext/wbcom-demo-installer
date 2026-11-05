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

	/** [ title, status, category, description ]. */
	private const ITEMS = array(
		array( 'Monthly critique sessions', 'completed', 'Features', 'A recurring space and event for sharing work in progress and getting kind, specific feedback.' ),
		array( 'Mentoring programme for new members', 'in-progress', 'Features', 'Pair every new member with someone who has been here a while, for their first month.' ),
		array( 'Dark mode across the community', 'completed', 'Improvements', 'Every page follows the reader\'s light or dark setting.' ),
		array( 'Local meetups in more cities', 'planned', 'Features', 'Start small monthly meetups in Lisbon, Berlin and Lagos, with a simple host kit.' ),
		array( 'Better search for old discussions', 'planned', 'Improvements', 'Find answers from past threads by topic, person and date.' ),
		array( 'Accessibility review swap', 'under-review', 'Features', 'Members post a page and two others review it for accessibility, then swap.' ),
		array( 'Faster image uploads on mobile', 'in-progress', 'Improvements', 'Smaller uploads and progress you can see on slow connections.' ),
		array( 'Weekly digest email', 'planned', 'Features', 'One calm email a week with the best discussions you missed.' ),
	);

	private const STATUSES = array(
		'planned'      => 'Planned',
		'in-progress'  => 'In Progress',
		'completed'    => 'Completed',
		'under-review' => 'Under Review',
	);

	public function id(): string {
		return 'roadmap';
	}

	public function label(): string {
		return 'Product Roadmap';
	}

	public function available(): bool {
		return defined( 'ROADMAP_VERSION' ) && post_type_exists( 'roadmap_item' );
	}

	public function version(): string {
		return defined( 'ROADMAP_VERSION' ) ? (string) ROADMAP_VERSION : '';
	}

	public function min_version(): string {
		return '1.8.1';
	}

	public function icon(): string {
		return 'map';
	}

	public function order(): int {
		return 80;
	}

	public function summary(): string {
		return sprintf(
			/* translators: %d: items */
			__( 'A community roadmap with %d items across planned, in progress and completed, voted on by members', 'wbcom-demo-installer' ),
			count( self::ITEMS )
		);
	}

	public function plan(): array {
		return array(
			'term' => array( 0, 'setup', __( 'roadmap statuses and categories', 'wbcom-demo-installer' ) ),
			'item' => array( count( self::ITEMS ), 'sample', __( 'roadmap items', 'wbcom-demo-installer' ) ),
		);
	}

	public function steps( Context $ctx ): array {
		return array( 'items', 'votes' );
	}

	/**
	 * A term id, created (and recorded) when missing.
	 *
	 * @param string  $slug     Slug.
	 * @param string  $name     Name.
	 * @param string  $taxonomy Taxonomy.
	 * @param Context $ctx      Context.
	 */
	private function term( string $slug, string $name, string $taxonomy, Context $ctx ): int {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$new = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $new ) ) {
			return 0;
		}
		$ctx->record( $this->id(), 'term', (int) $new['term_id'], 'setup', array( 'taxonomy' => $taxonomy ) );
		return (int) $new['term_id'];
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
		$author = $ctx->person( 0, 'moderator' );
		foreach ( self::ITEMS as $i => $item ) {
			list( $title, $status, $cat, $desc ) = $item;
			$id = wp_insert_post(
				array(
					'post_type'    => 'roadmap_item',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => wpautop( $desc ),
					'post_author'  => $author,
					'post_date'    => get_date_from_gmt( $ctx->date( $i, count( self::ITEMS ), 75 ) ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'item ' . $title, $id );
				continue;
			}
			wp_set_object_terms( $id, $this->term( $status, self::STATUSES[ $status ], 'roadmap_status', $ctx ), 'roadmap_status' );
			wp_set_object_terms( $id, $this->term( sanitize_title( $cat ), $cat, 'roadmap_category', $ctx ), 'roadmap_category' );
			update_post_meta( $id, '_roadmap_priority', array( 'high', 'medium', 'low' )[ $i % 3 ] );
			update_post_meta( $id, '_roadmap_progress', array( 'completed' => 100, 'in-progress' => 50 )[ $status ] ?? 0 );
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
			$tax  = (string) ( $row->meta['taxonomy'] ?? '' );
			$term = $tax ? get_term( $id, $tax ) : null;
			if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
				wp_delete_term( $id, $tax );
			}
			return true;
		}
		if ( get_post( $id ) ) {
			wp_delete_post( $id, true );
		}
		// Product Roadmap keeps votes when an item is deleted (card filed). Remove only this item's votes.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'roadmap_votes', array( 'item_id' => $id ), array( '%d' ) );
		return true;
	}

	public function late_types(): array {
		return array( 'term' );
	}

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
