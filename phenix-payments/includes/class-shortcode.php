<?php
/**
 * [phenix_payments] shortcode.
 *
 * Attributes:
 *   amount       Fixed amount, e.g. "250" or "250.00". When set, the client
 *                cannot change it (the amount is signed server-side).
 *   description  Default/fixed description.
 *   reference    Default/fixed invoice or project reference.
 *   title        Form heading.
 *   button_text  Text of the "continue" button.
 *
 * With no amount, the client enters the amount (if enabled in settings).
 * "?ref=INV-1001&amount=150" in the page URL can pre-fill a custom-amount
 * form (still validated server-side).
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Shortcode {

	const TAG = 'phenix_payments';

	/** @var int */
	private static $instance_count = 0;

	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style( 'phenix-payments', PHENIX_PAY_URL . 'assets/css/phenix-payments.css', array(), PHENIX_PAY_VERSION );
		wp_register_script( 'phenix-payments', PHENIX_PAY_URL . 'assets/js/phenix-payments.js', array(), PHENIX_PAY_VERSION, true );
	}

	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'amount'      => '',
				'description' => '',
				'reference'   => '',
				'title'       => __( 'Make a Payment', 'phenix-payments' ),
				'button_text' => __( 'Review payment', 'phenix-payments' ),
			),
			$atts,
			self::TAG
		);

		$currency = Phenix_Pay_Settings::currency();
		$env      = Phenix_Pay_Settings::environment();

		if ( ! Phenix_Pay_Settings::has_credentials() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="phx-pay-admin-notice">' . esc_html__( 'Phenix Payments: PayPal credentials are not configured for the active environment. Visit Settings → Phenix Payments. (Only administrators see this message.)', 'phenix-payments' ) . '</div>';
			}
			return '<div class="phx-pay-unavailable">' . esc_html__( 'Online payments are temporarily unavailable. Please contact us to arrange payment.', 'phenix-payments' ) . '</div>';
		}

		// Fixed amount?
		$fixed_minor  = null;
		$amount_token = '';
		if ( '' !== trim( (string) $atts['amount'] ) ) {
			$fixed_minor = Phenix_Pay_Money::to_minor( (string) $atts['amount'] );
			if ( null === $fixed_minor || $fixed_minor <= 0 ) {
				return current_user_can( 'manage_options' )
					? '<div class="phx-pay-admin-notice">' . esc_html__( 'Phenix Payments: the shortcode "amount" attribute is invalid. Use a value like 250 or 250.00.', 'phenix-payments' ) . '</div>'
					: '';
			}
			$amount_token = Phenix_Pay_Payment_Service::sign_fixed(
				$fixed_minor,
				$currency,
				sanitize_textarea_field( $atts['description'] ),
				sanitize_text_field( $atts['reference'] )
			);
		} elseif ( ! Phenix_Pay_Settings::get( 'allow_custom_amount' ) ) {
			return current_user_can( 'manage_options' )
				? '<div class="phx-pay-admin-notice">' . esc_html__( 'Phenix Payments: custom amounts are disabled, so this shortcode needs an amount, e.g. [phenix_payments amount="250"].', 'phenix-payments' ) . '</div>'
				: '';
		}

		// Optional URL pre-fill (custom amount forms only; never trusted).
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$prefill_ref    = isset( $_GET['ref'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['ref'] ) ), 0, 100 ) : '';
		$prefill_amount = '';
		if ( null === $fixed_minor && isset( $_GET['amount'] ) ) {
			$parsed = Phenix_Pay_Money::to_minor( sanitize_text_field( wp_unslash( $_GET['amount'] ) ) );
			if ( null !== $parsed ) {
				$prefill_amount = Phenix_Pay_Money::to_decimal( $parsed );
			}
		}
		// phpcs:enable

		$this->enqueue( $currency, $env );

		self::$instance_count++;
		$uid = 'phx-pay-' . self::$instance_count;

		$view = array(
			'uid'            => $uid,
			'title'          => $atts['title'],
			'button_text'    => $atts['button_text'],
			'currency'       => $currency,
			'symbol'         => Phenix_Pay_Money::CURRENCIES[ $currency ]['symbol'],
			'fixed_minor'    => $fixed_minor,
			'amount_token'   => $amount_token,
			'description'    => null !== $fixed_minor ? $atts['description'] : '',
			'reference'      => '' !== $atts['reference'] ? $atts['reference'] : $prefill_ref,
			'reference_lock' => null !== $fixed_minor && '' !== $atts['reference'],
			'desc_default'   => $atts['description'],
			'prefill_amount' => $prefill_amount,
			'min'            => Phenix_Pay_Money::to_decimal( (int) Phenix_Pay_Settings::get( 'min_amount' ) ),
			'max'            => Phenix_Pay_Money::to_decimal( (int) Phenix_Pay_Settings::get( 'max_amount' ) ),
			'is_sandbox'     => 'sandbox' === $env,
			'card_fields'    => (bool) Phenix_Pay_Settings::get( 'enable_card_fields' ),
			'brand'          => (string) Phenix_Pay_Settings::get( 'brand_name' ),
		);

		ob_start();
		include PHENIX_PAY_DIR . 'templates/payment-form.php';
		return ob_get_clean();
	}

	private function enqueue( $currency, $env ) {
		// Block themes and some page builders render shortcodes before
		// wp_enqueue_scripts fires, so make sure the handles exist.
		if ( ! wp_script_is( 'phenix-payments', 'registered' ) ) {
			$this->register_assets();
		}
		wp_enqueue_style( 'phenix-payments' );

		$accent = sanitize_hex_color( (string) Phenix_Pay_Settings::get( 'accent_color' ) );
		if ( $accent ) {
			wp_add_inline_style( 'phenix-payments', '.phx-pay{--phx-accent:' . $accent . ';}' );
		}

		wp_enqueue_script( 'phenix-payments' );

		static $localized = false;
		if ( $localized ) {
			return;
		}
		$localized = true;

		$disable = array();
		$enable  = array();
		if ( ! Phenix_Pay_Settings::get( 'enable_card_button' ) ) {
			$disable[] = 'card';
		}
		if ( Phenix_Pay_Settings::get( 'enable_paylater' ) ) {
			$enable[] = 'paylater';
		} else {
			$disable[] = 'paylater';
		}
		$disable[] = 'venmo';

		$components = array( 'buttons' );
		if ( Phenix_Pay_Settings::get( 'enable_card_fields' ) ) {
			$components[] = 'card-fields';
		}

		$sdk_args = array(
			'client-id'  => Phenix_Pay_Settings::credential( 'client_id' ),
			'currency'   => $currency,
			'intent'     => 'capture',
			'components' => implode( ',', $components ),
		);
		if ( $disable ) {
			$sdk_args['disable-funding'] = implode( ',', $disable );
		}
		if ( $enable ) {
			$sdk_args['enable-funding'] = implode( ',', $enable );
		}

		wp_localize_script(
			'phenix-payments',
			'PhenixPayConfig',
			array(
				'restUrl'   => esc_url_raw( rest_url( Phenix_Pay_REST_Controller::NS . '/' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				// Official PayPal JavaScript SDK, loaded from PayPal's domain.
				'sdkUrl'    => add_query_arg( array_map( 'rawurlencode', $sdk_args ), 'https://www.paypal.com/sdk/js' ),
				'currency'  => $currency,
				'locale'    => str_replace( '_', '-', get_locale() ),
				'i18n'      => array(
					'required'      => __( 'This field is required.', 'phenix-payments' ),
					'email'         => __( 'Please enter a valid email address.', 'phenix-payments' ),
					'amount'        => __( 'Please enter a valid amount.', 'phenix-payments' ),
					/* translators: 1: min, 2: max */
					'amountRange'   => __( 'Please enter an amount between %1$s and %2$s.', 'phenix-payments' ),
					'fixErrors'     => __( 'Please correct the highlighted fields.', 'phenix-payments' ),
					'sdkError'      => __( 'The secure PayPal checkout could not be loaded. Please check your connection, disable ad blockers for this page, or try again later.', 'phenix-payments' ),
					'generic'       => __( 'Something went wrong and your payment was not completed. You have not been charged. Please try again.', 'phenix-payments' ),
					'cancelled'     => __( 'Payment cancelled. You have not been charged. You can try again whenever you are ready.', 'phenix-payments' ),
					'processing'    => __( 'Processing your payment securely…', 'phenix-payments' ),
					'cardIncomplete'=> __( 'Please check your card details and try again.', 'phenix-payments' ),
					'successTitle'  => __( 'Payment successful', 'phenix-payments' ),
					'pendingTitle'  => __( 'Payment received – pending confirmation', 'phenix-payments' ),
					'failedTitle'   => __( 'Payment not completed', 'phenix-payments' ),
					'lblAmount'     => __( 'Amount', 'phenix-payments' ),
					'lblReference'  => __( 'Reference', 'phenix-payments' ),
					'lblTransaction'=> __( 'PayPal transaction ID', 'phenix-payments' ),
					'lblPaymentId'  => __( 'Payment ID', 'phenix-payments' ),
					'cardholder'    => __( 'Cardholder name', 'phenix-payments' ),
					'network'       => __( 'Network error. Please check your connection. If you completed the payment, do not pay again — contact us and we will confirm it.', 'phenix-payments' ),
				),
			)
		);
	}
}
