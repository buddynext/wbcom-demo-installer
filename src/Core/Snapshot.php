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
	 * @param string $name    Option name, "theme_mod:<name>" or "post_meta:<post id>:<key>".
	 * @param mixed  $value   New value.
	 * @param bool   $setting A product setting (a provider's settings()), restored only after the sample content
	 *                        is gone: content can need it on to be deleted (BuddyPress groups need Groups active).
	 */
	public static function write( string $name, $value, bool $setting = false ): void {
		$snap = self::load();
		if ( ! array_key_exists( $name, $snap ) ) {
			$snap[ $name ] = array( 'before' => self::read( $name ) );
		}
		if ( $setting ) {
			$snap[ $name ]['setting'] = true;
		}
		self::put( $name, $value );
		// Remember the value as WordPress stored it: core sanitises some options on save (blogname escapes &, ' and "),
		// and comparing the raw value with the stored one would read the demo's own write as an owner change.
		$stored                   = self::read( $name );
		$snap[ $name ]['written'] = null === $stored ? $value : $stored;
		update_option( self::OPTION, $snap, false );
	}

	/**
	 * The value a setting had before the demo first wrote it, or null when the demo has not written it.
	 *
	 * @param string $name Setting name, as passed to write().
	 * @return mixed
	 */
	public static function before( string $name ) {
		$snap = self::load();
		return isset( $snap[ $name ] ) && is_array( $snap[ $name ] ) ? ( $snap[ $name ]['before'] ?? null ) : null;
	}

	/**
	 * Restore every value the owner has not changed since; report the ones they did.
	 *
	 * @param bool|null $settings Null restores everything; false only the look (logo, menu, home page, name);
	 *                            true only product settings. Restored entries leave the snapshot.
	 * @return string[] Names kept because the owner changed them.
	 */
	public static function restore( ?bool $settings = null ): array {
		$kept = array();
		$snap = self::load();
		foreach ( $snap as $name => $entry ) {
			if ( null !== $settings && is_array( $entry ) && ! empty( $entry['setting'] ) !== $settings ) {
				continue;
			}
			unset( $snap[ $name ] );
			if ( ! is_array( $entry ) || ! array_key_exists( 'written', $entry ) ) {
				continue;
			}
			// A page setting whose page is gone (a demo page removed earlier): nothing to restore, and not the owner's change.
			if ( str_starts_with( (string) $name, 'post_meta:' ) && ! get_post( (int) explode( ':', (string) $name )[1] ) ) {
				continue;
			}
			$current = self::read( (string) $name );
			$before  = $entry['before'] ?? null;
			if ( self::normalise( $current ) === self::normalise( $entry['written'] ) ) {
				self::put( (string) $name, $before );
			} elseif ( is_array( $entry['written'] ) && is_array( $current ) && ( null === $before || is_array( $before ) ) ) {
				// A settings array that other code also writes (a product adding its own keys, a theme registering menu
				// locations): put back only the keys the demo wrote, and keep any key someone else changed.
				$changed = false;
				$merged  = self::merge_back( (array) $before, $entry['written'], $current, $changed );
				self::put( (string) $name, null === $before && ! $merged ? null : $merged );
				if ( $changed ) {
					$kept[] = $name;
				}
			} else {
				$kept[] = $name;
			}
		}
		if ( $snap ) {
			update_option( self::OPTION, $snap, false );
		} else {
			delete_option( self::OPTION );
		}
		return $kept;
	}

	/**
	 * Three-way restore of one array: start from what is there now, return each key the demo wrote to its value
	 * before the demo (or remove it when the demo added it), unless it changed since; bring back keys the demo removed.
	 *
	 * @param array<mixed> $before  Value before the demo.
	 * @param array<mixed> $written Value the demo wrote.
	 * @param array<mixed> $current Value now.
	 * @param bool         $changed Set when a key the demo wrote was changed since (that key is kept as it is).
	 * @return array<mixed>
	 */
	private static function merge_back( array $before, array $written, array $current, bool &$changed ): array {
		$out = $current;
		foreach ( $written as $key => $value ) {
			if ( array_key_exists( $key, $before ) && self::normalise( $before[ $key ] ) === self::normalise( $value ) ) {
				continue; // Carried over unchanged: not the demo's to restore or report (a theme may set it since).
			}
			if ( ! array_key_exists( $key, $current ) ) {
				continue; // Removed since: leave it removed.
			}
			if ( self::normalise( $current[ $key ] ) !== self::normalise( $value ) ) {
				$changed = true;
				continue;
			}
			if ( array_key_exists( $key, $before ) ) {
				$out[ $key ] = $before[ $key ];
			} else {
				unset( $out[ $key ] );
			}
		}
		foreach ( $before as $key => $value ) {
			if ( ! array_key_exists( $key, $written ) && ! array_key_exists( $key, $current ) ) {
				$out[ $key ] = $value; // The demo removed it.
			}
		}
		return $out;
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
