<?php
/**
 * Data access for payment records. All queries use $wpdb->prepare().
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Payments_Repository {

	const STATUSES = array(
		'created'            => 'Awaiting payment',
		'capturing'          => 'Processing',
		'pending'            => 'Pending (PayPal review)',
		'completed'          => 'Completed',
		'declined'           => 'Declined',
		'cancelled'          => 'Cancelled',
		'failed'             => 'Failed',
		'refunded'           => 'Refunded',
		'partially_refunded' => 'Partially refunded',
		'reversed'           => 'Reversed / disputed',
	);

	/** Statuses from which a capture attempt may start. */
	const CAPTURABLE = array( 'created', 'cancelled', 'failed', 'declined' );

	public static function status_label( $status ) {
		return isset( self::STATUSES[ $status ] ) ? self::STATUSES[ $status ] : ucfirst( (string) $status );
	}

	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * @return int|false Inserted row id.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$now  = self::now();
		$row  = array(
			'public_id'      => $data['public_id'],
			'environment'    => $data['environment'],
			'status'         => 'created',
			'customer_name'  => $data['customer_name'],
			'customer_email' => $data['customer_email'],
			'reference'      => $data['reference'],
			'description'    => $data['description'],
			'amount_minor'   => (int) $data['amount_minor'],
			'currency'       => $data['currency'],
			'created_at'     => $now,
			'updated_at'     => $now,
		);
		$ok = $wpdb->insert(
			Phenix_Pay_Installer::payments_table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function find( $id ) {
		return self::find_by( 'id', (int) $id, '%d' );
	}

	public static function find_by_public_id( $public_id ) {
		return self::find_by( 'public_id', (string) $public_id );
	}

	public static function find_by_order_id( $order_id ) {
		return self::find_by( 'paypal_order_id', (string) $order_id );
	}

	public static function find_by_capture_id( $capture_id ) {
		return self::find_by( 'paypal_capture_id', (string) $capture_id );
	}

	private static function find_by( $column, $value, $format = '%s' ) {
		global $wpdb;
		$allowed = array( 'id', 'public_id', 'paypal_order_id', 'paypal_capture_id' );
		if ( ! in_array( $column, $allowed, true ) || '' === $value ) {
			return null;
		}
		$table = Phenix_Pay_Installer::payments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column are whitelisted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = {$format} LIMIT 1", $value ) );
		return $row ? $row : null;
	}

	/**
	 * Update arbitrary whitelisted columns.
	 */
	public static function update( $id, array $fields ) {
		global $wpdb;
		$allowed = array(
			'status'            => '%s',
			'paypal_order_id'   => '%s',
			'paypal_capture_id' => '%s',
			'payment_method'    => '%s',
			'status_detail'     => '%s',
			'refunded_minor'    => '%d',
			'completed_at'      => '%s',
			'notified'          => '%d',
		);
		$data    = array();
		$formats = array();
		foreach ( $fields as $key => $value ) {
			if ( isset( $allowed[ $key ] ) ) {
				$data[ $key ] = $value;
				$formats[]    = $allowed[ $key ];
			}
		}
		if ( empty( $data ) ) {
			return false;
		}
		$data['updated_at'] = self::now();
		$formats[]          = '%s';
		return false !== $wpdb->update( Phenix_Pay_Installer::payments_table(), $data, array( 'id' => (int) $id ), $formats, array( '%d' ) );
	}

	/**
	 * Atomically move a payment from one of $from statuses to $to.
	 * Returns true only for the single request that won the transition,
	 * which is how concurrent/duplicate captures are prevented.
	 */
	public static function transition( $id, array $from, $to ) {
		global $wpdb;
		$table        = Phenix_Pay_Installer::payments_table();
		$placeholders = implode( ',', array_fill( 0, count( $from ), '%s' ) );
		$args         = array_merge( array( $to, self::now(), (int) $id ), $from );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql      = $wpdb->prepare( "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d AND status IN ({$placeholders})", $args );
		$affected = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return 1 === (int) $affected;
	}

	/**
	 * Atomically flag a payment as notified. Returns true only once.
	 */
	public static function claim_notification( $id ) {
		global $wpdb;
		$table = Phenix_Pay_Installer::payments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "UPDATE {$table} SET notified = 1 WHERE id = %d AND notified = 0", (int) $id );
		return 1 === (int) $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Record a webhook event id. Returns false if it was already processed.
	 */
	public static function claim_event( $event_id, $event_type ) {
		global $wpdb;
		$table = Phenix_Pay_Installer::events_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare( "INSERT IGNORE INTO {$table} (event_id, event_type, received_at) VALUES (%s, %s, %s)", $event_id, $event_type, self::now() );
		return 1 === (int) $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function release_event( $event_id ) {
		global $wpdb;
		$wpdb->delete( Phenix_Pay_Installer::events_table(), array( 'event_id' => $event_id ), array( '%s' ) );
	}

	public static function link_event( $event_id, $payment_id ) {
		global $wpdb;
		$wpdb->update( Phenix_Pay_Installer::events_table(), array( 'payment_id' => (int) $payment_id ), array( 'event_id' => $event_id ), array( '%d' ), array( '%s' ) );
	}

	/**
	 * Search/filter for the admin list table.
	 *
	 * @param array $args search, status, environment, date_from, date_to, orderby, order, per_page, page.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$table  = Phenix_Pay_Installer::payments_table();
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(customer_name LIKE %s OR customer_email LIKE %s OR reference LIKE %s OR description LIKE %s OR paypal_order_id LIKE %s OR paypal_capture_id LIKE %s OR public_id LIKE %s)';
			$params   = array_merge( $params, array_fill( 0, 7, $like ) );
		}
		if ( ! empty( $args['status'] ) && isset( self::STATUSES[ $args['status'] ] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( ! empty( $args['environment'] ) && in_array( $args['environment'], array( 'sandbox', 'live' ), true ) ) {
			$where[]  = 'environment = %s';
			$params[] = $args['environment'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}

		$sortable = array( 'created_at', 'amount_minor', 'customer_name', 'status', 'reference' );
		$orderby  = ( isset( $args['orderby'] ) && in_array( $args['orderby'], $sortable, true ) ) ? $args['orderby'] : 'created_at';
		$order    = ( isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ) ? 'ASC' : 'DESC';
		$per_page = max( 1, min( 200, (int) ( isset( $args['per_page'] ) ? $args['per_page'] : 20 ) ) );
		$page     = max( 1, (int) ( isset( $args['page'] ) ? $args['page'] : 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$items_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, array_merge( $params, array( $per_page, $offset ) ) ) );
		// phpcs:enable

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	public static function status_counts() {
		global $wpdb;
		$table = Phenix_Pay_Installer::payments_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status" );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ $row->status ] = (int) $row->c;
		}
		return $counts;
	}
}
