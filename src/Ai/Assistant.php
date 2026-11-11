<?php
/**
 * The one place the installer talks to an AI model: WordPress core's AI client and the connectors the owner set up.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Ai;

defined( 'ABSPATH' ) || exit;

/**
 * Core AI only (owner, 10 November 2026): Settings > Connectors holds the owner's own key for Anthropic, Google or
 * OpenAI and core passes it to `wp_ai_client_prompt()`. No adapters, keys or settings of our own.
 */
final class Assistant {

	/**
	 * Seconds one request may take; a build step must finish well inside one PHP request.
	 */
	private const TIMEOUT = 90;

	/**
	 * Whether a connector can generate text on this site right now.
	 */
	public static function available(): bool {
		$ok = function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && wp_supports_ai()
			&& true === wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation();
		/**
		 * Filter whether the AI site builder is offered (lab tests answer through wbcom_demo_ai_pre_json).
		 *
		 * @param bool $ok Whether a core AI connector can generate text.
		 */
		return (bool) apply_filters( 'wbcom_demo_ai_available', $ok );
	}

	/**
	 * What the site check shows about AI.
	 *
	 * @return array{available:bool,connectors:string[],url:string,manage:bool}
	 */
	public static function overview(): array {
		$names = array();
		if ( function_exists( 'wp_get_connectors' ) ) {
			foreach ( wp_get_connectors() as $connector ) {
				if ( 'ai_provider' === ( $connector['type'] ?? '' ) ) {
					$names[] = (string) $connector['name'];
				}
			}
		}
		return array(
			'available'  => self::available(),
			'connectors' => $names,
			// Connectors arrived in WordPress 7.0; older sites get no link (the card says to update).
			'url'        => function_exists( 'wp_get_connectors' ) ? admin_url( 'options-connectors.php' ) : '',
			'manage'     => current_user_can( 'manage_options' ),
		);
	}

	/**
	 * Ask for JSON matching a schema.
	 *
	 * @param string              $system Instructions.
	 * @param string              $prompt The request.
	 * @param array<string,mixed> $schema JSON schema of the answer.
	 * @param int                 $tokens Answer size limit.
	 * @return array<string,mixed>|\WP_Error Decoded answer.
	 */
	public static function json( string $system, string $prompt, array $schema, int $tokens = 4000 ) {
		/**
		 * Answer instead of the AI (lab tests). Return an array to use it, null to call the model.
		 *
		 * @param array<string,mixed>|null $answer Answer.
		 * @param string                   $prompt The request.
		 * @param array<string,mixed>      $schema Schema.
		 */
		$pre = apply_filters( 'wbcom_demo_ai_pre_json', null, $prompt, $schema );
		if ( is_array( $pre ) ) {
			return $pre;
		}
		if ( ! self::available() ) {
			return new \WP_Error( 'wbcom_demo_ai_off', __( 'No AI connector is set up. Add one in Settings > Connectors.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( self::TIMEOUT + 30 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		$options = new \WordPress\AiClient\Providers\Http\DTO\RequestOptions();
		$options->setTimeout( (float) self::TIMEOUT );
		$text = wp_ai_client_prompt( $prompt )
			->using_system_instruction( $system )
			->using_max_tokens( $tokens )
			->using_request_options( $options )
			->as_json_response( $schema )
			->generate_text();
		if ( is_wp_error( $text ) ) {
			/* translators: %s: error from the AI connector. */
			return new \WP_Error( 'wbcom_demo_ai_failed', sprintf( __( 'The AI connector did not answer: %s', 'wbcom-demo-installer' ), $text->get_error_message() ), array( 'status' => 502 ) );
		}
		// Some models wrap JSON in a code fence even when asked not to.
		$text = trim( (string) preg_replace( '/^```(?:json)?\s*|\s*```$/', '', trim( (string) $text ) ) );
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'wbcom_demo_ai_shape', __( 'The AI answer could not be read. Try again, or pick a starting point yourself.', 'wbcom-demo-installer' ), array( 'status' => 502 ) );
		}
		return $data;
	}
}
