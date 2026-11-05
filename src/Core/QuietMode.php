<?php
/**
 * Quiet mode: no mail while building or finishing, and never to a sample person.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Mail and notification suppression.
 *
 * Two layers:
 * 1. While a build or finish step runs, every wp_mail() is stopped (the step is short and admin-started).
 * 2. Always, mail whose recipients are all sample people is stopped. This catches mail a product queued
 *    in Action Scheduler during the build that fires later.
 */
final class QuietMode {

	/** Domain every sample person's address uses (reserved .invalid TLD, never deliverable). */
	public const DOMAIN = 'demo-people.invalid';

	/**
	 * Whether a step is running in this request.
	 *
	 * @var bool
	 */
	private static bool $active = false;

	/**
	 * Register the mail filter.
	 */
	public static function init(): void {
		add_filter( 'pre_wp_mail', array( self::class, 'filter_mail' ), 5, 2 );
	}

	/**
	 * Turn quiet mode on for the rest of this request (and the products' own switches).
	 */
	public static function on(): void {
		if ( self::$active ) {
			return;
		}
		self::$active = true;
		foreach ( self::product_filters() as $filter => $value ) {
			add_filter( $filter, $value ? '__return_true' : '__return_false', 999 );
		}
	}

	/**
	 * Products' own notification switches. Value is what the filter returns while quiet.
	 *
	 * @return array<string,bool>
	 */
	private static function product_filters(): array {
		return (array) apply_filters(
			'wbcom_demo_quiet_filters',
			array(
				'buddynext_notification_should_send' => false,
				'jetonomy_notification_should_send'  => false,
				'wb_gam_email_enabled'               => false,
			)
		);
	}

	/**
	 * Is quiet mode active in this request.
	 */
	public static function active(): bool {
		return self::$active;
	}

	/**
	 * Stop mail.
	 *
	 * @param null|bool           $short Short-circuit value from earlier filters.
	 * @param array<string,mixed> $atts  wp_mail() arguments.
	 * @return null|bool
	 */
	public static function filter_mail( $short, $atts ) {
		if ( null !== $short ) {
			return $short;
		}
		if ( self::$active ) {
			return false;
		}
		$to = $atts['to'] ?? array();
		$to = is_array( $to ) ? $to : explode( ',', (string) $to );
		$to = array_filter( array_map( 'trim', $to ) );
		if ( ! $to ) {
			return null;
		}
		foreach ( $to as $address ) {
			if ( ! self::is_sample_address( $address ) ) {
				return null;
			}
		}
		return false;
	}

	/**
	 * Whether an address belongs to a sample person.
	 *
	 * @param string $address Address, possibly "Name <addr>".
	 */
	public static function is_sample_address( string $address ): bool {
		if ( preg_match( '/<([^>]+)>/', $address, $m ) ) {
			$address = $m[1];
		}
		return str_ends_with( strtolower( trim( $address ) ), '@' . self::DOMAIN );
	}
}
