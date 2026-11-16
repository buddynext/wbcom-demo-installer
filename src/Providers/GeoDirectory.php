<?php
/**
 * GeoDirectory: places in its default city run by the sample people, with categories, photos, phone, website,
 * opening hours, map pins and star-rated reviews from other sample people.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Blocks;
use WbcomDemo\Core\Clock;
use WbcomDemo\Core\Context;
use WbcomDemo\Core\Ledger;
use WbcomDemo\Core\Provider;
use WbcomDemo\Core\Snapshot;

defined( 'ABSPATH' ) || exit;

/**
 * GeoDirectory provider. A place is a gd_place post whose GeoDirectory fields go in the wp_insert_post() array,
 * where GeoDirectory's own save_post handler writes them to its detail table (as its dummy data does). The
 * dummy data itself is not used: it downloads photos from GeoDirectory's CDN, reverse-geocodes random map points
 * over the network, credits the admin and keeps no ids, so it could not be recorded or removed cleanly.
 */
final class GeoDirectory extends Provider {

	/**
	 * GeoDirectory's place post type.
	 */
	private const TYPE = 'gd_place';

	/**
	 * Places saved per step: each one imports a photo (with its thumbnail sizes).
	 */
	private const PER_STEP = 6;

	/**
	 * Place fields a fresh GeoDirectory lacks (its dummy data adds them), so the place pages show a phone number,
	 * a website and opening hours.
	 */
	private const FIELDS = array( 'phone', 'website', 'business_hours' );

	/**
	 * Fields shown under the description on a place's Profile tab, with GeoDirectory's admin label and icon. A fresh
	 * GeoDirectory shows "detail" fields only through a sidebar widget its setup wizard adds, which a theme's own
	 * sidebar (Reign's) does not have, so the address, phone, website and hours would not show at all.
	 */
	private const DETAILS = array(
		'address'        => array( 'Address', 'fas fa-map-marker-alt' ),
		'phone'          => array( 'Phone', 'fas fa-phone' ),
		'website'        => array( 'Website', 'fas fa-globe' ),
		'business_hours' => array( 'Business Hours', 'fas fa-clock' ),
	);

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'geodirectory';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'GeoDirectory';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return defined( 'GEODIRECTORY_VERSION' ) && post_type_exists( self::TYPE ) && class_exists( 'GeoDir_Comments' ) && function_exists( 'geodir_custom_field_save' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return defined( 'GEODIRECTORY_VERSION' ) ? (string) GEODIRECTORY_VERSION : '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function min_version(): string {
		return '2.8.0';
	}

	/**
	 * {@inheritDoc}
	 */
	public function icon(): string {
		return 'map';
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 56; // Next to WB Listora.
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		$c = $this->content();
		return sprintf(
			/* translators: 1: places, 2: categories */
			__( '%1$d places in %2$d categories with photos, opening hours, map pins and reviews', 'wbcom-demo-installer' ),
			count( $c['places'] ),
			count( $c['categories'] )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function activation_redirects(): array {
		return array( '_gd_activation_redirect' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function ai_keep(): array {
		// Streets and zip codes match the fixed map coordinates.
		$rows = (array) ( $this->content()['places'] ?? array() );
		return array_values( array_unique( array_merge( array_column( $rows, 3 ), array_column( $rows, 4 ) ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		self::ensure_installed();
		$c     = $this->content();
		$plan  = array(
			'place'      => array( count( $c['places'] ), 'sample', __( 'places', 'wbcom-demo-installer' ) ),
			'attachment' => array( count( $c['places'] ), 'sample', __( 'place photos', 'wbcom-demo-installer' ) ),
			'review'     => array( count( $c['reviews'] ), 'sample', __( 'reviews', 'wbcom-demo-installer' ) ),
		);
		$terms = self::missing_terms( array( self::TYPE . 'category' => array_column( $c['categories'], 0 ) ) );
		if ( $terms ) {
			$plan['term'] = array( $terms, 'setup', __( 'place categories', 'wbcom-demo-installer' ) );
		}
		$tabs = count( self::missing_details() );
		if ( $tabs ) {
			$plan['tab'] = array( $tabs, 'setup', __( 'place page details (address, phone, website, hours)', 'wbcom-demo-installer' ) );
		}
		$fields = count( $this->missing_fields() );
		if ( $fields ) {
			$plan['field'] = array( $fields, 'setup', __( 'place fields (phone, website, hours)', 'wbcom-demo-installer' ) );
		}
		return $plan;
	}

	/**
	 * {@inheritDoc}
	 */
	public function type_labels(): array {
		// After a build plan() no longer lists categories and fields (none are missing), but Finish still names them.
		return parent::type_labels() + array(
			'term'  => __( 'place categories', 'wbcom-demo-installer' ),
			'field' => __( 'place fields (phone, website, hours)', 'wbcom-demo-installer' ),
			'tab'   => __( 'place page details (address, phone, website, hours)', 'wbcom-demo-installer' ),
		);
	}

	/**
	 * The place page layout: GeoDirectory's tab items for places.
	 *
	 * @return object[]
	 */
	private static function tabs(): array {
		return class_exists( 'GeoDir_Settings_Cpt_Tabs' ) ? (array) \GeoDir_Settings_Cpt_Tabs::get_tabs_fields( self::TYPE, 'post' ) : array();
	}

	/**
	 * The place page's Profile tab (the description), null when the owner removed it.
	 */
	private static function profile_tab(): ?object {
		foreach ( self::tabs() as $tab ) {
			if ( 'post_content' === $tab->tab_key && ! (int) $tab->tab_level ) {
				return $tab;
			}
		}
		return null;
	}

	/**
	 * Details the place page does not show yet, none when it has no Profile tab to put them in.
	 *
	 * @return string[] Field keys.
	 */
	private static function missing_details(): array {
		if ( ! self::profile_tab() ) {
			return array();
		}
		$shown = array_column( self::tabs(), 'tab_key' );
		return array_values( array_diff( array_keys( self::DETAILS ), $shown ) );
	}

	/**
	 * The place fields this site lacks, as GeoDirectory defines them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function missing_fields(): array {
		$defs   = array_filter(
			(array) \GeoDir_Admin_Dummy_Data::extra_custom_fields( self::TYPE ),
			static fn( $f ) => in_array( $f['htmlvar_name'] ?? '', self::FIELDS, true )
		);
		$defs[] = array( // As GeoDirectory's standard places dummy data defines it.
			'post_type'      => self::TYPE,
			'data_type'      => 'TEXT',
			'field_type'     => 'business_hours',
			'admin_title'    => __( 'Business Hours', 'wbcom-demo-installer' ),
			'frontend_desc'  => __( 'Select your business opening/operating hours.', 'wbcom-demo-installer' ),
			'frontend_title' => __( 'Business Hours', 'wbcom-demo-installer' ),
			'htmlvar_name'   => 'business_hours',
			'default_value'  => '',
			'is_active'      => '1',
			'option_values'  => '',
			'is_default'     => '0',
			'show_in'        => '[owntab],[detail]',
			'show_on_pkg'    => '',
			'field_icon'     => 'fas fa-clock',
			'clabels'        => __( 'Business Hours', 'wbcom-demo-installer' ),
		);
		return array_values( array_filter( $defs, static fn( $f ) => ! geodir_get_field_infoby( 'htmlvar_name', (string) $f['htmlvar_name'], self::TYPE ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Context $ctx See parent.
	 */
	public function steps( Context $ctx ): array {
		$steps = array( 'setup' );
		for ( $i = 0, $n = count( $this->content()['places'] ); $i < $n; $i += self::PER_STEP ) {
			$steps[] = 'places:' . $i;
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
		self::ensure_installed();
		list( $name, $offset ) = array_pad( explode( ':', $step, 2 ), 2, '0' );
		match ( $name ) {
			'setup'   => $this->setup( $ctx ),
			'places'  => $this->places( $ctx, (int) $offset ),
			'reviews' => $this->reviews( $ctx ),
			default   => null,
		};
	}

	/**
	 * GeoDirectory creates its tables, default fields and pages only on a wp-admin or WP-CLI request after it is
	 * activated. Installed from the wizard and built over REST, no such request comes between, so its tables do not
	 * exist yet; run GeoDirectory's own installer first, as its first admin page load would.
	 */
	private static function ensure_installed(): void {
		if ( ! defined( 'GEODIRECTORY_VERSION' ) || GEODIRECTORY_VERSION === get_option( 'geodirectory_version' ) ) {
			return;
		}
		if ( ! class_exists( 'GeoDir_Admin_Install' ) && defined( 'GEODIRECTORY_PLUGIN_DIR' ) && is_readable( GEODIRECTORY_PLUGIN_DIR . 'includes/admin/class-geodir-admin-install.php' ) ) {
			require_once GEODIRECTORY_PLUGIN_DIR . 'includes/admin/class-geodir-admin-install.php';
		}
		if ( class_exists( 'GeoDir_Admin_Install' ) ) {
			\GeoDir_Admin_Install::install();
		}
	}

	/**
	 * The place fields and categories the places need. A new category gets the icon, colour and schema type
	 * GeoDirectory shows on category lists and map pins; an existing one is used as it is.
	 *
	 * @param Context $ctx Context.
	 */
	private function setup( Context $ctx ): void {
		// The demo sets the directory up, so GeoDirectory's "You're almost ready, run the Setup Wizard" notice would
		// contradict it. Drop it through Snapshot: Remove everything brings it back. Its wizard stays in its menu.
		$notices = (array) get_option( 'geodirectory_admin_notices', array() );
		if ( in_array( 'install', $notices, true ) ) {
			Snapshot::write( 'geodirectory_admin_notices', array_diff( $notices, array( 'install' ) ) ); // Keys kept, as GeoDirectory's remove_notice() keeps them.
			if ( class_exists( 'GeoDir_Admin_Notices' ) ) {
				\GeoDir_Admin_Notices::remove_notice( 'install' );
			}
		}
		foreach ( $this->missing_fields() as $field ) {
			$id = geodir_custom_field_save( $field );
			if ( is_wp_error( $id ) || ! $id ) {
				$ctx->warn( $this->id(), 'field ' . $field['htmlvar_name'], is_wp_error( $id ) ? $id : __( 'GeoDirectory did not save it.', 'wbcom-demo-installer' ) );
				continue;
			}
			$ctx->record( $this->id(), 'field', (int) $id, 'setup' );
		}
		$this->details( $ctx );
		$taxonomy = self::TYPE . 'category';
		$map      = array();
		foreach ( (array) $this->content()['categories'] as $key => $cat ) {
			list( $label, $icon, $color, $schema ) = $cat;
			$new                                   = ! term_exists( $label, $taxonomy );
			$map[ $key ]                           = $this->ensure_term( $label, $taxonomy, $ctx );
			if ( $new && $map[ $key ] ) {
				update_term_meta( $map[ $key ], 'ct_cat_font_icon', $icon );
				update_term_meta( $map[ $key ], 'ct_cat_color', $color );
				update_term_meta( $map[ $key ], 'ct_cat_schema', $schema );
			}
		}
		if ( function_exists( 'geodir_get_term_icon_rebuild' ) ) {
			geodir_get_term_icon_rebuild();
		}
		$ctx->set( $this->id(), 'categories', $map );
	}

	/**
	 * Show the address, phone, website and hours under the description, as an owner does by dragging those fields
	 * into the Profile tab in GeoDirectory's place page layout (its save_tab_item() and set_tabs_orders()).
	 *
	 * @param Context $ctx Context.
	 */
	private function details( Context $ctx ): void {
		$profile = self::profile_tab();
		foreach ( self::missing_details() as $n => $key ) {
			list( $name, $icon ) = self::DETAILS[ $key ];
			$saved               = \GeoDir_Settings_Cpt_Tabs::save_tab_item(
				array(
					'post_type'   => self::TYPE,
					'tab_layout'  => 'post',
					'tab_type'    => 'meta',
					'tab_name'    => $name,
					'tab_icon'    => $icon,
					'tab_key'     => $key,
					'tab_content' => '',
				)
			);
			$id                  = 0;
			foreach ( self::tabs() as $tab ) {
				$id = $key === $tab->tab_key && 'meta' === $tab->tab_type ? (int) $tab->id : $id;
			}
			if ( is_wp_error( $saved ) || ! $id || ! $profile ) {
				$ctx->warn( $this->id(), 'place page ' . $name, is_wp_error( $saved ) ? $saved : __( 'GeoDirectory did not save it.', 'wbcom-demo-installer' ) );
				continue;
			}
			$ctx->record( $this->id(), 'tab', $id, 'setup' );
			// Into the Profile tab, after the description, in this order (the key is the sort order).
			\GeoDir_Settings_Cpt_Tabs::set_tabs_orders(
				array(
					100 + $n => array(
						'id'         => $id,
						'tab_level'  => 1,
						'tab_parent' => (int) $profile->id,
					),
				)
			);
		}
	}

	/**
	 * Save one chunk of places, each owned by a sample person who lists places, with its photo as GeoDirectory's
	 * first image.
	 *
	 * @param Context $ctx    Context.
	 * @param int     $offset First place index of this step.
	 */
	private function places( Context $ctx, int $offset ): void {
		$c                               = $this->content();
		$cats                            = (array) $ctx->get( $this->id(), 'categories', array() );
		$ids                             = (array) $ctx->get( $this->id(), 'places', array() );
		$total                           = count( $c['places'] );
		list( $city, $region, $country ) = $c['city'];
		foreach ( array_slice( $c['places'], $offset, self::PER_STEP, true ) as $i => $row ) {
			list( , $title, $cat, $street, $zip, $lat, $lng, $phone, $site, $photo, $featured, $desc ) = $row;
			$owner  = $ctx->person( (int) $i, 'listing_owner' );
			$cat_id = (int) ( $cats[ $cat ] ?? 0 );
			$date   = $ctx->date( (int) $i, $total, 120 );
			// GeoDirectory reads its fields from the post array (its wp_insert_post_data and save_post handlers).
			$data = array(
				'post_type'        => self::TYPE,
				'post_status'      => 'publish',
				'post_title'       => $title,
				'post_content'     => wpautop( $desc ),
				'post_author'      => $owner,
				'post_date_gmt'    => $date,
				'post_date'        => get_date_from_gmt( $date ),
				'comment_status'   => 'open',
				'post_category'    => $cat_id ? array( $cat_id ) : array(),
				'default_category' => $cat_id,
				'featured'         => (int) $featured,
				'street'           => $street,
				'city'             => $city,
				'region'           => $region,
				'country'          => $country,
				'zip'              => $zip,
				'latitude'         => $lat,
				'longitude'        => $lng,
				'phone'            => $phone,
				'website'          => $site,
				'business_hours'   => (string) ( $c['hours'][ $cat ] ?? '' ),
			);
			Clock::set( $date );
			$id = wp_insert_post( $data, true );
			Clock::reset();
			if ( is_wp_error( $id ) ) {
				$ctx->warn( $this->id(), 'place ' . $title, $id );
				continue;
			}
			$ctx->record( $this->id(), 'place', (int) $id );
			$ids[ $i ] = (int) $id;
			if ( $cat_id ) {
				wp_set_object_terms( (int) $id, array( $cat_id ), self::TYPE . 'category' );
			}
			$this->photo( $ctx, (int) $id, 'photos/' . $photo, $owner, $title );
		}
		$ctx->set( $this->id(), 'places', $ids );
	}

	/**
	 * A place's photo: our recorded attachment becomes the featured image and GeoDirectory's first image. The image
	 * is registered with GeoDirectory's own insert_attachment() for a file already in uploads (the way its importer
	 * adds images), so no image is downloaded over the network. Deleting the place deletes it through GeoDirectory.
	 *
	 * @param Context $ctx      Context.
	 * @param int     $place_id Place id.
	 * @param string  $rel      Path under media/.
	 * @param int     $owner    Owner id.
	 * @param string  $title    Place name.
	 */
	private function photo( Context $ctx, int $place_id, string $rel, int $owner, string $title ): void {
		$image = $ctx->attachment( $rel, $owner, $this->id() );
		if ( ! $image ) {
			return;
		}
		set_post_thumbnail( $place_id, $image );
		$file = (string) get_post_meta( $image, '_wp_attached_file', true ); // "2026/11/name.jpg".
		$meta = wp_get_attachment_metadata( $image );
		$was  = get_current_user_id();
		wp_set_current_user( $owner ); // GeoDirectory credits the image to the current user.
		// insert_attachment() puts a placeholder file in the current uploads folder, where the attachment just went.
		$done = \GeoDir_Media::insert_attachment( $place_id, 'post_images', $file, $title, '', 0, 1, true, '', is_array( $meta ) ? $meta : array() );
		wp_set_current_user( $was );
		if ( is_wp_error( $done ) ) {
			$ctx->warn( $this->id(), 'photo ' . $title, $done );
			return;
		}
		// What GeoDirectory's own save stores for the first image (lists filtered to places with a photo).
		geodir_save_post_meta( $place_id, 'featured_image', (string) $done['file'] );
	}

	/**
	 * Reviews: comments with a star rating, saved through GeoDirectory's own save_rating() as its review form does,
	 * so the place's average rating and review count update.
	 *
	 * @param Context $ctx Context.
	 */
	private function reviews( Context $ctx ): void {
		$ids     = (array) $ctx->get( $this->id(), 'places', array() );
		$reviews = (array) $this->content()['reviews'];
		$was     = get_current_user_id();
		// A review tells the place owner and every administrator (the Reign GeoDirectory add-on reads GeoDirectory's
		// recipient filter); while building, only sample people are told.
		$only_sample = static fn( $users ) => array_values( array_filter( (array) $users, static fn( $u ) => (bool) get_user_meta( (int) $u, People::FLAG, true ) ) );
		add_filter( 'geodir_review_users_lists', $only_sample, 999 );
		foreach ( $reviews as $n => $row ) {
			list( $place_i, $person, $rating, $text ) = $row;
			$place_id                                 = (int) ( $ids[ $place_i ] ?? 0 );
			$user_id                                  = $ctx->person( (int) $person );
			$user                                     = $user_id ? get_userdata( $user_id ) : false;
			if ( ! $place_id || ! $user || (int) get_post_field( 'post_author', $place_id ) === $user_id ) {
				continue;
			}
			$placed = (string) get_post_field( 'post_date_gmt', $place_id );
			$date   = $ctx->date( (int) $n, count( $reviews ), 90 );
			$date   = $date > $placed ? $date : $ctx->after( $placed, (int) $n );
			Clock::set( $date ); // Bridges such as the Reign add-on's activity post get the review's date.
			$id = wp_insert_comment(
				array(
					'comment_post_ID'      => $place_id,
					'comment_author'       => $user->display_name,
					'comment_author_email' => $user->user_email,
					'comment_content'      => $text,
					'comment_type'         => 'comment',
					'comment_approved'     => 1,
					'user_id'              => $user_id,
					'comment_date_gmt'     => $date,
					'comment_date'         => get_date_from_gmt( $date ),
				)
			);
			Clock::reset();
			if ( ! $id ) {
				continue;
			}
			$ctx->record( $this->id(), 'review', (int) $id );
			// save_rating() reads the stars from the review form field and the reviewer from the current user.
			wp_set_current_user( $user_id );
			$_REQUEST['geodir_overallrating'] = (string) max( 1, min( 5, (int) $rating ) );
			\GeoDir_Comments::save_rating( (int) $id );
			unset( $_REQUEST['geodir_overallrating'] );
		}
		remove_filter( 'geodir_review_users_lists', $only_sample, 999 );
		wp_set_current_user( $was );
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
			case 'place':
				// GeoDirectory's before_delete_post handler removes its detail row and images (the featured
				// attachment included) and its review rows go with the comments.
				return self::delete_post( $id );
			case 'review':
				if ( get_comment( $id ) ) {
					wp_delete_comment( $id, true ); // GeoDirectory's delete_comment handler drops the rating.
				}
				return ! get_comment( $id );
			case 'attachment':
				return self::delete_post( $id, true );
			case 'term':
				return self::remove_unused_term( $id, (string) ( $meta['taxonomy'] ?? self::TYPE . 'category' ) );
			case 'tab':
				if ( ! class_exists( 'GeoDir_Settings_Cpt_Tabs' ) ) {
					return false;
				}
				\GeoDir_Settings_Cpt_Tabs::delete_tab( $id, self::TYPE );
				return ! in_array( $id, array_map( 'intval', array_column( self::tabs(), 'id' ) ), true );
			case 'field':
				if ( ! class_exists( 'GeoDir_Settings_Cpt_Cf' ) ) {
					return false;
				}
				\GeoDir_Settings_Cpt_Cf::delete_custom_field( $id );
				// It answers "invalid field" once the field is gone, so a second call confirms the delete.
				return is_wp_error( \GeoDir_Settings_Cpt_Cf::delete_custom_field( $id ) );
		}
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function late_types(): array {
		return array( 'term', 'tab', 'field' );
	}

	/**
	 * A GeoDirectory page by its setting key, 0 when not published.
	 *
	 * @param string $key Setting key, e.g. page_location.
	 */
	private static function page( string $key ): int {
		$id = function_exists( 'geodir_get_option' ) ? (int) geodir_get_option( $key ) : 0;
		return $id && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * The places archive.
	 */
	private static function archive(): string {
		$link = get_post_type_archive_link( self::TYPE );
		return $link ? (string) $link : '';
	}

	/**
	 * GeoDirectory's location page: search, categories, map and the newest places on one page.
	 */
	public function front_page(): int {
		return self::page( 'page_location' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * GeoDirectory's "Admin Hints" box sits on every directory page for administrators, and the owner walks the
	 * demo as one; hints are turned off for the demo and come back on Remove everything.
	 */
	public function settings(): array {
		return array( 'geodir_settings' => array( 'enable_hints' => 0 ) );
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
	 * The directory's part of the designed home page, in GeoDirectory's own blocks (search, category tiles,
	 * listings, map), so the owner edits them with GeoDirectory's settings.
	 *
	 * @param Context $ctx See parent.
	 */
	public function home( Context $ctx ): array {
		$h       = (array) ( $this->content()['home'] ?? array() );
		$archive = (string) get_post_type_archive_link( self::TYPE );
		$add     = function_exists( 'geodir_add_listing_page_url' ) ? (string) geodir_add_listing_page_url( self::TYPE ) : '';
		$places  = (int) ( wp_count_posts( self::TYPE )->publish ?? 0 );
		$cats    = (int) wp_count_terms(
			array(
				'taxonomy'   => self::TYPE . 'category',
				'hide_empty' => true,
			)
		);
		$buttons = array_values( array_filter( array( $archive ? array( (string) $h['browse'], $archive ) : null, $add ? array( (string) $h['add'], $add ) : null ) ) );
		return array(
			'hero'     => array(
				'title'   => (string) $h['title'],
				'text'    => (string) $h['text'],
				'image'   => (string) $h['image'],
				'buttons' => $buttons,
				'closing' => (string) $h['closing'],
				'cta'     => $add ? array( (string) $h['add'], $add ) : ( $buttons[0] ?? array() ),
			),
			'stats'    => array(
				array( $places, __( 'places listed', 'wbcom-demo-installer' ) ),
				array( $cats, __( 'categories', 'wbcom-demo-installer' ) ),
			),
			'sections' => array(
				self::gd_block( 'search', array(), "[gd_search mb='3']" ),
				Blocks::heading( (string) $h['cats'][0] ) . Blocks::paragraph( (string) $h['cats'][1] )
					. self::gd_block(
						'categories',
						array(
							'hide_empty'  => true,
							'design_type' => 'icon-top',
							'icon_size'   => 'box-medium',
						),
						"[gd_categories post_type='0' hide_empty='true' sort_by='count' design_type='icon-top' icon_size='box-medium' mb='3']"
					),
				Blocks::heading( (string) $h['featured'][0] ) . Blocks::paragraph( (string) $h['featured'][1] )
					. self::gd_block( 'listings', array( 'post_limit' => 6 ), "[gd_listings post_type='gd_place' post_limit='6' layout='3' title_tag='h3' mb='3']" )
					. ( $archive ? Blocks::button( (string) $h['all'], $archive ) : '' ),
				Blocks::heading( (string) $h['map'][0] ) . Blocks::paragraph( (string) $h['map'][1] )
					. self::gd_block(
						'map',
						array(
							'map_type'         => 'directory',
							'search_filter'    => true,
							'post_type_filter' => true,
							'cat_filter'       => true,
							'height'           => '425px',
						),
						"[gd_map map_type='directory' post_settings='true' search_filter='true' post_type_filter='true' cat_filter='true' child_collapse='true' scrollwheel='false' width='100%' height='425px' maptype='ROADMAP' mb='4']"
					),
			),
		);
	}

	/**
	 * One of GeoDirectory's widget blocks as its own editor saves it: attributes plus the shortcode it renders.
	 *
	 * @param string              $name      Widget name (search, categories, listings, map).
	 * @param array<string,mixed> $attrs     Block attributes.
	 * @param string              $shortcode The shortcode GeoDirectory renders for it.
	 */
	private static function gd_block( string $name, array $attrs, string $shortcode ): string {
		$block = 'geodirectory/geodir-widget-' . $name;
		$json  = (string) wp_json_encode(
			$attrs + array(
				'content'      => '',
				'sd_shortcode' => $shortcode,
			),
			JSON_UNESCAPED_SLASHES
		);
		return '<!-- wp:' . $block . ' ' . $json . ' --><div class="wp-block-geodirectory-geodir-widget-' . $name . '"></div><!-- /wp:' . $block . ' -->';
	}

	/**
	 * {@inheritDoc}
	 */
	public function full_width_archives(): array {
		return array( 'gd_place' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function layout_pages(): array {
		return array_filter( array( self::page( 'page_location' ), self::page( 'page_archive' ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function menu_items(): array {
		$url = self::archive();
		return $url
			? array(
				array(
					'title' => __( 'Places', 'wbcom-demo-installer' ),
					'url'   => $url,
				),
			)
			: array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		if ( ! $this->available() ) {
			return array();
		}
		$t      = $this->content()['guide'];
		$places = Ledger::ids( $this->id(), 'place' );
		$pick   = $places ? (int) min( $places ) : 0;
		if ( ! $pick ) {
			$any  = get_posts(
				array(
					'post_type'   => self::TYPE,
					'post_status' => 'publish',
					'fields'      => 'ids',
					'numberposts' => 1,
				)
			);
			$pick = $any ? (int) $any[0] : 0;
		}
		$browse = self::archive();
		$add    = self::page( 'page_add' );
		return array(
			'name'         => $t['name'],
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => array(
				array(
					'key'    => 'member',
					'user'   => People::user_id( 'listing_owner' ),
					'label'  => $t['roles']['member'][0],
					'points' => $t['roles']['member'][1],
				),
			),
			'member'       => self::guide_steps(
				$t['member'],
				array(
					'browse' => '' !== $browse ? $browse : null,
					'open'   => $pick ? (string) get_permalink( $pick ) : null,
					'add'    => $add ? (string) add_query_arg( 'listing_type', self::TYPE, (string) get_permalink( $add ) ) : null,
				),
				array(
					'browse' => array(
						'public'    => true,
						'as'        => 'member',
						'highlight' => true,
					),
					'open'   => array(
						'public' => true,
						'as'     => 'member',
					),
					'add'    => array( 'as' => 'member' ),
				)
			),
			'owner'        => self::guide_steps(
				$t['owner'],
				array(
					'places'   => admin_url( 'edit.php?post_type=' . self::TYPE ),
					'reviews'  => admin_url( 'edit-comments.php' ),
					'settings' => admin_url( 'admin.php?page=gd-settings' ),
				)
			),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}
}
