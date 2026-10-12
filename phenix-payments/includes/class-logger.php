<?php
/**
 * Minimal logger.
 *
 * Entries are kept in a capped, non-autoloaded option so they are visible to
 * administrators in the dashboard but never written to a publicly reachable
 * file. Secrets and card-like data are redacted before anything is stored.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Logger {

	const OPTION      = 'phenix_pay_log';
	const MAX_ENTRIES = 200;

	/** Keys whose values must never be logged. */
	const SENSITIVE_KEYS = array(
		'client_secret',
		'secret',
		'access_token',
		'authorization',
		'password',
		'number',
		'cvv',
		'security_code',
		'expiry',
		'paypal-transmission-sig',
		'transmission_sig',
	);

	public static function error( $message, array $context = array() ) {
		self::write( 'error', $message, $context );
	}

	public static function warning( $message, array $context = array() ) {
		self::write( 'warning', $message, $context );
	}

	public static function info( $message, array $context = array() ) {
		self::write( 'info', $message, $context );
	}

	/**
	 * Debug entries are stored only when debug logging is enabled.
	 */
	public static function debug( $message, array $context = array() ) {
		if ( Phenix_Pay_Settings::get( 'debug_log' ) ) {
			self::write( 'debug', $message, $context );
		}
	}

	public static function entries() {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? array_reverse( $log ) : array();
	}

	public static function clear() {
		delete_option( self::OPTION );
	}

	private static function write( $level, $message, array $context ) {
		$entry = array(
			'time'    => gmdate( 'Y-m-d H:i:s' ),
			'level'   => $level,
			'message' => self::scrub_string( (string) $message ),
			'context' => self::redact( $context ),
		);

		$log = get_option( self::OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = $entry;
		if ( count( $log ) > self::MAX_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_ENTRIES );
		}
		update_option( self::OPTION, $log, false );

		if ( 'error' === $level && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[phenix-payments] ' . $entry['message'] );
		}
	}

	/**
	 * Recursively redact sensitive keys.
	 *
	 * @param mixed $data Data to redact.
	 * @return mixed
	 */
	public static function redact( $data ) {
		if ( is_object( $data ) ) {
			$data = (array) $data;
		}
		if ( ! is_array( $data ) ) {
			return is_string( $data ) ? self::scrub_string( $data ) : $data;
		}
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_string( $key ) && in_array( strtolower( $key ), self::SENSITIVE_KEYS, true ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}
			$out[ $key ] = self::redact( $value );
		}
		return $out;
	}

	/**
	 * Mask anything that looks like a bearer token or a card number.
	 */
	private static function scrub_string( $value ) {
		$value = preg_replace( '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/', 'Bearer [redacted]', $value );
		$value = preg_replace( '/\b(?:\d[ -]?){13,19}\b/', '[redacted-digits]', $value );
		return mb_substr( $value, 0, 2000 );
	}
}
