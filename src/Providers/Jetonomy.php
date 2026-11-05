<?php
/**
 * Jetonomy: forum categories, spaces, discussions, replies, accepted answers, votes and ideas.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Clock;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Jetonomy provider. Uses Jetonomy models only.
 */
final class Jetonomy extends Provider {

	private const TOPICS_PER_STEP = 5;

	/**
	 * Content.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $content = null;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'jetonomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Jetonomy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'JETONOMY_VERSION' ) && class_exists( '\Jetonomy\Models\Post' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'JETONOMY_VERSION' ) ? (string) JETONOMY_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '2.0.6';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'forum';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 30;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'JETONOMY_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * Content file.
	 *
	 * @return array<string,mixed>
	 */
	private function content(): array {
		return $this->content ??= require WBCOM_DEMO_DIR . 'content/jetonomy.php';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c      = $this->content();
		$spaces = array_sum( array_map( static fn( $cat ) => count( $cat['spaces'] ), $c['categories'] ) );
		return sprintf(
			/* translators: 1: categories, 2: spaces, 3: discussions */
			__( '%1$d forum categories, %2$d forum spaces, %3$d discussions with replies, accepted answers, votes and ideas', 'wbcom-demo-installer' ),
			count( $c['categories'] ),
			$spaces,
			count( $c['topics'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		return array( 'jetonomy_setup_complete' => true );
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c      = $this->content();
		$spaces = array_sum( array_map( static fn( $cat ) => count( $cat['spaces'] ), $c['categories'] ) );
		return array(
			'category' => array( count( $c['categories'] ), 'setup', __( 'forum categories', 'wbcom-demo-installer' ) ),
			'space'    => array( $spaces, 'setup', __( 'forum spaces', 'wbcom-demo-installer' ) ),
			'tag'      => array( count( $c['tags'] ), 'setup', __( 'forum tags', 'wbcom-demo-installer' ) ),
			'post'     => array( count( $c['topics'] ), 'sample', __( 'discussions', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'structure' );
		for ( $i = 0, $n = count( $this->content()['topics'] ); $i < $n; $i += self::TOPICS_PER_STEP ) {
			$steps[] = 'topics:' . $i;
		}
		$steps[] = 'votes';
		return $steps;
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		list( $name, $arg ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		match ( $name ) {
			'structure' => $this->structure( $ctx ),
			'topics'    => $this->topics( (int) $arg, $ctx ),
			'votes'     => $this->votes( $ctx ),
			default     => null,
		};
	}

	/**
	 * Categories, spaces, memberships and tags.
	 *
	 * @param Context $ctx Context.
	 */
	private function structure( Context $ctx ): void {
		$c      = $this->content();
		$people = $ctx->people();
		$mods   = $ctx->people( 'moderator' );
		$spaces = array();
		foreach ( $c['categories'] as $cat ) {
			// A category or space kept from an earlier demo (Finish: keep my setup) is reused as the
			// container. It is not recorded, so a later Finish removes only this demo's content in it.
			$found  = \Jetonomy\Models\Category::find_by_slug( $cat['slug'] );
			$cat_id = $found ? (int) $found->id : \Jetonomy\Models\Category::create(
				array(
					'name'        => $cat['name'],
					'slug'        => $cat['slug'],
					'description' => $cat['desc'],
					'visibility'  => 'public',
				)
			);
			if ( $cat_id <= 0 ) {
				$ctx->warn( $this->id(), 'category ' . $cat['slug'], 'not created' );
				continue;
			}
			if ( ! $found ) {
				$ctx->record( $this->id(), 'category', (int) $cat_id, 'setup' );
			}
			foreach ( $cat['spaces'] as $s ) {
				$owner    = $mods ? $mods[ count( $spaces ) % count( $mods ) ] : $people[0];
				$kept     = \Jetonomy\Models\Space::find_by_slug( $s['slug'] );
				$space_id = $kept ? (int) $kept->id : \Jetonomy\Models\Space::create(
					array(
						'category_id' => (int) $cat_id,
						'author_id'   => $owner,
						'type'        => $s['type'],
						'title'       => $s['title'],
						'slug'        => $s['slug'],
						'description' => $s['desc'],
						'icon'        => $s['icon'],
						'visibility'  => 'public',
						'join_policy' => 'open',
					),
					$owner
				);
				if ( is_wp_error( $space_id ) || $space_id <= 0 ) {
					$ctx->warn( $this->id(), 'space ' . $s['slug'], is_wp_error( $space_id ) ? $space_id : 'not created' );
					continue;
				}
				if ( ! $kept ) {
					$ctx->record( $this->id(), 'space', (int) $space_id, 'setup' );
				}
				$spaces[ $s['slug'] ] = array(
					'id'   => (int) $space_id,
					'type' => $kept ? (string) $kept->type : $s['type'],
				);
				foreach ( $people as $k => $uid ) {
					\Jetonomy\Models\SpaceMember::add( (int) $space_id, $uid, $uid === $owner ? 'admin' : ( in_array( $uid, $mods, true ) ? 'moderator' : 'member' ) );
				}
			}
		}
		$ctx->set( $this->id(), 'spaces', $spaces );
		foreach ( $people as $uid ) {
			\Jetonomy\Models\UserProfile::find_or_create( $uid );
		}

		$tags = array();
		foreach ( $c['tags'] as $name ) {
			$slug    = sanitize_title( $name );
			$existed = \Jetonomy\Models\Tag::exists( $slug );
			$tid     = \Jetonomy\Models\Tag::find_or_create( $name );
			if ( $tid > 0 ) {
				$tags[] = $tid;
				if ( ! $existed ) {
					$ctx->record( $this->id(), 'tag', $tid, 'setup' );
				}
			}
		}
		$ctx->set( $this->id(), 'tags', $tags );
	}

	/**
	 * Five discussions with replies and accepted answers.
	 *
	 * @param int     $offset Offset.
	 * @param Context $ctx    Context.
	 */
	private function topics( int $offset, Context $ctx ): void {
		$c      = $this->content();
		$spaces = (array) $ctx->get( $this->id(), 'spaces', array() );
		$tags   = (array) $ctx->get( $this->id(), 'tags', array() );
		$people = $ctx->people();
		$n      = count( $people );
		$total  = count( $c['topics'] );
		foreach ( array_slice( $c['topics'], $offset, self::TOPICS_PER_STEP, true ) as $i => $t ) {
			// Replies: a count drawn from the shared pool, or the topic's own lines. Accepted: the answer text or false.
			list( $space, $who, $title, $body, $replies, $accepted ) = $t;
			$lines = is_array( $replies ) ? $replies : array();
			$count = $lines ? count( $lines ) : (int) $replies;
			if ( empty( $spaces[ $space ] ) ) {
				continue;
			}
			$author = $ctx->person( $who );
			$date   = $ctx->date( ( $i * 7 ) % $total, $total, 50 );
			$type   = match ( $spaces[ $space ]['type'] ) {
				'qa'    => 'question',
				'ideas' => 'idea',
				'feed'  => 'status',
				default => 'topic',
			};
			// Bridges (the BuddyNext feed post for a new discussion) get the discussion's date.
			Clock::set( $date );
			$post_id = \Jetonomy\Models\Post::create(
				array(
					'space_id'      => (int) $spaces[ $space ]['id'],
					'author_id'     => $author,
					'type'          => $type,
					'title'         => $title,
					'slug'          => sanitize_title( $title ) . '-' . ( $i + 1 ),
					'content'       => wpautop( $body ),
					'content_plain' => $body,
					'status'        => 'publish',
					'created_at'    => $date,
				)
			);
			Clock::reset();
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$ctx->warn( $this->id(), 'discussion ' . $i, is_wp_error( $post_id ) ? $post_id : 'not created' );
				continue;
			}
			$post_id = (int) $post_id;
			$ctx->record( $this->id(), 'post', $post_id, 'sample', array( 'author' => $author ) );
			foreach ( array_slice( $tags, $i % max( 1, count( $tags ) ), 1 + $i % 2 ) as $tid ) {
				\Jetonomy\Models\Tag::attach_to_post( $post_id, (int) $tid );
			}
			if ( 'idea' === $type && $i % 2 ) {
				\Jetonomy\Models\Post::set_idea_status( $post_id, array( 'planned', 'in_progress' )[ $i % 2 ] );
			}
			$last = $date;
			for ( $r = 0; $r < $count; $r++ ) {
				$text = $lines ? $lines[ $r ] : $c['replies'][ ( $i + $r ) % count( $c['replies'] ) ];
				$last = $ctx->after( $last, $i + $r );
				$rid  = \Jetonomy\Models\Reply::create(
					array(
						'post_id'       => $post_id,
						'author_id'     => $people[ ( $who + $r + 1 ) % $n ],
						'content'       => wpautop( $text ),
						'content_plain' => $text,
						'status'        => 'publish',
						'created_at'    => $last,
					)
				);
				if ( is_wp_error( $rid ) ) {
					$ctx->warn( $this->id(), 'reply', $rid );
				}
			}
			$active = (array) $ctx->get( $this->id(), 'active', array() );
			if ( is_string( $accepted ) && '' !== $accepted ) {
				$body = $accepted;
				$aid  = \Jetonomy\Models\Reply::create(
					array(
						'post_id'       => $post_id,
						'author_id'     => $people[ ( $who + 7 ) % $n ],
						'content'       => wpautop( $body ),
						'content_plain' => $body,
						'status'        => 'publish',
						'created_at'    => $ctx->after( $last, $i ),
					)
				);
				if ( ! is_wp_error( $aid ) && $aid ) {
					\Jetonomy\Models\Reply::mark_accepted( (int) $aid );
					\Jetonomy\Models\Post::accept_reply( $post_id, (int) $aid );
					$last = $ctx->after( $last, $i );
				}
			}
			// Replies made "now" would mark every discussion as active seconds ago.
			\Jetonomy\Models\Post::update( $post_id, array( 'last_reply_at' => $last ) );
			$sid            = (int) $spaces[ $space ]['id'];
			$active[ $sid ] = max( (string) ( $active[ $sid ] ?? '' ), $last );
			$ctx->set( $this->id(), 'active', $active );
		}
	}

	/**
	 * Votes on discussions, then a recount so every counter is right.
	 *
	 * @param Context $ctx Context.
	 */
	private function votes( Context $ctx ): void {
		$people = $ctx->people();
		$n      = count( $people );
		foreach ( Ledger::ids( $this->id(), 'post' ) as $k => $post_id ) {
			$post   = \Jetonomy\Models\Post::find( $post_id );
			$author = $post ? (int) $post->author_id : 0;
			$votes  = 2 + ( $k * 5 ) % 9;
			for ( $v = 0; $v < $votes; $v++ ) {
				$result = \Jetonomy\Models\Vote::cast( $people[ ( $k + $v * 3 ) % $n ], 'post', $post_id, 1 );
				// The vote REST route awards the author's reputation, the model does not; do what the route does
				// so the community leaderboards rank the sample people.
				if ( $author && is_array( $result ) && 'created' === $result['action'] ) {
					\Jetonomy\Trust\Reputation::award(
						$author,
						'post_upvoted',
						array(
							'object_type' => 'post',
							'object_id'   => $post_id,
						)
					);
				}
			}
		}
		if ( class_exists( '\Jetonomy\Recount' ) ) {
			\Jetonomy\Recount::run( 'all' );
		}
		foreach ( (array) $ctx->get( $this->id(), 'active', array() ) as $sid => $when ) {
			\Jetonomy\Models\Space::update( (int) $sid, array( 'last_activity_at' => $when ) );
		}
		delete_transient( 'jetonomy_community_pulse' );
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		switch ( $row->object_type ) {
			case 'post':
				if ( ! \Jetonomy\Models\Post::find( $id ) ) {
					return true;
				}
				return ! is_wp_error( \Jetonomy\Models\Post::delete( $id ) );
			case 'space':
				if ( ! \Jetonomy\Models\Space::find( $id ) ) {
					return true;
				}
				return ! is_wp_error( \Jetonomy\Models\Space::delete( $id ) );
			case 'category':
				if ( ! \Jetonomy\Models\Category::find( $id ) ) {
					return true;
				}
				return ! is_wp_error( \Jetonomy\Models\Category::delete( $id ) );
			case 'tag':
				\Jetonomy\Models\Tag::delete_with_relations( $id );
				return true;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'tag' );
	}

	/**
	 * Menu item.
	 */
	public function menu_items(): array {
		return function_exists( '\Jetonomy\base_url' )
			? array(
				array(
					'title' => __( 'Forums', 'wbcom-demo-installer' ),
					'url'   => \Jetonomy\base_url(),
				),
			)
			: array();
	}
}
