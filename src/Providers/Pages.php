<?php
/**
 * Pages and blog: About, Join, FAQ and Blog pages and a few blog posts, with Wbcom Essential.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

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
		return '4.7.0';
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
			// One per post, About and Join, and one per new category (an owner's existing category is not touched).
			'attachment' => array( count( $c['posts'] ) + 2 + $new_cats, 'sample', __( 'page, post and category images', 'wbcom-demo-installer' ) ),
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
			'attachment' => __( 'page, post and category images', 'wbcom-demo-installer' ),
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
			$ids = Ledger::ids( $this->id(), 'page' );
			foreach ( $ids as $id ) {
				if ( get_post_meta( $id, '_wbcom_demo_page', true ) === $key ) {
					$items[] = array(
						'title'   => '' !== ( $short[ $key ] ?? '' ) ? $short[ $key ] : get_the_title( $id ),
						'page_id' => (int) $id,
					);
				}
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
				$image = $ctx->attachment( $photo, (int) get_current_user_id(), $this->id() );
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
					$body[] = self::heading( $heading );
				}
				$body[] = self::paragraph( $text );
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
		$c          = $this->content();
		$admin      = (int) get_current_user_id();
		$about      = $c['about'];
		$cats       = array_map( 'intval', (array) $ctx->state->ref( $this->id(), 'categories', array() ) );
		$image      = $ctx->attachment( $about['image'], $admin, $this->id() );
		$join_image = $ctx->attachment( $c['join']['image'], $admin, $this->id() );
		$cols       = array();
		foreach ( $about['values'] as $value ) {
			$cols[] = self::heading( $value[0], 3 ) . "\n" . self::paragraph( $value[1] );
		}
		$quoted = get_userdata( $ctx->person( (int) $about['quote'][1] ) );
		$blocks = array(
			'about' => array(
				$image ? self::image( $image ) : '',
				self::paragraph( $about['intro'] ),
				self::columns( $cols ),
				self::quote( $about['quote'][0], $quoted ? $quoted->display_name : '' ),
				self::heading( __( 'From the blog', 'wbcom-demo-installer' ) ),
				self::posts_block( $cats, 'carousel', 3 ),
			),
			'join'  => array(
				$join_image ? self::image( $join_image ) : '',
				self::paragraph( $c['join']['intro'] ),
				self::columns(
					array_map(
						static fn( array $step ): string => self::heading( $step[0], 3 ) . "\n" . self::paragraph( $step[1] ),
						$c['join']['steps']
					)
				),
				self::button( $c['join']['button'], wp_registration_url() ),
			),
			'faq'   => array_merge(
				array( self::paragraph( $c['faq']['intro'] ) ),
				array_map( static fn( array $qa ): string => self::details( $qa[0], $qa[1] ), $c['faq']['items'] )
			),
			'blog'  => array(
				self::paragraph( $c['blog']['intro'] ),
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
				self::heading( __( 'Latest posts', 'wbcom-demo-installer' ) ),
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
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $titles[ $key ],
					'post_content' => implode( "\n\n", array_filter( $blocks[ $key ] ) ),
					'post_author'  => $admin,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'page ' . $titles[ $key ], $id );
				continue;
			}
			update_post_meta( (int) $id, '_wbcom_demo_page', $key );
			$ctx->record( $this->id(), 'page', (int) $id, 'setup' );
		}
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
		return self::delete_post( $id, 'attachment' === $row->object_type );
	}

	/**
	 * Core heading block.
	 *
	 * @param string $text  Text.
	 * @param int    $level Level.
	 */
	private static function heading( string $text, int $level = 2 ): string {
		$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
		return sprintf( '<!-- wp:heading%1$s --><h%2$d class="wp-block-heading">%3$s</h%2$d><!-- /wp:heading -->', $attrs, $level, esc_html( $text ) );
	}

	/**
	 * Core paragraph block.
	 *
	 * @param string $text Text.
	 */
	private static function paragraph( string $text ): string {
		return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
	}

	/**
	 * Core image block.
	 *
	 * @param int $id Attachment id.
	 */
	private static function image( int $id ): string {
		return sprintf(
			// A wide crop (21:9) keeps the page photo a banner, not a screenful, on any theme width.
			'<!-- wp:image {"id":%1$d,"aspectRatio":"21/9","scale":"cover","sizeSlug":"full","linkDestination":"none"} --><figure class="wp-block-image size-full"><img src="%2$s" alt="%3$s" class="wp-image-%1$d" style="aspect-ratio:21/9;object-fit:cover"/></figure><!-- /wp:image -->',
			$id,
			esc_url( (string) wp_get_attachment_image_url( $id, 'full' ) ),
			esc_attr( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) )
		);
	}

	/**
	 * Core columns block, one column per inner markup.
	 *
	 * @param string[] $inner Inner blocks per column.
	 */
	private static function columns( array $inner ): string {
		$cols = array_map( static fn( string $c ): string => '<!-- wp:column --><div class="wp-block-column">' . $c . '</div><!-- /wp:column -->', $inner );
		return '<!-- wp:columns --><div class="wp-block-columns">' . implode( '', $cols ) . '</div><!-- /wp:columns -->';
	}

	/**
	 * Core quote block.
	 *
	 * @param string $text Quote.
	 * @param string $who  Who said it.
	 */
	private static function quote( string $text, string $who ): string {
		return '<!-- wp:quote --><blockquote class="wp-block-quote">' . self::paragraph( $text ) . ( '' !== $who ? '<cite>' . esc_html( $who ) . '</cite>' : '' ) . '</blockquote><!-- /wp:quote -->';
	}

	/**
	 * Core details block (question and answer).
	 *
	 * @param string $q Question.
	 * @param string $a Answer.
	 */
	private static function details( string $q, string $a ): string {
		return '<!-- wp:details --><details class="wp-block-details"><summary>' . esc_html( $q ) . '</summary>' . self::paragraph( $a ) . '</details><!-- /wp:details -->';
	}

	/**
	 * Core button block.
	 *
	 * @param string $text Label.
	 * @param string $url  Link.
	 */
	private static function button( string $text, string $url ): string {
		return '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
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
