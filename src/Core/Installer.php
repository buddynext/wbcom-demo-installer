<?php
/**
 * Install and activate the free Wbcom products (and the free BuddyX theme) from the wizard.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * One product per call, released versions only.
 *
 * Packages come through the channel each plugin already trusts for its own updates (owner, 10 November 2026): the
 * store's EDD Software Licensing endpoint with the free distribution key the plugin ships, the same flow BuddyX Pro
 * and Reign use in their Getting Started installers. The store authorises the key for this site, then returns a
 * signed package URL on wbcomdesigns.com. Products listed on WordPress.org (Product Roadmap, the BuddyX theme)
 * install from there. Pro add-ons are listed with a store link and never installed here.
 */
final class Installer {

	private const STORE   = 'https://wbcomdesigns.com/';
	private const TIMEOUT = 30;

	/**
	 * BuddyNext, WPMediaVerse and Jetonomy are one set on every starting point (owner, 8 November 2026).
	 */
	public const CORE = array( 'buddynext', 'mediaverse', 'jetonomy' );

	/**
	 * Installable products, keyed by provider id (the theme by "buddyx").
	 *
	 * Each item id and free key is the one that plugin registers with its own EDD SDK in its main file.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function catalog(): array {
		$store   = static fn( string $label, string $basename, int $item, string $key, string $pro = '' ): array => array(
			'label'    => $label,
			'type'     => 'plugin',
			'basename' => $basename,
			'item_id'  => $item,
			'key'      => $key,
			'pro'      => $pro,
		);
		$catalog = array(
			'buddynext'    => $store( 'BuddyNext', 'buddynext/buddynext.php', 1664401, 'buddynext9a3c7e1d5f2b8a4c6e0d9b7f1a2c8e55', 'buddynext-pro/buddynext-pro.php' ),
			'mediaverse'   => $store( 'WPMediaVerse', 'wpmediaverse/wpmediaverse.php', 1660826, 'wbcomfree7a9c2e5d1f8b4c6a3e0d9b2f7c1a8e44', 'wpmediaverse-pro/wpmediaverse-pro.php' ),
			'jetonomy'     => $store( 'Jetonomy', 'jetonomy/jetonomy.php', 1660320, 'wbcomfreec7e2a9b45d8f1c3e6a0b9d2f7c4e8a11', 'jetonomy-pro/jetonomy-pro.php' ),
			'learnomy'     => $store( 'Learnomy', 'learnomy/learnomy.php', 1662698, 'wbcomfree5d8a1f3c7b2e9a4c6f0d1e8b3c9a7f25', 'learnomy-pro/learnomy-pro.php' ),
			'eventonomy'   => $store( 'Eventonomy', 'eventonomy/eventonomy.php', 1666307, 'eventonomyfree7a9c2e5d1f8b4c6a3e0d9b2f7c1a8e44', 'eventonomy-pro/eventonomy-pro.php' ),
			'careerboard'  => $store( 'WP Career Board', 'wp-career-board/wp-career-board.php', 1659888, 'wbcomfree5b8c1e7a9d3f2a4c6e0d1b7f9c2a6e00', 'wp-career-board-pro/wp-career-board-pro.php' ),
			'listora'      => $store( 'WB Listora', 'wb-listora/wb-listora.php', 1662779, 'wbcomfree8a5d1c7e3f2b9a4c6e0d1b7f9c2a6e55', 'wb-listora-pro/wb-listora-pro.php' ),
			'sellservices' => $store( 'WP Sell Services', 'wp-sell-services/wp-sell-services.php', 1660955, 'wbcomfree3c8a1f7e5d2b9a4c6e0f1d8b7a2c9e66', 'wp-sell-services-pro/wp-sell-services-pro.php' ),
			'mediashield'  => $store( 'MediaShield', 'mediashield/mediashield.php', 1661218, 'mediasheild7c2a9e5d1f8b4c6a3e0d9b2f7c1a8e12', 'mediashield-pro/mediashield-pro.php' ),
			'gamification' => $store( 'WB Gamification', 'wb-gamification/wb-gamification.php', 1662147, 'wbcomfree6e2a9c1d7b4f3c8a0e5d9b2f1a7c6e11' ),
			'roadmap'      => array(
				'label'    => 'Product Roadmap',
				'type'     => 'plugin',
				'basename' => 'product-roadmap/product-roadmap.php',
				'wporg'    => 'product-roadmap',
				'pro'      => 'roadmap-pro-addon/roadmap-pro-addon.php',
			),
			'buddyx'       => array(
				'label' => 'BuddyX',
				'type'  => 'theme',
				'wporg' => 'buddyx',
			),
		);
		/**
		 * Filter the products the wizard can install.
		 *
		 * @param array<string,array<string,mixed>> $catalog Provider id => entry.
		 */
		return (array) apply_filters( 'wbcom_demo_install_catalog', $catalog );
	}

	/**
	 * What the wizard shows: every catalog entry with its state and what this admin may do with it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function overview(): array {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$plugins = get_plugins();
		$out     = array();
		foreach ( self::catalog() as $id => $entry ) {
			$state = self::state( (string) $id, $entry, $plugins );
			if ( 'theme' === $entry['type'] && 'active' !== $state && self::wbcom_theme() ) {
				continue; // A Wbcom theme is active: BuddyX is not offered over it.
			}
			$pro_file = (string) ( $entry['pro'] ?? '' );
			$out[]    = array(
				'id'    => (string) $id,
				'label' => (string) $entry['label'],
				'type'  => (string) $entry['type'],
				'core'  => in_array( $id, self::CORE, true ),
				'state' => $state,
				'can'   => self::allowed( 'not_installed' === $state ? 'install' : 'activate', (string) $entry['type'] ),
				'pro'   => '' === $pro_file ? '' : ( isset( $plugins[ $pro_file ] ) ? ( is_plugin_active( $pro_file ) ? 'active' : 'inactive' ) : 'not_installed' ),
			);
		}
		return $out;
	}

	/**
	 * Install (when missing) and activate one catalog product.
	 *
	 * @param string $id Catalog id.
	 * @return true|\WP_Error
	 */
	public static function install( string $id ) {
		$catalog = self::catalog();
		if ( ! isset( $catalog[ $id ] ) ) {
			return new \WP_Error( 'wbcom_demo_unknown_product', __( 'This product cannot be installed from here.', 'wbcom-demo-installer' ), array( 'status' => 404 ) );
		}
		$entry = $catalog[ $id ];
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$state = self::state( $id, $entry, get_plugins() );
		if ( 'active' === $state ) {
			return true;
		}
		$action = 'not_installed' === $state ? 'install' : 'activate';
		if ( ! self::allowed( $action, (string) $entry['type'] ) ) {
			return new \WP_Error(
				'wbcom_demo_cannot_install',
				! wp_is_file_mod_allowed( 'wbcom_demo_install' )
					? __( 'This site does not allow installing plugins or themes from the dashboard (DISALLOW_FILE_MODS). Ask your host, or upload them another way.', 'wbcom-demo-installer' )
					: __( 'Your account is not allowed to install or activate plugins and themes on this site.', 'wbcom-demo-installer' ),
				array( 'status' => 403 )
			);
		}
		if ( 'install' === $action ) {
			$package = isset( $entry['wporg'] ) ? self::wporg_package( (string) $entry['wporg'], (string) $entry['type'] ) : self::store_package( (int) $entry['item_id'], (string) $entry['key'] );
			if ( is_wp_error( $package ) ) {
				return $package;
			}
			$installed = self::unpack( $package, (string) $entry['type'] );
			if ( is_wp_error( $installed ) ) {
				return $installed;
			}
		}
		if ( 'theme' === $entry['type'] ) {
			// The owner chose this theme on the wizard; it is not part of the demo, so Remove everything keeps it.
			switch_theme( (string) $entry['wporg'] );
			return wp_get_theme()->get_stylesheet() === $entry['wporg'] ? true : new \WP_Error( 'wbcom_demo_theme', __( 'BuddyX was installed but could not be switched on. Activate it from Appearance > Themes.', 'wbcom-demo-installer' ) );
		}
		$activated = activate_plugin( (string) $entry['basename'] );
		return is_wp_error( $activated ) ? $activated : true;
	}

	/**
	 * Installed and active, installed only, or missing.
	 *
	 * @param string                            $id      Catalog id.
	 * @param array<string,mixed>               $entry   Catalog entry.
	 * @param array<string,array<string,mixed>> $plugins get_plugins().
	 */
	private static function state( string $id, array $entry, array $plugins ): string {
		if ( 'theme' === $entry['type'] ) {
			$theme = wp_get_theme( (string) $entry['wporg'] );
			if ( ! $theme->exists() ) {
				return 'not_installed';
			}
			return get_stylesheet() === $theme->get_stylesheet() || get_template() === $theme->get_stylesheet() ? 'active' : 'inactive';
		}
		$file = (string) $entry['basename'];
		if ( ! isset( $plugins[ $file ] ) ) {
			// A product can be active from another folder name (a renamed upload): trust its provider.
			$provider = Registry::get( $id );
			return $provider && $provider->available() ? 'active' : 'not_installed';
		}
		return is_plugin_active( $file ) ? 'active' : 'inactive';
	}

	/**
	 * Whether this admin may install or activate, and the site allows file changes.
	 *
	 * @param string $action 'install' or 'activate'.
	 * @param string $type   'plugin' or 'theme'.
	 */
	private static function allowed( string $action, string $type ): bool {
		if ( 'install' === $action && ! wp_is_file_mod_allowed( 'wbcom_demo_install' ) ) {
			return false;
		}
		if ( 'theme' === $type ) {
			return current_user_can( 'switch_themes' ) && ( 'activate' === $action || current_user_can( 'install_themes' ) );
		}
		return current_user_can( 'activate_plugins' ) && ( 'activate' === $action || current_user_can( 'install_plugins' ) );
	}

	/**
	 * A Wbcom theme (BuddyX, BuddyX Pro, Reign or a child of one) is active.
	 */
	private static function wbcom_theme(): bool {
		return in_array( get_template(), array( 'buddyx', 'buddyx-pro', 'reign-theme' ), true );
	}

	/**
	 * Signed package URL from the store for a free product.
	 *
	 * @param int    $item Store item id.
	 * @param string $key  Free distribution key.
	 * @return string|\WP_Error
	 */
	private static function store_package( int $item, string $key ) {
		$ask = static function ( string $action ) use ( $item, $key ) {
			$response = wp_remote_post(
				self::STORE,
				array(
					'timeout' => self::TIMEOUT,
					'body'    => array(
						'edd_action'  => $action,
						'item_id'     => $item,
						'license'     => $key,
						'url'         => home_url(),
						'environment' => wp_get_environment_type(),
					),
				)
			);
			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'wbcom_demo_store_unreachable', __( 'Could not reach wbcomdesigns.com to download the plugin. Check that this site can make outgoing connections, then try again.', 'wbcom-demo-installer' ) );
			}
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $body ) ? $body : new \WP_Error( 'wbcom_demo_store_response', __( 'wbcomdesigns.com sent an unexpected answer. Try again in a minute.', 'wbcom-demo-installer' ) );
		};
		// The store hands out a package only for a key activated for this site, so activate the free key first.
		$licence = $ask( 'activate_license' );
		if ( is_wp_error( $licence ) ) {
			return $licence;
		}
		if ( empty( $licence['success'] ) && ! in_array( $licence['license'] ?? '', array( 'valid', 'active' ), true ) ) {
			/* translators: %s: reason given by the store. */
			return new \WP_Error( 'wbcom_demo_store_licence', sprintf( __( 'wbcomdesigns.com would not authorise the free download for this site (%s).', 'wbcom-demo-installer' ), sanitize_text_field( (string) ( $licence['error'] ?? $licence['license'] ?? 'unknown' ) ) ) );
		}
		$version = $ask( 'get_version' );
		if ( is_wp_error( $version ) ) {
			return $version;
		}
		return self::trusted( (string) ( $version['download_link'] ?? $version['package'] ?? '' ), 'wbcomdesigns.com' );
	}

	/**
	 * Download URL of the current WordPress.org release.
	 *
	 * @param string $slug WordPress.org slug.
	 * @param string $type 'plugin' or 'theme'.
	 * @return string|\WP_Error
	 */
	private static function wporg_package( string $slug, string $type ) {
		if ( 'theme' === $type ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
			$info = themes_api(
				'theme_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
		} else {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			$info = plugins_api(
				'plugin_information',
				array(
					'slug'   => $slug,
					'fields' => array( 'sections' => false ),
				)
			);
		}
		if ( is_wp_error( $info ) ) {
			return new \WP_Error( 'wbcom_demo_wporg', __( 'Could not reach WordPress.org to download it. Try again in a minute.', 'wbcom-demo-installer' ) );
		}
		return self::trusted( (string) ( is_object( $info ) ? ( $info->download_link ?? '' ) : '' ), 'wordpress.org' );
	}

	/**
	 * Only download from the expected host (or a subdomain of it).
	 *
	 * @param string $url  Package URL.
	 * @param string $host Expected host.
	 * @return string|\WP_Error
	 */
	private static function trusted( string $url, string $host ) {
		$got = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( '' === $url || 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) || ( $got !== $host && ! str_ends_with( $got, '.' . $host ) ) ) {
			return new \WP_Error( 'wbcom_demo_package_host', __( 'The download did not come from the expected address, so it was not installed.', 'wbcom-demo-installer' ) );
		}
		return $url;
	}

	/**
	 * Download and unpack a package with WordPress's own upgrader.
	 *
	 * @param string $package Package URL.
	 * @param string $type    'plugin' or 'theme'.
	 * @return true|\WP_Error
	 */
	private static function unpack( string $package, string $type ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( ! WP_Filesystem() ) {
			return new \WP_Error( 'wbcom_demo_filesystem', __( 'WordPress cannot write files on this site without FTP details. Install it from the Plugins or Themes screen instead.', 'wbcom-demo-installer' ) );
		}
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = 'theme' === $type ? new \Theme_Upgrader( $skin ) : new \Plugin_Upgrader( $skin );
		$result   = $upgrader->install( $package );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( true !== $result ) {
			$errors = $skin->get_errors();
			return $errors->has_errors() ? $errors : new \WP_Error( 'wbcom_demo_install_failed', __( 'The download could not be installed.', 'wbcom-demo-installer' ) );
		}
		return true;
	}
}
