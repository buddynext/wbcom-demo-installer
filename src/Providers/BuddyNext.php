<?php
/**
 * BuddyNext: profiles, spaces, feed, polls, photos, albums, messages and the social graph.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Clock;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\State;

defined( 'ABSPATH' ) || exit;

/**
 * BuddyNext provider. Uses BuddyNext services only.
 */
final class BuddyNext extends Provider {

	private const POSTS_PER_STEP = 10;

	/** Employers for profile work history. */
	private const COMPANIES = array( 'Nomad Studio', 'Hyperlane', 'Northwind Labs', 'Paperplane', 'Bluefin Data', 'Pixel Harbor', 'Tidewater Institute', 'Roastworks', 'Clearword', 'Lumen Cloud' );

	/**
	 * Content.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $content = null;

	public function id(): string {
		return 'buddynext';
	}

	public function label(): string {
		return 'BuddyNext';
	}

	public function available(): bool {
		return defined( 'BUDDYNEXT_VERSION' ) && class_exists( '\BuddyNext\Feed\PostService' );
	}

	public function version(): string {
		return defined( 'BUDDYNEXT_VERSION' ) ? (string) BUDDYNEXT_VERSION : '';
	}

	public function min_version(): string {
		return '1.2.8';
	}

	public function icon(): string {
		return 'users';
	}

	public function order(): int {
		return 10;
	}

	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: spaces, 2: posts */
			__( 'Profiles for everyone, %1$d spaces, %2$d posts with comments and reactions, follows, a poll and direct messages', 'wbcom-demo-installer' ),
			count( $c['spaces'] ) + count( $c['subspaces'] ),
			count( $c['posts'] ) + count( $c['links'] ) + count( $c['photos'] ) + 1
		);
	}

	/**
	 * Content file.
	 *
	 * @return array<string,mixed>
	 */
	private function content(): array {
		return $this->content ??= require WBCOM_DEMO_DIR . 'content/buddynext.php';
	}

	public function plan(): array {
		$c = $this->content();
		return array(
			'space_category' => array( count( $c['categories'] ), 'setup', __( 'space categories', 'wbcom-demo-installer' ) ),
			'space'          => array( count( $c['spaces'] ) + count( $c['subspaces'] ), 'setup', __( 'spaces', 'wbcom-demo-installer' ) ),
			'post'           => array( count( $c['posts'] ) + count( $c['links'] ) + count( $c['photos'] ) + 1, 'sample', __( 'feed posts', 'wbcom-demo-installer' ) ),
			'media'          => array( count( $c['photos'] ) + 6, 'sample', __( 'photos', 'wbcom-demo-installer' ) ),
			'album'          => array( count( $c['albums'] ), 'sample', __( 'space albums', 'wbcom-demo-installer' ) ),
			'profile_images' => array( 20, 'sample', __( 'profile pictures', 'wbcom-demo-installer' ) ),
			'hashtag'        => array( 0, 'sample', __( 'hashtags', 'wbcom-demo-installer' ) ),
		);
	}

	public function steps( Context $ctx ): array {
		$steps = array( 'profiles:0', 'profiles:10', 'spaces' );
		$n     = count( $this->content()['posts'] );
		for ( $i = 0; $i < $n; $i += self::POSTS_PER_STEP ) {
			$steps[] = 'posts:' . $i;
		}
		return array_merge( $steps, array( 'extras', 'media', 'social', 'hashtags' ) );
	}

	/**
	 * Capture every feed post created during a build (ours and the ones other products' bridges make),
	 * and give bridged posts the date of the item that caused them.
	 *
	 * @param Context $ctx Context.
	 */
	public function boot( Context $ctx ): void {
		if ( State::BUILDING !== $ctx->state->get( 'status' ) || ! $this->available() || ! $ctx->includes( $this->id() ) ) {
			return;
		}
		add_filter(
			'buddynext_post_before_save',
			static function ( $data ) {
				if ( is_array( $data ) && empty( $data['created_at'] ) && Clock::now() ) {
					$data['created_at'] = Clock::now();
				}
				return $data;
			},
			5
		);
		add_action(
			'buddynext_post_created',
			function ( $post_id, $user_id ) use ( $ctx ) {
				$ctx->record( $this->id(), 'post', (int) $post_id, 'sample', array( 'author' => (int) $user_id ), Clock::parent() );
			},
			1,
			2
		);
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
			'profiles' => $this->profiles( (int) $arg, $ctx ),
			'spaces'   => $this->spaces( $ctx ),
			'posts'    => $this->posts( (int) $arg, $ctx ),
			'extras'   => $this->extras( $ctx ),
			'media'    => $this->media( $ctx ),
			'social'   => $this->social( $ctx ),
			'hashtags' => $this->hashtags( $ctx ),
			default    => null,
		};
	}

	/**
	 * Profiles, avatars and covers for ten people.
	 *
	 * @param int     $offset Offset.
	 * @param Context $ctx    Context.
	 */
	private function profiles( int $offset, Context $ctx ): void {
		$profiles = new \BuddyNext\Profile\ProfileService();
		$storage  = new \BuddyNext\Media\ImageStorageService();
		$avatars  = new \BuddyNext\Profile\AvatarService();
		foreach ( array_slice( $ctx->people(), $offset, 10 ) as $user_id ) {
			$p = $ctx->profile( $user_id );
			update_user_meta( $user_id, 'bn_headline', $p['headline'] );
			update_user_meta( $user_id, 'bn_onboarding_complete', '1' );
			update_user_meta( $user_id, 'buddynext_email_verified', '1' );
			$profiles->save_profile(
				$user_id,
				array(
					'headline'   => $p['headline'],
					'bio'        => sprintf( '%s based in %s. Into %s.', $p['job'], $p['location'], $p['note'] ),
					'location'   => $p['location'],
					'work_title'      => $p['job'],
					'work_company'    => self::COMPANIES[ $p['index'] % count( self::COMPANIES ) ],
					'work_location'   => $p['location'],
					'work_current'    => '1',
					'work_start_date' => gmdate( 'Y-m-d', strtotime( '-' . ( 1 + $p['index'] % 6 ) . ' years' ) ),
					'edu_institution' => $p['school'],
					'edu_degree'      => $p['degree'],
					'edu_field'       => $p['field'],
					'edu_end_year'    => (string) ( 2008 + $p['index'] % 14 ),
					'edu_current'     => '0',
					'skills'          => $p['skills'],
					'social_linkedin' => 'https://www.linkedin.com/in/' . str_replace( '.', '', $p['login'] ),
					'social_twitter'  => 0 === $p['index'] % 3 ? '' : 'https://x.com/' . str_replace( '.', '', $p['login'] ),
				)
			);
			$url = $storage->store( $ctx->media( $p['avatar'] ), 'avatar', 'user', $user_id );
			if ( ! is_wp_error( $url ) ) {
				$avatars->save_avatar_url( $user_id, esc_url_raw( (string) $url ) );
			}
			$url = $storage->store( $ctx->media( $p['cover'] ), 'cover', 'user', $user_id );
			if ( ! is_wp_error( $url ) ) {
				$avatars->save_cover_url( $user_id, (string) $url );
			}
			$ctx->record( $this->id(), 'profile_images', $user_id );
		}
	}

	/**
	 * Space categories, spaces, sub-spaces and memberships.
	 *
	 * @param Context $ctx Context.
	 */
	private function spaces( Context $ctx ): void {
		$c       = $this->content();
		$cats    = new \BuddyNext\Spaces\SpaceCategoryService();
		$spaces  = new \BuddyNext\Spaces\SpaceService();
		$members = new \BuddyNext\Spaces\SpaceMemberService();
		$storage = new \BuddyNext\Media\ImageStorageService();

		$cat_ids = array();
		foreach ( $cats->get_all() as $cat ) {
			$cat_ids[ (string) $cat['slug'] ] = (int) $cat['id'];
		}
		foreach ( $c['categories'] as $slug => $name ) {
			if ( isset( $cat_ids[ $slug ] ) ) {
				continue;
			}
			$id = $cats->create( array( 'name' => $name, 'slug' => $slug ) );
			if ( ! is_wp_error( $id ) ) {
				$cat_ids[ $slug ] = (int) $id;
				$ctx->record( $this->id(), 'space_category', (int) $id, 'setup' );
			}
		}

		$people = $ctx->people();
		$n      = count( $people );
		$by     = array();
		$all    = array_merge( $c['spaces'], $c['subspaces'] );
		foreach ( $all as $i => $s ) {
			$owner = isset( $s['owner'] ) ? $ctx->person( $s['owner'] ) : $ctx->person( $i + 3 );
			$data  = array(
				'name'        => $s['name'],
				'slug'        => $s['slug'],
				'type'        => $s['type'],
				'description' => $s['desc'],
			);
			if ( ! empty( $s['category'] ) ) {
				$data['category_id'] = $cat_ids[ $s['category'] ] ?? 0;
			}
			if ( ! empty( $s['parent'] ) ) {
				if ( empty( $by[ $s['parent'] ] ) ) {
					continue;
				}
				$data['parent_id'] = $by[ $s['parent'] ];
			}
			Clock::set( $ctx->date( $i, count( $all ) + 4, 90 ) );
			$id = $spaces->create( $owner, $data );
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$id          = (int) $id;
			$by[ $s['slug'] ] = $id;
			$ctx->record( $this->id(), 'space', $id, 'setup', array( 'owner' => $owner ) );

			$avatar = $storage->store( $ctx->media( sprintf( 'avatars/avatar-%02d.png', ( ( $i + 4 ) % 12 ) + 1 ) ), 'avatar', 'space', $id );
			$cover  = $storage->store( $ctx->media( sprintf( 'covers/cover-%02d.png', ( ( $i + 3 ) % 8 ) + 1 ) ), 'cover', 'space', $id );
			$spaces->update(
				$id,
				$owner,
				array_filter(
					array(
						'avatar_url'      => is_wp_error( $avatar ) ? '' : $storage->variation_url( 'avatar', 'space', $id, 'full' ),
						'cover_image_url' => is_wp_error( $cover ) ? '' : $storage->variation_url( 'cover', 'space', $id, 'full' ),
					)
				)
			);

			if ( 'secret' === $s['type'] ) {
				continue;
			}
			$want   = (int) ( $s['members'] ?? (int) ( $n / 3 ) );
			$joined = 0;
			for ( $k = 0; $k < $n && $joined < $want; $k++ ) {
				$uid = $people[ ( $i + $k ) % $n ];
				if ( $uid === $owner ) {
					continue;
				}
				$members->join( $id, $uid, $ctx->date( $i + $k, $n + count( $all ), 80 ) );
				++$joined;
			}
		}
		Clock::reset();
		$ctx->set( $this->id(), 'spaces', $by );

		$profiles = new \BuddyNext\Profile\ProfileService();
		foreach ( $ctx->people() as $uid ) {
			$ids = array();
			foreach ( (array) ( $ctx->profile( $uid )['topics'] ?? array() ) as $slug ) {
				if ( isset( $cat_ids[ $slug ] ) ) {
					$ids[] = $cat_ids[ $slug ];
				}
			}
			if ( $ids ) {
				$profiles->save_profile( $uid, array( 'interests' => $ids ) );
			}
		}
	}

	/**
	 * Ten feed posts with comments and reactions.
	 *
	 * @param int     $offset Offset.
	 * @param Context $ctx    Context.
	 */
	private function posts( int $offset, Context $ctx ): void {
		$c      = $this->content();
		$total  = count( $c['posts'] );
		$spaces = (array) $ctx->get( $this->id(), 'spaces', array() );
		$svc    = new \BuddyNext\Feed\PostService();
		foreach ( array_slice( $c['posts'], $offset, self::POSTS_PER_STEP, true ) as $i => $row ) {
			list( $who, $space, $text ) = $row;
			$author = $ctx->person( $who );
			$date   = $ctx->date( $i, $total, 45 );
			$data   = array(
				'type'       => 'text',
				'content'    => $text,
				'created_at' => $date,
			);
			if ( $space && ! empty( $spaces[ $space ] ) ) {
				$data['space_id'] = (int) $spaces[ $space ];
				$this->ensure_member( (int) $spaces[ $space ], $author, $date );
			}
			$id = $svc->create( $author, $data );
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'post ' . $i, $id );
				continue;
			}
			$ctx->record( $this->id(), 'post', (int) $id, 'sample', array( 'author' => $author ) );
			$this->engage( (int) $id, $i, $date, $ctx );
		}
	}

	/**
	 * Authors join a space before posting in it, as a real member would.
	 *
	 * @param int    $space_id Space.
	 * @param int    $user_id  Author.
	 * @param string $date     Post date (they joined a little before).
	 */
	private function ensure_member( int $space_id, int $user_id, string $date ): void {
		$members = new \BuddyNext\Spaces\SpaceMemberService();
		if ( method_exists( $members, 'is_member' ) && $members->is_member( $space_id, $user_id ) ) {
			return;
		}
		$members->join( $space_id, $user_id, gmdate( 'Y-m-d H:i:s', strtotime( $date . ' UTC' ) - DAY_IN_SECONDS ) );
	}

	/**
	 * Comments and reactions on one post, varied so Top and Active sorts have something to rank.
	 *
	 * @param int     $post_id Post.
	 * @param int     $seq     Sequence.
	 * @param string  $date    Post date.
	 * @param Context $ctx     Context.
	 */
	private function engage( int $post_id, int $seq, string $date, Context $ctx ): void {
		$c         = $this->content();
		$people    = $ctx->people();
		$n         = count( $people );
		$comments  = new \BuddyNext\Comments\CommentService();
		$reactions = new \BuddyNext\Reactions\ReactionService();
		$emoji     = array( 'like', 'love', 'haha', 'wow', 'like', 'love' );
		$busy      = 0 === $seq % 4;
		$quiet     = 0 === $seq % 7;
		$cn        = $quiet ? 0 : ( $busy ? 4 : 1 + $seq % 2 );
		$rn        = $quiet ? 1 : ( $busy ? 9 : 2 + $seq % 4 );
		for ( $k = 0; $k < $cn; $k++ ) {
			$comments->create( $people[ ( $seq + $k + 1 ) % $n ], 'post', $post_id, $c['comments'][ ( $seq + $k ) % count( $c['comments'] ) ], null, $ctx->after( $date, $seq + $k ) );
		}
		for ( $k = 0; $k < $rn; $k++ ) {
			$reactions->react( $people[ ( $seq + $k + 2 ) % $n ], 'post', $post_id, $emoji[ ( $seq + $k ) % count( $emoji ) ], $ctx->after( $date, $seq * 3 + $k ) );
		}
	}

	/**
	 * Poll, link posts and reshares.
	 *
	 * @param Context $ctx Context.
	 */
	private function extras( Context $ctx ): void {
		$c      = $this->content();
		$svc    = new \BuddyNext\Feed\PostService();
		$spaces = (array) $ctx->get( $this->id(), 'spaces', array() );

		$author = $ctx->person( $c['poll']['author'] );
		$date   = $ctx->date( 18, 20, 20 );
		$id     = $svc->create(
			$author,
			array(
				'type'       => 'poll',
				'content'    => $c['poll']['question'],
				'options'    => $c['poll']['options'],
				'created_at' => $date,
			)
		);
		if ( ! is_wp_error( $id ) ) {
			$ctx->record( $this->id(), 'post', (int) $id, 'sample', array( 'author' => $author ) );
			$this->engage( (int) $id, 8, $date, $ctx );
			if ( class_exists( '\BuddyNext\Feed\PollService' ) ) {
				$polls   = new \BuddyNext\Feed\PollService();
				$options = array_column( $polls->results( (int) $id ), 'id' );
				foreach ( $ctx->people() as $k => $voter ) {
					// Uneven spread, a clear winner, a few people who did not vote.
					if ( $options && 0 !== $k % 5 ) {
						$polls->vote( $voter, (int) $id, (int) $options[ array( 0, 1, 0, 2, 0, 3, 1 )[ $k % 7 ] % count( $options ) ] );
					}
				}
			}
		}

		foreach ( $c['links'] as $i => $row ) {
			list( $who, $space, $text, $url ) = $row;
			$author = $ctx->person( $who );
			$date   = $ctx->date( 5 + $i * 7, 20, 40 );
			$data   = array(
				'type'       => 'link',
				'content'    => $text,
				'link_url'   => $url,
				'created_at' => $date,
			);
			if ( $space && ! empty( $spaces[ $space ] ) ) {
				$data['space_id'] = (int) $spaces[ $space ];
			}
			$id = $svc->create( $author, $data );
			if ( ! is_wp_error( $id ) ) {
				$ctx->record( $this->id(), 'post', (int) $id, 'sample', array( 'author' => $author ) );
				$this->engage( (int) $id, 20 + $i, $date, $ctx );
			}
		}

		if ( class_exists( '\BuddyNext\Feed\ShareService' ) ) {
			$share = new \BuddyNext\Feed\ShareService();
			$ids   = Ledger::ids( $this->id(), 'post' );
			foreach ( array( array( 3, 1, 'Worth a look, sharing with the group.' ), array( 6, 4, 'Resharing this, great thread.' ), array( 13, 9, '' ) ) as $k => $spec ) {
				if ( empty( $ids[ $spec[1] ] ) ) {
					continue;
				}
				$sharer = $ctx->person( $spec[0] );
				$sid    = $share->share( $sharer, $ids[ $spec[1] ], $spec[2], $ctx->date( 30 + $k * 4, 44, 45 ) );
				if ( is_wp_error( $sid ) ) {
					$ctx->warn( $this->id(), 'share', $sid );
				} else {
					$ctx->record( $this->id(), 'post', (int) $sid, 'sample', array( 'author' => $sharer ) );
				}
			}
		}
	}

	/**
	 * Photo posts and space albums (needs WPMediaVerse).
	 *
	 * @param Context $ctx Context.
	 */
	private function media( Context $ctx ): void {
		if ( ! class_exists( '\BuddyNext\Media\MediaClient' ) || ! \BuddyNext\Media\MediaClient::available() ) {
			return;
		}
		$c        = $this->content();
		$uploader = \BuddyNext\Media\MediaClient::upload();
		if ( ! is_object( $uploader ) || ! method_exists( $uploader, 'handle' ) ) {
			return;
		}
		$svc    = new \BuddyNext\Feed\PostService();
		$spaces = (array) $ctx->get( $this->id(), 'spaces', array() );

		foreach ( $c['photos'] as $i => $row ) {
			list( $who, $space, $text, $img ) = $row;
			$author   = $ctx->person( $who );
			$media_id = $this->upload( $uploader, $img, $author, $ctx );
			if ( ! $media_id ) {
				continue;
			}
			$date = $ctx->date( 10 + $i * 9, 50, 40 );
			$data = array(
				'type'       => 'photo',
				'content'    => $text,
				'media_ids'  => array( $media_id ),
				'created_at' => $date,
			);
			if ( $space && ! empty( $spaces[ $space ] ) ) {
				$data['space_id'] = (int) $spaces[ $space ];
			}
			$id = $svc->create( $author, $data );
			if ( ! is_wp_error( $id ) ) {
				$ctx->record( $this->id(), 'post', (int) $id, 'sample', array( 'author' => $author ) );
				$this->engage( (int) $id, 30 + $i, $date, $ctx );
			}
		}

		$albums = \BuddyNext\Media\MediaClient::albums();
		if ( ! is_object( $albums ) || ! method_exists( $albums, 'create' ) ) {
			return;
		}
		$space_svc = new \BuddyNext\Spaces\SpaceService();
		foreach ( $c['albums'] as $a ) {
			$space_id = (int) ( $spaces[ $a['space'] ] ?? 0 );
			if ( ! $space_id ) {
				continue;
			}
			$owner = (int) ( $space_svc->get( $space_id )['owner_id'] ?? 0 );
			$items = array();
			foreach ( $a['images'] as $img ) {
				$mid = $this->upload( $uploader, $img, $owner, $ctx );
				if ( $mid ) {
					$items[] = $mid;
				}
			}
			if ( ! $items ) {
				continue;
			}
			$album_id = $albums->create(
				$owner,
				array(
					'title'       => $a['title'],
					'description' => $a['desc'],
					'privacy'     => 'public',
				)
			);
			if ( is_wp_error( $album_id ) || (int) $album_id <= 0 ) {
				continue;
			}
			$album_id = (int) $album_id;
			$albums->add_items( $album_id, $items );
			if ( method_exists( $albums, 'set_cover' ) ) {
				$albums->set_cover( $album_id, $items[0] );
			}
			update_space_meta( $space_id, 'mvs_media_tab', '1' );
			\BuddyNext\Media\Galleries::assign_album_to_space( $album_id, $space_id );
			$ctx->record( $this->id(), 'album', $album_id );
		}
	}

	/**
	 * Upload one media pack image through the WPMediaVerse upload path.
	 *
	 * @param object  $uploader Upload service.
	 * @param string  $img      Path under media/.
	 * @param int     $author   Owner.
	 * @param Context $ctx      Context.
	 */
	private function upload( object $uploader, string $img, int $author, Context $ctx ): int {
		$tmp = $ctx->temp_copy( $img );
		if ( ! $tmp ) {
			return 0;
		}
		$was = get_current_user_id();
		wp_set_current_user( $author );
		$id = $uploader->handle(
			array(
				'tmp_name' => $tmp,
				'name'     => basename( $img ),
				'type'     => 'image/png',
				'size'     => (int) filesize( $tmp ),
				'error'    => 0,
			),
			$author,
			array()
		);
		wp_set_current_user( $was );
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( is_wp_error( $id ) || (int) $id <= 0 ) {
			return 0;
		}
		$ctx->record( $this->id(), 'media', (int) $id );
		return (int) $id;
	}

	/**
	 * Follows, connections, bookmarks and direct messages between sample people only.
	 *
	 * @param Context $ctx Context.
	 */
	private function social( Context $ctx ): void {
		$people  = $ctx->people();
		$n       = count( $people );
		$follows = new \BuddyNext\SocialGraph\FollowService();
		$conns   = new \BuddyNext\SocialGraph\ConnectionService();
		foreach ( $people as $i => $uid ) {
			foreach ( array( 1, 2, 5 ) as $k => $hop ) {
				$follows->follow( $uid, $people[ ( $i + $hop ) % $n ], $ctx->date( $i * 3 + $k, $n * 3, 60 ) );
			}
			$other = $people[ ( $i + 3 ) % $n ];
			if ( true === $conns->send_request( $uid, $other, '', $ctx->date( $i, $n, 50 ) ) ) {
				$conns->accept_request( $other, $uid );
			}
		}

		if ( class_exists( '\BuddyNext\Feed\BookmarkService' ) ) {
			$bookmarks = new \BuddyNext\Feed\BookmarkService();
			$posts     = Ledger::ids( $this->id(), 'post' );
			if ( $posts ) {
				foreach ( $people as $i => $uid ) {
					for ( $b = 0; $b < 3; $b++ ) {
						$bookmarks->bookmark( $uid, $posts[ ( $i * 2 + $b ) % count( $posts ) ] );
					}
				}
			}
		}

		$messaging = class_exists( '\BuddyNext\Media\MediaClient' ) ? \BuddyNext\Media\MediaClient::messaging() : null;
		if ( is_object( $messaging ) && method_exists( $messaging, 'find_or_create_conversation' ) ) {
			foreach ( $this->content()['threads'] as $t ) {
				list( $a, $b, $messages ) = $t;
				$a    = $ctx->person( $a );
				$b    = $ctx->person( $b );
				$conv = $messaging->find_or_create_conversation( $a, $b );
				$cid  = is_array( $conv ) ? (int) ( $conv['conversation_id'] ?? 0 ) : 0;
				if ( ! $cid ) {
					continue;
				}
				foreach ( $messages as $k => $body ) {
					$messaging->send_message( $cid, 0 === $k % 2 ? $a : $b, array( 'content' => $body ) );
				}
			}
		}
	}

	/**
	 * Index hashtags now (normally deferred to Action Scheduler) and record tags this build introduced.
	 *
	 * @param Context $ctx Context.
	 */
	private function hashtags( Context $ctx ): void {
		if ( ! function_exists( 'buddynext_feature_enabled' ) || ! buddynext_feature_enabled( 'hashtags' ) ) {
			return;
		}
		$svc  = new \BuddyNext\Hashtags\HashtagService();
		$tags = array();
		foreach ( $this->content()['posts'] as $row ) {
			foreach ( $svc->extract_from_text( $row[2] ) as $slug ) {
				$tags[ $slug ] = null === $svc->get_by_slug( $slug );
			}
		}
		$indexer = new \BuddyNext\Hashtags\HashtagListener( $svc );
		foreach ( Ledger::ids( $this->id(), 'post' ) as $pid ) {
			$indexer->async_index_hashtags( 'post', (int) $pid, '' );
		}
		foreach ( $tags as $slug => $is_new ) {
			$tag = $is_new ? $svc->get_by_slug( $slug ) : null;
			if ( $tag && ! empty( $tag['id'] ) ) {
				$ctx->record( $this->id(), 'hashtag', (int) $tag['id'], 'sample', array( 'slug' => $slug ) );
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
			case 'post':
				$svc = new \BuddyNext\Feed\PostService();
				if ( ! $svc->get( $id ) ) {
					return true;
				}
				$author = (int) ( $row->meta['author'] ?? 0 );
				$result = $svc->delete( $id, $author ?: get_current_user_id() );
				return ! is_wp_error( $result ) || ! $svc->get( $id );
			case 'media':
				$repo = \BuddyNext\Media\MediaClient::available() ? \BuddyNext\Media\MediaClient::repo() : null;
				if ( is_object( $repo ) && method_exists( $repo, 'delete_cascade' ) ) {
					$repo->delete_cascade( $id );
				}
				return true;
			case 'album':
				if ( 'mvs_album' === get_post_type( $id ) ) {
					wp_delete_post( $id, true );
				}
				return true;
			case 'space':
				$svc   = new \BuddyNext\Spaces\SpaceService();
				$space = $svc->get( $id );
				if ( ! $space ) {
					return true;
				}
				$storage = new \BuddyNext\Media\ImageStorageService();
				$storage->delete( 'avatar', 'space', $id );
				$storage->delete( 'cover', 'space', $id );
				$result = $svc->delete( $id, (int) $space['owner_id'] );
				return ! is_wp_error( $result );
			case 'space_category':
				$result = ( new \BuddyNext\Spaces\SpaceCategoryService() )->delete( $id );
				return ! is_wp_error( $result );
			case 'profile_images':
				$storage = new \BuddyNext\Media\ImageStorageService();
				$storage->delete( 'avatar', 'user', $id );
				$storage->delete( 'cover', 'user', $id );
				return true;
			case 'hashtag':
				return $this->remove_hashtag( $id, (string) ( $row->meta['slug'] ?? '' ) );
		}
		return true;
	}

	public function late_types(): array {
		return array( 'hashtag' );
	}

	/**
	 * Kept spaces get the site owner as owner before their sample owners are removed.
	 *
	 * @param string[] $kept     Kept keys.
	 * @param int      $owner_id Site owner.
	 */
	public function handover( array $kept, int $owner_id ): void {
		if ( ! in_array( 'buddynext:space', $kept, true ) ) {
			return;
		}
		$svc = new \BuddyNext\Spaces\SpaceService();
		foreach ( Ledger::ids( $this->id(), 'space' ) as $space_id ) {
			$space = $svc->get( $space_id );
			if ( $space && (int) $space['owner_id'] !== $owner_id && get_user_meta( (int) $space['owner_id'], People::FLAG, true ) ) {
				$svc->assign_owner( $space_id, $owner_id, $owner_id );
			}
		}
	}

	/**
	 * Remove a hashtag this build introduced, only when nothing uses or follows it.
	 *
	 * BuddyNext has no public delete for a hashtag registry row yet (card filed), so this deletes
	 * exactly this id and nothing else.
	 *
	 * @param int    $id   Hashtag id.
	 * @param string $slug Slug.
	 */
	private function remove_hashtag( int $id, string $slug ): bool {
		wp_cache_delete( 'hashtag_' . $slug, 'buddynext_hashtags' );
		$svc = new \BuddyNext\Hashtags\HashtagService();
		$svc->recount( array( $id ) );
		wp_cache_delete( 'hashtag_' . $slug, 'buddynext_hashtags' );
		$tag = $slug ? $svc->get_by_slug( $slug ) : null;
		if ( ! $tag || (int) $tag['id'] !== $id ) {
			return true;
		}
		if ( (int) ( $tag['post_count'] ?? 0 ) > 0 || (int) ( $tag['follower_count'] ?? 0 ) > 0 ) {
			return true; // Real members use it now: it stays.
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'bn_hashtags', array( 'id' => $id ), array( '%d' ) );
		wp_cache_delete( 'hashtag_' . $slug, 'buddynext_hashtags' );
		return true;
	}

	/**
	 * Pages for the menu.
	 */
	public function menu_items(): array {
		$items = array();
		foreach ( array(
			'buddynext_page_activity' => __( 'Activity', 'wbcom-demo-installer' ),
			'buddynext_page_people'   => __( 'Members', 'wbcom-demo-installer' ),
			'buddynext_page_spaces'   => __( 'Spaces', 'wbcom-demo-installer' ),
		) as $option => $title ) {
			$page = (int) get_option( $option );
			if ( $page && 'publish' === get_post_status( $page ) ) {
				$items[] = array(
					'title'   => $title,
					'page_id' => $page,
				);
			}
		}
		return $items;
	}

	public function front_page(): int {
		$page = (int) get_option( 'buddynext_page_activity' );
		return $page && 'publish' === get_post_status( $page ) ? $page : 0;
	}
}
