<?php
/**
 * Uninstall: remove the installer's own data. If sample content is still on the site, keep the
 * ledger and state so reinstalling the plugin can still finish the demo cleanly.
 *
 * @package WbcomDemo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wbcom_demo_table = $wpdb->prefix . 'wbcom_demo_ledger';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wbcom_demo_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wbcom_demo_table ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wbcom_demo_left = $wbcom_demo_exists ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE kind = %s', $wbcom_demo_table, 'sample' ) ) : 0;

if ( $wbcom_demo_left > 0 ) {
	return;
}

if ( $wbcom_demo_exists ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wbcom_demo_table ) );
}
delete_option( 'wbcom_demo_state' );
delete_option( 'wbcom_demo_snapshot' );
delete_option( 'wbcom_demo_db_version' );
delete_option( 'wbcom_demo_lock' );
delete_metadata( 'user', 0, 'wbcom_demo_notice_dismissed', '', true );
