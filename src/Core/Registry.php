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
			foreach ( self::builtin() as $class ) {
				if ( class_exists( $class ) ) {
					$registry->add( new $class() );
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
	 * Built-in provider classes.
	 *
	 * @return array<int,class-string<Provider>>
	 */
	private static function builtin(): array {
		return array(
			Providers\People::class,
			Providers\BuddyNext::class,
			Providers\MediaVerse::class,
			Providers\Jetonomy::class,
			Providers\Learnomy::class,
			Providers\Eventonomy::class,
			Providers\CareerBoard::class,
			Providers\Listora::class,
			Providers\SellServices::class,
			Providers\Gamification::class,
			Providers\MediaShield::class,
			Providers\Roadmap::class,
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
