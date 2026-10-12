<?php
/**
 * Thin client for the official PayPal REST APIs used by this plugin:
 *  - OAuth 2.0 client credentials       POST /v1/oauth2/token
 *  - Orders v2                          /v2/checkout/orders
 *  - Payments v2 (captures)             /v2/payments/captures/{id}
 *  - Webhook signature verification     POST /v1/notifications/verify-webhook-signature
 *
 * Uses the WordPress HTTP API (no external SDK dependency). TLS
 * verification is always on.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_PayPal_Client {

	const HOSTS = array(
		'sandbox' => 'https://api-m.sandbox.paypal.com',
		'live'    => 'https://api-m.paypal.com',
	);

	/** @var string */
	private $env;
	/** @var string */
	private $client_id;
	/** @var string */
	private $client_secret;

	public function __construct( $env = null, $client_id = null, $client_secret = null ) {
		$this->env           = ( 'live' === $env || 'sandbox' === $env ) ? $env : Phenix_Pay_Settings::environment();
		$this->client_id     = null !== $client_id ? $client_id : Phenix_Pay_Settings::credential( 'client_id', $this->env );
		$this->client_secret = null !== $client_secret ? $client_secret : Phenix_Pay_Settings::credential( 'client_secret', $this->env );
	}

	public function environment() {
		return $this->env;
	}

	public function is_configured() {
		return '' !== $this->client_id && '' !== $this->client_secret;
	}

	private function base_url() {
		return self::HOSTS[ $this->env ];
	}

	private function token_transient() {
		return 'phenix_pay_tok_' . $this->env . '_' . substr( hash( 'sha256', $this->client_id . '|' . $this->client_secret ), 0, 16 );
	}

	/**
	 * @return string|WP_Error
	 */
	public function get_access_token( $force = false ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'phenix_pay_not_configured', __( 'PayPal API credentials are not configured.', 'phenix-payments' ) );
		}

		$key = $this->token_transient();
		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		$response = wp_remote_post(
			$this->base_url() . '/v1/oauth2/token',
			array(
				'timeout'   => 20,
				'sslverify' => true,
				'headers'   => array(
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
					'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
					'Accept'        => 'application/json',
				),
				'body'      => 'grant_type=client_credentials',
			)
		);

		if ( is_wp_error( $response ) ) {
			Phenix_Pay_Logger::error( 'PayPal OAuth request failed: ' . $response->get_error_message() );
			return new WP_Error( 'phenix_pay_http', __( 'Could not reach PayPal.', 'phenix-payments' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			$err = isset( $body['error_description'] ) ? $body['error_description'] : ( isset( $body['error'] ) ? $body['error'] : 'HTTP ' . $code );
			Phenix_Pay_Logger::error( 'PayPal OAuth rejected credentials', array( 'env' => $this->env, 'status' => $code, 'error' => $err ) );
			return new WP_Error( 'phenix_pay_auth', sprintf( /* translators: %s: error */ __( 'PayPal rejected the API credentials: %s', 'phenix-payments' ), $err ) );
		}

		$ttl = isset( $body['expires_in'] ) ? (int) $body['expires_in'] - 300 : 3000;
		set_transient( $key, $body['access_token'], max( 60, min( HOUR_IN_SECONDS, $ttl ) ) );

		return $body['access_token'];
	}

	/**
	 * Perform an authenticated API request.
	 *
	 * @param string            $method  HTTP method.
	 * @param string            $path    API path.
	 * @param array|string|null $body    Array (JSON-encoded), raw JSON string, or null.
	 * @param array             $headers Extra headers.
	 * @return array{code:int, body:array|null}|WP_Error
	 */
	public function request( $method, $path, $body = null, array $headers = array(), $retry = true ) {
		$token = $this->get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'method'    => $method,
			'timeout'   => 45,
			'sslverify' => true,
			'headers'   => array_merge(
				array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'Prefer'        => 'return=representation',
				),
				$headers
			),
		);
		if ( null !== $body ) {
			$args['body'] = is_string( $body ) ? $body : wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base_url() . $path, $args );

		if ( is_wp_error( $response ) ) {
			Phenix_Pay_Logger::error( 'PayPal API request failed', array( 'path' => $path, 'error' => $response->get_error_message() ) );
			return new WP_Error( 'phenix_pay_http', __( 'Could not reach PayPal. Please try again.', 'phenix-payments' ) );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code && $retry ) {
			delete_transient( $this->token_transient() );
			return $this->request( $method, $path, $body, $headers, false );
		}

		Phenix_Pay_Logger::debug(
			'PayPal API ' . $method . ' ' . $path,
			array(
				'status'   => $code,
				'debug_id' => wp_remote_retrieve_header( $response, 'paypal-debug-id' ),
			)
		);

		return array(
			'code'     => $code,
			'body'     => is_array( $decoded ) ? $decoded : null,
			'debug_id' => (string) wp_remote_retrieve_header( $response, 'paypal-debug-id' ),
		);
	}

	public function create_order( array $payload, $request_id ) {
		return $this->request( 'POST', '/v2/checkout/orders', $payload, array( 'PayPal-Request-Id' => $request_id ) );
	}

	public function get_order( $order_id ) {
		return $this->request( 'GET', '/v2/checkout/orders/' . rawurlencode( $order_id ) );
	}

	public function capture_order( $order_id, $request_id ) {
		return $this->request( 'POST', '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', '{}', array( 'PayPal-Request-Id' => $request_id ) );
	}

	public function get_capture( $capture_id ) {
		return $this->request( 'GET', '/v2/payments/captures/' . rawurlencode( $capture_id ) );
	}

	public function get_webhook( $webhook_id ) {
		return $this->request( 'GET', '/v1/notifications/webhooks/' . rawurlencode( $webhook_id ) );
	}

	/**
	 * Verify a webhook delivery with PayPal's verification API.
	 *
	 * The event body is embedded exactly as received (not re-encoded), as
	 * PayPal computes the signature over the original payload.
	 *
	 * @param array  $headers    Lower-cased request headers.
	 * @param string $raw_body   Raw request body.
	 * @param string $webhook_id Webhook ID configured for this environment.
	 * @return bool
	 */
	public function verify_webhook_signature( array $headers, $raw_body, $webhook_id ) {
		$required = array( 'paypal-auth-algo', 'paypal-cert-url', 'paypal-transmission-id', 'paypal-transmission-sig', 'paypal-transmission-time' );
		foreach ( $required as $h ) {
			if ( empty( $headers[ $h ] ) ) {
				return false;
			}
		}
		if ( '' === $webhook_id || null === json_decode( $raw_body ) ) {
			return false;
		}

		$envelope = '{'
			. '"auth_algo":' . wp_json_encode( $headers['paypal-auth-algo'] ) . ','
			. '"cert_url":' . wp_json_encode( $headers['paypal-cert-url'] ) . ','
			. '"transmission_id":' . wp_json_encode( $headers['paypal-transmission-id'] ) . ','
			. '"transmission_sig":' . wp_json_encode( $headers['paypal-transmission-sig'] ) . ','
			. '"transmission_time":' . wp_json_encode( $headers['paypal-transmission-time'] ) . ','
			. '"webhook_id":' . wp_json_encode( $webhook_id ) . ','
			. '"webhook_event":' . $raw_body
			. '}';

		$result = $this->request( 'POST', '/v1/notifications/verify-webhook-signature', $envelope );
		if ( is_wp_error( $result ) || 200 !== $result['code'] ) {
			return false;
		}
		return isset( $result['body']['verification_status'] ) && 'SUCCESS' === $result['body']['verification_status'];
	}

	/**
	 * Extract a readable error from a PayPal error response.
	 */
	public static function error_issue( $body ) {
		if ( isset( $body['details'][0]['issue'] ) ) {
			return (string) $body['details'][0]['issue'];
		}
		if ( isset( $body['name'] ) ) {
			return (string) $body['name'];
		}
		return 'UNKNOWN_ERROR';
	}
}
