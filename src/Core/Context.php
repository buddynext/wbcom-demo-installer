<?php
/**
 * What a provider gets while building: people, dates, the ledger and the media pack.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Build context handed to every provider step.
 */
final class Context {

	/**
	 * Constructor.
	 *
	 * @param State $state Installer state.
	 */
	public function __construct( public readonly State $state ) {}

	/**
	 * Current build id.
	 */
	public function build_id(): string {
		return (string) $this->state->get( 'build_id' );
	}

	/**
	 * Sample people's user ids, optionally only those with a role tag.
	 *
	 * @param string $role Role tag from content/people.php, e.g. "instructor". '' for everyone.
	 * @return int[]
	 */
	public function people( string $role = '' ): array {
		$out = array();
		foreach ( (array) $this->state->get( 'people' ) as $person ) {
			if ( '' === $role || in_array( $role, $person['roles'], true ) ) {
				$out[] = (int) $person['id'];
			}
		}
		return $out;
	}

	/**
	 * One person by roster index (wraps around).
	 *
	 * @param int    $i    Index.
	 * @param string $role Optional role tag.
	 */
	public function person( int $i, string $role = '' ): int {
		$ids = $this->people( $role );
		return $ids ? $ids[ abs( $i ) % count( $ids ) ] : 0;
	}

	/**
	 * Roster entry for a user id (name, headline, roles...).
	 *
	 * @param int $user_id User id.
	 * @return array<string,mixed>
	 */
	public function profile( int $user_id ): array {
		foreach ( (array) $this->state->get( 'people' ) as $person ) {
			if ( (int) $person['id'] === $user_id ) {
				return $person;
			}
		}
		return array();
	}

	/**
	 * A date on the build timeline, spread over the last $days days, oldest first for low $i.
	 *
	 * @param int $i    Position.
	 * @param int $n    Number of positions.
	 * @param int $days Window.
	 * @return string MySQL datetime, UTC.
	 */
	public function date( int $i, int $n, int $days = 60 ): string {
		$n      = max( 1, $n );
		$window = $days * DAY_IN_SECONDS;
		$step   = $window / $n;
		$jitter = ( ( $i * 7919 ) % 97 ) / 97 * $step * 0.8;
		$ts     = time() - $window + (int) ( $i * $step + $jitter );
		return gmdate( 'Y-m-d H:i:s', min( $ts, time() - HOUR_IN_SECONDS ) );
	}

	/**
	 * A date some minutes to days after another, never in the future.
	 *
	 * @param string $after MySQL datetime, UTC.
	 * @param int    $seq   Sequence for spread.
	 */
	public function after( string $after, int $seq ): string {
		$base = strtotime( $after . ' UTC' );
		$ts   = $base + ( ( $seq * 2213 ) % 172800 ) + 600;
		return gmdate( 'Y-m-d H:i:s', min( $ts, time() - 300 ) );
	}

	/**
	 * Record a created object in the ledger.
	 *
	 * @param string              $provider Provider id.
	 * @param string              $type     Object type.
	 * @param int                 $id       Object id.
	 * @param string              $kind     'sample' or 'setup'.
	 * @param array<string,mixed> $meta     Removal data.
	 * @param int                 $parent   Parent ledger id.
	 * @return int Ledger id.
	 */
	public function record( string $provider, string $type, int $id, string $kind = 'sample', array $meta = array(), int $parent = 0 ): int {
		return Ledger::add( $this->build_id(), $provider, $type, $id, $kind, $parent, $meta );
	}

	/**
	 * Absolute path of a media pack file.
	 *
	 * @param string $rel Path under media/.
	 */
	public function media( string $rel ): string {
		return WBCOM_DEMO_DIR . 'media/' . ltrim( $rel, '/' );
	}

	/**
	 * Copy a media pack file to a temp file (uploaders may move or delete their input).
	 *
	 * @param string $rel Path under media/.
	 * @return string Temp path, '' on failure.
	 */
	public function temp_copy( string $rel ): string {
		$src = $this->media( $rel );
		if ( ! is_readable( $src ) ) {
			return '';
		}
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$tmp = wp_tempnam( basename( $src ) );
		return ( $tmp && copy( $src, $tmp ) ) ? $tmp : '';
	}

	/**
	 * Add a media pack image to the Media Library as an attachment and record it.
	 *
	 * @param string $rel      Path under media/.
	 * @param int    $author   Owner user id.
	 * @param string $provider Provider that owns the attachment in the ledger.
	 * @param string $kind     'sample' or 'setup'.
	 * @return int Attachment id, 0 on failure.
	 */
	public function attachment( string $rel, int $author, string $provider, string $kind = 'sample' ): int {
		$tmp = $this->temp_copy( $rel );
		if ( ! $tmp ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_sideload(
			array(
				'name'     => basename( $rel ),
				'tmp_name' => $tmp,
			),
			0,
			null,
			array( 'post_author' => $author )
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		update_post_meta( $id, '_wbcom_demo', $this->build_id() );
		$this->record( $provider, 'attachment', (int) $id, $kind );
		return (int) $id;
	}

	/**
	 * Provider reference store.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Key.
	 * @param mixed  $value    Value.
	 */
	public function set( string $provider, string $key, $value ): void {
		$this->state->ref_set( $provider, $key, $value );
	}

	/**
	 * Read a provider reference.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Key.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public function get( string $provider, string $key, $fallback = null ) {
		return $this->state->ref( $provider, $key, $fallback );
	}

	/**
	 * Note something a provider could not create (shown in the wizard and CLI, never fatal).
	 *
	 * @param string           $provider Provider id.
	 * @param string           $what     What was being created.
	 * @param \WP_Error|string $error    Error.
	 */
	public function warn( string $provider, string $what, $error ): void {
		$list   = (array) $this->state->get( 'warnings' );
		$list[] = sprintf( '%s: %s (%s)', $provider, $what, is_wp_error( $error ) ? $error->get_error_message() : (string) $error );
		$this->state->set( 'warnings', array_slice( $list, -100 ) );
	}

	/**
	 * Whether a provider is part of this build.
	 *
	 * @param string $id Provider id.
	 */
	public function includes( string $id ): bool {
		return in_array( $id, (array) $this->state->get( 'include' ), true );
	}
}
