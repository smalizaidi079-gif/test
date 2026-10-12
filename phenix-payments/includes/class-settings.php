<?php
/**
 * Settings storage and credential handling.
 *
 * Credentials can be supplied in two ways:
 *  1. (Recommended) constants in wp-config.php, e.g.
 *     define( 'PHENIX_PAY_LIVE_CLIENT_ID', '...' );
 *     define( 'PHENIX_PAY_LIVE_CLIENT_SECRET', '...' );
 *     define( 'PHENIX_PAY_LIVE_WEBHOOK_ID', '...' );
 *  2. The settings screen. Client secrets entered there are encrypted with a
 *     key derived from this site's secret salts (which live in wp-config.php,
 *     not the database), so a database dump alone does not reveal them.
 *
 * Only REST API app credentials (Client ID / Secret) are used. The plugin
 * never asks for or stores a PayPal login or password.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Settings {

	const OPTION     = 'phenix_pay_settings';
	const ENC_PREFIX = 'enc:v1:';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		return array(
			'environment'           => 'sandbox',
			'sandbox_client_id'     => '',
			'sandbox_client_secret' => '',
			'sandbox_webhook_id'    => '',
			'live_client_id'        => '',
			'live_client_secret'    => '',
			'live_webhook_id'       => '',
			'currency'              => 'USD',
			'enable_card_button'    => 1,
			'enable_card_fields'    => 0,
			'enable_paylater'       => 0,
			'allow_custom_amount'   => 1,
			'min_amount'            => 100,     // Minor units: 1.00.
			'max_amount'            => 1000000, // Minor units: 10,000.00.
			'brand_name'            => 'The Phenix LLC',
			'accent_color'          => '#d9480f',
			'success_message'       => 'Thank you! Your payment has been received. A confirmation has been sent to your email address.',
			'notify_admin'          => 1,
			'notify_email'          => '',
			'send_receipt'          => 1,
			'debug_log'             => 0,
			'delete_on_uninstall'   => 0,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $stored ) ? $stored : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		self::$cache = null;
		$merged      = array_merge( self::all(), $values );
		update_option( self::OPTION, $merged, false );
		self::$cache = null;
	}

	public static function environment() {
		return 'live' === self::get( 'environment' ) ? 'live' : 'sandbox';
	}

	public static function is_sandbox() {
		return 'sandbox' === self::environment();
	}

	public static function currency() {
		$currency = self::get( 'currency' );
		return Phenix_Pay_Money::is_supported_currency( $currency ) ? $currency : 'USD';
	}

	/**
	 * Whether the given credential is locked by a wp-config.php constant.
	 *
	 * @param string $env   sandbox|live.
	 * @param string $field client_id|client_secret|webhook_id.
	 */
	public static function constant_name( $env, $field ) {
		return 'PHENIX_PAY_' . strtoupper( $env ) . '_' . strtoupper( $field );
	}

	public static function is_constant_defined( $env, $field ) {
		$name = self::constant_name( $env, $field );
		return defined( $name ) && '' !== (string) constant( $name );
	}

	/**
	 * Resolve a credential for an environment (constant wins over option).
	 *
	 * @param string      $field client_id|client_secret|webhook_id.
	 * @param string|null $env   Defaults to the active environment.
	 */
	public static function credential( $field, $env = null ) {
		$env = ( 'live' === $env || 'sandbox' === $env ) ? $env : self::environment();

		if ( self::is_constant_defined( $env, $field ) ) {
			return trim( (string) constant( self::constant_name( $env, $field ) ) );
		}

		$value = (string) self::get( $env . '_' . $field );
		if ( 'client_secret' === $field ) {
			$value = self::decrypt( $value );
		}
		return trim( $value );
	}

	public static function has_credentials( $env = null ) {
		return '' !== self::credential( 'client_id', $env ) && '' !== self::credential( 'client_secret', $env );
	}

	/* --------------------------------------------------------------------
	 * Secret encryption (libsodium; bundled with WordPress since 5.2).
	 * ------------------------------------------------------------------ */

	private static function key() {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . '|phenix-pay-credentials', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return self::ENC_PREFIX . base64_encode( $nonce . $cipher );
	}

	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( '' === $stored ) {
			return '';
		}
		if ( 0 !== strpos( $stored, self::ENC_PREFIX ) ) {
			return ''; // Never accept unencrypted secrets from the database.
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$raw = base64_decode( substr( $stored, strlen( self::ENC_PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		if ( false === $plain ) {
			Phenix_Pay_Logger::warning( 'A stored PayPal client secret could not be decrypted (were the WordPress salts changed?). Please re-enter it.' );
			return '';
		}
		return $plain;
	}
}
