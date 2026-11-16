<?php
/**
 * WooCommerce: a small community store with categories, product photos, a few sale prices, stock and reviews
 * from the sample people. Replaces the guide-only integration of the same id and keeps its "Try the demo" text.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce provider. Products are saved through WooCommerce's product classes (so lookup tables, prices and
 * stock stay in step); reviews are product comments with a rating, as WooCommerce stores them.
 */
final class WooCommerce extends Provider {

	/**
	 * Products saved per step: each one imports a photo (with its thumbnail sizes).
	 */
	private const PER_STEP = 5;

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'WooCommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Simple' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'WC_VERSION' ) ? (string) WC_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '9.0.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'store';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 75; // After the community products, before the guide-only integrations.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: products, 2: categories */
			__( 'A store with %1$d products in %2$d categories, sale prices, stock and reviews', 'wbcom-demo-installer' ),
			count( $c['products'] ),
			count( $c['categories'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( '_wc_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function boot( Context $ctx ): void {
		static $done = false;
		if ( $done || ! $this->available() ) {
			return;
		}
		$done = true;
		// Opening the store (woocommerce_coming_soon, in settings()) and closing it again on Finish make WooCommerce
		// re-save the Cart page to clear caches and add a "your store is live" note. Neither is the owner's, so while
		// a demo step runs the Cart page keeps no revision, and a note added by that switch is taken back.
		add_filter(
			'wp_revisions_to_keep',
			static fn( $keep, $post ) => $post instanceof \WP_Post && (int) get_option( 'woocommerce_cart_page_id' ) === (int) $post->ID ? 0 : $keep,
			10,
			2
		);
		$notes = '\\Automattic\\WooCommerce\\Admin\\Notes\\Notes';
		$name  = 'wc-admin-order-withdrawal-feature';
		$had   = null;
		add_action(
			'update_option_woocommerce_coming_soon',
			static function () use ( $notes, $name, &$had ): void {
				$had = class_exists( $notes ) && $notes::get_note_by_name( $name );
			},
			1
		);
		add_action(
			'update_option_woocommerce_coming_soon',
			static function () use ( $notes, $name, &$had ): void {
				if ( false === $had && $notes::get_note_by_name( $name ) ) {
					$notes::delete_notes_with_name( $name );
				}
			},
			99
		);
	}

	/**
	 * Open the shop (a new store starts in "coming soon" mode) and mark the setup task list as done, since the
	 * demo has the products, categories and pages it asks for.
	 */
	public function settings(): array {
		return array(
			'woocommerce_coming_soon'      => 'no',
			'woocommerce_task_list_hidden' => 'yes',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		$c     = $this->content();
		$plan  = array(
			'product'    => array( count( $c['products'] ), 'sample', __( 'products', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['products'] ), 'sample', __( 'product photos', 'wbcom-demo-installer' ) ),
			'review'     => array( count( $c['reviews'] ), 'sample', __( 'reviews', 'wbcom-demo-installer' ) ),
		);
		$terms = self::missing_terms( array( 'product_cat' => array_values( (array) $c['categories'] ) ) );
		if ( $terms ) {
			$plan['term'] = array( $terms, 'setup', __( 'product categories', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'categories' );
		for ( $i = 0, $n = count( $this->content()['products'] ); $i < $n; $i += self::PER_STEP ) {
			$steps[] = 'products:' . $i;
		}
		$steps[] = 'reviews';
		return $steps;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $step See parent.
	 * @param Context $ctx  See parent.
	 */
	public function run( string $step, Context $ctx ): void {
		list( $name, $offset ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		switch ( $name ) {
			case 'categories':
				$map = array();
				foreach ( (array) $this->content()['categories'] as $key => $label ) {
					$map[ $key ] = $this->ensure_term( $label, 'product_cat', $ctx );
				}
				$ctx->set( $this->id(), 'categories', $map );
				break;
			case 'products':
				$this->products( $ctx, (int) $offset );
				break;
			case 'reviews':
				$this->reviews( $ctx );
				break;
		}
	}

	/**
	 * Save one chunk of products through WooCommerce's product class.
	 *
	 * @param Context $ctx    Context.
	 * @param int     $offset First product index of this step.
	 */
	private function products( Context $ctx, int $offset ): void {
		$all      = (array) $this->content()['products'];
		$cats     = (array) $ctx->get( $this->id(), 'categories', array() );
		$ids      = (array) $ctx->get( $this->id(), 'products', array() );
		$owner    = $ctx->person( 0 );
		$products = array_slice( $all, $offset, self::PER_STEP, true );
		foreach ( $products as $i => $row ) {
			list( $title, $photo, $cat, $regular, $sale, $stock, $short, $desc, $featured, $virtual ) = $row;
			$product = new \WC_Product_Simple();
			$product->set_name( $title );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_regular_price( (string) $regular );
			if ( '' !== $sale ) {
				$product->set_sale_price( (string) $sale );
			}
			$product->set_short_description( $short );
			$product->set_description( wpautop( $desc ) );
			$product->set_featured( (bool) $featured );
			$product->set_virtual( (bool) $virtual );
			$product->set_reviews_allowed( true );
			if ( null !== $stock ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $stock );
			}
			if ( ! empty( $cats[ $cat ] ) ) {
				$product->set_category_ids( array( (int) $cats[ $cat ] ) );
			}
			$product->set_date_created( $ctx->date( (int) $i, count( $all ), 45 ) );
			$image = $ctx->attachment( 'products/' . $photo . '.jpg', $owner, $this->id() );
			if ( $image ) {
				$product->set_image_id( $image );
			}
			$id = $product->save();
			if ( ! $id ) {
				$ctx->warn( $this->id(), 'product ' . $title, __( 'WooCommerce did not save it.', 'wbcom-demo-installer' ) );
				continue;
			}
			// The store is run by a sample person, never by the site's admin.
			wp_update_post(
				array(
					'ID'          => $id,
					'post_author' => $owner,
				)
			);
			$ctx->record( $this->id(), 'product', (int) $id );
			$ids[ $i ] = (int) $id;
		}
		$ctx->set( $this->id(), 'products', $ids );
	}

	/**
	 * Reviews from sample people, stored as WooCommerce does: an approved "review" comment with a rating.
	 *
	 * @param Context $ctx Context.
	 */
	private function reviews( Context $ctx ): void {
		$ids     = (array) $ctx->get( $this->id(), 'products', array() );
		$reviews = (array) $this->content()['reviews'];
		foreach ( $reviews as $n => $row ) {
			list( $product_index, $person, $rating, $text ) = $row;
			$product_id                                     = (int) ( $ids[ $product_index ] ?? 0 );
			$user_id                                        = $ctx->person( (int) $person );
			$user = $user_id ? get_userdata( $user_id ) : false;
			if ( ! $product_id || ! $user ) {
				continue;
			}
			$id = wp_insert_comment(
				array(
					'comment_post_ID'      => $product_id,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_content'      => $text,
					'comment_type'         => 'review',
					'comment_approved'     => 1,
					'user_id'              => $user_id,
					'comment_date_gmt'     => $ctx->date( (int) $n, count( $reviews ), 30 ),
					// time-ok: WordPress stores this field in site time beside its _gmt column; the build works in UTC.
					'comment_date'         => get_date_from_gmt( $ctx->date( (int) $n, count( $reviews ), 30 ) ),
					'comment_meta'         => array(
						'rating'   => max( 1, min( 5, (int) $rating ) ),
						'verified' => 1,
					),
				)
			);
			if ( ! $id ) {
				continue;
			}
			$ctx->record( $this->id(), 'review', (int) $id );
		}
		// Average rating and review counts on each product, as WooCommerce works them out after a new review.
		if ( class_exists( 'WC_Comments' ) ) {
			foreach ( array_unique( array_filter( array_map( 'intval', $ids ) ) ) as $product_id ) {
				\WC_Comments::clear_transients( $product_id );
			}
		}
	}

	/**
	 * Share the sample products out among marketplace stores (Dokan, WCFM, WC Vendors): the stores follow the
	 * sample categories in order (apparel, accessories, home, books), and a product in no matched category goes
	 * round. Each product's author becomes its store's person, which is how these marketplaces tell a product's
	 * vendor.
	 *
	 * @param Context $ctx    Context.
	 * @param int[]   $stores Store owners' user ids, in content order.
	 * @return array<int,int> Product id => store owner id.
	 */
	public static function share_products( Context $ctx, array $stores ): array {
		$stores = array_values( $stores );
		if ( ! $stores ) {
			return array();
		}
		$by_term = array();
		foreach ( array_values( (array) $ctx->get( 'woocommerce', 'categories', array() ) ) as $k => $term_id ) {
			$by_term[ (int) $term_id ] = $stores[ $k % count( $stores ) ];
		}
		$out = array();
		foreach ( Ledger::ids( 'woocommerce', 'product' ) as $n => $product_id ) {
			$terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
			$term  = is_array( $terms ) && $terms ? (int) $terms[0] : 0;
			$owner = $by_term[ $term ] ?? $stores[ $n % count( $stores ) ];
			wp_update_post(
				array(
					'ID'          => $product_id,
					'post_author' => $owner,
				)
			);
			$out[ $product_id ] = $owner;
		}
		return $out;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		$id   = (int) $row->object_id;
		$meta = is_array( $row->meta ?? null ) ? $row->meta : (array) json_decode( (string) ( $row->meta ?? '' ), true );
		switch ( $row->object_type ) {
			case 'product':
				$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
				if ( $product ) {
					$product->delete( true );
				}
				return ! get_post( $id );
			case 'review':
				if ( get_comment( $id ) ) {
					wp_delete_comment( $id, true );
				}
				return ! get_comment( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'term':
				$taxonomy = (string) ( $meta['taxonomy'] ?? 'product_cat' );
				$gone     = self::remove_unused_term( $id, $taxonomy );
				if ( $gone && 'product_cat' === $taxonomy ) {
					// WooCommerce adds a category to its analytics lookup table on create and edit but never removes
					// it on delete; its own rebuild action drops the rows of categories that no longer exist.
					do_action( 'generate_category_lookup_table' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own rebuild action.
				}
				return $gone;
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$url = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
		return $url
			? array(
				array(
					'title' => __( 'Shop', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
			)
			: array();
	}

	/**
	 * Marketplaces whose hero leads the home page when they are in the build: they sell these products.
	 */
	private const MARKETPLACES = array( 'dokan', 'wcfm', 'wcvendors' );

	/**
	 * {@inheritDoc}
	 */
	public function offers_home(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * The store's part of the designed home page: shop by category in core blocks, and the top rated products in
	 * WooCommerce's own Product Collection block, so the owner edits it with WooCommerce's settings. A marketplace
	 * in the build brings its own hero, so the store then adds only its numbers and sections.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		$h       = (array) ( $this->content()['home'] ?? array() );
		$shop    = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
		$account = function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'myaccount' ) > 0 ? (string) wc_get_page_permalink( 'myaccount' ) : '';
		// Products a shopper can see: Dokan, for one, keeps a hidden "Reverse Withdrawal Payment" product.
		$found    = wc_get_products(
			array(
				'status'     => 'publish',
				'visibility' => 'catalog',
				'limit'      => 1,
				'paginate'   => true,
				'return'     => 'ids',
			)
		);
		$products = is_object( $found ) ? (int) $found->total : 0;
		$count    = (int) wp_count_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);
		$cats     = array();
		$terms    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 4,
				'exclude'    => array( (int) get_option( 'default_product_cat' ) ), // Uncategorized.
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$cats[] = array(
				'name'  => $term->name,
				'url'   => (string) get_term_link( $term ),
				'count' => (int) $term->count,
				'image' => self::category_image( $term ),
			);
		}
		$marketplace = (bool) array_filter( self::MARKETPLACES, static fn( string $id ): bool => $ctx->includes( $id ) && ( Registry::get( $id )?->available() ?? false ) );
		$buttons     = array_values( array_filter( array( $shop ? array( (string) $h['shop'], $shop ) : null, $account ? array( (string) $h['account'], $account ) : null ) ) );

		$cards    = array_map(
			static fn( array $c ): string => ( $c['image'] ? Blocks::image( $c['image'] ) : '' )
				. Blocks::link_heading( $c['name'], $c['url'] )
				/* translators: %d: number of products. */
				. Blocks::paragraph( sprintf( _n( '%d product', '%d products', $c['count'], 'wbcom-demo-installer' ), $c['count'] ) ),
			$cats
		);
		$sections = array();
		if ( $cards ) {
			$sections[] = Blocks::heading( (string) $h['cats'][0] ) . Blocks::paragraph( (string) $h['cats'][1] ) . Blocks::columns( $cards );
		}
		if ( $products ) {
			$sections[] = Blocks::heading( (string) $h['top'][0] ) . Blocks::paragraph( (string) $h['top'][1] ) . self::top_rated( 4 )
				. ( $shop ? Blocks::button( (string) $h['all'], $shop ) : '' );
		}
		return array(
			'hero'     => $marketplace ? array() : array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => $buttons,
				'closing' => (string) $h['closing'],
			),
			'stats'    => array(
				array( $products, __( 'products', 'wbcom-demo-installer' ) ),
				array( $count, __( 'product categories', 'wbcom-demo-installer' ) ),
			),
			'sections' => $sections,
		);
	}

	/**
	 * A category's picture: its own thumbnail, else the photo of its newest product.
	 *
	 * @param \WP_Term $term Product category.
	 */
	private static function category_image( \WP_Term $term ): int {
		$id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( $id ) {
			return $id;
		}
		$product = get_posts(
			array(
				'post_type'   => 'product',
				'numberposts' => 1,
				'fields'      => 'ids',
				'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- one product per category, four categories, once per build.
					array(
						'taxonomy' => 'product_cat',
						'terms'    => $term->term_id,
					),
				),
			)
		);
		return $product ? (int) get_post_thumbnail_id( (int) $product[0] ) : 0;
	}

	/**
	 * WooCommerce's Product Collection block set to its "Top rated" collection, as its own editor saves it.
	 *
	 * @param int $count Products shown, in one row.
	 */
	private static function top_rated( int $count ): string {
		$attrs = array(
			'queryId'              => 0,
			'query'                => array(
				'perPage'                       => $count,
				'pages'                         => 1,
				'offset'                        => 0,
				'postType'                      => 'product',
				'order'                         => 'desc',
				'orderBy'                       => 'rating',
				'search'                        => '',
				'exclude'                       => array(),
				'inherit'                       => false,
				'taxQuery'                      => (object) array(),
				'isProductCollectionBlock'      => true,
				'featured'                      => false,
				'woocommerceOnSale'             => false,
				'woocommerceStockStatus'        => array( 'instock', 'outofstock', 'onbackorder' ),
				'woocommerceAttributes'         => array(),
				'woocommerceHandPickedProducts' => array(),
				'filterable'                    => false,
			),
			'tagName'              => 'div',
			'displayLayout'        => array(
				'type'          => 'flex',
				'columns'       => $count,
				'shrinkColumns' => true,
			),
			'dimensions'           => array( 'widthType' => 'fill' ),
			'collection'           => 'woocommerce/product-collection/top-rated',
			'hideControls'         => array( 'inherit', 'order', 'filterable' ),
			'queryContextIncludes' => array( 'collection' ),
		);
		return '<!-- wp:woocommerce/product-collection ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) . ' --><div class="wp-block-woocommerce-product-collection">'
			. '<!-- wp:woocommerce/product-template -->'
			. '<!-- wp:woocommerce/product-image {"imageSizing":"thumbnail","isDescendentOfQueryLoop":true} --><!-- wp:woocommerce/product-sale-badge {"isDescendentOfQueryLoop":true,"align":"right"} /--><!-- /wp:woocommerce/product-image -->'
			. '<!-- wp:post-title {"textAlign":"center","level":3,"isLink":true,"__woocommerceNamespace":"woocommerce/product-collection/product-title"} /-->'
			. '<!-- wp:woocommerce/product-price {"isDescendentOfQueryLoop":true,"textAlign":"center"} /-->'
			. '<!-- wp:woocommerce/product-button {"isDescendentOfQueryLoop":true,"textAlign":"center"} /-->'
			. '<!-- /wp:woocommerce/product-template --></div><!-- /wp:woocommerce/product-collection -->';
	}

	/**
	 * A marketplace's part of the home page (Dokan, WCFM, WC Vendors share it): the hero, the store count, and a
	 * section of store cards (banner, linked name, about line, four to a row) in core blocks the owner can edit.
	 * The copy is the marketplace's content 'home' entry.
	 *
	 * @param array<string,mixed>                                              $h          Home copy.
	 * @param array<int,array{name:string,url:string,about:string,banner:int}> $stores     Stores.
	 * @param string                                                           $stores_url Store list page link, or ''.
	 * @param string                                                           $sell       Seller sign-up link, or ''.
	 * @return array<string,mixed>
	 */
	public static function marketplace_home( array $h, array $stores, string $stores_url, string $sell ): array {
		if ( ! $stores ) {
			return array();
		}
		$shop    = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
		$buttons = array_values( array_filter( array( $stores_url ? array( (string) $h['stores'], $stores_url ) : null, $shop ? array( (string) $h['shop'], $shop ) : null ) ) );
		$cards   = '';
		foreach ( array_chunk( $stores, 4 ) as $row ) {
			$cards .= Blocks::columns(
				array_map(
					static fn( array $s ): string => ( $s['banner'] ? Blocks::image( $s['banner'] ) : '' ) . Blocks::link_heading( $s['name'], $s['url'] ) . Blocks::paragraph( wp_strip_all_tags( $s['about'] ) ),
					$row
				)
			);
		}
		return array(
			'hero'     => array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => $buttons,
				'closing' => (string) ( $sell ? $h['closing'] : $h['browse'] ),
				'cta'     => $sell ? array( (string) $h['sell'], $sell ) : ( $buttons[0] ?? array() ),
			),
			'stats'    => array( array( count( $stores ), __( 'stores', 'wbcom-demo-installer' ) ) ),
			'sections' => array(
				Blocks::heading( (string) $h['meet'][0] ) . Blocks::paragraph( (string) $h['meet'][1] ) . $cards
					. ( $stores_url ? Blocks::button( (string) $h['all'], $stores_url ) : '' ),
			),
		);
	}

	/**
	 * The "Try the demo" text stays in content/integrations.php, shared with the guide-only integration.
	 */
	public function guide(): array {
		return ( new Integration( 'woocommerce' ) )->guide();
	}

	/**
	 * {@inheritDoc}
	 *
	 * The shop page, for a store without a community (a community provider comes first in the order).
	 */
	public function front_page(): int {
		$page = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		return $page > 0 && 'publish' === get_post_status( $page ) ? $page : 0;
	}
}
