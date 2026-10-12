<?php
/**
 * Email notifications, sent once per completed payment.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Notifications {

	public static function payment_completed( $record ) {
		if ( ! $record || 'completed' !== $record->status ) {
			return;
		}
		if ( ! Phenix_Pay_Payments_Repository::claim_notification( $record->id ) ) {
			return; // Already sent.
		}

		$brand  = (string) Phenix_Pay_Settings::get( 'brand_name' );
		$amount = Phenix_Pay_Money::format( $record->amount_minor, $record->currency );
		$test   = 'sandbox' === $record->environment ? '[SANDBOX TEST] ' : '';

		$lines = array(
			__( 'Amount', 'phenix-payments' ) . ': ' . $amount,
			__( 'Reference', 'phenix-payments' ) . ': ' . ( '' !== $record->reference ? $record->reference : '-' ),
			__( 'Description', 'phenix-payments' ) . ': ' . ( '' !== $record->description ? $record->description : '-' ),
			__( 'PayPal transaction ID', 'phenix-payments' ) . ': ' . $record->paypal_capture_id,
			__( 'Date (UTC)', 'phenix-payments' ) . ': ' . $record->completed_at,
		);

		if ( Phenix_Pay_Settings::get( 'notify_admin' ) ) {
			$to = Phenix_Pay_Settings::get( 'notify_email' );
			$to = is_email( $to ) ? $to : get_option( 'admin_email' );
			$body = sprintf( "%s\n\n%s: %s <%s>\n%s\n%s: %s\n",
				__( 'A new payment has been completed.', 'phenix-payments' ),
				__( 'Customer', 'phenix-payments' ),
				$record->customer_name,
				$record->customer_email,
				implode( "\n", $lines ),
				__( 'Method', 'phenix-payments' ),
				$record->payment_method
			);
			wp_mail( $to, $test . sprintf( /* translators: %s amount */ __( 'Payment received: %s', 'phenix-payments' ), $amount ), $body );
		}

		if ( Phenix_Pay_Settings::get( 'send_receipt' ) && is_email( $record->customer_email ) ) {
			$body = sprintf(
				"%s\n\n%s\n\n%s\n\n%s\n",
				sprintf( /* translators: %s customer name */ __( 'Hi %s,', 'phenix-payments' ), $record->customer_name ),
				sprintf( /* translators: %s business name */ __( 'Thank you for your payment to %s. Here are your payment details:', 'phenix-payments' ), $brand ),
				implode( "\n", $lines ),
				sprintf( /* translators: %s business name */ __( 'This is an automated receipt from %s. Please keep it for your records.', 'phenix-payments' ), $brand )
			);
			wp_mail( $record->customer_email, $test . sprintf( /* translators: %s business name */ __( 'Your payment receipt from %s', 'phenix-payments' ), $brand ), $body );
		}
	}
}
