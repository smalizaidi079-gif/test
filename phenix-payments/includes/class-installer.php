<?php
/**
 * Activation, upgrades and database schema.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Installer {

	const DB_OPTION = 'phenix_pay_db_version';

	public static function activate() {
		self::create_tables();
		if ( false === get_option( Phenix_Pay_Settings::OPTION ) ) {
			add_option( Phenix_Pay_Settings::OPTION, Phenix_Pay_Settings::defaults(), '', false );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'phenix_pay_cleanup' );
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== PHENIX_PAY_DB_VERSION ) {
			self::create_tables();
		}
	}

	public static function payments_table() {
		global $wpdb;
		return $wpdb->prefix . 'phenix_payments';
	}

	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'phenix_payment_events';
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$payments = self::payments_table();
		$events   = self::events_table();

		// Only the minimum needed to track and reconcile a payment is stored.
		// No card numbers, CVVs, expiry dates, billing addresses or IPs.
		$sql_payments = "CREATE TABLE {$payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			public_id char(36) NOT NULL,
			environment varchar(10) NOT NULL DEFAULT 'sandbox',
			status varchar(20) NOT NULL DEFAULT 'created',
			customer_name varchar(191) NOT NULL DEFAULT '',
			customer_email varchar(191) NOT NULL DEFAULT '',
			reference varchar(100) NOT NULL DEFAULT '',
			description varchar(255) NOT NULL DEFAULT '',
			amount_minor bigint(20) unsigned NOT NULL DEFAULT 0,
			currency char(3) NOT NULL DEFAULT 'USD',
			refunded_minor bigint(20) unsigned NOT NULL DEFAULT 0,
			paypal_order_id varchar(64) DEFAULT NULL,
			paypal_capture_id varchar(64) DEFAULT NULL,
			payment_method varchar(60) NOT NULL DEFAULT '',
			status_detail varchar(255) NOT NULL DEFAULT '',
			notified tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY public_id (public_id),
			UNIQUE KEY paypal_order_id (paypal_order_id),
			KEY paypal_capture_id (paypal_capture_id),
			KEY status (status),
			KEY customer_email (customer_email),
			KEY created_at (created_at)
		) {$charset};";

		$sql_events = "CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_id varchar(64) NOT NULL,
			event_type varchar(64) NOT NULL DEFAULT '',
			payment_id bigint(20) unsigned DEFAULT NULL,
			received_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY payment_id (payment_id)
		) {$charset};";

		dbDelta( $sql_payments );
		dbDelta( $sql_events );

		update_option( self::DB_OPTION, PHENIX_PAY_DB_VERSION, false );
	}
}
