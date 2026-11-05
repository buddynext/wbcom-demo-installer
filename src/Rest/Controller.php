<?php
/**
 * REST routes the wizard uses. Admin only.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Rest;

use WbcomDemo\Core\Engine;
use WbcomDemo\Core\Finisher;
use WbcomDemo\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /wbcom-demo/v1.
 */
final class Controller {

	private const NS = 'wbcom-demo/v1';

	/**
	 * Register routes.
	 */
	public static function register(): void {
		$auth = array( self::class, 'can' );
		register_rest_route(
			self::NS,
			'/site',
			array(
				'methods'             => 'GET',
				'permission_callback' => $auth,
				'callback'            => static fn() => rest_ensure_response(
					array(
						'site'   => Engine::site(),
						'status' => Engine::status(),
					)
				),
			)
		);
		register_rest_route(
			self::NS,
			'/build',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'args'                => array(
					'recipe'  => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'include' => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
					'look'    => array(
						'type'    => 'string',
						'enum'    => array( 'demo', 'keep' ),
						'default' => 'demo',
					),
					'consent' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
				'callback'            => static fn( \WP_REST_Request $r ) => rest_ensure_response(
					Engine::start_build( (string) $r['recipe'], array_map( 'sanitize_key', (array) $r['include'] ), (string) $r['look'], (bool) $r['consent'] )
				),
			)
		);
		register_rest_route(
			self::NS,
			'/step',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'callback'            => static fn() => rest_ensure_response( Engine::step() ),
			)
		);
		$finish_args = array(
			'mode'   => array(
				'type'    => 'string',
				'enum'    => array( 'keep', 'custom', 'all' ),
				'default' => 'keep',
			),
			'custom' => array(
				'type'    => 'object',
				'default' => array(),
			),
		);
		register_rest_route(
			self::NS,
			'/finish/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'args'                => $finish_args,
				'callback'            => static fn( \WP_REST_Request $r ) => rest_ensure_response( Finisher::preview( (string) $r['mode'], self::custom( $r ) ) ),
			)
		);
		register_rest_route(
			self::NS,
			'/finish',
			array(
				'methods'             => 'POST',
				'permission_callback' => $auth,
				'args'                => $finish_args,
				'callback'            => static fn( \WP_REST_Request $r ) => rest_ensure_response( Finisher::start( (string) $r['mode'], self::custom( $r ) ) ),
			)
		);
	}

	/**
	 * Custom choices as "provider:type" => bool.
	 *
	 * @param \WP_REST_Request $r Request.
	 * @return array<string,bool>
	 */
	private static function custom( \WP_REST_Request $r ): array {
		$out = array();
		foreach ( (array) $r['custom'] as $key => $value ) {
			if ( preg_match( '/^[a-z0-9_]+:[a-z0-9_]+$/', (string) $key ) ) {
				$out[ (string) $key ] = (bool) $value;
			}
		}
		return $out;
	}

	/**
	 * Admins only.
	 *
	 * @return true|\WP_Error
	 */
	public static function can() {
		if ( current_user_can( Plugin::CAP ) ) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden', __( 'Only site administrators can set up or remove demo content.', 'wbcom-demo-installer' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
	}
}
