<?php
/**
 * WPMediaVerse: a photo library across the sample people, albums and favorites.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Clock;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * MediaVerse provider. Uses the MediaVerse container services only.
 */
final class MediaVerse extends Provider {

	private const PER_STEP = 8;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'mediaverse';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WPMediaVerse';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'MVS_VERSION' ) && class_exists( '\WPMediaVerse\Core\Plugin' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'MVS_VERSION' ) ? (string) MVS_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '2.6.4'; // Latest release; the site check updates an older copy.
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'image';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 20;
	}

	/**
	 * {@inheritDoc}
	 */
	public function keeps_logs(): string {
		return __( 'WPMediaVerse keeps its usage ledger as an accounting record, with the sample people removed from it.', 'wbcom-demo-installer' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function ai_skip_keys(): array {
		return array( 'photos', 'tags' ); // Library photos: their titles describe the pictures.
	}

	/**
	 * {@inheritDoc}
	 */
	public function pro_label(): string {
		return defined( 'MVS_PRO_VERSION' ) ? __( 'Pro add-on active', 'wbcom-demo-installer' ) : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: photos */
			__( '%d photos with titles and tags across the sample people, 3 albums and favorites', 'wbcom-demo-installer' ),
			count( $this->content()['photos'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( 'mvs_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function settings(): array {
		return array(
			'mvs_setup_complete' => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$photos = $this->content()['photos'];
		return array(
			'category' => array( count( array_unique( array_column( $photos, 'category' ) ) ), 'setup', __( 'media categories', 'wbcom-demo-installer' ) ),
			'tag'      => array( count( array_unique( array_merge( ...array_column( $photos, 'tags' ) ) ) ), 'sample', __( 'media tags', 'wbcom-demo-installer' ) ),
			'media'    => array( count( $photos ), 'sample', __( 'photos', 'wbcom-demo-installer' ) ),
			'album'    => array( 3, 'sample', __( 'albums', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		return array_merge( self::batches( 'photos', count( $this->content()['photos'] ), self::PER_STEP ), array( 'albums' ) );
	}

	/**
	 * Container service.
	 *
	 * @param string $id Service id.
	 * @return object|null
	 */
	private function service( string $id ): ?object {
		try {
			$svc = \WPMediaVerse\Core\Plugin::container()->get( $id );
			return is_object( $svc ) ? $svc : null;
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Run a step.
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 * @throws \RuntimeException When the WPMediaVerse upload service is missing.
	 */
	public function run( string $step, Context $ctx ): void {
		if ( 'albums' === $step ) {
			$this->albums( $ctx );
			return;
		}
		$offset = (int) substr( $step, 7 );
		$upload = $this->service( 'upload' );
		$repo   = $this->service( 'media_repository' );
		$favs   = $this->service( 'favorites' );
		if ( ! $upload ) {
			throw new \RuntimeException( 'WPMediaVerse upload service is not available.' );
		}
		$photos = $this->content()['photos'];
		$people = $ctx->people();
		$by_cat = (array) $ctx->get( $this->id(), 'by_category', array() );
		foreach ( array_slice( $photos, $offset, self::PER_STEP, true ) as $i => $p ) {
			$owner = $ctx->person( $p['owner'] );
			$tmp   = $ctx->temp_copy( 'photos/' . $p['file'] );
			if ( ! $tmp ) {
				continue;
			}
			// Terms first, so their ledger rows come before the photo's and are removed after it (newest first).
			$cat_id  = $this->ensure_term( $p['category'], 'mvs_category', $ctx, '', 'category' );
			$tag_ids = array_filter( array_map( fn( $tag ) => $this->ensure_term( $tag, 'mvs_tag', $ctx, '', 'tag', 'sample' ), $p['tags'] ) );
			$date    = $ctx->date( $i, count( $photos ), 55 );
			$was     = get_current_user_id();
			wp_set_current_user( $owner );
			Clock::set( $date );
			$id = $upload->handle(
				array(
					'name'     => $p['file'],
					'type'     => 'image/jpeg',
					'tmp_name' => $tmp,
					'size'     => (int) filesize( $tmp ),
					'error'    => 0,
				),
				$owner,
				array(
					'title'       => $p['title'],
					'description' => $p['desc'],
					'privacy'     => 'public',
					'status'      => 'publish',
					'created_at'  => $date,
				)
			);
			Clock::reset();
			wp_set_current_user( $was );
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			if ( is_wp_error( $id ) || (int) $id <= 0 ) {
				$ctx->warn( $this->id(), 'photo ' . $p['file'], is_wp_error( $id ) ? $id : 'upload failed' );
				continue;
			}
			$id = (int) $id;
			$ctx->record( $this->id(), 'media', $id, 'sample', array( 'owner' => $owner ) );
			// The same writes as MediaVerse's own upload (MediaController): terms plus the name copies its cards read.
			wp_set_object_terms( $id, array_values( $tag_ids ), 'mvs_tag' );
			if ( $cat_id ) {
				wp_set_object_terms( $id, array( $cat_id ), 'mvs_category' );
			}
			if ( $repo && method_exists( $repo, 'set' ) ) {
				$repo->set( $id, 'tags', wp_json_encode( $p['tags'] ) );
				$repo->set( $id, 'category', wp_json_encode( array( $p['category'] ) ) );
			}
			$by_cat[ $p['category'] ][] = array( $id, $owner );
			$this->engage( $id, $i, $owner, $people );
			if ( $favs && method_exists( $favs, 'toggle' ) ) {
				for ( $f = 0; $f < 1 + $i % 5; $f++ ) {
					$fan = $people[ ( $i + $f * 4 + 1 ) % count( $people ) ];
					if ( $fan !== $owner ) {
						$favs->toggle( $id, $fan );
					}
				}
			}
		}
		$ctx->set( $this->id(), 'by_category', $by_cat );
	}

	/**
	 * Reactions and a few comments from other sample people.
	 *
	 * @param int   $media_id Media.
	 * @param int   $i        Sequence.
	 * @param int   $owner    Owner (does not react to their own photo).
	 * @param int[] $people   Sample people.
	 */
	private function engage( int $media_id, int $i, int $owner, array $people ): void {
		$reactions = $this->service( 'reactions' );
		$comments  = $this->service( 'comments' );
		$types     = array( 'like', 'love', 'wow', 'like', 'love', 'haha' );
		$was       = get_current_user_id();
		$n         = count( $people );
		for ( $r = 0; $r < 2 + ( $i * 3 ) % 7; $r++ ) {
			$uid = $people[ ( $i + $r * 2 + 1 ) % $n ];
			if ( $uid !== $owner && $reactions && method_exists( $reactions, 'toggle' ) ) {
				wp_set_current_user( $uid );
				$reactions->toggle( $media_id, $uid, $types[ ( $i + $r ) % count( $types ) ] );
			}
		}
		$lines = array( 'The light in this is perfect.', 'Where was this taken?', 'Saving this for my moodboard.', 'Beautiful composition.', 'This made my day.' );
		for ( $c = 0; $c < $i % 3; $c++ ) {
			$uid = $people[ ( $i + $c * 5 + 3 ) % $n ];
			if ( $uid !== $owner && $comments && method_exists( $comments, 'add' ) ) {
				wp_set_current_user( $uid );
				$comments->add( $media_id, $uid, $lines[ ( $i + $c ) % count( $lines ) ] );
			}
		}
		wp_set_current_user( $was );
	}

	/**
	 * Albums, one per subject, owned by the person with most photos in it.
	 *
	 * @param Context $ctx Context.
	 */
	private function albums( Context $ctx ): void {
		$albums = $this->service( 'albums' );
		if ( ! $albums || ! method_exists( $albums, 'create' ) ) {
			return;
		}
		$titles = array(
			'Nature'       => array( __( 'Places I keep going back to', 'wbcom-demo-installer' ), __( 'Landscapes from the last year of trips.', 'wbcom-demo-installer' ) ),
			'Architecture' => array( __( 'Lines and light', 'wbcom-demo-installer' ), __( 'Buildings, glass and the shapes cities make.', 'wbcom-demo-installer' ) ),
			'Food'         => array( __( 'Food we loved', 'wbcom-demo-installer' ), __( 'Dishes worth travelling for.', 'wbcom-demo-installer' ) ),
		);
		foreach ( (array) $ctx->get( $this->id(), 'by_category', array() ) as $cat => $items ) {
			if ( empty( $titles[ $cat ] ) ) {
				continue;
			}
			// MediaVerse files only an owner's own media into their album (AlbumService::add_items), so the album
			// belongs to the person with most photos of the subject and holds just theirs.
			$counts = array_count_values( array_map( 'intval', array_column( $items, 1 ) ) );
			arsort( $counts );
			$owner = (int) array_key_first( $counts );
			$items = array_values( array_filter( $items, static fn( $it ) => (int) $it[1] === $owner ) );
			if ( count( $items ) < 2 ) {
				continue;
			}
			$was = get_current_user_id();
			wp_set_current_user( $owner );
			$album = $albums->create(
				$owner,
				array(
					'title'       => $titles[ $cat ][0],
					'description' => $titles[ $cat ][1],
					'privacy'     => 'public',
					'created_at'  => $ctx->date( 40, 60, 60 ),
				)
			);
			if ( ! is_wp_error( $album ) && (int) $album > 0 ) {
				$albums->add_items( (int) $album, array_map( 'intval', array_column( $items, 0 ) ) );
				if ( method_exists( $albums, 'set_cover' ) ) {
					$albums->set_cover( (int) $album, (int) $items[0][0] );
				}
				$ctx->record( $this->id(), 'album', (int) $album );
			}
			wp_set_current_user( $was );
		}
	}

	/**
	 * Remove one object.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		if ( 'album' === $row->object_type ) {
			return self::delete_post( $id );
		}
		if ( 'category' === $row->object_type || 'tag' === $row->object_type ) {
			return self::remove_unused_term( $id, (string) ( $row->meta['taxonomy'] ?? '' ) );
		}
		if ( 'media' === $row->object_type ) {
			$repo = $this->service( 'media_repository' );
			if ( ! $repo || ! method_exists( $repo, 'delete_cascade' ) ) {
				return false;
			}
			if ( method_exists( $repo, 'exists' ) && ! $repo->exists( $id ) ) {
				return true;
			}
			$repo->delete_cascade( $id );
			return ! method_exists( $repo, 'exists' ) || ! $repo->exists( $id );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		if ( ! class_exists( '\\WPMediaVerse\\Core\\SettingsHelper' ) ) {
			return array();
		}
		$page_url = static function ( string $key ): string {
			$id = (int) \WPMediaVerse\Core\SettingsHelper::get_page_id( $key );
			return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
		};
		$explore  = $page_url( 'explore' );
		if ( '' === $explore ) {
			return array();
		}
		$upload = $page_url( 'upload' );
		$mine   = $page_url( 'dashboard' );
		$t      = $this->content()['guide'];
		$admin  = static fn( string $slug ): string => admin_url( 'admin.php?page=' . $slug );
		return array(
			'name'       => $t['name'],
			'tab'        => $t['tab'],
			'roles'      => array(
				array(
					'key'    => 'creator',
					'user'   => People::user_id( '', 1 ),
					'label'  => $t['roles']['creator'][0],
					'points' => $t['roles']['creator'][1],
				),
			),
			'member'     => self::guide_steps(
				$t['member'],
				array(
					'explore' => $explore,
					'share'   => '' !== $upload ? $upload : $explore,
					'mine'    => '' !== $mine ? $mine : null,
				),
				array(
					'explore' => array(
						'public' => true,
						'as'     => 'creator',
					),
					'share'   => array(
						'as'        => 'creator',
						'highlight' => true,
					),
					'mine'    => array( 'as' => 'creator' ),
				)
			),
			'owner'      => self::guide_steps(
				$t['owner'],
				array(
					'media'    => $admin( 'mvs-media' ),
					'settings' => $admin( 'mvs-settings' ),
				)
			),
			'highlights' => self::guide_highlights( $t['highlights'] ),
		);
	}


	/**
	 * {@inheritDoc}
	 */
	public function offers_home(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * On a media site (no community platform in the demo): the hero, the photo count and links to browse and
	 * upload. With a community platform, media is part of the community and adds nothing here.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		// With a community platform in the demo, media is one of its features: the community leads the page.
		if ( ! class_exists( '\WPMediaVerse\Core\SettingsHelper' ) || ! class_exists( '\WPMediaVerse\Core\Plugin' )
			|| array_filter( Registry::all(), static fn( Provider $p ): bool => $p->community() && $ctx->includes( $p->id() ) ) ) {
			return array();
		}
		$h     = (array) ( $this->content()['home'] ?? array() );
		$page  = static function ( string $key ): string {
			$id = (int) \WPMediaVerse\Core\SettingsHelper::get_page_id( $key );
			return $id && 'publish' === get_post_status( $id ) ? (string) get_permalink( $id ) : '';
		};
		$links = array_values( array_filter( array( '' !== $page( 'explore' ) ? array( (string) $h['browse'], $page( 'explore' ) ) : null, '' !== $page( 'upload' ) ? array( (string) $h['upload'], $page( 'upload' ) ) : null ) ) );
		// No photo grid: the mvs/media-grid block is unstyled outside WPMediaVerse's own pages (its grid CSS loads only
		// there), so the section links to the gallery instead. Add the block here once WPMediaVerse styles it anywhere.
		return array(
			'hero'     => array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => $links,
				'closing' => (string) $h['closing'],
			),
			'stats'    => array( array( (int) \WPMediaVerse\Core\Plugin::container()->get( 'media_repository' )->count_published(), __( 'photos shared', 'wbcom-demo-installer' ) ) ),
			'sections' => array( Blocks::heading( (string) $h['latest'][0] ) . Blocks::paragraph( (string) $h['latest'][1] ) . ( $links ? Blocks::buttons( $links ) : '' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		if ( ! class_exists( '\WPMediaVerse\Core\SettingsHelper' ) ) {
			return array();
		}
		$page = (int) \WPMediaVerse\Core\SettingsHelper::get_page_id( 'explore' );
		return $page && 'publish' === get_post_status( $page )
			? array(
				array(
					'title'   => __( 'Media', 'wbcom-demo-installer' ),
					'page_id' => $page,
				),
			)
			: array();
	}
}
