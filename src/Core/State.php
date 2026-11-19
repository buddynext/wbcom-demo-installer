<?php
/**
 * Persistent installer state: what is built, what is queued, what failed.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * One option holds the whole state so a build or finish can resume from any request.
 */
final class State {

	public const OPTION = 'wbcom_demo_state';

	/** Statuses. */
	public const IDLE      = 'idle';
	public const BUILDING  = 'building';
	public const BUILT     = 'built';
	public const FINISHING = 'finishing';

	/**
	 * Raw data.
	 *
	 * @var array<string,mixed>
	 */
	private array $data;

	/**
	 * Load from the database.
	 */
	public function __construct() {
		$saved      = get_option( self::OPTION, array() );
		$this->data = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Default shape.
	 *
	 * @return array<string,mixed>
	 */
	private static function defaults(): array {
		return array(
			'status'   => self::IDLE,
			'build_id' => '',
			'recipe'   => '',
			'include'  => array(),
			'look'     => 'demo',
			'queue'    => array(),
			'total'    => 0,
			'done'     => 0,
			'people'   => array(),
			'refs'     => array(),
			'failed'   => array(),
			'errors'   => array(),
			'warnings' => array(),
			'built_at' => '',
			'finish'   => array(),
			'report'   => array(),
		);
	}

	/**
	 * Read a key.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Set a key (call save() to persist).
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function set( string $key, $value ): self {
		$this->data[ $key ] = $value;
		return $this;
	}

	/**
	 * Persist.
	 */
	public function save(): void {
		update_option( self::OPTION, $this->data, false );
	}

	/**
	 * Reset to idle, keeping the last report.
	 */
	public function reset(): void {
		$report               = $this->data['report'];
		$this->data           = self::defaults();
		$this->data['report'] = $report;
		$this->save();
	}

	/**
	 * Where the demo stands, for themes and plugins (see wbcom_demo_status()). Times are UTC, Y-m-d H:i:s.
	 *
	 * Status: idle (no demo, or Remove everything put the site back), building, built, finishing, or finished (the
	 * owner took the demo live and nothing is left to remove). finished_at is set once a finish has run on this
	 * build and kept something, so "built" with a finished_at and samples_left 0 is a demo already taken live whose kept pages are still
	 * removable; samples_left says whether sample people or content remain (0 = live).
	 *
	 * @return array{status:string,demo_id:string,demo_label:string,built_at:string,finished_at:string,samples_left:int}
	 */
	public static function summary(): array {
		$state    = new self();
		$status   = (string) $state->get( 'status' );
		$report   = (array) $state->get( 'report' );
		$ended    = ! empty( $report['finished_at'] ) && 'all' !== ( $report['mode'] ?? '' );
		$finished = self::IDLE === $status && $ended;
		$from     = $finished ? $report : $state->all();
		$built_at = (string) ( $from['built_at'] ?? '' );
		// A finish of this build, not one left over from an earlier demo.
		$this_build = $ended && ( $finished || ( self::BUILT === $status && (string) $report['finished_at'] >= $built_at ) );
		return array(
			'status'       => $finished ? 'finished' : $status,
			'demo_id'      => (string) ( $from['demo_id'] ?? '' ),
			'demo_label'   => (string) ( $from['demo'] ?? '' ),
			'built_at'     => $built_at,
			'finished_at'  => $this_build ? (string) $report['finished_at'] : '',
			'samples_left' => in_array( $status, array( self::BUILT, self::FINISHING ), true ) ? Ledger::total( 'sample' ) : 0,
		);
	}

	/**
	 * Whole state (for REST/CLI status).
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		return $this->data;
	}

	/**
	 * Per-provider reference store (ids a later step needs).
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Key.
	 * @param mixed  $value    Value.
	 */
	public function ref_set( string $provider, string $key, $value ): void {
		$this->data['refs'][ $provider ][ $key ] = $value;
	}

	/**
	 * Read a provider reference.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Key.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public function ref( string $provider, string $key, $fallback = null ) {
		return $this->data['refs'][ $provider ][ $key ] ?? $fallback;
	}
}
