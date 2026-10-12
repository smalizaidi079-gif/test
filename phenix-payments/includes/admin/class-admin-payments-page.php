<?php
/**
 * Payment records screen (list, search, filter, detail, refresh, CSV export).
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Phenix_Pay_Payments_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'payment',
				'plural'   => 'payments',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'created_at'     => __( 'Date', 'phenix-payments' ),
			'customer_name'  => __( 'Customer', 'phenix-payments' ),
			'reference'      => __( 'Reference', 'phenix-payments' ),
			'amount_minor'   => __( 'Amount', 'phenix-payments' ),
			'status'         => __( 'Status', 'phenix-payments' ),
			'payment_method' => __( 'Method', 'phenix-payments' ),
			'transaction'    => __( 'PayPal transaction ID', 'phenix-payments' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at'    => array( 'created_at', true ),
			'customer_name' => array( 'customer_name', false ),
			'reference'     => array( 'reference', false ),
			'amount_minor'  => array( 'amount_minor', false ),
			'status'        => array( 'status', false ),
		);
	}

	public static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$date = function ( $key ) {
			$v = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		$filters = array(
			'search'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'status'      => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
			'environment' => isset( $_GET['environment'] ) ? sanitize_key( $_GET['environment'] ) : '',
			'date_from'   => $date( 'date_from' ),
			'date_to'     => $date( 'date_to' ),
			'orderby'     => isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'created_at',
			'order'       => isset( $_GET['order'] ) ? sanitize_key( $_GET['order'] ) : 'desc',
		);
		// phpcs:enable
		return $filters;
	}

	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'phenix_pay_per_page', 20 );
		$args     = array_merge(
			self::filters(),
			array(
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		$result                = Phenix_Pay_Payments_Repository::query( $args );
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	public function no_items() {
		esc_html_e( 'No payments found.', 'phenix-payments' );
	}

	protected function column_created_at( $item ) {
		$view = add_query_arg(
			array(
				'page'    => Phenix_Pay_Admin_Payments_Page::SLUG,
				'payment' => (int) $item->id,
			),
			admin_url( 'admin.php' )
		);
		$refresh = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'phenix_pay_refresh',
					'payment' => (int) $item->id,
				),
				admin_url( 'admin-post.php' )
			),
			'phenix_pay_refresh_' . (int) $item->id
		);
		$actions = array(
			'view'    => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'Details', 'phenix-payments' ) . '</a>',
			'refresh' => '<a href="' . esc_url( $refresh ) . '">' . esc_html__( 'Refresh from PayPal', 'phenix-payments' ) . '</a>',
		);
		$date = get_date_from_gmt( $item->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		$env  = 'sandbox' === $item->environment ? ' <span class="phx-pill phx-pill--sandbox phx-pill--sm">' . esc_html__( 'Sandbox', 'phenix-payments' ) . '</span>' : '';
		return '<a href="' . esc_url( $view ) . '"><strong>' . esc_html( $date ) . '</strong></a>' . $env . $this->row_actions( $actions );
	}

	protected function column_customer_name( $item ) {
		return esc_html( $item->customer_name ) . '<br><a href="mailto:' . esc_attr( $item->customer_email ) . '">' . esc_html( $item->customer_email ) . '</a>';
	}

	protected function column_reference( $item ) {
		$out = $item->reference ? '<strong>' . esc_html( $item->reference ) . '</strong>' : '—';
		if ( $item->description ) {
			$out .= '<br><span class="description">' . esc_html( wp_trim_words( $item->description, 12 ) ) . '</span>';
		}
		return $out;
	}

	protected function column_amount_minor( $item ) {
		$out = esc_html( Phenix_Pay_Money::format( $item->amount_minor, $item->currency ) );
		if ( (int) $item->refunded_minor > 0 ) {
			$out .= '<br><span class="description">' . esc_html( sprintf( /* translators: %s amount */ __( 'Refunded %s', 'phenix-payments' ), Phenix_Pay_Money::format( $item->refunded_minor, $item->currency ) ) ) . '</span>';
		}
		return $out;
	}

	protected function column_status( $item ) {
		return '<span class="phx-status-badge phx-status-badge--' . esc_attr( $item->status ) . '">' . esc_html( Phenix_Pay_Payments_Repository::status_label( $item->status ) ) . '</span>';
	}

	protected function column_payment_method( $item ) {
		return $item->payment_method ? esc_html( $item->payment_method ) : '—';
	}

	protected function column_transaction( $item ) {
		$out = $item->paypal_capture_id ? '<code>' . esc_html( $item->paypal_capture_id ) . '</code>' : '—';
		if ( $item->paypal_order_id ) {
			$out .= '<br><span class="description">' . esc_html__( 'Order:', 'phenix-payments' ) . ' ' . esc_html( $item->paypal_order_id ) . '</span>';
		}
		return $out;
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$f = self::filters();
		?>
		<div class="alignleft actions phx-filters">
			<label class="screen-reader-text" for="phx-filter-status"><?php esc_html_e( 'Status', 'phenix-payments' ); ?></label>
			<select name="status" id="phx-filter-status">
				<option value=""><?php esc_html_e( 'All statuses', 'phenix-payments' ); ?></option>
				<?php foreach ( Phenix_Pay_Payments_Repository::STATUSES as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['status'], $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<label class="screen-reader-text" for="phx-filter-env"><?php esc_html_e( 'Environment', 'phenix-payments' ); ?></label>
			<select name="environment" id="phx-filter-env">
				<option value=""><?php esc_html_e( 'Live & Sandbox', 'phenix-payments' ); ?></option>
				<option value="live" <?php selected( $f['environment'], 'live' ); ?>><?php esc_html_e( 'Live only', 'phenix-payments' ); ?></option>
				<option value="sandbox" <?php selected( $f['environment'], 'sandbox' ); ?>><?php esc_html_e( 'Sandbox only', 'phenix-payments' ); ?></option>
			</select>
			<label for="phx-from"><?php esc_html_e( 'From', 'phenix-payments' ); ?></label>
			<input type="date" id="phx-from" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>">
			<label for="phx-to"><?php esc_html_e( 'To', 'phenix-payments' ); ?></label>
			<input type="date" id="phx-to" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>">
			<?php submit_button( __( 'Filter', 'phenix-payments' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}

class Phenix_Pay_Admin_Payments_Page {

	const SLUG = 'phenix-payments';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_phenix_pay_refresh', array( $this, 'refresh' ) );
		add_action( 'admin_post_phenix_pay_export', array( $this, 'export' ) );
		add_filter( 'set-screen-option', array( $this, 'save_screen_option' ), 10, 3 );
	}

	public function save_screen_option( $status, $option, $value ) {
		return 'phenix_pay_per_page' === $option ? min( 200, max( 1, (int) $value ) ) : $status;
	}

	public function menu() {
		$hook = add_menu_page(
			__( 'Phenix Payments', 'phenix-payments' ),
			__( 'Phenix Payments', 'phenix-payments' ),
			Phenix_Pay_Plugin::capability(),
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-money-alt',
			56
		);
		add_submenu_page( self::SLUG, __( 'Payments', 'phenix-payments' ), __( 'Payments', 'phenix-payments' ), Phenix_Pay_Plugin::capability(), self::SLUG, array( $this, 'render' ) );
		add_action(
			'load-' . $hook,
			function () {
				add_screen_option(
					'per_page',
					array(
						'default' => 20,
						'option'  => 'phenix_pay_per_page',
					)
				);
			}
		);
	}

	public function render() {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'phenix-payments' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$payment_id = isset( $_GET['payment'] ) ? absint( $_GET['payment'] ) : 0;
		if ( $payment_id ) {
			$this->render_detail( $payment_id );
			return;
		}

		$table = new Phenix_Pay_Payments_List_Table();
		$table->prepare_items();
		$filters    = Phenix_Pay_Payments_List_Table::filters();
		$filters['s'] = $filters['search'];
		unset( $filters['search'] );
		$export_url = wp_nonce_url( add_query_arg( array_map( 'rawurlencode', array_merge( array( 'action' => 'phenix_pay_export' ), array_filter( $filters ) ) ), admin_url( 'admin-post.php' ) ), 'phenix_pay_export' );
		?>
		<div class="wrap phx-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Payments', 'phenix-payments' ); ?></h1>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'phenix-payments' ); ?></a>
			<hr class="wp-header-end">
			<?php $this->flash(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php
				$table->search_box( __( 'Search payments', 'phenix-payments' ), 'phx-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	private function flash() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['phx_msg'] ) ) {
			$type = isset( $_GET['phx_type'] ) && 'error' === $_GET['phx_type'] ? 'error' : 'success';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['phx_msg'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	private function render_detail( $id ) {
		$p = Phenix_Pay_Payments_Repository::find( $id );
		echo '<div class="wrap phx-admin">';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">&larr; ' . esc_html__( 'All payments', 'phenix-payments' ) . '</a></p>';
		if ( ! $p ) {
			echo '<p>' . esc_html__( 'Payment not found.', 'phenix-payments' ) . '</p></div>';
			return;
		}
		$this->flash();
		$dashboard = 'live' === $p->environment ? 'https://www.paypal.com/activity/payment/' : 'https://www.sandbox.paypal.com/activity/payment/';
		$rows      = array(
			__( 'Status', 'phenix-payments' )              => '<span class="phx-status-badge phx-status-badge--' . esc_attr( $p->status ) . '">' . esc_html( Phenix_Pay_Payments_Repository::status_label( $p->status ) ) . '</span>' . ( $p->status_detail ? ' <span class="description">' . esc_html( $p->status_detail ) . '</span>' : '' ),
			__( 'Environment', 'phenix-payments' )         => esc_html( ucfirst( $p->environment ) ),
			__( 'Customer', 'phenix-payments' )            => esc_html( $p->customer_name ) . ' &lt;' . esc_html( $p->customer_email ) . '&gt;',
			__( 'Reference', 'phenix-payments' )           => esc_html( $p->reference ? $p->reference : '—' ),
			__( 'Description', 'phenix-payments' )         => esc_html( $p->description ? $p->description : '—' ),
			__( 'Amount', 'phenix-payments' )              => esc_html( Phenix_Pay_Money::format( $p->amount_minor, $p->currency ) ),
			__( 'Refunded', 'phenix-payments' )            => esc_html( Phenix_Pay_Money::format( $p->refunded_minor, $p->currency ) ),
			__( 'Payment method', 'phenix-payments' )      => esc_html( $p->payment_method ? $p->payment_method : '—' ),
			__( 'PayPal transaction ID', 'phenix-payments' ) => $p->paypal_capture_id ? '<code>' . esc_html( $p->paypal_capture_id ) . '</code> <a href="' . esc_url( $dashboard . rawurlencode( $p->paypal_capture_id ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View in PayPal', 'phenix-payments' ) . '</a>' : '—',
			__( 'PayPal order ID', 'phenix-payments' )     => $p->paypal_order_id ? '<code>' . esc_html( $p->paypal_order_id ) . '</code>' : '—',
			__( 'Payment ID (internal)', 'phenix-payments' ) => '<code>' . esc_html( $p->public_id ) . '</code>',
			__( 'Created', 'phenix-payments' )             => esc_html( get_date_from_gmt( $p->created_at, 'Y-m-d H:i:s' ) ),
			__( 'Completed', 'phenix-payments' )           => esc_html( $p->completed_at ? get_date_from_gmt( $p->completed_at, 'Y-m-d H:i:s' ) : '—' ),
			__( 'Last updated', 'phenix-payments' )        => esc_html( get_date_from_gmt( $p->updated_at, 'Y-m-d H:i:s' ) ),
		);
		echo '<h1>' . esc_html__( 'Payment details', 'phenix-payments' ) . '</h1><table class="widefat striped phx-detail"><tbody>';
		foreach ( $rows as $label => $html ) {
			// $html is escaped above.
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table>';
		$refresh = wp_nonce_url( add_query_arg( array( 'action' => 'phenix_pay_refresh', 'payment' => (int) $p->id, 'back' => 'detail' ), admin_url( 'admin-post.php' ) ), 'phenix_pay_refresh_' . (int) $p->id );
		echo '<p><a class="button" href="' . esc_url( $refresh ) . '">' . esc_html__( 'Refresh status from PayPal', 'phenix-payments' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Refunds are issued from your PayPal account (Activity → transaction → Issue a refund). The status here updates automatically via webhook.', 'phenix-payments' ) . '</p>';
		echo '</div>';
	}

	public function refresh() {
		$id = isset( $_GET['payment'] ) ? absint( $_GET['payment'] ) : 0;
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'phenix-payments' ), 403 );
		}
		check_admin_referer( 'phenix_pay_refresh_' . $id );

		$record = Phenix_Pay_Payments_Repository::find( $id );
		$args   = array( 'page' => self::SLUG );
		if ( isset( $_GET['back'] ) && 'detail' === $_GET['back'] ) {
			$args['payment'] = $id;
		}
		if ( ! $record ) {
			$args['phx_msg']  = __( 'Payment not found.', 'phenix-payments' );
			$args['phx_type'] = 'error';
		} else {
			$service = new Phenix_Pay_Payment_Service( new Phenix_Pay_PayPal_Client( $record->environment ) );
			$result  = $service->refresh( $record );
			if ( is_wp_error( $result ) ) {
				$args['phx_msg']  = $result->get_error_message();
				$args['phx_type'] = 'error';
			} else {
				$args['phx_msg'] = sprintf( /* translators: %s status */ __( 'Status refreshed: %s', 'phenix-payments' ), Phenix_Pay_Payments_Repository::status_label( $result->status ) );
				Phenix_Pay_Logger::info( 'Payment refreshed by admin', array( 'payment' => $record->public_id, 'user' => get_current_user_id() ) );
			}
		}
		wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function export() {
		if ( ! current_user_can( Phenix_Pay_Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'phenix-payments' ), 403 );
		}
		check_admin_referer( 'phenix_pay_export' );

		$args = array_merge( Phenix_Pay_Payments_List_Table::filters(), array( 'per_page' => 200 ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=phenix-payments-' . gmdate( 'Ymd-His' ) . '.csv' );
		header( 'X-Content-Type-Options: nosniff' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Date (UTC)', 'Environment', 'Status', 'Customer name', 'Customer email', 'Reference', 'Description', 'Amount', 'Currency', 'Refunded', 'Payment method', 'PayPal transaction ID', 'PayPal order ID', 'Payment ID' ) );
		$page = 1;
		do {
			$args['page'] = $page;
			$res          = Phenix_Pay_Payments_Repository::query( $args );
			foreach ( $res['items'] as $p ) {
				fputcsv(
					$out,
					array_map(
						array( __CLASS__, 'csv_safe' ),
						array( $p->created_at, $p->environment, $p->status, $p->customer_name, $p->customer_email, $p->reference, $p->description, Phenix_Pay_Money::to_decimal( $p->amount_minor ), $p->currency, Phenix_Pay_Money::to_decimal( $p->refunded_minor ), $p->payment_method, $p->paypal_capture_id, $p->paypal_order_id, $p->public_id )
					)
				);
			}
			$page++;
		} while ( count( $res['items'] ) === 200 );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Neutralise spreadsheet formula injection in client-supplied text.
	 */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
