<?php
/**
 * Money helpers. Amounts are handled as integer minor units (cents)
 * internally to avoid floating point errors.
 *
 * @package PhenixPayments
 */

defined( 'ABSPATH' ) || exit;

class Phenix_Pay_Money {

	/**
	 * Currencies offered in settings. All use two decimal places, which keeps
	 * the conversion logic simple and correct. CAD and USD are the primary
	 * currencies for a Canadian PayPal business account.
	 */
	const CURRENCIES = array(
		'USD' => array( 'label' => 'US Dollar', 'symbol' => '$' ),
		'CAD' => array( 'label' => 'Canadian Dollar', 'symbol' => '$' ),
		'EUR' => array( 'label' => 'Euro', 'symbol' => '€' ),
		'GBP' => array( 'label' => 'Pound Sterling', 'symbol' => '£' ),
		'AUD' => array( 'label' => 'Australian Dollar', 'symbol' => '$' ),
	);

	public static function is_supported_currency( $code ) {
		return is_string( $code ) && isset( self::CURRENCIES[ $code ] );
	}

	/**
	 * Parse a user-supplied decimal string into minor units.
	 *
	 * Accepts "150", "150.5", "150.50", "1,500.00". Rejects negatives,
	 * exponents, more than two decimals and anything else.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null Minor units, or null when invalid.
	 */
	public static function to_minor( $value ) {
		if ( is_int( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = str_replace( array( ',', ' ' ), '', trim( $value ) );
		if ( ! preg_match( '/^(\d{1,9})(?:\.(\d{1,2}))?$/', $value, $m ) ) {
			return null;
		}
		$whole    = (int) $m[1];
		$fraction = isset( $m[2] ) ? str_pad( $m[2], 2, '0' ) : '00';
		return ( $whole * 100 ) + (int) $fraction;
	}

	/**
	 * Format minor units as the decimal string PayPal expects ("150.00").
	 */
	public static function to_decimal( $minor ) {
		$minor = (int) $minor;
		return sprintf( '%d.%02d', intdiv( $minor, 100 ), $minor % 100 );
	}

	/**
	 * Human-readable amount, e.g. "$150.00 USD".
	 */
	public static function format( $minor, $currency ) {
		$symbol = isset( self::CURRENCIES[ $currency ]['symbol'] ) ? self::CURRENCIES[ $currency ]['symbol'] : '';
		return $symbol . number_format_i18n( ( (int) $minor ) / 100, 2 ) . ' ' . $currency;
	}
}
