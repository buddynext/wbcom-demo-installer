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
	private const COMMUNITY = array( 'buddyboss', 'peepso', 'buddynext', 'buddypress' ); // BuddyBoss first: it also answers to BuddyPress's functions.

	/** Purposes, in the order the gallery shows them. */
	private const PURPOSES = array( 'community', 'learning', 'events', 'jobs', 'directory', 'services', 'shop' );

	/** BuddyNext with what makes a BuddyNext community: "members" on a site without a community plugin. */
	private const OUR_MEMBERS = array( 'buddynext', 'mediaverse', 'jetonomy', 'gamification' );

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
			if ( '*' !== ( $demo['theme'] ?? '' ) && ( $demo['theme'] ?? '' ) !== $family ) {
				continue;
			}
			$demo['requires'] = self::expand( (array) $demo['requires'] );
			$rows             = array();
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
				'image'    => self::image( (string) $id, (string) ( $demo['preview'] ?? '' ) ),
				'preview'  => (string) ( $demo['preview'] ?? '' ),
				'rows'     => $rows,
				'products' => self::products( (array) $demo['requires'] ),
				'conflict' => $conflict,
				// Ready: build now. Fixable: the missing pieces install or switch on from here. Otherwise the owner
				// uploads something first (a paid add-on or plugin).
				'state'    => $conflict ? 'conflict' : ( ! $needed ? 'ready' : ( array_filter( $needed, static fn( array $r ): bool => ! $r['can'] ) ? 'blocked' : 'fixable' ) ),
				'fix'      => array_values( array_map( static fn( array $r ): string => (string) $r['id'], array_filter( $needed, static fn( array $r ): bool => $r['can'] ) ) ),
				'purpose'  => (string) ( $demo['purpose'] ?? '' ),
				'ours'     => 'ours' === ( $demo['group'] ?? '' ),
				'yours'    => self::triggered( $demo ),
				'featured' => false,
			);
		}
		return self::feature( $out );
	}

	/**
	 * One card per purpose on top: the demo for a third-party plugin the site already has (the owner chose it), else
	 * ours. Featured cards come first, the owner's own add-on before the rest; every other card stays below for
	 * "See every combination".
	 *
	 * @param array<int,array<string,mixed>> $cards Cards.
	 * @return array<int,array<string,mixed>>
	 */
	private static function feature( array $cards ): array {
		$rank = array(
			'ready'    => 0,
			'fixable'  => 1,
			'blocked'  => 2,
			'conflict' => 3,
		);
		$top  = array();
		foreach ( self::PURPOSES as $purpose ) {
			$theirs = array_filter( $cards, static fn( array $c ): bool => $purpose === $c['purpose'] && '' !== $c['yours'] && ! $c['ours'] );
			// An uploaded add-on before a merely active plugin, then the one closest to ready.
			usort( $theirs, static fn( array $a, array $b ): int => array( 'addon' !== $a['yours'], $rank[ $a['state'] ] ) <=> array( 'addon' !== $b['yours'], $rank[ $b['state'] ] ) );
			$pick = $theirs ? reset( $theirs ) : current( array_filter( $cards, static fn( array $c ): bool => $purpose === $c['purpose'] && $c['ours'] ) );
			if ( $pick ) {
				$top[] = $pick['id'];
			}
		}
		foreach ( $cards as &$c ) {
			$c['featured'] = in_array( $c['id'], $top, true );
			$c['yours']    = $c['featured'] ? $c['yours'] : '';
		}
		unset( $c );
		usort(
			$cards,
			static function ( array $a, array $b ) use ( $top ): int {
				$pos = static fn( array $c ): int => $c['featured'] ? ( 'addon' === $c['yours'] ? 0 : 1 + (int) array_search( $c['id'], $top, true ) ) : 100;
				return $pos( $a ) <=> $pos( $b );
			}
		);
		return $cards;
	}

	/**
	 * Card image: a shot bundled for a demo without its own sandbox, else the live demo's image on
	 * wbcomdesigns.com/demos (same sandbox as Live preview), so the zip stays small and cards follow the sandboxes.
	 *
	 * @param string $id      Demo id.
	 * @param string $preview Live preview URL.
	 */
	private static function image( string $id, string $preview ): string {
		if ( file_exists( WBCOM_DEMO_DIR . 'media/demos/' . $id . '.webp' ) ) {
			return WBCOM_DEMO_URL . 'media/demos/' . $id . '.webp';
		}
		$host = (string) wp_parse_url( $preview, PHP_URL_HOST );
		return '' !== $host ? 'https://wbcomdesigns.com/images/sandboxes/' . rawurlencode( $host ) . '.webp' : '';
	}

	/**
	 * Why the site already has this demo: 'addon' (its Reign add-on is uploaded), 'plugin' (its plugin is active) or ''.
	 *
	 * @param array<string,mixed> $demo Demo.
	 */
	private static function triggered( array $demo ): string {
		if ( ! empty( $demo['addon'] ) && '' !== self::addon_file( (string) ( (array) $demo['addon'] )[0] ) ) {
			return 'addon'; // The owner uploaded the Reign add-on: they bought it for this.
		}
		foreach ( (array) ( $demo['trigger'] ?? array() ) as $id ) {
			if ( in_array( Installer::status( (string) $id )['state'], array( 'active', 'outdated' ), true ) ) {
				return 'plugin';
			}
		}
		return '';
	}

	/**
	 * Requirements with "members" spelled out: BuddyPress, BuddyBoss or PeepSo when the site runs one, else BuddyNext and its set.
	 *
	 * @param string[] $requires Requirements.
	 * @return string[]
	 */
	private static function expand( array $requires ): array {
		$out = array();
		foreach ( $requires as $need ) {
			if ( 'members' === $need ) {
				// BuddyNext (or none yet) means the whole BuddyNext set, also once BuddyNext itself is active.
				$active = self::active_community();
				$out    = array_merge( $out, in_array( $active, array( '', 'buddynext' ), true ) ? self::OUR_MEMBERS : array( $active ) );
				continue;
			}
			$out[] = $need;
		}
		return array_values( array_unique( $out ) );
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
