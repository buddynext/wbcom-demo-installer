<?php
/**
 * The Starting point gallery: named demos for the active theme (whole-site combinations, and one per Reign add-on),
 * each with what it needs and where the site stands on it, read from the installed theme and plugins.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

use WbcomDemo\Providers\Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Demo gallery.
 */
final class Demos {

	/** Community plugins that give any demo its members (catalog ids). */
	private const COMMUNITY = array( 'buddypress', 'buddyboss', 'peepso', 'buddynext' );

	/**
	 * Every demo, as content/demos.php defines it.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all(): array {
		/**
		 * Demos offered on the Starting point gallery.
		 *
		 * @param array<string,array<string,mixed>> $demos Demos by id.
		 */
		return (array) apply_filters( 'wbcom_demo_demos', require WBCOM_DEMO_DIR . 'content/demos.php' );
	}

	/**
	 * The demos for the active theme, with each requirement's state.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function overview(): array {
		$family = Theme::theme_family();
		$out    = array();
		foreach ( self::all() as $id => $demo ) {
			if ( ( $demo['theme'] ?? '' ) !== $family ) {
				continue;
			}
			$rows = array();
			if ( ! empty( $demo['addon'] ) ) {
				$rows[] = self::addon_row( (array) $demo['addon'], true );
			}
			foreach ( (array) $demo['requires'] as $need ) {
				$rows[] = 'community' === $need ? self::community_row() : self::plugin_row( (string) $need );
			}
			foreach ( (array) ( $demo['optional'] ?? array() ) as $addon ) {
				$rows[] = self::addon_row( (array) $addon, false );
			}
			$conflict = self::conflict( (array) $demo['requires'] );
			$needed   = array_filter( $rows, static fn( array $r ): bool => $r['required'] && 'active' !== $r['state'] );
			$out[]    = array(
				'id'       => (string) $id,
				'group'    => (string) ( $demo['group'] ?? 'theme' ),
				'name'     => (string) $demo['name'],
				'desc'     => (string) $demo['desc'],
				'image'    => file_exists( WBCOM_DEMO_DIR . 'media/demos/' . $id . '.webp' ) ? WBCOM_DEMO_URL . 'media/demos/' . $id . '.webp' : '',
				'preview'  => (string) ( $demo['preview'] ?? '' ),
				'rows'     => $rows,
				'products' => self::products( (array) $demo['requires'] ),
				'conflict' => $conflict,
				// Ready: build now. Fixable: the missing pieces install or switch on from here. Otherwise the owner
				// uploads something first (a paid add-on or plugin).
				'state'    => $conflict ? 'conflict' : ( ! $needed ? 'ready' : ( array_filter( $needed, static fn( array $r ): bool => ! $r['can'] ) ? 'blocked' : 'fixable' ) ),
				'fix'      => array_values( array_map( static fn( array $r ): string => (string) $r['id'], array_filter( $needed, static fn( array $r ): bool => $r['can'] ) ) ),
			);
		}
		return $out;
	}

	/**
	 * Products a demo builds: its requirements that have a provider, the community plugin the site runs for
	 * "community".
	 *
	 * @param string[] $requires Catalog ids.
	 * @return string[] Provider ids.
	 */
	private static function products( array $requires ): array {
		$map = array(
			'buddyboss'     => 'buddypress', // BuddyBoss Platform runs the BuddyPress provider.
			'wcfm-frontend' => '',
		);
		$out = array();
		foreach ( $requires as $need ) {
			if ( 'community' === $need ) {
				$active = self::active_community();
				$need   = '' !== $active ? $active : 'buddypress';
			}
			$id = array_key_exists( $need, $map ) ? $map[ $need ] : $need;
			if ( '' !== $id ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * A catalog plugin requirement.
	 *
	 * @param string $id Catalog id.
	 * @return array<string,mixed>
	 */
	private static function plugin_row( string $id ): array {
		$s = Installer::status( $id );
		return array(
			'id'       => $id,
			'label'    => $s['label'],
			'kind'     => 'plugin',
			'state'    => $s['state'],
			'can'      => $s['can'],
			'paid'     => $s['paid'],
			'url'      => $s['url'],
			'required' => true,
		);
	}

	/**
	 * The members part of an add-on demo: the community plugin the site already runs, else BuddyPress.
	 *
	 * @return array<string,mixed>
	 */
	private static function community_row(): array {
		$active = self::active_community();
		$row    = self::plugin_row( '' !== $active ? $active : 'buddypress' );
		return $row;
	}

	/**
	 * A Reign add-on: required for an add-on demo, optional (improves it) for a theme demo. Add-ons are paid and
	 * uploaded by the owner; an installed one can be switched on from here.
	 *
	 * @param array{0:string,1:string} $addon    Folder and name.
	 * @param bool                     $required Required.
	 * @return array<string,mixed>
	 */
	private static function addon_row( array $addon, bool $required ): array {
		$file  = self::addon_file( (string) $addon[0] );
		$state = '' === $file ? 'not_installed' : ( is_plugin_active( $file ) ? 'active' : 'inactive' );
		return array(
			'id'       => 'addon:' . $addon[0],
			'label'    => (string) $addon[1],
			'kind'     => 'addon',
			'state'    => $state,
			'can'      => 'inactive' === $state && current_user_can( 'activate_plugins' ),
			'paid'     => true,
			'url'      => 'https://wbcomdesigns.com/my-account/?ref=nav', // Downloads with the Reign licence.
			'required' => $required,
		);
	}

	/**
	 * Another community plugin is active than the one a theme demo is built on (a BuddyPress demo on a BuddyNext
	 * site): the two do not run side by side, so the card names the conflict instead of offering to install.
	 *
	 * @param string[] $requires Catalog ids.
	 * @return string Label of the active community plugin, or ''.
	 */
	private static function conflict( array $requires ): string {
		$wants = array_values( array_intersect( $requires, self::COMMUNITY ) );
		if ( ! $wants ) {
			return '';
		}
		$active = self::active_community();
		return '' !== $active && ! in_array( $active, $wants, true ) ? Installer::status( $active )['label'] : '';
	}

	/**
	 * The community plugin active on the site (catalog id), or ''.
	 */
	private static function active_community(): string {
		foreach ( self::COMMUNITY as $id ) {
			if ( 'active' === Installer::status( $id )['state'] ) {
				return $id;
			}
		}
		return '';
	}

	/**
	 * The installed plugin file of a Reign add-on, by folder (main file names differ: Reign GeoDirectory's is
	 * reign-geolocation.php), or ''.
	 *
	 * @param string $folder Add-on folder.
	 */
	public static function addon_file( string $folder ): string {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( dirname( (string) $file ) === $folder ) {
				return (string) $file;
			}
		}
		return '';
	}

	/**
	 * Every add-on folder the demos name (the only add-ons the installer may switch on).
	 *
	 * @return string[]
	 */
	public static function addon_folders(): array {
		$out = array();
		foreach ( self::all() as $demo ) {
			foreach ( array_merge( empty( $demo['addon'] ) ? array() : array( $demo['addon'] ), (array) ( $demo['optional'] ?? array() ) ) as $addon ) {
				$out[] = (string) ( (array) $addon )[0];
			}
		}
		return array_values( array_unique( $out ) );
	}
}
