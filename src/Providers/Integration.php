<?php
/**
 * A third-party plugin on a demo site (BuddyPress, bbPress, LearnDash, Dokan...): its part of the "Try the demo" page.
 * Builds nothing; one instance per integration id in content/integrations.php.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Providers;

use WbcomDemo\Core\Context;
use WbcomDemo\Core\Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Guide-only provider for one integration.
 */
final class Integration extends Provider {

	/**
	 * Integration id.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Integration text for every id, see content/integrations.php.
	 *
	 * @var array<string,array<string,mixed>>|null
	 */
	private static ?array $all = null;

	/**
	 * Integration ids in display order.
	 *
	 * @return string[]
	 */
	public static function ids(): array {
		if ( null === self::$all ) {
			$file      = WBCOM_DEMO_DIR . 'content/integrations.php';
			self::$all = (array) apply_filters( 'wbcom_demo_content', is_readable( $file ) ? require $file : array(), 'integrations' );
		}
		return array_keys( self::$all );
	}

	/**
	 * Constructor.
	 *
	 * @param string $key Integration id.
	 */
	public function __construct( string $key ) {
		$this->key = $key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return $this->key;
	}

	/**
	 * This integration's text.
	 *
	 * @return array<string,mixed>
	 */
	private function text(): array {
		self::ids();
		return (array) ( self::$all[ $this->key ] ?? array() );
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return (string) ( $this->text()['name'] ?? $this->key );
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		switch ( $this->key ) {
			case 'peepso':
				return class_exists( 'PeepSo' );
			case 'bbpress':
				return function_exists( 'bbp_get_forum_post_type' );
			case 'learndash':
				return defined( 'LEARNDASH_VERSION' );
			case 'woocommerce':
				return function_exists( 'wc_get_page_permalink' );
			case 'dokan':
				return function_exists( 'dokan_get_navigation_url' );
			case 'wcfm':
				return function_exists( 'get_wcfm_url' ) && function_exists( 'wcfmmp_get_store_url' );
			case 'wcvendors':
				return class_exists( 'WCV_Vendors' );
			case 'jobmanager':
				return defined( 'JOB_MANAGER_VERSION' );
			case 'tribeevents':
				return function_exists( 'tribe_get_events_link' );
		}
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function order(): int {
		return 90; // After the products (community platforms have their own providers).
	}

	/**
	 * {@inheritDoc}
	 */
	public function summary(): string {
		return '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function plan(): array {
		return array();
	}

	/**
	 * Nothing to build.
	 *
	 * @param Context $ctx Build context.
	 * @return string[]
	 */
	public function steps( Context $ctx ): array {
		return array();
	}

	/**
	 * Nothing to build.
	 *
	 * @param string  $step Step key.
	 * @param Context $ctx  Build context.
	 */
	public function run( string $step, Context $ctx ): void {}

	/**
	 * Nothing recorded, so nothing to remove.
	 *
	 * @param object $row Ledger row.
	 */
	public function remove( object $row ): bool {
		return true;
	}

	/**
	 * A page's link, or null when the page is not set.
	 *
	 * @param int $page_id Page id.
	 */
	private static function page( int $page_id ): ?string {
		return $page_id && 'publish' === get_post_status( $page_id ) ? (string) get_permalink( $page_id ) : null;
	}

	/**
	 * Step links by step key: [ member links, owner links ]. A null link drops the step.
	 *
	 * @return array{0:array<string,string|null>,1:array<string,string|null>}
	 */
	private function links(): array {
		$archive = static function ( string $type ): ?string {
			$link = get_post_type_archive_link( $type );
			return $link ? (string) $link : null;
		};
		switch ( $this->key ) {
			case 'peepso':
				$page = static function ( string $key ): ?string {
					$slug = trim( (string) ( ( (array) get_option( 'peepso_config', array() ) )[ 'page_' . $key ] ?? '' ), '/' );
					return '' === $slug && 'activity' !== $key ? null : home_url( '' === $slug ? '/' : '/' . $slug . '/' );
				};
				return array(
					array(
						'activity' => $page( 'activity' ),
						'members'  => $page( 'members' ),
						'groups'   => $page( 'groups' ),
					),
					array(
						'components' => admin_url( 'admin.php?page=peepso_config' ),
						'users'      => admin_url( 'users.php' ),
					),
				);
			case 'bbpress':
				return array( array( 'forums' => $archive( bbp_get_forum_post_type() ) ), array( 'forums' => admin_url( 'edit.php?post_type=' . bbp_get_forum_post_type() ) ) );
			case 'learndash':
				$course = get_posts(
					array(
						'post_type'   => 'sfwd-courses',
						'numberposts' => 1,
						'fields'      => 'ids',
					)
				);
				return array( array( 'courses' => $archive( 'sfwd-courses' ) ?? ( $course ? (string) get_permalink( $course[0] ) : null ) ), array( 'courses' => admin_url( 'edit.php?post_type=sfwd-courses' ) ) );
			case 'woocommerce':
				return array(
					array(
						'shop'    => (string) wc_get_page_permalink( 'shop' ),
						'account' => (string) wc_get_page_permalink( 'myaccount' ),
					),
					array(
						'orders'   => admin_url( 'admin.php?page=wc-orders' ),
						'products' => admin_url( 'edit.php?post_type=product' ),
					),
				);
			case 'dokan':
				return array(
					array(
						'stores'    => self::page( (int) dokan_get_option( 'store_listing', 'dokan_pages', 0 ) ),
						'dashboard' => (string) dokan_get_navigation_url(),
					),
					array( 'dokan' => admin_url( 'admin.php?page=dokan' ) ),
				);
			case 'wcfm':
				return array(
					array(
						'stores'    => self::page( WCFM::stores_page() ),
						'dashboard' => (string) get_wcfm_url(),
					),
					array(
						'vendors'  => (string) get_wcfm_vendors_url(),
						'settings' => (string) get_wcfm_settings_url(),
					),
				);
			case 'wcvendors':
				return array(
					array(
						'stores'    => self::page( (int) get_option( 'wcvendors_vendors_page_id' ) ),
						'dashboard' => self::page( (int) get_option( 'wcvendors_vendor_dashboard_page_id' ) ),
					),
					array(
						'vendors'  => admin_url( 'users.php?role=vendor' ),
						'settings' => admin_url( 'admin.php?page=wcv-settings' ),
					),
				);
			case 'jobmanager':
				return array( array( 'jobs' => self::page( (int) get_option( 'job_manager_jobs_page_id' ) ) ), array( 'jobs' => admin_url( 'edit.php?post_type=job_listing' ) ) );
			case 'tribeevents':
				return array( array( 'events' => (string) tribe_get_events_link() ), array( 'events' => admin_url( 'edit.php?post_type=tribe_events' ) ) );
		}
		return array( array(), array() );
	}

	/**
	 * A sample person for a role: vendors by their role, everyone else from the first cast role that has someone.
	 *
	 * @param string $key Role key.
	 */
	private static function person( string $key ): int {
		foreach ( 'vendor' === $key ? array( 'vendor' ) : array( 'member', 'attendee', 'student', 'buyer' ) as $role ) {
			$id = People::user_id( $role );
			if ( $id ) {
				return $id;
			}
		}
		return 0;
	}

	/**
	 * {@inheritDoc}
	 */
	public function guide(): array {
		$t = $this->text();
		if ( ! $t || ! $this->available() ) {
			return array();
		}
		list( $member_links, $owner_links ) = $this->links();
		$roles                              = array();
		foreach ( (array) $t['roles'] as $key => $role ) {
			$roles[] = array(
				'key'    => (string) $key,
				'user'   => self::person( (string) $key ),
				'label'  => $role[0],
				'points' => $role[1],
			);
		}
		$first = (string) array_key_first( (array) $t['roles'] );
		$extra = array();
		foreach ( array_keys( (array) $t['member'] ) as $i => $step ) {
			$extra[ $step ] = array(
				'as'        => 'dashboard' === $step ? 'vendor' : $first,
				'public'    => ! in_array( $step, array( 'dashboard' ), true ),
				'highlight' => 0 === $i,
			);
		}
		return array(
			'name'         => $t['name'],
			'tab'          => $t['tab'],
			'member_label' => $t['member_label'],
			'owner_points' => $t['owner_points'],
			'roles'        => $roles,
			'member'       => self::guide_steps( $t['member'], $member_links, $extra ),
			'owner'        => self::guide_steps( $t['owner'], $owner_links ),
			'highlights'   => self::guide_highlights( $t['highlights'] ),
		);
	}
}
