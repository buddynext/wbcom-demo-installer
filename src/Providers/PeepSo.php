<?php
/**
 * PeepSo: a dated stream of members' posts and link shares, with comments and reactions. PeepSo core only; Groups,
 * Friends and Photos are separate PeepSo plugins and are not built. Members keep PeepSo's default avatar and cover.
 *
 * PeepSo does not load under WP-CLI (its constructor returns early there), so this provider builds from the wizard
 * or REST only; a WP-CLI build skips it as not available.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Stream content through PeepSoActivity and PeepSoReactionsModel.
 */
final class PeepSo extends Provider {

	/**
	 * Stream posts per step.
	 */
	private const POSTS_PER_STEP = 12;

	/**
	 * Posts that get replies and reactions (the newest ones).
	 */
	private const ENGAGED = 14;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'peepso';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'PeepSo';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return class_exists( 'PeepSo' ) && class_exists( 'PeepSoActivity' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return class_exists( 'PeepSo' ) && defined( 'PeepSo::PLUGIN_VERSION' ) ? (string) \PeepSo::PLUGIN_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '7.0.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'users';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 12; // After the people; first in the tour, like the other community platforms.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return __( 'A stream of member posts and link shares, with comments and reactions', 'wbcom-demo-installer' );
	}

	/**
	 * Stream posts in posting order: [ author index, text, url ]. Group and photo posts become plain posts.
	 *
	 * @return array<int,array<int,mixed>>
	 */
	private function posts(): array {
		$c   = $this->content();
		$out = array();
		foreach ( array_merge( (array) $c['posts'], (array) $c['photos'] ) as $row ) {
			$out[] = array( $row[0], $row[2], '' );
		}
		foreach ( (array) $c['links'] as $row ) {
			$out[] = array( $row[0], $row[2], $row[3] );
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$engaged = min( self::ENGAGED, count( $this->posts() ) );
		$replies = 0;
		for ( $k = 0; $k < $engaged; $k++ ) {
			$replies += 1 + $k % 3;
		}
		return array(
			'member'    => array( count( People::roster() ), 'sample', __( 'PeepSo member profiles', 'wbcom-demo-installer' ) ),
			'post'      => array( count( $this->posts() ), 'sample', __( 'stream posts', 'wbcom-demo-installer' ) ),
			'comment'   => array( $replies, 'sample', __( 'comments', 'wbcom-demo-installer' ) ),
			'reactions' => array( $engaged, 'sample', __( 'posts with reactions', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * Ordered step keys.
	 *
	 * @param Context $ctx Build context.
	 * @return string[]
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'members' );
		for ( $i = 0, $n = count( $this->posts() ); $i < $n; $i += self::POSTS_PER_STEP ) {
			$steps[] = 'posts:' . $i;
		}
		$steps[] = 'engage';
		return $steps;
	}

	/**
	 * PeepSo queues its own mail in a table and sends it later: sample people get none.
	 *
	 * @param Context $ctx Context.
	 */
	public function boot( Context $ctx ): void {
		static $on = false;
		if ( $on || ! $this->available() ) {
			return;
		}
		$on = true;
		// PeepSo passes the recipient first: apply_filters( 'peepso_mailqueue_allowed', $user_id, TRUE ).
		add_filter( 'peepso_mailqueue_allowed', static fn( $user_id, $allowed = true ) => $allowed && ! get_user_meta( (int) $user_id, People::FLAG, true ), 10, 2 );
	}

	/**
	 * Run one step.
	 *
	 * @param string  $step Step key.
	 * @param Context $ctx  Build context.
	 */
	public function run( string $step, Context $ctx ): void {
		list( $name, $offset ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		$admin                 = get_current_user_id();
		try {
			if ( 'members' === $name ) {
				// PeepSo adds a row per member when the account is made and keeps it when the account is deleted.
				foreach ( $ctx->people() as $user ) {
					$ctx->record( $this->id(), 'member', (int) $user );
				}
			} elseif ( 'posts' === $name ) {
				$this->make_posts( $ctx, (int) $offset );
			} elseif ( 'engage' === $name ) {
				$this->engage( $ctx );
			}
		} finally {
			wp_set_current_user( $admin );
		}
	}

	/**
	 * PeepSo dates: its post date fields, in UTC and site time (add_post and add_comment pass them to wp_insert_post).
	 *
	 * @param string $gmt MySQL datetime, UTC.
	 * @return array<string,string>
	 */
	private static function dated( string $gmt ): array {
		return array(
			'post_date_gmt' => $gmt,
			'post_date'     => get_date_from_gmt( $gmt ),
		);
	}

	/**
	 * Replace {@N} with PeepSo's mention tag for that cast member.
	 *
	 * @param string  $text Text.
	 * @param Context $ctx  Context.
	 */
	private static function mentions( string $text, Context $ctx ): string {
		return (string) preg_replace_callback(
			'/\{@(\d+)\}/',
			static function ( array $m ) use ( $ctx ): string {
				$user = get_userdata( $ctx->person( (int) $m[1] ) );
				return $user ? '@peepso_user_' . $user->ID . '(' . $user->display_name . ')' : '';
			},
			$text
		);
	}

	/**
	 * Members' posts and link shares on their own stream, over the last three weeks.
	 *
	 * @param Context $ctx    Context.
	 * @param int     $offset First item.
	 */
	private function make_posts( Context $ctx, int $offset ): void {
		$all = $this->posts();
		foreach ( array_slice( $all, $offset, self::POSTS_PER_STEP, true ) as $i => $row ) {
			list( $who, $text, $url ) = $row;
			$user                     = $ctx->person( (int) $who );
			if ( ! $user ) {
				continue;
			}
			$content = self::mentions( (string) $text, $ctx ) . ( '' !== $url ? "\n\n" . $url : '' );
			wp_set_current_user( $user ); // PeepSo checks the poster's permissions and privacy as the current user.
			// A fresh PeepSoActivity per post: the instance collects link previews and saves them with the next post.
			$id = ( new \PeepSoActivity() )->add_post( $user, $user, $content, self::dated( $ctx->date( (int) $i, count( $all ), 21 ) ) );
			if ( $id ) {
				$ctx->record( $this->id(), 'post', (int) $id );
			} else {
				$ctx->warn( $this->id(), 'post', wp_trim_words( (string) $text, 6 ) );
			}
		}
	}

	/**
	 * Comments and reactions on the newest posts, from other members, a little after each post.
	 *
	 * @param Context $ctx Context.
	 */
	private function engage( Context $ctx ): void {
		$replies = (array) $this->content()['comments'];
		$people  = $ctx->people();
		if ( ! $replies || count( $people ) < 2 ) {
			return;
		}
		$activity = \PeepSoActivity::get_instance();
		$ids      = array_slice( array_reverse( Ledger::ids( $this->id(), 'post' ) ), 0, self::ENGAGED );
		foreach ( $ids as $k => $post_id ) {
			$post = get_post( (int) $post_id );
			if ( ! $post ) {
				continue;
			}
			for ( $r = 0, $count = 1 + $k % 3; $r < $count; $r++ ) {
				$user = $people[ ( $k * 5 + $r * 3 + 1 ) % count( $people ) ];
				if ( $user === (int) $post->post_author ) {
					continue;
				}
				wp_set_current_user( $user );
				$comment = ( new \PeepSoActivity() )->add_comment( (int) $post_id, $user, self::mentions( (string) $replies[ ( $k + $r ) % count( $replies ) ], $ctx ), self::dated( $ctx->after( $post->post_date_gmt, $k * 3 + $r ) ) );
				if ( $comment ) {
					$ctx->record( $this->id(), 'comment', (int) $comment );
				}
			}
			$act = $activity->get_activity_data( (int) $post_id );
			if ( ! $act ) {
				continue;
			}
			$fans = array();
			for ( $f = 0; $f < 2 + $k % 4; $f++ ) {
				$fan = $people[ ( $k * 7 + $f * 2 + 2 ) % count( $people ) ];
				if ( $fan === (int) $post->post_author ) {
					continue;
				}
				wp_set_current_user( $fan ); // The model reacts as the current user.
				$model = new \PeepSoReactionsModel();
				$types = array_keys( (array) $model->reactions_published );
				if ( ! $types ) {
					break;
				}
				$model->init( (int) $act->act_id );
				$model->user_reaction_set( (int) $types[ ( $k + $f ) % min( 4, count( $types ) ) ] );
				$fans[] = $fan;
			}
			if ( $fans ) {
				$ctx->record( $this->id(), 'reactions', (int) $act->act_id, 'sample', array( 'users' => $fans ) );
			}
		}
	}

	/**
	 * Remove one ledger row's object; true only once it is gone.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		if ( ! $this->available() ) {
			return false; // PeepSo is off (or this is WP-CLI): say what is left rather than report it removed.
		}
		global $wpdb;
		$id       = (int) $row->object_id;
		$activity = \PeepSoActivity::get_instance();
		switch ( $row->object_type ) {
			case 'post':
			case 'comment':
				if ( get_post( $id ) ) {
					$act = $activity->get_activity_data( $id );
					$activity->delete_post( $id );
					if ( $act ) {
						( new \PeepSoActivityRanking() )->delete( (int) $act->act_id ); // delete_post() leaves the ranking row.
					}
				}
				// PeepSo has no delete for post followers (only follow on/off), and delete_post() leaves them: removed
				// here for our own post id only, the documented exception to product APIs.
				$wpdb->delete( $wpdb->prefix . 'peepso_activity_followers', array( 'post_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return ! get_post( $id );
			case 'reactions':
				if ( ! $activity->get_activity( $id ) ) {
					return true; // Removed with its post.
				}
				$meta  = is_array( $row->meta ?? null ) ? $row->meta : (array) json_decode( (string) ( $row->meta ?? '' ), true );
				$admin = get_current_user_id();
				$left  = 0;
				foreach ( (array) ( $meta['users'] ?? array() ) as $user ) {
					wp_set_current_user( (int) $user ); // The model removes the current user's reaction.
					$model = new \PeepSoReactionsModel();
					$model->init( $id );
					$model->user_reaction_reset();
					$check = new \PeepSoReactionsModel();
					$check->init( $id );
					$left += false === $check->my_reaction ? 0 : 1;
				}
				wp_set_current_user( $admin );
				return 0 === $left;
			case 'member':
				// PeepSo's deleted_user handler keeps its own member row: removed for our sample person only, the
				// documented exception to product APIs. Ledger order removes it just before the account itself.
				$wpdb->delete( $wpdb->prefix . 'peepso_users', array( 'usr_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return true;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		return ( new Integration( 'peepso' ) )->guide();
	}

	/**
	 * {@inheritDoc}
	 *
	 * PeepSo's activity stream page (PeepSo keeps it as a slug in its own settings).
	 */
	public function front_page(): int {
		if ( ! class_exists( 'PeepSo' ) ) {
			return 0;
		}
		$slug = trim( (string) \PeepSo::get_option( 'page_activity' ), '/' );
		$page = '' !== $slug ? get_page_by_path( $slug ) : null;
		return $page instanceof \WP_Post && 'publish' === $page->post_status ? (int) $page->ID : 0;
	}
}
