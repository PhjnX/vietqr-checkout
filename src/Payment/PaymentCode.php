<?php
/**
 * Payment code: the short reference the customer types into the transfer note.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Payment;

/**
 * Builds and parses payment codes such as "DH1234".
 *
 * Banks often rewrite the transfer note: they upper-case it, drop accents and
 * punctuation, insert spaces or prepend their own text
 * ("MBVCB.123.DH1234.CT tu ..."). Parsing therefore searches for the prefix
 * followed by digits anywhere in the text instead of expecting an exact match.
 *
 * Pure PHP, no WordPress calls, so it is fully unit-tested.
 */
final class PaymentCode {

	/**
	 * Allowed prefix: 2-5 latin letters. Kept short so it survives bank rewriting.
	 */
	const PREFIX_PATTERN = '/^[A-Z]{2,5}$/';

	/**
	 * Normalise and validate a prefix coming from the settings screen.
	 *
	 * @param string $prefix Raw prefix.
	 * @return string Upper-cased prefix, or "DH" when the value is invalid.
	 */
	public static function sanitize_prefix( $prefix ) {
		$prefix = strtoupper( trim( (string) $prefix ) );
		return preg_match( self::PREFIX_PATTERN, $prefix ) ? $prefix : 'DH';
	}

	/**
	 * Build the code for an order.
	 *
	 * @param string $prefix   Prefix from the settings.
	 * @param int    $order_id WooCommerce order ID.
	 * @return string e.g. "DH1234".
	 */
	public static function build( $prefix, $order_id ) {
		return self::sanitize_prefix( $prefix ) . (int) $order_id;
	}

	/**
	 * Find the order ID inside a transfer note.
	 *
	 * @param string $prefix Prefix from the settings.
	 * @param string $text   Transfer note as received from the bank.
	 * @return int|null Order ID, or null when no code is found.
	 */
	public static function extract_order_id( $prefix, $text ) {
		$prefix = self::sanitize_prefix( $prefix );
		$text   = strtoupper( (string) $text );

		// The prefix must not be glued to letters before it ("ADH12" is not "DH12"),
		// spaces between prefix and digits are tolerated ("DH 12").
		$pattern = '/(?<![A-Z])' . preg_quote( $prefix, '/' ) . '\s?(\d{1,10})(?!\d)/';

		if ( ! preg_match( $pattern, $text, $matches ) ) {
			return null;
		}

		$order_id = (int) $matches[1];
		return $order_id > 0 ? $order_id : null;
	}
}
