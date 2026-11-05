<?php
/**
 * The ledger: every object a build creates, so Finish never has to guess.
 *
 * @package WbcomDemo
 */

namespace WbcomDemo\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Ledger table access. One row per created object.
 *
 * Rows are removed in reverse creation order (id DESC), which is children
 * before parents: comments before posts, posts before spaces, people last.
 */
final class Ledger {

	private const DB_VERSION = '1';
	private const DB_OPTION  = 'wbcom_demo_db_version';

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wbcom_demo_ledger';
	}

	/**
	 * Create or update the table.
	 */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				build_id varchar(36) NOT NULL,
				provider varchar(40) NOT NULL,
				object_type varchar(40) NOT NULL,
				object_id bigint(20) unsigned NOT NULL,
				kind varchar(10) NOT NULL DEFAULT 'sample',
				parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
				meta longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY object (provider,object_type,object_id),
				KEY build_kind (build_id,provider,kind),
				KEY type_kind (provider,object_type,kind)
			) ENGINE=InnoDB {$charset};"
		);
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Install when the schema is missing or older (also covers network activation gaps).
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::DB_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Record one created object. Re-recording the same object is a no-op.
	 *
	 * @param string              $build_id Build id.
	 * @param string              $provider Provider id.
	 * @param string              $type     Object type, provider-scoped.
	 * @param int                 $id       Object id.
	 * @param string              $kind     'sample' or 'setup'.
	 * @param int                 $parent_id   Ledger row id of the parent, or 0.
	 * @param array<string,mixed> $meta     Extra data the provider needs to remove it (e.g. author).
	 * @return int Ledger row id (existing row id when already recorded).
	 */
	public static function add( string $build_id, string $provider, string $type, int $id, string $kind = 'sample', int $parent_id = 0, array $meta = array() ): int {
		global $wpdb;
		if ( $id <= 0 ) {
			return 0;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- own table.
		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (build_id, provider, object_type, object_id, kind, parent_id, meta, created_at) VALUES (%s, %s, %s, %d, %s, %d, %s, %s)',
				$table,
				$build_id,
				$provider,
				$type,
				$id,
				'setup' === $kind ? 'setup' : 'sample',
				$parent_id,
				$meta ? wp_json_encode( $meta ) : null,
				current_time( 'mysql', true )
			)
		);
		if ( $wpdb->insert_id ) {
			return (int) $wpdb->insert_id;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE provider = %s AND object_type = %s AND object_id = %d', $table, $provider, $type, $id ) );
	}

	/**
	 * Whether an object is already in the ledger.
	 *
	 * @param string $provider Provider id.
	 * @param string $type     Object type.
	 * @param int    $id       Object id.
	 */
	public static function has( string $provider, string $type, int $id ): bool {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE provider = %s AND object_type = %s AND object_id = %d', $table, $provider, $type, $id ) );
	}

	/**
	 * Counts grouped by provider, type and kind.
	 *
	 * @return array<int,object{provider:string,object_type:string,kind:string,n:int}>
	 */
	public static function counts(): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT provider, object_type, kind, COUNT(*) AS n FROM %i GROUP BY provider, object_type, kind ORDER BY MIN(id)', $table ) );
		foreach ( $rows as $r ) {
			$r->n = (int) $r->n;
		}
		return $rows;
	}

	/**
	 * Next batch of rows to remove, newest first, limited to the selected provider:type keys.
	 *
	 * @param string[] $keys    Selected "provider:type" keys.
	 * @param int[]    $skip    Ledger ids to skip (failed or waiting).
	 * @param int      $limit   Batch size.
	 * @return array<int,object>
	 */
	public static function next_batch( array $keys, array $skip, int $limit = 25 ): array {
		global $wpdb;
		if ( ! $keys ) {
			return array();
		}
		$where  = array();
		$params = array( self::table() );
		foreach ( $keys as $key ) {
			list( $provider, $type ) = array_pad( explode( ':', $key, 2 ), 2, '' );
			$where[]                 = '(provider = %s AND object_type = %s)';
			$params[]                = $provider;
			$params[]                = $type;
		}
		$sql = 'SELECT * FROM %i WHERE (' . implode( ' OR ', $where ) . ')';
		if ( $skip ) {
			$sql   .= ' AND id NOT IN (' . implode( ',', array_fill( 0, count( $skip ), '%d' ) ) . ')';
			$params = array_merge( $params, array_map( 'absint', $skip ) );
		}
		$sql     .= ' ORDER BY id DESC LIMIT %d';
		$params[] = $limit;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		foreach ( $rows as $r ) {
			$r->meta = $r->meta ? (array) json_decode( $r->meta, true ) : array();
		}
		return $rows;
	}

	/**
	 * Delete ledger rows by id.
	 *
	 * @param int[] $ids Ledger row ids.
	 */
	public static function forget( array $ids ): void {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %d per id, built above.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')', self::table(), ...array_values( $ids ) ) );
	}

	/**
	 * Hand rows over to the owner: drop them from the ledger without touching the objects.
	 *
	 * @param string[] $keys "provider:type" keys.
	 */
	public static function handover( array $keys ): void {
		global $wpdb;
		$table = self::table();
		foreach ( $keys as $key ) {
			list( $provider, $type ) = array_pad( explode( ':', $key, 2 ), 2, '' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete(
				$table,
				array(
					'provider'    => $provider,
					'object_type' => $type,
				)
			);
		}
	}

	/**
	 * Total rows, optionally of one kind.
	 *
	 * @param string $kind 'sample', 'setup' or '' for all.
	 */
	public static function total( string $kind = '' ): int {
		global $wpdb;
		$table = self::table();
		if ( $kind ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE kind = %s', $table, $kind ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Object ids of one provider:type, oldest first.
	 *
	 * @param string $provider Provider id.
	 * @param string $type     Object type.
	 * @return int[]
	 */
	public static function ids( string $provider, string $type ): array {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT object_id FROM %i WHERE provider = %s AND object_type = %s ORDER BY id', $table, $provider, $type ) ) );
	}

	/**
	 * Drop the table (uninstall only, and only when empty).
	 */
	public static function drop(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		delete_option( self::DB_OPTION );
	}
}
