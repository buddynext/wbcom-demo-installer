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

	/** The film on taking a demo site live: YouTube id and length. */
	public const GO_LIVE = array( 'WijuvjG6WiE', '2:39' );

	/** Purposes, in the order the gallery shows them. */
	private const PURPOSES = array( 'community', 'learning', 'events', 'jobs', 'directory', 'services', 'shop' );

	/** BuddyNext with what makes a BuddyNext community: "members" on a site without a community plugin. */
	private const OUR_MEMBERS = array( 'buddynext', 'mediaverse', 'jetonomy', 'gamification' );

	/**
	 * What a demo's home page leads with. A demo whose first requirement is a community plugin is about that
	 * community (BuddyX + BuddyNext, Reign + BuddyPress), so its hero is the community's; any other demo leads
	 * with the product it is named for, the community around it following.
	 *
	 * @param string $demo Gallery demo id; empty for a build started without one.
	 * @return string 'community' or ''.
	 */
	public static function lead( string $demo ): string {
		$first = (string) ( self::all()[ $demo ]['requires'][0] ?? '' );
		return in_array( $first, array( 'buddynext', 'buddypress', 'buddyboss', 'peepso' ), true ) ? 'community' : '';
	}

	/**
	 * Whether a build of these products is that gallery demo: only then does it carry the demo's name, niche and
	 * hero. It must build the demo's main product (its community plugin for a community demo, else the first product
	 * beyond the community set and pages); the owner may add or drop
	 * others on the Products step. A build from another starting point is not the demo, even when the page was
	 * opened on the demo's link.
	 *
	 * @param string   $demo     Gallery demo id.
	 * @param string[] $products Provider ids being built.
	 */
	public static function builds( string $demo, array $products ): bool {
		$d   = self::all()[ $demo ] ?? null;
		$all = is_array( $d ) ? self::products( self::expand( (array) $d['requires'] ) ) : array();
		// A community demo is about its community plugin; any other demo about its first product beyond the community
		// set and pages, which come with most demos.
		$own  = array_values( array_diff( $all, self::COMMUNITY, self::OUR_MEMBERS, array( 'pages' ) ) );
		$main = 'community' === self::lead( $demo ) ? ( $all[0] ?? '' ) : ( $own[0] ?? ( $all[0] ?? '' ) );
		return '' !== $main && in_array( $main, $products, true );
	}

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
	 * The gallery for a theme's own setup screen (see wbcom_demo_list()): the featured cards first, as the gallery shows
	 * them, each with a link that opens the wizard on it.
	 *
	 * @return array{demos:array<int,array<string,mixed>>,go_live:array{id:string,length:string}}
	 */
	public static function listing(): array {
		$film  = static fn( array $f ): array => $f ? array(
			'id'     => (string) $f[0],
			'length' => (string) $f[1],
		) : array();
		$cards = self::overview();
		usort( $cards, static fn( array $a, array $b ): int => (int) $b['featured'] <=> (int) $a['featured'] );
		$demos = array();
		foreach ( $cards as $c ) {
			$demos[] = array(
				'id'       => $c['id'],
				'name'     => $c['name'],
				'desc'     => $c['desc'],
				'group'    => $c['group'],
				'purpose'  => $c['purpose'],
				'featured' => $c['featured'],
				'state'    => $c['state'],
				'image'    => $c['image'],
				'preview'  => $c['preview'],
				'film'     => $film( $c['film'] ),
				'needs'    => $c['needs'],
				'optional' => $c['optional'],
				'url'      => add_query_arg( 'demo', rawurlencode( $c['id'] ), admin_url( 'themes.php?page=wbcom-demo' ) ),
			);
		}
		return array(
			'demos'   => $demos,
			'go_live' => array(
				'id'     => self::GO_LIVE[0],
				'length' => self::GO_LIVE[1],
			),
		);
	}

	/**
	 * The demos for the active theme, with each requirement's state.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function overview(): array {
		$family = Theme::theme_family();
		$buddyx = Installer::offer_buddyx();
		$out    = array();
		foreach ( self::all() as $id => $demo ) {
			// Without BuddyX Pro or Reign, the BuddyX demos are offered too: the free theme installs with them.
			$theme = 'BuddyX' === ( $demo['theme'] ?? '' ) && 'BuddyX' !== $family && $buddyx;
			if ( '*' !== ( $demo['theme'] ?? '' ) && ( $demo['theme'] ?? '' ) !== $family && ! $theme ) {
				continue;
			}
			$demo['requires'] = self::expand( (array) $demo['requires'] );
			$rows             = array();
			if ( $theme ) {
				$rows[]          = array_merge( self::plugin_row( 'buddyx' ), array( 'kind' => 'theme' ) );
				$demo['purpose'] = ''; // A demo that switches the theme is never put on top of the gallery.
			}
			if ( ! empty( $demo['addon'] ) ) {
				$rows[] = self::addon_row( (array) $demo['addon'], true );
			}
			foreach ( (array) $demo['requires'] as $need ) {
				$rows[] = 'community' === $need ? self::community_row() : self::plugin_row( (string) $need );
			}
			foreach ( (array) ( $demo['optional'] ?? array() ) as $addon ) {
				$rows[] = self::addon_row( (array) $addon, false );
			}
			foreach ( $rows as &$row ) {
				$row['how'] = 'active' === $row['state'] ? '' : self::need( $row )['action']; // The card says the same as needs.
			}
			unset( $row );
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
				// What is still missing and who does it: the installer (install), the owner (upload a paid plugin or
				// add-on from their account), or an administrator (this account or host cannot install plugins).
				'needs'    => array_values( array_map( array( self::class, 'need' ), $needed ) ),
				'optional' => array_values(
					array_map(
						array( self::class, 'need' ),
						array_filter( $rows, static fn( array $r ): bool => ! $r['required'] && 'active' !== $r['state'] )
					)
				),
				'purpose'  => (string) ( $demo['purpose'] ?? '' ),
				'ours'     => 'ours' === ( $demo['group'] ?? '' ),
				'yours'    => self::triggered( $demo ),
				'featured' => false,
				// The setup film recorded on this theme: YouTube id and length, or none.
				'film'     => array_values( (array) ( $demo['film'][ $family ] ?? $demo['film']['*'] ?? array() ) ),
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
	 * One missing item, as a card, a theme or WP-CLI tells the owner about it.
	 *
	 * @param array<string,mixed> $row Requirement row.
	 * @return array{label:string,kind:string,action:string,url:string}
	 */
	private static function need( array $row ): array {
		// Upload only when the owner can: a paid item not on the site yet, on a host and account that allow installing.
		$upload = ! empty( $row['paid'] ) && 'not_installed' === $row['state'] && wp_is_file_mod_allowed( 'wbcom_demo_install' ) && current_user_can( 'install_plugins' );
		$action = $row['can'] ? 'install' : ( $upload ? 'upload' : 'ask' );
		return array(
			'label'  => (string) $row['label'],
			'kind'   => (string) ( $row['kind'] ?? 'plugin' ), // plugin, addon (a Reign add-on, from the Wbcom account) or theme.
			'action' => $action,
			'url'    => 'upload' === $action ? (string) $row['url'] : '',
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
			// Outdated is active too (older than the demo needs; the card updates it), never "no community plugin".
			if ( in_array( Installer::status( $id )['state'], array( 'active', 'outdated' ), true ) ) {
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
