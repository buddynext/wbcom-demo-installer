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
	 * @param string $name  Option name, "theme_mod:<name>" or "post_meta:<post id>:<key>".
	 * @param mixed  $value New value.
	 */
	public static function write( string $name, $value ): void {
		$snap = self::load();
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
		foreach ( self::load() as $name => $entry ) {
			if ( ! is_array( $entry ) || ! array_key_exists( 'written', $entry ) ) {
				continue;
			}
			if ( self::normalise( self::read( (string) $name ) ) === self::normalise( $entry['written'] ) ) {
				self::put( (string) $name, $entry['before'] ?? null );
			} else {
				$kept[] = $name;
			}
		}
		delete_option( self::OPTION );
		return $kept;
	}

	/**
	 * The stored snapshot, or an empty one when the option was damaged (a string, an object).
	 *
	 * @return array<string,mixed>
	 */
	private static function load(): array {
		$snap = get_option( self::OPTION, array() );
		return is_array( $snap ) ? $snap : array();
	}

	/**
	 * A value as the options table gives it back: true is "1", false is "", numbers are strings.
	 * Strict comparison of these keeps an owner's change ("yes" is not the demo's true).
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function normalise( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'normalise' ), $value );
		}
		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		return is_object( $value ) ? self::normalise( get_object_vars( $value ) ) : $value;
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
		if ( str_starts_with( $name, 'post_meta:' ) ) {
			list( , $id, $key ) = explode( ':', $name, 3 );
			return metadata_exists( 'post', (int) $id, $key ) ? get_post_meta( (int) $id, $key, true ) : null;
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
		if ( str_starts_with( $name, 'post_meta:' ) ) {
			list( , $id, $key ) = explode( ':', $name, 3 );
			null === $value ? delete_post_meta( (int) $id, $key ) : update_post_meta( (int) $id, $key, $value );
			return;
		}
		if ( str_starts_with( $name, 'theme_mod:' ) ) {
			null === $value ? remove_theme_mod( substr( $name, 10 ) ) : set_theme_mod( substr( $name, 10 ), $value );
			return;
		}
		null === $value ? delete_option( $name ) : update_option( $name, $value );
	}
}
