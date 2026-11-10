<?php
/**
 * Three ready site ideas from one sentence the owner writes.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Ai;

use WbcomDemo\Core\Installer;
use WbcomDemo\Core\Recipes;
use WbcomDemo\Core\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * The AI only chooses from what this installer can build; every answer is checked before the wizard sees it.
 */
final class Ideas {

	public const COUNT = 3;

	/**
	 * Ask for ideas and return them checked.
	 *
	 * @param string $description What the owner wants, in their words.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public static function suggest( string $description ) {
		$description = trim( sanitize_textarea_field( $description ) );
		if ( mb_strlen( $description ) < 10 ) {
			return new \WP_Error( 'wbcom_demo_ai_short', __( 'Describe your site in a sentence or two: who it is for and what people do there.', 'wbcom-demo-installer' ), array( 'status' => 400 ) );
		}
		$answer = Assistant::json( self::system(), self::prompt( mb_substr( $description, 0, 600 ) ), self::schema(), 3000 );
		if ( is_wp_error( $answer ) ) {
			return $answer;
		}
		$ideas = self::check( $answer );
		if ( ! $ideas ) {
			return new \WP_Error( 'wbcom_demo_ai_no_ideas', __( 'The AI did not suggest a site this installer can build. Try describing it another way, or pick a starting point yourself.', 'wbcom-demo-installer' ), array( 'status' => 502 ) );
		}
		return $ideas;
	}

	/**
	 * Products the installer can build: active ones and free ones it can install.
	 *
	 * @return array<string,array{label:string,summary:string,state:string}>
	 */
	public static function products(): array {
		$states = array_column( Installer::overview(), 'state', 'id' );
		$out    = array();
		foreach ( Registry::all() as $id => $p ) {
			if ( in_array( $id, array( 'people', 'theme' ), true ) ) {
				continue;
			}
			$state = $p->available() ? 'active' : ( $states[ $id ] ?? '' );
			if ( '' === $state ) {
				continue; // Not active and not installable from here.
			}
			$out[ $id ] = array(
				'label'   => $p->label(),
				'summary' => $p->summary(),
				'state'   => $state,
			);
		}
		return $out;
	}

	/**
	 * Instructions for the model.
	 */
	private static function system(): string {
		return 'You plan demo websites built with Wbcom Designs WordPress products. You only suggest what the listed '
			. 'starting points and products can build; never invent products, plugins, pages or features. Each idea is a '
			. 'believable, specific site a real owner could launch: a clear audience, a name that sounds like a real '
			. 'community or business (no "demo", "sample" or "test"), and highlights naming the actual spaces, courses, '
			. 'events, listings, jobs or services it would have. BuddyNext, WPMediaVerse and Jetonomy are always part of '
			. 'every idea. Make the three ideas clearly different from each other. No links, no prices unless asked, no '
			. 'emoji. Write every text value in the language given.';
	}

	/**
	 * The request, with everything the model may choose from.
	 *
	 * @param string $description Owner's description.
	 */
	private static function prompt( string $description ): string {
		$recipes = array();
		foreach ( Recipes::all() as $id => $r ) {
			if ( 'everything' !== $id ) {
				$recipes[] = sprintf( '- %s: %s (%s). Uses: %s', $id, $r['name'], $r['desc'], implode( ', ', $r['use'] ) );
			}
		}
		$products = array();
		foreach ( self::products() as $id => $p ) {
			$products[] = sprintf( '- %s (%s): %s', $id, $p['label'], wp_strip_all_tags( $p['summary'] ) );
		}
		return sprintf(
			"The owner describes the site:\n\"%s\"\n\nLanguage for every text value: %s (WordPress locale %s).\nCurrent site name: %s\n\nStarting points (use the id):\n%s\n\nProducts (use the id):\n%s\n\nSuggest exactly %d ideas.",
			$description,
			self::language(),
			get_locale(),
			(string) get_option( 'blogname' ),
			implode( "\n", $recipes ),
			implode( "\n", $products ),
			self::COUNT
		);
	}

	/**
	 * The site language's English name, for the model.
	 */
	private static function language(): string {
		$locale = get_locale();
		if ( 'en_US' === $locale ) {
			return 'English';
		}
		require_once ABSPATH . 'wp-admin/includes/translation-install.php';
		$all = wp_get_available_translations();
		return (string) ( $all[ $locale ]['english_name'] ?? $locale );
	}

	/**
	 * JSON schema of the answer.
	 *
	 * @return array<string,mixed>
	 */
	public static function schema(): array {
		$recipes  = array_values( array_diff( array_keys( Recipes::all() ), array( 'everything' ) ) );
		$products = array_keys( self::products() );
		// Only keywords every connector accepts (Anthropic rejects minItems above 1, and length limits vary):
		// counts and lengths are enforced in check(), and the prompt states them.
		$text = static fn( int $max ): array => array(
			'type'        => 'string',
			'description' => sprintf( 'At most %d characters.', $max ),
		);
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'ideas' ),
			'properties'           => array(
				'ideas' => array(
					'type'        => 'array',
					'description' => sprintf( 'Exactly %d ideas.', self::COUNT ),
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'name', 'tagline', 'recipe', 'products', 'why', 'highlights', 'audience', 'tone' ),
						'properties'           => array(
							'name'       => $text( 60 ),
							'tagline'    => $text( 120 ),
							'audience'   => $text( 160 ),
							'tone'       => $text( 60 ),
							'recipe'     => array(
								'type' => 'string',
								'enum' => $recipes,
							),
							'products'   => array(
								'type'  => 'array',
								'items' => array(
									'type' => 'string',
									'enum' => $products,
								),
							),
							'why'        => array(
								'type'  => 'array',
								'items' => array(
									'type'                 => 'object',
									'additionalProperties' => false,
									'required'             => array( 'product', 'reason' ),
									'properties'           => array(
										'product' => array(
											'type' => 'string',
											'enum' => $products,
										),
										'reason'  => $text( 160 ),
									),
								),
							),
							'highlights' => array(
								'type'        => 'array',
								'description' => 'Up to 6 concrete things the site will have.',
								'items'       => $text( 120 ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Keep only what this installer can build, with safe text. The schema is a request, not a guarantee.
	 *
	 * @param array<string,mixed> $answer Decoded answer.
	 * @return array<int,array<string,mixed>>
	 */
	public static function check( array $answer ): array {
		$recipes  = Recipes::all();
		$products = self::products();
		$clean    = static fn( $v, int $max ): string => mb_substr( trim( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ) ), 0, $max );
		$out      = array();
		foreach ( array_slice( (array) ( $answer['ideas'] ?? array() ), 0, self::COUNT ) as $idea ) {
			if ( ! is_array( $idea ) ) {
				continue;
			}
			$recipe = (string) ( $idea['recipe'] ?? '' );
			$name   = $clean( $idea['name'] ?? '', 60 );
			if ( ! isset( $recipes[ $recipe ] ) || 'everything' === $recipe || '' === $name ) {
				continue;
			}
			// The community set and the starting point's own product always come along.
			$ids = array_merge( Installer::CORE, $recipes[ $recipe ]['needs'], array_map( 'strval', (array) ( $idea['products'] ?? array() ) ) );
			$ids = array_values( array_unique( array_filter( $ids, static fn( $id ) => isset( $products[ $id ] ) ) ) );
			if ( array_diff( $recipes[ $recipe ]['needs'], $ids ) ) {
				continue; // Its main product can be neither found nor installed here.
			}
			$why = array();
			foreach ( (array) ( $idea['why'] ?? array() ) as $w ) {
				$pid = is_array( $w ) ? (string) ( $w['product'] ?? '' ) : '';
				if ( in_array( $pid, $ids, true ) ) {
					$why[ $pid ] = $clean( $w['reason'] ?? '', 160 );
				}
			}
			$out[] = array(
				'name'       => $name,
				'tagline'    => $clean( $idea['tagline'] ?? '', 120 ),
				'audience'   => $clean( $idea['audience'] ?? '', 160 ),
				'tone'       => $clean( $idea['tone'] ?? '', 60 ),
				'recipe'     => $recipe,
				'products'   => $ids,
				'missing'    => array_values( array_filter( $ids, static fn( $id ) => 'active' !== $products[ $id ]['state'] ) ),
				'why'        => $why,
				'highlights' => array_values( array_filter( array_map( static fn( $h ) => $clean( $h, 120 ), array_slice( (array) ( $idea['highlights'] ?? array() ), 0, 6 ) ) ) ),
			);
		}
		return $out;
	}
}
