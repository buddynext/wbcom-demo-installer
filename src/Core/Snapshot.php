<?php
/**
 * Before-values of every site option the build changes, so Finish can restore them.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Option snapshot with change detection.
 */
final class Snapshot {

	private const OPTION = 'wbcom_demo_snapshot';

	/**
	 * Remember an option's current value and the value we are about to write, then write it.
	 *
	 * @param string $name  Option name, or "theme_mod:<name>".
	 * @param mixed  $value New value.
	 */
	public static function write( string $name, $value ): void {
		$snap = get_option( self::OPTION, array() );
		if ( ! array_key_exists( $name, $snap ) ) {
			$snap[ $name ] = array( 'before' => self::read( $name ) );
		}
		$snap[ $name ]['written'] = $value;
		update_option( self::OPTION, $snap, false );
		self::put( $name, $value );
	}

	/**
	 * Restore every value the owner has not changed since; report the ones they did.
	 *
	 * @return string[] Names kept because the owner changed them.
	 */
	public static function restore(): array {
		$kept = array();
		foreach ( get_option( self::OPTION, array() ) as $name => $entry ) {
			if ( self::read( $name ) == $entry['written'] ) { // phpcs:ignore Universal.Operators.StrictComparisons -- options round-trip as strings.
				self::put( $name, $entry['before'] );
			} else {
				$kept[] = $name;
			}
		}
		delete_option( self::OPTION );
		return $kept;
	}

	/**
	 * Forget the snapshot (owner keeps the demo look).
	 */
	public static function forget(): void {
		delete_option( self::OPTION );
	}

	/**
	 * Read an option or theme mod.
	 *
	 * @param string $name Name.
	 * @return mixed
	 */
	private static function read( string $name ) {
		if ( str_starts_with( $name, 'theme_mod:' ) ) {
			return get_theme_mod( substr( $name, 10 ), null );
		}
		return get_option( $name, null );
	}

	/**
	 * Write an option or theme mod (null deletes).
	 *
	 * @param string $name  Name.
	 * @param mixed  $value Value.
	 */
	private static function put( string $name, $value ): void {
		if ( str_starts_with( $name, 'theme_mod:' ) ) {
			null === $value ? remove_theme_mod( substr( $name, 10 ) ) : set_theme_mod( substr( $name, 10 ), $value );
			return;
		}
		null === $value ? delete_option( $name ) : update_option( $name, $value );
	}
}
