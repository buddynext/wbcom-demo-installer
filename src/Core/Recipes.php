<?php
/**
 * Starting points: presets of products plus home page and menu choices.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Recipe list, filtered to what is active.
 */
final class Recipes {

	/**
	 * Every recipe. `needs` must all be active for it to show; `use` is the default product mix.
	 *
	 * @return array<string,array{name:string,desc:string,needs:string[],use:string[]}>
	 */
	public static function all(): array {
		// BuddyNext, WPMediaVerse and Jetonomy are one set on every starting point (owner, 8 November 2026): a
		// community without media and forums feels empty. Products that are not active are left out.
		$recipes = array(
			'community'  => array(
				'name'  => __( 'Community', 'wbcom-demo-installer' ),
				'desc'  => __( 'Members, spaces, feed, photos and forums', 'wbcom-demo-installer' ),
				'needs' => array( 'buddynext' ),
				'use'   => array( 'buddynext', 'mediaverse', 'jetonomy', 'roadmap', 'gamification', 'pages' ),
			),
			'learning'   => array(
				'name'  => __( 'Learning community', 'wbcom-demo-installer' ),
				'desc'  => __( 'Courses with a member community around them', 'wbcom-demo-installer' ),
				'needs' => array( 'learnomy' ),
				'use'   => array( 'buddynext', 'mediaverse', 'jetonomy', 'learnomy', 'mediashield', 'gamification', 'pages' ),
			),
			'events'     => array(
				'name'  => __( 'Events community', 'wbcom-demo-installer' ),
				'desc'  => __( 'Meetups, tickets and RSVPs for members', 'wbcom-demo-installer' ),
				'needs' => array( 'eventonomy' ),
				'use'   => array( 'buddynext', 'mediaverse', 'jetonomy', 'eventonomy', 'gamification', 'pages' ),
			),
			'jobs'       => array(
				'name'  => __( 'Job board community', 'wbcom-demo-installer' ),
				'desc'  => __( 'Employers, jobs and candidate profiles', 'wbcom-demo-installer' ),
				'needs' => array( 'careerboard' ),
				'use'   => array( 'buddynext', 'mediaverse', 'jetonomy', 'careerboard', 'gamification', 'pages' ),
			),
			'directory'  => array(
				'name'  => __( 'Directory', 'wbcom-demo-installer' ),
				'desc'  => __( 'Listings with reviews, maps and favorites', 'wbcom-demo-installer' ),
				'needs' => array( 'listora' ),
				'use'   => array( 'buddynext', 'mediaverse', 'jetonomy', 'listora', 'gamification', 'pages' ),
			),
			'services'   => array(
				'name'  => __( 'Services marketplace', 'wbcom-demo-installer' ),
				'desc'  => __( 'Freelancers selling services, with orders and reviews', 'wbcom-demo-installer' ),
				'needs' => array( 'sellservices' ),
				'use'   => array( 'buddynext', 'mediaverse', 'jetonomy', 'sellservices', 'gamification', 'pages' ),
			),
			'everything' => array(
				'name'  => __( 'Everything I have', 'wbcom-demo-installer' ),
				'desc'  => __( 'Every active product, linked together', 'wbcom-demo-installer' ),
				'needs' => array(),
				'use'   => array(),
			),
		);
		return (array) apply_filters( 'wbcom_demo_recipes', $recipes );
	}

	/**
	 * Recipes whose products are active, with `use` narrowed to active products.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function available(): array {
		$active = array_keys( Registry::choosable() );
		$out    = array();
		foreach ( self::all() as $id => $recipe ) {
			if ( array_diff( $recipe['needs'], $active ) ) {
				continue;
			}
			$recipe['use'] = 'everything' === $id ? $active : array_values( array_intersect( $recipe['use'], $active ) );
			if ( ! $recipe['use'] ) {
				continue;
			}
			$out[ $id ] = $recipe;
		}
		return $out;
	}
}
