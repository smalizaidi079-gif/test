<?php
/**
 * Core payment workflow:
 *   create_checkout()  -> creates a local record + a PayPal order (server-side amount)
 *   capture()          -> verifies the PayPal order, captures it once, verifies the result
 *   handle_webhook()   -> verifies the PayPal signature, then synchronises status
 *
 * A payment is only ever marked "completed" from data returned by PayPal's API
 * to this server (capture response, order/capture lookup, or a webhook whose
 * signature PayPal itself has verified). Browser callbacks are never trusted.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Payment_Service {

	const RATE_LIMIT_MAX    = 10;  // Order creations ...
	const RATE_LIMIT_WINDOW = 600; // ... per 10 minutes per client.

	/** @var Phenix_Pay_PayPal_Client */
	private $client;

	public function __construct( Phenix_Pay_PayPal_Client $client = null ) {
		$this->client = $client ? $client : new Phenix_Pay_PayPal_Client();
	}

	/* ===================================================================
	 * Amount tokens for fixed-price forms.
	 * ================================================================= */

	/**
	 * Sign a fixed amount configured in a shortcode so the browser cannot
	 * change it. Returns an opaque token embedded in the form.
	 */
	public static function sign_fixed( $amount_minor, $currency, $description, $reference ) {
		$payload = wp_json_encode(
			array(
				'a' => (int) $amount_minor,
				'c' => (string) $currency,
				'd' => (string) $description,
				'r' => (string) $reference,
			)
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );
		return $encoded . '.' . hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) . '|phenix-pay-amount' );
	}

	/**
	 * @return array|null Decoded fixed configuration, or null if tampered.
	 */
	public static function verify_fixed( $token ) {
		if ( ! is_string( $token ) || false === strpos( $token, '.' ) ) {
			return null;
		}
		list( $encoded, $mac ) = explode( '.', $token, 2 );
		$expected              = hash_hmac( 'sha256', $encoded, wp_salt( 'auth' ) . '|phenix-pay-amount' );
		if ( ! hash_equals( $expected, $mac ) ) {
			return null;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$data = json_decode( base64_decode( strtr( $encoded, '-_', '+/' ) ), true );
		if ( ! is_array( $data ) || ! isset( $data['a'], $data['c'] ) ) {
			return null;
		}
		return $data;
	}

	/* ===================================================================
	 * Checkout creation.
	 * ================================================================= */

	/**
	 * Validate the submitted form and create a PayPal order.
	 *
	 * @param array $input Raw request params.
	 * @return array|WP_Error { order_id, payment_id }
	 */
	public function create_checkout( array $input ) {
		if ( ! $this->client->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Online payments are temporarily unavailable. Please contact us.', 'phenix-payments' ), array( 'status' => 503 ) );
		}

		$data = $this->validate_checkout_input( $input );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Idempotency: the browser generates one attempt id per checkout.
		// Re-submitting the same attempt returns the same PayPal order rather
		// than creating a duplicate.
		$existing = Phenix_Pay_Payments_Repository::find_by_public_id( $data['public_id'] );
		if ( $existing ) {
			$same = (int) $existing->amount_minor === $data['amount_minor']
				&& $existing->currency === $data['currency']
				&& strtolower( $existing->customer_email ) === strtolower( $data['customer_email'] );
			if ( $same && 'created' === $existing->status && ! empty( $existing->paypal_order_id ) ) {
				return array(
					'order_id'   => $existing->paypal_order_id,
					'payment_id' => $existing->public_id,
				);
			}
			return new WP_Error( 'duplicate_attempt', __( 'This payment was already started. Please refresh the page to begin a new payment.', 'phenix-payments' ), array( 'status' => 409 ) );
		}

		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 'rate_limited', __( 'Too many payment attempts. Please wait a few minutes and try again.', 'phenix-payments' ), array( 'status' => 429 ) );
		}

		$data['environment'] = $this->client->environment();
		$payment_id          = Phenix_Pay_Payments_Repository::insert( $data );
		if ( ! $payment_id ) {
			Phenix_Pay_Logger::error( 'Could not save payment record before creating PayPal order.' );
			return new WP_Error( 'db_error', __( 'We could not start your payment. Please try again.', 'phenix-payments' ), array( 'status' => 500 ) );
		}

		$payload = $this->build_order_payload( $data );
		$result  = $this->client->create_order( $payload, 'order-' . $data['public_id'] );

		if ( is_wp_error( $result ) || ! in_array( $result['code'], array( 200, 201 ), true ) || empty( $result['body']['id'] ) ) {
			$issue = is_wp_error( $result ) ? $result->get_error_code() : Phenix_Pay_PayPal_Client::error_issue( $result['body'] );
			Phenix_Pay_Logger::error(
				'PayPal order creation failed',
				array(
					'payment'  => $data['public_id'],
					'issue'    => $issue,
					'debug_id' => is_wp_error( $result ) ? '' : $result['debug_id'],
				)
			);
			Phenix_Pay_Payments_Repository::update( $payment_id, array( 'status' => 'failed', 'status_detail' => 'ORDER_CREATE_FAILED: ' . $issue ) );
			return new WP_Error( 'order_failed', __( 'PayPal could not start this payment. Please try again or contact us.', 'phenix-payments' ), array( 'status' => 502 ) );
		}

		$order_id = sanitize_text_field( $result['body']['id'] );
		Phenix_Pay_Payments_Repository::update( $payment_id, array( 'paypal_order_id' => $order_id ) );

		Phenix_Pay_Logger::info( 'Checkout started', array( 'payment' => $data['public_id'], 'order' => $order_id ) );

		return array(
			'order_id'   => $order_id,
			'payment_id' => $data['public_id'],
		);
	}

	/**
	 * @return array|WP_Error Normalised record data.
	 */
	private function validate_checkout_input( array $input ) {
		$errors = array();

		$name = isset( $input['name'] ) ? sanitize_text_field( wp_unslash( (string) $input['name'] ) ) : '';
		if ( '' === $name || mb_strlen( $name ) > 100 ) {
			$errors['name'] = __( 'Please enter your full name (up to 100 characters).', 'phenix-payments' );
		}

		$email = isset( $input['email'] ) ? sanitize_email( wp_unslash( (string) $input['email'] ) ) : '';
		if ( '' === $email || ! is_email( $email ) || strlen( $email ) > 191 ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'phenix-payments' );
		}

		$reference   = isset( $input['reference'] ) ? sanitize_text_field( wp_unslash( (string) $input['reference'] ) ) : '';
		$description = isset( $input['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $input['description'] ) ) : '';
		$currency    = Phenix_Pay_Settings::currency();

		// Amount: either a signed fixed amount, or a client-entered amount
		// validated against the configured limits.
		$token = isset( $input['amount_token'] ) ? (string) $input['amount_token'] : '';
		if ( '' !== $token ) {
			$fixed = self::verify_fixed( $token );
			if ( null === $fixed || $fixed['c'] !== $currency ) {
				Phenix_Pay_Logger::warning( 'Rejected checkout with an invalid fixed-amount token.' );
				return new WP_Error( 'invalid_amount', __( 'This payment form is out of date. Please refresh the page and try again.', 'phenix-payments' ), array( 'status' => 400 ) );
			}
			$amount_minor = (int) $fixed['a'];
			if ( '' !== $fixed['d'] ) {
				$description = $fixed['d'];
			}
			if ( '' !== $fixed['r'] ) {
				$reference = $fixed['r'];
			}
		} else {
			if ( ! Phenix_Pay_Settings::get( 'allow_custom_amount' ) ) {
				return new WP_Error( 'invalid_amount', __( 'Custom amounts are not accepted. Please refresh the page.', 'phenix-payments' ), array( 'status' => 400 ) );
			}
			$amount_minor = Phenix_Pay_Money::to_minor( isset( $input['amount'] ) ? (string) $input['amount'] : '' );
			$min          = (int) Phenix_Pay_Settings::get( 'min_amount' );
			$max          = (int) Phenix_Pay_Settings::get( 'max_amount' );
			if ( null === $amount_minor || $amount_minor < $min || $amount_minor > $max ) {
				$errors['amount'] = sprintf(
					/* translators: 1: min amount, 2: max amount */
					__( 'Please enter an amount between %1$s and %2$s.', 'phenix-payments' ),
					Phenix_Pay_Money::format( $min, $currency ),
					Phenix_Pay_Money::format( $max, $currency )
				);
			}
		}

		if ( empty( $errors['amount'] ) && ( $amount_minor <= 0 || $amount_minor > 99999999 ) ) {
			$errors['amount'] = __( 'The payment amount is not valid.', 'phenix-payments' );
		}

		$reference   = mb_substr( $reference, 0, 100 );
		$description = mb_substr( $description, 0, 255 );

		$attempt = isset( $input['attempt_id'] ) ? strtolower( (string) $input['attempt_id'] ) : '';
		if ( ! wp_is_uuid( $attempt, 4 ) ) {
			return new WP_Error( 'invalid_request', __( 'Invalid request. Please refresh the page and try again.', 'phenix-payments' ), array( 'status' => 400 ) );
		}

		$funding = isset( $input['funding'] ) ? (string) $input['funding'] : 'paypal';
		if ( ! in_array( $funding, array( 'paypal', 'card', 'card_fields', 'paylater' ), true ) ) {
			$funding = 'paypal';
		}
		if ( 'card_fields' === $funding && ! Phenix_Pay_Settings::get( 'enable_card_fields' ) ) {
			return new WP_Error( 'invalid_request', __( 'Card payments are not available.', 'phenix-payments' ), array( 'status' => 400 ) );
		}

		if ( $errors ) {
			return new WP_Error( 'validation', __( 'Please correct the highlighted fields.', 'phenix-payments' ), array( 'status' => 422, 'fields' => $errors ) );
		}

		return array(
			'public_id'      => $attempt,
			'customer_name'  => $name,
			'customer_email' => $email,
			'reference'      => $reference,
			'description'    => $description,
			'amount_minor'   => $amount_minor,
			'currency'       => $currency,
			'funding'        => $funding,
		);
	}

	private function build_order_payload( array $data ) {
		$brand = mb_substr( (string) Phenix_Pay_Settings::get( 'brand_name' ), 0, 127 );

		$summary = '' !== $data['reference'] ? sprintf( 'Ref %s', $data['reference'] ) : '';
		if ( '' !== $data['description'] ) {
			$summary = trim( $summary . ( $summary ? ' - ' : '' ) . $data['description'] );
		}
		if ( '' === $summary ) {
			$summary = sprintf( 'Payment to %s', $brand );
		}

		$unit = array(
			'reference_id' => $data['public_id'],
			'custom_id'    => $data['public_id'],
			// Unique per PayPal account; PayPal rejects a second order with
			// the same invoice_id, adding a server-side duplicate guard.
			'invoice_id'   => 'PHX-' . $data['public_id'],
			'description'  => mb_substr( $summary, 0, 127 ),
			'amount'       => array(
				'currency_code' => $data['currency'],
				'value'         => Phenix_Pay_Money::to_decimal( $data['amount_minor'] ),
			),
		);

		$payload = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array( $unit ),
		);

		if ( 'card_fields' === $data['funding'] ) {
			// Advanced card fields: ask for 3D Secure when the issuer requires it.
			$payload['payment_source'] = array(
				'card' => array(
					'attributes' => array(
						'verification' => array( 'method' => 'SCA_WHEN_REQUIRED' ),
					),
				),
			);
		} else {
			// Services: no shipping address is collected.
			$payload['application_context'] = array(
				'brand_name'          => $brand,
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'PAY_NOW',
			);
		}

		/**
		 * Filter the PayPal order payload before it is sent.
		 * The amount must not be changed through this filter.
		 */
		return apply_filters( 'phenix_pay_order_payload', $payload, $data );
	}

	/* ===================================================================
	 * Capture.
	 * ================================================================= */

	/**
	 * Capture an approved order exactly once and verify the result.
	 *
	 * @param string $order_id PayPal order id.
	 * @return array|WP_Error Public payment summary.
	 */
	public function capture( $order_id ) {
		$order_id = (string) $order_id;
		if ( ! preg_match( '/^[A-Z0-9]{5,64}$/', $order_id ) ) {
			return new WP_Error( 'invalid_request', __( 'Invalid payment reference.', 'phenix-payments' ), array( 'status' => 400 ) );
		}

		$record = Phenix_Pay_Payments_Repository::find_by_order_id( $order_id );
		if ( ! $record ) {
			Phenix_Pay_Logger::warning( 'Capture requested for an unknown order.', array( 'order' => $order_id ) );
			return new WP_Error( 'not_found', __( 'We could not find this payment. Please contact us.', 'phenix-payments' ), array( 'status' => 404 ) );
		}

		if ( $record->environment !== $this->client->environment() ) {
			return new WP_Error( 'env_mismatch', __( 'This payment was started in a different mode. Please refresh and try again.', 'phenix-payments' ), array( 'status' => 409 ) );
		}

		// Only one request may perform the capture.
		if ( ! Phenix_Pay_Payments_Repository::transition( $record->id, Phenix_Pay_Payments_Repository::CAPTURABLE, 'capturing' ) ) {
			$record = Phenix_Pay_Payments_Repository::find( $record->id );
			return $this->public_summary( $record );
		}

		// 1) Verify the order with PayPal before capturing.
		$lookup = $this->client->get_order( $order_id );
		if ( is_wp_error( $lookup ) || 200 !== $lookup['code'] ) {
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'created' ) );
			return new WP_Error( 'paypal_unavailable', __( 'We could not confirm your payment with PayPal. Please try again.', 'phenix-payments' ), array( 'status' => 502 ) );
		}
		$order = $lookup['body'];

		if ( ! $this->order_matches_record( $order, $record ) ) {
			Phenix_Pay_Logger::error( 'Order does not match local record; refusing to capture.', array( 'payment' => $record->public_id, 'order' => $order_id ) );
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'failed', 'status_detail' => 'ORDER_MISMATCH' ) );
			return new WP_Error( 'mismatch', __( 'This payment could not be verified. You have not been charged. Please contact us.', 'phenix-payments' ), array( 'status' => 409 ) );
		}

		$order_status = isset( $order['status'] ) ? $order['status'] : '';
		if ( 'COMPLETED' === $order_status ) {
			$record = $this->apply_order( $record, $order );
			return $this->public_summary( $record );
		}
		if ( 'APPROVED' !== $order_status ) {
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'created', 'status_detail' => 'ORDER_' . sanitize_key( $order_status ) ) );
			return new WP_Error( 'not_approved', __( 'The payment has not been approved yet. Please complete the PayPal step and try again.', 'phenix-payments' ), array( 'status' => 409 ) );
		}

		// 2) 3D Secure result for advanced card payments.
		if ( ! $this->three_ds_acceptable( $order ) ) {
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'declined', 'status_detail' => '3DS_AUTHENTICATION_FAILED' ) );
			return new WP_Error( 'card_auth_failed', __( 'Your card could not be authenticated. Please try again or use another card. You have not been charged.', 'phenix-payments' ), array( 'status' => 402, 'retry' => true ) );
		}

		// 3) Capture (idempotent on PayPal's side via PayPal-Request-Id).
		$result = $this->client->capture_order( $order_id, 'capture-' . $record->public_id );
		if ( is_wp_error( $result ) ) {
			// Network failure: outcome unknown. Look the order up again.
			return $this->resync_after_uncertain_capture( $record, $order_id );
		}

		if ( in_array( $result['code'], array( 200, 201 ), true ) && isset( $result['body']['status'] ) ) {
			$record = $this->apply_order( $record, $result['body'] );
			return $this->public_summary( $record );
		}

		$issue = Phenix_Pay_PayPal_Client::error_issue( $result['body'] );
		Phenix_Pay_Logger::warning( 'Capture rejected by PayPal', array( 'payment' => $record->public_id, 'issue' => $issue, 'debug_id' => $result['debug_id'] ) );

		if ( 'ORDER_ALREADY_CAPTURED' === $issue || 'DUPLICATE_INVOICE_ID' === $issue ) {
			return $this->resync_after_uncertain_capture( $record, $order_id );
		}

		if ( in_array( $issue, array( 'INSTRUMENT_DECLINED', 'PAYER_ACTION_REQUIRED', 'TRANSACTION_REFUSED', 'PAYER_CANNOT_PAY', 'CARD_EXPIRED', 'PAYEE_BLOCKED_TRANSACTION' ), true ) ) {
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'declined', 'status_detail' => $issue ) );
			return new WP_Error(
				'declined',
				__( 'Your payment was declined. You have not been charged. Please try another card or payment method.', 'phenix-payments' ),
				array( 'status' => 402, 'retry' => 'INSTRUMENT_DECLINED' === $issue, 'issue' => $issue )
			);
		}

		Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'failed', 'status_detail' => 'CAPTURE_FAILED: ' . $issue ) );
		return new WP_Error( 'capture_failed', __( 'Your payment could not be completed. You have not been charged. Please try again or contact us.', 'phenix-payments' ), array( 'status' => 502 ) );
	}

	private function resync_after_uncertain_capture( $record, $order_id ) {
		$lookup = $this->client->get_order( $order_id );
		if ( ! is_wp_error( $lookup ) && 200 === $lookup['code'] && $this->order_matches_record( $lookup['body'], $record ) ) {
			$record = $this->apply_order( $record, $lookup['body'] );
			if ( 'capturing' !== $record->status ) {
				return $this->public_summary( $record );
			}
		}
		// Leave the record as "capturing"; the webhook or an admin refresh
		// will settle it. Tell the client the truth: status unknown.
		Phenix_Pay_Payments_Repository::update( $record->id, array( 'status_detail' => 'CAPTURE_OUTCOME_UNKNOWN' ) );
		return array(
			'status'     => 'processing',
			'payment_id' => $record->public_id,
			'message'    => __( 'Your payment is being processed. We will email you once PayPal confirms it. Please do not pay again.', 'phenix-payments' ),
		);
	}

	/**
	 * Ensure an order returned by PayPal belongs to this record and has the
	 * expected amount and currency.
	 */
	private function order_matches_record( $order, $record ) {
		if ( ! is_array( $order ) || empty( $order['purchase_units'][0] ) ) {
			return false;
		}
		$unit = $order['purchase_units'][0];
		if ( isset( $order['id'] ) && $order['id'] !== $record->paypal_order_id ) {
			return false;
		}
		$custom = isset( $unit['custom_id'] ) ? $unit['custom_id'] : ( isset( $unit['reference_id'] ) ? $unit['reference_id'] : '' );
		if ( $custom !== $record->public_id ) {
			return false;
		}
		return $this->amount_matches( isset( $unit['amount'] ) ? $unit['amount'] : null, $record );
	}

	private function amount_matches( $amount, $record ) {
		if ( ! is_array( $amount ) || ! isset( $amount['currency_code'], $amount['value'] ) ) {
			return false;
		}
		return $amount['currency_code'] === $record->currency
			&& Phenix_Pay_Money::to_minor( (string) $amount['value'] ) === (int) $record->amount_minor;
	}

	/**
	 * Decide whether to capture a card-fields order given its 3D Secure
	 * result. Follows PayPal's recommended handling: proceed when liability
	 * shifted or authentication was not applicable; reject failed/rejected
	 * authentication and unknown outcomes.
	 */
	private function three_ds_acceptable( array $order ) {
		if ( empty( $order['payment_source']['card'] ) ) {
			return true; // Not an advanced card payment.
		}
		$auth = isset( $order['payment_source']['card']['authentication_result'] ) ? $order['payment_source']['card']['authentication_result'] : null;
		if ( ! $auth ) {
			return true; // No 3DS performed (not required by issuer/region).
		}
		$liability = isset( $auth['liability_shift'] ) ? $auth['liability_shift'] : '';
		$enroll    = isset( $auth['three_d_secure']['enrollment_status'] ) ? $auth['three_d_secure']['enrollment_status'] : '';
		$status    = isset( $auth['three_d_secure']['authentication_status'] ) ? $auth['three_d_secure']['authentication_status'] : '';

		if ( in_array( $liability, array( 'POSSIBLE', 'YES' ), true ) ) {
			$ok = true;
		} elseif ( 'NO' === $liability ) {
			// Card not enrolled / unavailable / bypassed: no authentication failure.
			$ok = in_array( $enroll, array( 'N', 'U', 'B' ), true ) && '' === $status;
		} else {
			$ok = false; // UNKNOWN or missing.
		}

		return (bool) apply_filters( 'phenix_pay_three_ds_acceptable', $ok, $auth, $order );
	}

	/**
	 * Apply an order representation returned by PayPal to the local record.
	 */
	public function apply_order( $record, array $order ) {
		if ( ! $this->order_matches_record( $order, $record ) ) {
			Phenix_Pay_Logger::error( 'Order/record mismatch while applying status.', array( 'payment' => $record->public_id ) );
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'failed', 'status_detail' => 'ORDER_MISMATCH' ) );
			return Phenix_Pay_Payments_Repository::find( $record->id );
		}

		$method = $this->describe_payment_source( isset( $order['payment_source'] ) ? $order['payment_source'] : array() );
		$unit   = $order['purchase_units'][0];

		if ( ! empty( $unit['payments']['captures'][0] ) ) {
			return $this->apply_capture( $record, $unit['payments']['captures'][0], $method );
		}

		if ( $method ) {
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'payment_method' => $method ) );
		}
		if ( 'capturing' === $record->status ) {
			Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'created' ) );
		}
		return Phenix_Pay_Payments_Repository::find( $record->id );
	}

	/**
	 * Apply a capture object returned by PayPal to the local record.
	 */
	public function apply_capture( $record, array $capture, $method = '' ) {
		$capture_id = isset( $capture['id'] ) ? sanitize_text_field( $capture['id'] ) : '';
		$status     = isset( $capture['status'] ) ? $capture['status'] : '';

		if ( isset( $capture['custom_id'] ) && $capture['custom_id'] !== $record->public_id ) {
			Phenix_Pay_Logger::error( 'Capture custom_id does not match record.', array( 'payment' => $record->public_id, 'capture' => $capture_id ) );
			return $record;
		}
		if ( ! $this->amount_matches( isset( $capture['amount'] ) ? $capture['amount'] : null, $record ) ) {
			Phenix_Pay_Logger::error( 'Captured amount/currency does not match record.', array( 'payment' => $record->public_id, 'capture' => $capture_id ) );
			Phenix_Pay_Payments_Repository::update(
				$record->id,
				array(
					'status'            => 'failed',
					'paypal_capture_id' => $capture_id,
					'status_detail'     => 'AMOUNT_MISMATCH - review in PayPal',
				)
			);
			return Phenix_Pay_Payments_Repository::find( $record->id );
		}

		$map = array(
			'COMPLETED'          => 'completed',
			'PENDING'            => 'pending',
			'DECLINED'           => 'declined',
			'FAILED'             => 'declined',
			'REFUNDED'           => 'refunded',
			'PARTIALLY_REFUNDED' => 'partially_refunded',
			'REVERSED'           => 'reversed',
		);
		$new_status = isset( $map[ $status ] ) ? $map[ $status ] : 'pending';

		$fields = array(
			'status'            => $new_status,
			'paypal_capture_id' => $capture_id,
			'status_detail'     => isset( $capture['status_details']['reason'] ) ? sanitize_text_field( $capture['status_details']['reason'] ) : '',
		);
		if ( $method ) {
			$fields['payment_method'] = $method;
		}
		if ( 'completed' === $new_status && empty( $record->completed_at ) ) {
			$fields['completed_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		if ( 'refunded' === $new_status ) {
			$fields['refunded_minor'] = (int) $record->amount_minor;
		}

		Phenix_Pay_Payments_Repository::update( $record->id, $fields );
		$record = Phenix_Pay_Payments_Repository::find( $record->id );

		if ( 'completed' === $new_status ) {
			Phenix_Pay_Notifications::payment_completed( $record );
		}

		/** Fires after a payment's status is synchronised from PayPal. */
		do_action( 'phenix_pay_status_updated', $record, $new_status );

		return $record;
	}

	private function describe_payment_source( $source ) {
		if ( ! is_array( $source ) || empty( $source ) ) {
			return '';
		}
		if ( isset( $source['card'] ) ) {
			$card  = $source['card'];
			$label = 'Card';
			if ( ! empty( $card['brand'] ) ) {
				$label .= ' - ' . sanitize_text_field( $card['brand'] );
			}
			if ( ! empty( $card['type'] ) && 'UNKNOWN' !== $card['type'] ) {
				$label .= ' (' . strtolower( sanitize_text_field( $card['type'] ) ) . ')';
			}
			return mb_substr( $label, 0, 60 );
		}
		if ( isset( $source['paypal'] ) ) {
			return 'PayPal';
		}
		$keys = array_keys( $source );
		return mb_substr( ucwords( str_replace( '_', ' ', sanitize_key( $keys[0] ) ) ), 0, 60 );
	}

	/* ===================================================================
	 * Cancellation (buyer closed the PayPal window).
	 * ================================================================= */

	public function cancel( $order_id ) {
		$record = Phenix_Pay_Payments_Repository::find_by_order_id( (string) $order_id );
		if ( $record ) {
			// Only an untouched order can be labelled cancelled; this can never
			// downgrade a paid or processing payment.
			Phenix_Pay_Payments_Repository::transition( $record->id, array( 'created' ), 'cancelled' );
		}
		return array( 'status' => 'cancelled' );
	}

	/* ===================================================================
	 * Admin refresh.
	 * ================================================================= */

	/**
	 * Re-read a payment's status from PayPal (admin action).
	 *
	 * @return object|WP_Error Updated record.
	 */
	public function refresh( $record ) {
		$client = new Phenix_Pay_PayPal_Client( $record->environment );
		if ( ! $client->is_configured() ) {
			return new WP_Error( 'not_configured', __( 'Credentials for this environment are not configured.', 'phenix-payments' ) );
		}
		if ( ! empty( $record->paypal_capture_id ) ) {
			$res = $client->get_capture( $record->paypal_capture_id );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			if ( 200 === $res['code'] ) {
				return $this->apply_capture( $record, $res['body'] );
			}
		}
		if ( ! empty( $record->paypal_order_id ) ) {
			$res = $client->get_order( $record->paypal_order_id );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			if ( 200 === $res['code'] ) {
				return $this->apply_order( $record, $res['body'] );
			}
			if ( 404 === $res['code'] && in_array( $record->status, array( 'created', 'cancelled' ), true ) ) {
				// Unpaid PayPal orders expire; nothing was charged.
				Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'cancelled', 'status_detail' => 'ORDER_EXPIRED' ) );
				return Phenix_Pay_Payments_Repository::find( $record->id );
			}
		}
		return new WP_Error( 'not_found', __( 'PayPal has no record of this payment.', 'phenix-payments' ) );
	}

	/* ===================================================================
	 * Webhooks.
	 * ================================================================= */

	/**
	 * @param string $raw_body Raw JSON body.
	 * @param array  $headers  Lower-cased headers.
	 * @return int HTTP status to return to PayPal.
	 */
	public function handle_webhook( $raw_body, array $headers ) {
		$webhook_id = Phenix_Pay_Settings::credential( 'webhook_id' );
		if ( '' === $webhook_id ) {
			Phenix_Pay_Logger::warning( 'Webhook received but no Webhook ID is configured for the active environment; ignoring.' );
			return 503;
		}

		if ( ! $this->client->verify_webhook_signature( $headers, $raw_body, $webhook_id ) ) {
			Phenix_Pay_Logger::warning( 'Webhook signature verification FAILED; event ignored.', array( 'transmission_id' => isset( $headers['paypal-transmission-id'] ) ? $headers['paypal-transmission-id'] : '' ) );
			return 400;
		}

		$event = json_decode( $raw_body, true );
		if ( ! is_array( $event ) || empty( $event['id'] ) || empty( $event['event_type'] ) ) {
			return 400;
		}

		$event_id   = sanitize_text_field( $event['id'] );
		$event_type = sanitize_text_field( $event['event_type'] );

		if ( ! Phenix_Pay_Payments_Repository::claim_event( $event_id, $event_type ) ) {
			return 200; // Already processed.
		}

		try {
			$payment_id = $this->dispatch_event( $event_type, isset( $event['resource'] ) ? (array) $event['resource'] : array() );
		} catch ( Exception $e ) {
			$payment_id = new WP_Error( 'exception', $e->getMessage() );
		}

		if ( is_wp_error( $payment_id ) ) {
			// Let PayPal retry later.
			Phenix_Pay_Payments_Repository::release_event( $event_id );
			Phenix_Pay_Logger::error( 'Webhook processing failed; PayPal will retry.', array( 'event' => $event_type, 'error' => $payment_id->get_error_message() ) );
			return 500;
		}

		if ( $payment_id ) {
			Phenix_Pay_Payments_Repository::link_event( $event_id, $payment_id );
		}
		Phenix_Pay_Logger::info( 'Webhook processed', array( 'event' => $event_type, 'payment_id' => $payment_id ) );
		return 200;
	}

	/**
	 * @return int|WP_Error|null Local payment id when matched.
	 */
	private function dispatch_event( $type, array $resource ) {
		switch ( $type ) {
			case 'CHECKOUT.ORDER.APPROVED':
				// Buyer approved but our capture call may never have happened
				// (e.g. browser closed). Capture server-side; safe to repeat.
				$record = isset( $resource['id'] ) ? Phenix_Pay_Payments_Repository::find_by_order_id( $resource['id'] ) : null;
				if ( ! $record ) {
					return null;
				}
				if ( in_array( $record->status, array( 'created', 'cancelled' ), true ) ) {
					$result = $this->capture( $record->paypal_order_id );
					if ( is_wp_error( $result ) && 502 === ( isset( $result->get_error_data()['status'] ) ? $result->get_error_data()['status'] : 0 ) ) {
						return $result;
					}
				}
				return (int) $record->id;

			case 'CHECKOUT.ORDER.COMPLETED':
				$record = isset( $resource['id'] ) ? Phenix_Pay_Payments_Repository::find_by_order_id( $resource['id'] ) : null;
				if ( ! $record ) {
					return null;
				}
				$res = $this->client->get_order( $record->paypal_order_id );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				if ( 200 === $res['code'] ) {
					$this->apply_order( $record, $res['body'] );
				}
				return (int) $record->id;

			case 'PAYMENT.CAPTURE.COMPLETED':
			case 'PAYMENT.CAPTURE.PENDING':
			case 'PAYMENT.CAPTURE.DENIED':
			case 'PAYMENT.CAPTURE.DECLINED':
			case 'PAYMENT.CAPTURE.REVERSED':
				$record = $this->record_for_capture( $resource );
				if ( ! $record ) {
					return null;
				}
				$capture_id = isset( $resource['id'] ) ? $resource['id'] : '';
				// Re-read the capture from the API for authoritative state.
				$res = $this->client->get_capture( $capture_id );
				if ( is_wp_error( $res ) ) {
					return $res;
				}
				if ( 200 === $res['code'] ) {
					$this->apply_capture( $record, $res['body'] );
				}
				if ( 'PAYMENT.CAPTURE.REVERSED' === $type ) {
					Phenix_Pay_Payments_Repository::update( $record->id, array( 'status' => 'reversed', 'status_detail' => 'REVERSED (chargeback/dispute or reversal)' ) );
				}
				return (int) $record->id;

			case 'PAYMENT.CAPTURE.REFUNDED':
				return $this->handle_refund_event( $resource );
		}
		return null; // Event type not used by this plugin.
	}

	private function record_for_capture( array $capture ) {
		if ( ! empty( $capture['id'] ) ) {
			$record = Phenix_Pay_Payments_Repository::find_by_capture_id( $capture['id'] );
			if ( $record ) {
				return $record;
			}
		}
		if ( ! empty( $capture['supplementary_data']['related_ids']['order_id'] ) ) {
			$record = Phenix_Pay_Payments_Repository::find_by_order_id( $capture['supplementary_data']['related_ids']['order_id'] );
			if ( $record ) {
				return $record;
			}
		}
		if ( ! empty( $capture['custom_id'] ) && wp_is_uuid( $capture['custom_id'], 4 ) ) {
			return Phenix_Pay_Payments_Repository::find_by_public_id( $capture['custom_id'] );
		}
		return null;
	}

	/**
	 * The resource of PAYMENT.CAPTURE.REFUNDED is a refund; its "up" link
	 * points at the refunded capture.
	 */
	private function handle_refund_event( array $refund ) {
		$capture_id = '';
		foreach ( isset( $refund['links'] ) ? (array) $refund['links'] : array() as $link ) {
			if ( isset( $link['rel'], $link['href'] ) && 'up' === $link['rel'] && preg_match( '#/captures/([A-Z0-9]+)$#', $link['href'], $m ) ) {
				$capture_id = $m[1];
			}
		}
		$record = $capture_id ? Phenix_Pay_Payments_Repository::find_by_capture_id( $capture_id ) : null;
		if ( ! $record && ! empty( $refund['custom_id'] ) && wp_is_uuid( $refund['custom_id'], 4 ) ) {
			$record = Phenix_Pay_Payments_Repository::find_by_public_id( $refund['custom_id'] );
		}
		if ( ! $record ) {
			return null;
		}

		$refunded = 0;
		if ( isset( $refund['amount']['value'], $refund['amount']['currency_code'] ) && $refund['amount']['currency_code'] === $record->currency ) {
			$refunded = (int) Phenix_Pay_Money::to_minor( (string) $refund['amount']['value'] );
		}
		// Events are de-duplicated, so each refund is counted once.
		$total  = min( (int) $record->amount_minor, (int) $record->refunded_minor + $refunded );
		$status = $total >= (int) $record->amount_minor ? 'refunded' : 'partially_refunded';

		Phenix_Pay_Payments_Repository::update(
			$record->id,
			array(
				'status'         => $status,
				'refunded_minor' => $total,
				'status_detail'  => sprintf( 'Refunded %s', Phenix_Pay_Money::to_decimal( $total ) ),
			)
		);
		do_action( 'phenix_pay_status_updated', Phenix_Pay_Payments_Repository::find( $record->id ), $status );
		return (int) $record->id;
	}

	/* ===================================================================
	 * Helpers.
	 * ================================================================= */

	/**
	 * What the browser is allowed to know about a payment.
	 */
	public function public_summary( $record ) {
		$messages = array(
			'completed' => (string) Phenix_Pay_Settings::get( 'success_message' ),
			'pending'   => __( 'Your payment was received and is pending review by PayPal. We will confirm by email once it clears. Please do not pay again.', 'phenix-payments' ),
			'capturing' => __( 'Your payment is being processed. We will email you once PayPal confirms it. Please do not pay again.', 'phenix-payments' ),
			'declined'  => __( 'Your payment was declined. You have not been charged.', 'phenix-payments' ),
			'failed'    => __( 'Your payment could not be completed. You have not been charged. Please contact us.', 'phenix-payments' ),
		);
		$status   = $record->status;
		$public   = in_array( $status, array( 'completed', 'refunded', 'partially_refunded', 'reversed' ), true ) ? 'completed' : $status;
		if ( 'capturing' === $status ) {
			$public = 'processing';
		}
		return array(
			'status'         => $public,
			'payment_id'     => $record->public_id,
			'transaction_id' => in_array( $public, array( 'completed', 'pending' ), true ) ? (string) $record->paypal_capture_id : '',
			'amount'         => Phenix_Pay_Money::format( $record->amount_minor, $record->currency ),
			'reference'      => $record->reference,
			'message'        => isset( $messages[ $status ] ) ? $messages[ $status ] : $messages['completed'],
		);
	}

	private function check_rate_limit() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		/** Filter the client identifier used for rate limiting (e.g. behind a trusted proxy). */
		$ip  = (string) apply_filters( 'phenix_pay_client_ip', $ip );
		$key = 'phenix_pay_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 20 );

		$hits = (int) get_transient( $key );
		if ( $hits >= self::RATE_LIMIT_MAX ) {
			return false;
		}
		set_transient( $key, $hits + 1, self::RATE_LIMIT_WINDOW );
		return true;
	}
}
