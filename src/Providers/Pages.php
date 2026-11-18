<?php
/**
 * Pages and blog: About, Join, FAQ and Blog pages and a few blog posts, with Wbcom Essential.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * The regular pages and the blog that turn a community demo into a complete model site.
 *
 * Pages are built from core blocks, which stay valid in the editor whatever the owner changes later. Wbcom Essential's
 * server-rendered blocks (post carousel, category grid) add the blog sections; its static blocks are not written
 * here, because their saved HTML must match the editor's exactly and an owner opening the page would see it flagged.
 */
final class Pages extends Provider {

	private const PAGES = array( 'about', 'join', 'faq', 'blog' );

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'pages';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Wbcom Essential';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'WBCOM_ESSENTIAL_VERSION' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WBCOM_ESSENTIAL_VERSION' ) ? (string) WBCOM_ESSENTIAL_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '4.7.2'; // Latest release; the site check updates an older copy.
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'home';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 85;
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return sprintf(
			/* translators: %d: number of blog posts */
			__( 'About, Join, FAQ and Blog pages and %d blog posts by the sample people, so the demo is a complete website', 'wbcom-demo-installer' ),
			count( $this->content()['posts'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c        = $this->content();
		$new_cats = self::missing_terms( array( 'category' => array_column( $c['categories'], 0 ) ) );
		return array(
			'page'       => array( count( self::PAGES ), 'setup', __( 'pages', 'wbcom-demo-installer' ) ),
			'category'   => array( $new_cats, 'setup', __( 'blog categories', 'wbcom-demo-installer' ) ),
			'post'       => array( count( $c['posts'] ), 'sample', __( 'blog posts', 'wbcom-demo-installer' ) ),
			// About and Join, and one per new category (an owner's existing category is not touched).
			'image'      => array( 2 + $new_cats, 'setup', __( 'page and category images', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['posts'] ), 'sample', __( 'blog post images', 'wbcom-demo-installer' ) ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function type_labels(): array {
		return array(
			'page'       => __( 'pages', 'wbcom-demo-installer' ),
			'category'   => __( 'blog categories', 'wbcom-demo-installer' ),
			'post'       => __( 'blog posts', 'wbcom-demo-installer' ),
			'image'      => __( 'page and category images', 'wbcom-demo-installer' ),
			'attachment' => __( 'blog post images', 'wbcom-demo-installer' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx Context.
	 */
	public function steps( Context $ctx ): array {
		return array( 'posts', 'pages' ); // Posts first, so the blog blocks on the pages have something to show.
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $step Step.
	 * @param Context $ctx  Context.
	 */
	public function run( string $step, Context $ctx ): void {
		'posts' === $step ? $this->posts( $ctx ) : $this->pages( $ctx );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$items = array();
		$short = array( 'faq' => (string) ( $this->content()['faq']['menu'] ?? '' ) );
		foreach ( array( 'about', 'blog', 'faq' ) as $key ) {
			// This demo's page, or the one the owner kept from an earlier demo and this build reused.
			$id = self::demo_page( $key );
			if ( $id ) {
				$items[] = array(
					'title'   => '' !== ( $short[ $key ] ?? '' ) ? $short[ $key ] : get_the_title( $id ),
					'page_id' => $id,
				);
			}
		}
		return $items;
	}

	/**
	 * {@inheritDoc}
	 */
	public function layout_pages(): array {
		return Ledger::ids( $this->id(), 'page' );
	}

	/**
	 * Blog posts by the sample people.
	 *
	 * @param Context $ctx Context.
	 */
	private function posts( Context $ctx ): void {
		$c     = $this->content();
		$terms = array();
		foreach ( $c['categories'] as $key => $cat ) {
			list( $name, $photo ) = $cat;
			$terms[ $key ]        = $this->ensure_term( $name, 'category', $ctx, '', 'category' );
			// A photo only on a category the demo made, so the category grid shows pictures, not placeholders.
			if ( $terms[ $key ] && Ledger::has( $this->id(), 'category', $terms[ $key ] ) && ! get_term_meta( $terms[ $key ], 'thumbnail_id', true ) ) {
				$image = $ctx->attachment( $photo, (int) get_current_user_id(), $this->id(), 'setup', 'image' );
				if ( $image ) {
					update_term_meta( $terms[ $key ], 'thumbnail_id', $image );
				}
			}
		}
		// The blog blocks on the pages show only these categories, so the owner's own posts (and Hello world!) stay out.
		$ctx->state->ref_set( $this->id(), 'categories', array_values( array_filter( array_map( 'intval', $terms ) ) ) );
		$n = count( $c['posts'] );
		foreach ( $c['posts'] as $i => $post ) {
			$author = $ctx->person( (int) $post['author'] );
			$body   = array();
			foreach ( $post['body'] as $part ) {
				list( $heading, $text ) = $part;
				if ( '' !== $heading ) {
					$body[] = Blocks::heading( $heading );
				}
				$body[] = Blocks::paragraph( $text );
			}
			$date = $ctx->date( $i, $n, 45 );
			$id   = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_status'   => 'publish',
					'post_title'    => $post['title'],
					'post_excerpt'  => $post['excerpt'],
					'post_content'  => implode( "\n\n", $body ),
					'post_author'   => $author,
					// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
					'post_date'     => get_date_from_gmt( $date ),
					'post_category' => array_filter( array( (int) ( $terms[ $post['category'] ] ?? 0 ) ) ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'post ' . $post['title'], $id );
				continue;
			}
			$ctx->record( $this->id(), 'post', (int) $id );
			$image = $ctx->attachment( $post['image'], $author, $this->id() );
			if ( $image ) {
				set_post_thumbnail( (int) $id, $image );
			}
		}
	}

	/**
	 * About, Join, FAQ and Blog pages.
	 *
	 * @param Context $ctx Context.
	 */
	private function pages( Context $ctx ): void {
		$c     = $this->content();
		$admin = (int) get_current_user_id();
		$about = $c['about'];
		$cats  = array_map( 'intval', (array) $ctx->state->ref( $this->id(), 'categories', array() ) );
		// Setup, like the pages that show them: Keep setup keeps About and Join, so it keeps their pictures.
		$image      = $ctx->attachment( $about['image'], $admin, $this->id(), 'setup', 'image' );
		$join_image = $ctx->attachment( $c['join']['image'], $admin, $this->id(), 'setup', 'image' );
		$cols       = array();
		foreach ( $about['values'] as $value ) {
			$cols[] = Blocks::heading( $value[0], 3 ) . "\n" . Blocks::paragraph( $value[1] );
		}
		$quoted = get_userdata( $ctx->person( (int) $about['quote'][1] ) );
		$blocks = array(
			'about' => array(
				$image ? Blocks::image( $image ) : '',
				Blocks::paragraph( $about['intro'] ),
				Blocks::columns( $cols ),
				Blocks::quote( $about['quote'][0], $quoted ? $quoted->display_name : '' ),
				Blocks::heading( __( 'From the blog', 'wbcom-demo-installer' ) ),
				self::posts_block( $cats, 'grid', 3 ), // One row of three: no carousel arrows over the cards.
			),
			'join'  => array(
				$join_image ? Blocks::image( $join_image ) : '',
				Blocks::paragraph( $c['join']['intro'] ),
				Blocks::columns(
					array_map(
						static fn( array $step ): string => Blocks::heading( $step[0], 3 ) . "\n" . Blocks::paragraph( $step[1] ),
						$c['join']['steps']
					)
				),
				Blocks::button( $c['join']['button'], wp_registration_url() ),
			),
			'faq'   => array_merge(
				array( Blocks::paragraph( $c['faq']['intro'] ) ),
				array_map( static fn( array $qa ): string => Blocks::details( $qa[0], $qa[1] ), $c['faq']['items'] )
			),
			'blog'  => array(
				Blocks::paragraph( $c['blog']['intro'] ),
				// Wbcom Essential's blocks are rendered on the server, so they never go stale in the editor; theme colours
				// keep them matched to BuddyX, BuddyX Pro, Reign or any theme with a palette.
				'<!-- wp:wbcom-essential/category-grid ' . wp_json_encode(
					array(
						'selectedCategories' => $cats,
						'columns'            => max( 1, min( 4, count( $cats ) ) ),
						'showPostCount'      => true,
						'useThemeColors'     => true,
						'imageRatio'         => '0.5', // Wide, short tiles: the categories lead into the posts, not over them.
						'overlayColor'       => '#111111', // Titles stay readable on bright photos.
					)
				) . ' /-->',
				Blocks::heading( __( 'Latest posts', 'wbcom-demo-installer' ) ),
				self::posts_block( $cats, 'grid', 9 ),
			),
		);
		$titles = array(
			'about' => $about['title'],
			'join'  => $c['join']['title'],
			'faq'   => $c['faq']['title'],
			'blog'  => $c['blog']['title'],
		);
		foreach ( self::PAGES as $key ) {
			$found = self::demo_page( $key );
			if ( $found ) {
				// Made before a retry of this step, or kept by the owner from an earlier demo (adopted): never a second copy.
				if ( ! Ledger::has( $this->id(), 'page', $found ) ) {
					$ctx->reused( get_the_title( $found ) );
				}
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $titles[ $key ],
					'post_content' => implode( "\n\n", array_filter( $blocks[ $key ] ) ),
					'post_author'  => $admin,
					'meta_input'   => array( '_wbcom_demo_page' => $key ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'page ' . $titles[ $key ], $id );
				continue;
			}
			$ctx->record( $this->id(), 'page', (int) $id, 'setup' );
		}
	}

	/**
	 * The published page a demo made for a key (about, join, faq, blog), or 0.
	 *
	 * @param string $key Page key.
	 */
	private static function demo_page( string $key ): int {
		$found = get_posts(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'meta_key'    => '_wbcom_demo_page', // phpcs:ignore WordPress.DB.SlowDBQuery -- four pages, read once per build.
				'meta_value'  => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id = (int) $row->object_id;
		if ( 'category' === $row->object_type ) {
			return self::remove_unused_term( $id, 'category' );
		}
		return self::delete_post( $id, in_array( $row->object_type, array( 'attachment', 'image' ), true ) );
	}

	/**
	 * The demo's posts through Wbcom Essential's post block (server-rendered), as a carousel or a grid.
	 *
	 * @param int[]  $cats Category ids the block shows.
	 * @param string $mode 'carousel' or 'grid'.
	 * @param int    $n    Posts.
	 */
	private static function posts_block( array $cats, string $mode, int $n ): string {
		return '<!-- wp:wbcom-essential/post-carousel ' . wp_json_encode(
			array(
				'postsPerPage'  => $n,
				'categories'    => $cats,
				'displayMode'   => $mode,
				'slidesPerView' => 3,
				'showImage'     => true,
				'showExcerpt'   => true,
				'showCategory'  => true,
				'showAuthor'    => true,
			)
		) . ' /-->';
	}
}
