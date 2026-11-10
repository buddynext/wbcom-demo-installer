<?php
/**
 * Finish demo: remove what the owner chose, newest first, then verify.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

use WbcomDemo\Providers\People;

defined( 'ABSPATH' ) || exit;

/**
 * Removal in reverse creation order through each product's own delete functions.
 */
final class Finisher {

	private const BATCH = 25;

	/**
	 * Work out what each mode removes and keeps.
	 *
	 * @param string             $mode   'keep' (keep setup), 'custom' or 'all'.
	 * @param array<string,bool> $custom "provider:type" => remove? (custom mode only).
	 * @return array{remove:string[],keep:string[],people:bool}
	 */
	public static function selection( string $mode, array $custom = array() ): array {
		$remove = array();
		$keep   = array();
		foreach ( Ledger::counts() as $row ) {
			$key = $row->provider . ':' . $row->object_type;
			if ( 'people:user' === $key || in_array( $key, $remove, true ) || in_array( $key, $keep, true ) ) {
				continue;
			}
			if ( 'all' === $mode ) {
				$del = true;
			} elseif ( 'custom' === $mode ) {
				$del = ! empty( $custom[ $key ] );
			} else {
				$del = 'sample' === $row->kind;
			}
			if ( $del ) {
				$remove[] = $key;
			} else {
				$keep[] = $key;
			}
		}
		// People go only when nothing they made is kept.
		$people = true;
		foreach ( Ledger::counts() as $row ) {
			$key = $row->provider . ':' . $row->object_type;
			if ( 'sample' === $row->kind && in_array( $key, $keep, true ) ) {
				$people = false;
			}
		}
		if ( 'custom' === $mode && isset( $custom['people:user'] ) && ! $custom['people:user'] ) {
			$people = false;
		}
		if ( $people ) {
			$remove[] = 'people:user';
		}
		return array(
			'remove' => $remove,
			'keep'   => $keep,
			'people' => $people,
		);
	}

	/**
	 * Dry run: counts per key for the wizard.
	 *
	 * @param string             $mode   Mode.
	 * @param array<string,bool> $custom Custom choices.
	 * @return array<string,mixed>
	 */
	public static function preview( string $mode, array $custom = array() ): array {
		$sel  = self::selection( $mode, $custom );
		$rows = array(
			'remove' => array(),
			'keep'   => array(),
		);
		foreach ( Ledger::counts() as $row ) {
			$key             = $row->provider . ':' . $row->object_type;
			$side            = in_array( $key, $sel['remove'], true ) ? 'remove' : 'keep';
			$p               = Registry::get( $row->provider );
			$rows[ $side ][] = array(
				'key'       => $key,
				'product'   => $p ? $p->label() : $row->provider,
				'label'     => Engine::type_label( $row->provider, $row->object_type ),
				'kind'      => $row->kind,
				'count'     => $row->n,
				'available' => $p ? $p->available() : false,
			);
		}
		return $rows + array(
			'people'  => $sel['people'],
			'restore' => 'all' === $mode,
		);
	}

	/**
	 * Start a finish.
	 *
	 * @param string             $mode   Mode.
	 * @param array<string,bool> $custom Custom choices.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function start( string $mode, array $custom = array() ) {
		$state = new State();
		if ( ! in_array( $state->get( 'status' ), array( State::BUILT, State::BUILDING ), true ) ) {
			return new \WP_Error( 'wbcom_demo_none', __( 'There is no demo to finish.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		$mode = in_array( $mode, array( 'keep', 'custom', 'all' ), true ) ? $mode : 'keep';
		$sel  = self::selection( $mode, $custom );
		// Put the previous look back first, while the demo menu and logo still exist,
		// so deleting them cannot be mistaken for an owner change.
		$kept_options = 'all' === $mode ? Snapshot::restore() : array();
		$state->set( 'status', State::FINISHING )
			->set( 'queue', array() )
			->set(
				'finish',
				array(
					'mode'         => $mode,
					'remove'       => $sel['remove'],
					'keep'         => $sel['keep'],
					'people'       => $sel['people'],
					'skip'         => array(),
					'waiting'      => array(),
					'removed'      => 0,
					'kept_options' => $kept_options,
					'total'        => self::count_keys( $sel['remove'] ),
				)
			)
			->save();
		return Engine::status();
	}

	/**
	 * Rows behind a set of keys.
	 *
	 * @param string[] $keys Keys.
	 */
	private static function count_keys( array $keys ): int {
		$n = 0;
		foreach ( Ledger::counts() as $row ) {
			if ( in_array( $row->provider . ':' . $row->object_type, $keys, true ) ) {
				$n += $row->n;
			}
		}
		return $n;
	}

	/**
	 * Remove one batch; finish up when nothing is left.
	 *
	 * @param State $state State.
	 */
	public static function step( State $state ): void {
		$f   = (array) $state->get( 'finish' );
		$ctx = new Context( $state );
		if ( ! empty( $f['trying'] ) ) {
			// The last request died while removing this row (a fatal error in the product): skip it, report it.
			$f['skip'][] = (int) $f['trying'];
			unset( $f['trying'] );
			$state->set( 'finish', $f )->save();
		}
		foreach ( Registry::all() as $p ) {
			$p->boot( $ctx );
		}
		// Order: normal rows newest first, then late types, then the people.
		$late = array();
		foreach ( Registry::all() as $id => $p ) {
			foreach ( $p->late_types() as $type ) {
				$late[] = $id . ':' . $type;
			}
		}
		$skip   = array_merge( $f['skip'], $f['waiting'] );
		$normal = array_values( array_diff( $f['remove'], $late, array( 'people:user' ) ) );
		$rows   = Ledger::next_batch( $normal, $skip, self::BATCH );
		if ( ! $rows ) {
			$rows = Ledger::next_batch( array_values( array_intersect( $f['remove'], $late ) ), $skip, self::BATCH );
		}
		if ( ! $rows ) {
			$rows = Ledger::next_batch( array_values( array_intersect( $f['remove'], array( 'people:user' ) ) ), $skip, self::BATCH );
		}
		if ( $rows && 'people' === $rows[0]->provider && $f['waiting'] ) {
			// A product is inactive and still holds sample content made by these people:
			// keep the people until that content can be removed too.
			foreach ( Ledger::next_batch( array( 'people:user' ), array(), 1000 ) as $person ) {
				$f['waiting'][] = (int) $person->id;
			}
			$state->set( 'finish', $f )->save();
			return;
		}
		if ( $rows && empty( $f['handed'] ) && 'people' === $rows[0]->provider ) {
			// Everything but the people is gone: give kept items a real owner first.
			$owner = (int) get_current_user_id();
			foreach ( Registry::all() as $p ) {
				if ( $owner && $p->available() ) {
					$p->handover( $f['keep'], $owner );
				}
			}
			$f['handed'] = true;
			$state->set( 'finish', $f )->save();
			return;
		}
		if ( $rows ) {
			$done = array();
			foreach ( $rows as $row ) {
				$p = Registry::get( $row->provider );
				if ( ! $p || ! $p->available() ) {
					$f['waiting'][] = (int) $row->id;
					continue;
				}
				$f['trying'] = (int) $row->id;
				$state->set( 'finish', $f )->save();
				try {
					$ok = $p->remove( $row );
				} catch ( \Throwable $e ) {
					$ok = false;
				}
				unset( $f['trying'] );
				if ( $ok ) {
					$done[] = (int) $row->id;
				} else {
					$f['skip'][] = (int) $row->id;
				}
			}
			Ledger::forget( $done );
			$f['removed'] += count( $done );
			$state->set( 'finish', $f )->save();
			return;
		}
		self::complete( $state, $f );
	}

	/**
	 * Continue the last finish: remove what it was asked to remove and could not (a product was inactive,
	 * or a remove failed), with the same choices.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function resume() {
		$report = (array) ( new State() )->get( 'report' );
		$keys   = array_values( array_filter( (array) ( $report['remove'] ?? array() ), 'is_string' ) );
		if ( ! $keys || ! self::count_keys( $keys ) ) {
			return new \WP_Error( 'wbcom_demo_nothing_waiting', __( 'Nothing is waiting to be removed.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		return self::start( 'custom', array_fill_keys( $keys, true ) );
	}

	/**
	 * The owner keeps what the demo left as their own: the installer stops tracking it and the demo ends.
	 * Only once no sample content is left, so sample people and their content can never be orphaned.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function adopt() {
		$state = new State();
		if ( State::BUILT !== $state->get( 'status' ) ) {
			return new \WP_Error( 'wbcom_demo_none', __( 'There is no demo to finish.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		if ( Ledger::total( 'sample' ) ) {
			return new \WP_Error( 'wbcom_demo_sample_left', __( 'Remove the sample content first. Only settings, pages, menus and categories can be kept as yours.', 'wbcom-demo-installer' ), array( 'status' => 409 ) );
		}
		$keys = array();
		foreach ( Ledger::counts() as $row ) {
			$keys[] = $row->provider . ':' . $row->object_type;
		}
		Ledger::handover( $keys );
		Snapshot::forget();
		$state->reset();
		return Engine::status();
	}

	/**
	 * Products that keep their own records about removed people (logs, transactions). The installer does not
	 * touch product tables, so it names them; each product team owns the cleanup.
	 *
	 * @return string[]
	 */
	private static function product_logs(): array {
		$found = array();
		foreach ( Registry::all() as $p ) {
			if ( $p->available() && $p->keeps_logs() ) {
				$found[] = $p->keeps_logs();
			}
		}
		return $found;
	}

	/**
	 * WordPress turns `fresh_site` off when the demo publishes a page. After "Remove everything",
	 * turn it back on, unless the owner has published something of their own since the build.
	 *
	 * @param State $state State.
	 */
	private static function restore_fresh_site( State $state ): void {
		if ( '1' !== (string) $state->get( 'fresh_site' ) || '1' === (string) get_option( 'fresh_site' ) ) {
			return;
		}
		$own = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'after'  => (string) $state->get( 'started_at' ),
					),
				),
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( ! $own ) {
			update_option( 'fresh_site', '1' );
		}
	}

	/**
	 * Wrap up: hand over kept setup, restore the look if asked, verify, report.
	 *
	 * @param State               $state State.
	 * @param array<string,mixed> $f     Finish data.
	 */
	private static function complete( State $state, array $f ): void {
		$kept_options = (array) ( $f['kept_options'] ?? array() );
		if ( 'all' === $f['mode'] ) {
			self::restore_fresh_site( $state );
		}
		// Kept items stay in the ledger (owner, 10 November 2026): they can still be removed later, until the
		// owner says they are theirs (adopt()).

		$issues = array();
		foreach ( Registry::all() as $id => $p ) {
			if ( 'people' === $id && ! $f['people'] ) {
				continue;
			}
			if ( $p->available() ) {
				$issues = array_merge( $issues, $p->verify() );
			}
		}
		$left        = 0;
		$kept_sample = 0;
		$kept_setup  = 0;
		foreach ( Ledger::counts() as $row ) {
			if ( in_array( $row->provider . ':' . $row->object_type, $f['remove'], true ) ) {
				$left += $row->n;
			} elseif ( 'sample' === $row->kind ) {
				$kept_sample += $row->n;
			} else {
				$kept_setup += $row->n;
			}
		}
		$waiting_products = array();
		foreach ( Ledger::rows( array_map( 'intval', $f['waiting'] ) ) as $row ) {
			if ( 'people' === $row->provider ) {
				continue; // They wait for the product, not for activation of their own.
			}
			$p                                  = Registry::get( $row->provider );
			$waiting_products[ $row->provider ] = $p ? $p->label() : $row->provider;
		}
		$report = array(
			'mode'         => $f['mode'],
			'remove'       => $f['remove'],
			'removed'      => $f['removed'],
			'left'         => $left,
			'kept_sample'  => $kept_sample,
			'kept_setup'   => $kept_setup,
			'waiting_for'  => array_values( $waiting_products ),
			'leftovers'    => self::product_logs(),
			'waiting'      => count( $f['waiting'] ),
			'failed'       => count( $f['skip'] ),
			'kept'         => $f['keep'],
			'kept_options' => $kept_options,
			'issues'       => $issues,
			'people_left'  => (int) ( new \WP_User_Query(
				array(
					'meta_key'    => People::FLAG, // phpcs:ignore WordPress.DB.SlowDBQuery
					'count_total' => true,
					'fields'      => 'ID',
					'number'      => 1,
				)
			) )->get_total(),
			'finished_at'  => current_time( 'mysql', true ),
		);
		$state->set( 'report', $report );

		if ( Ledger::total() > 0 ) {
			// Something stays: kept by choice, waiting for a product, or failed. It is still removable.
			$state->set( 'status', State::BUILT )->set( 'finish', array() )->save();
		} else {
			$state->reset();
		}
		/**
		 * A finish completed.
		 *
		 * @param array $report Report.
		 */
		do_action( 'wbcom_demo_finished', $report );
	}
}
