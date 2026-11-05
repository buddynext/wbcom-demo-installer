<?php
/**
 * The build clock: the date of the item being created right now, so bridges in other
 * products (a feed post for a new discussion, a badge activity) carry the same date.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Request-scoped current date and parent ledger row.
 */
final class Clock {

	/**
	 * Current date (MySQL, UTC) or ''.
	 *
	 * @var string
	 */
	private static string $now = '';

	/**
	 * Ledger id of the item whose side effects are being created, or 0.
	 *
	 * @var int
	 */
	private static int $parent = 0;

	/**
	 * Set the clock.
	 *
	 * @param string $date   MySQL datetime, UTC.
	 * @param int    $parent_id Parent ledger id.
	 */
	public static function set( string $date, int $parent_id = 0 ): void {
		self::$now    = $date;
		self::$parent = $parent_id;
	}

	/**
	 * Current date or ''.
	 */
	public static function now(): string {
		return self::$now;
	}

	/**
	 * Current parent ledger id.
	 */
	public static function parent(): int {
		return self::$parent;
	}

	/**
	 * Clear.
	 */
	public static function reset(): void {
		self::$now    = '';
		self::$parent = 0;
	}
}
