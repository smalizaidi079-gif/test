<?php
/**
 * Uninstall: removes data only if the administrator opted in.
 * By default payment records are kept, as they are financial records.
 *
 * @package PhenixPayments
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$phenix_pay_settings = get_option( 'phenix_pay_settings', array() );

// Always remove cached OAuth tokens and transient data.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_phenix\_pay\_%' OR option_name LIKE '\_transient\_timeout\_phenix\_pay\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

if ( is_array( $phenix_pay_settings ) && ! empty( $phenix_pay_settings['delete_on_uninstall'] ) ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}phenix_payments" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}phenix_payment_events" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
	delete_option( 'phenix_pay_settings' );
	delete_option( 'phenix_pay_db_version' );
	delete_option( 'phenix_pay_log' );
	delete_option( 'phenix_pay_connection_status' );
}
