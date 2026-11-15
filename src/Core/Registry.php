<?php
/**
 * Provider registry: built-in providers plus any a product registers itself.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

use WbcomDemo\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every provider, ordered.
 */
final class Registry {

	/**
	 * Providers by id.
	 *
	 * @var array<string,Provider>|null
	 */
	private static ?array $providers = null;

	/**
	 * Register a provider. A product shipping its own provider with the same id replaces the built-in one.
	 *
	 * @param Provider $provider Provider.
	 */
	public function add( Provider $provider ): void {
		if ( Provider::CONTRACT !== $provider::CONTRACT ) {
			return;
		}
		self::$providers[ $provider->id() ] = $provider;
	}

	/**
	 * All providers, in build order.
	 *
	 * @return array<string,Provider>
	 */
	public static function all(): array {
		if ( null === self::$providers ) {
			self::$providers = array();
			$registry        = new self();
			$builtin         = array();
			foreach ( self::builtin() as $class ) {
				if ( class_exists( $class ) ) {
					$provider                   = new $class();
					$builtin[ $provider->id() ] = true;
					$registry->add( $provider );
				}
			}
			// Third-party plugins a demo site runs (guide only), one provider per integration, unless a built-in
			// provider for the same plugin builds its content and owns its guide (PeepSo).
			foreach ( Providers\Integration::ids() as $id ) {
				if ( ! isset( $builtin[ $id ] ) ) {
					$registry->add( new Providers\Integration( $id ) );
				}
			}
			/**
			 * Register or replace providers.
			 *
			 * @param Registry $registry Registry.
			 */
			do_action( 'wbcom_demo_register', $registry );
			uasort( self::$providers, static fn( Provider $a, Provider $b ) => $a->order() <=> $b->order() );
		}
		return self::$providers;
	}

	/**
	 * Built-in providers, freshly made and not cached: for work that runs before add-ons register theirs
	 * (clearing activation redirects at plugins_loaded), so all() still sees every add-on later.
	 *
	 * @return Provider[]
	 */
	public static function builtin_providers(): array {
		$out = array();
		foreach ( self::builtin() as $class ) {
			if ( class_exists( $class ) ) {
				$out[] = new $class();
			}
		}
		return $out;
	}

	/**
	 * Built-in provider classes.
	 *
	 * @return array<int,class-string<Provider>>
	 */
	private static function builtin(): array {
		return array(
			Providers\People::class,
			Providers\BuddyNext::class,
			Providers\BuddyPress::class,
			Providers\PeepSo::class,
			Providers\MediaVerse::class,
			Providers\Jetonomy::class,
			Providers\Learnomy::class,
			Providers\Eventonomy::class,
			Providers\CareerBoard::class,
			Providers\Listora::class,
			Providers\SellServices::class,
			Providers\WooSellServices::class,
			Providers\Gamification::class,
			Providers\MediaShield::class,
			Providers\Roadmap::class,
			Providers\Pages::class,
			Providers\Theme::class,
		);
	}

	/**
	 * One provider.
	 *
	 * @param string $id Provider id.
	 */
	public static function get( string $id ): ?Provider {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Products the owner can choose (active, supported, not internal).
	 *
	 * @return array<string,Provider>
	 */
	public static function choosable(): array {
		return array_filter(
			self::all(),
			static fn( Provider $p ) => ! in_array( $p->id(), array( 'people', 'theme' ), true ) && $p->available()
		);
	}
}
