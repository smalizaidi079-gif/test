<?php
/**
 * Public REST endpoints used by the payment form and PayPal webhooks.
 *
 *   POST /wp-json/phenix-payments/v1/orders                 create PayPal order
 *   POST /wp-json/phenix-payments/v1/orders/{id}/capture    capture + verify
 *   POST /wp-json/phenix-payments/v1/orders/{id}/cancel     buyer cancelled
 *   POST /wp-json/phenix-payments/v1/webhook                PayPal webhooks
 *
 * Browser endpoints require a valid WordPress REST nonce (X-WP-Nonce).
 * The webhook endpoint is authenticated by PayPal's signature, verified
 * through PayPal's verification API before anything is processed.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_REST_Controller {

	const NS = 'phenix-payments/v1';

	public function register_routes() {
		$order_id = '(?P<order_id>[A-Z0-9]{5,64})';

		register_rest_route(
			self::NS,
			'/orders',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_order' ),
				'permission_callback' => array( $this, 'check_nonce' ),
			)
		);
		register_rest_route(
			self::NS,
			'/orders/' . $order_id . '/capture',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'capture_order' ),
				'permission_callback' => array( $this, 'check_nonce' ),
			)
		);
		register_rest_route(
			self::NS,
			'/orders/' . $order_id . '/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_order' ),
				'permission_callback' => array( $this, 'check_nonce' ),
			)
		);
		register_rest_route(
			self::NS,
			'/webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'webhook' ),
				// Authenticated by PayPal signature verification inside the callback.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * CSRF protection for the browser endpoints.
	 */
	public function check_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'x_wp_nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'stale_session', __( 'Your session has expired. Please refresh the page and try again.', 'phenix-payments' ), array( 'status' => 403 ) );
		}
		return true;
	}

	private function service() {
		return new Phenix_Pay_Payment_Service();
	}

	public function create_order( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$result = $this->service()->create_checkout( is_array( $params ) ? $params : array() );
		return $this->respond( $result );
	}

	public function capture_order( WP_REST_Request $request ) {
		$result = $this->service()->capture( $request['order_id'] );
		return $this->respond( $result );
	}

	public function cancel_order( WP_REST_Request $request ) {
		return $this->respond( $this->service()->cancel( $request['order_id'] ) );
	}

	public function webhook( WP_REST_Request $request ) {
		$headers = array();
		foreach ( $request->get_headers() as $name => $values ) {
			$headers[ str_replace( '_', '-', strtolower( $name ) ) ] = is_array( $values ) ? (string) reset( $values ) : (string) $values;
		}
		$status = $this->service()->handle_webhook( (string) $request->get_body(), $headers );
		return new WP_REST_Response( array( 'received' => 200 === $status ), $status );
	}

	/**
	 * Convert service results to REST responses without leaking internals.
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data   = (array) $result->get_error_data();
			$status = isset( $data['status'] ) ? (int) $data['status'] : 400;
			$body   = array(
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
			if ( isset( $data['fields'] ) ) {
				$body['fields'] = $data['fields'];
			}
			if ( ! empty( $data['retry'] ) ) {
				$body['retry'] = true;
			}
			$response = new WP_REST_Response( $body, $status );
		} else {
			$response = new WP_REST_Response( $result, 200 );
		}
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
