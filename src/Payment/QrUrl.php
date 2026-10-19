<?php
/**
 * VietQR image URL builder.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Payment;

/**
 * Builds the image URL of a VietQR code through the public VietQR Quick Link
 * service (img.vietqr.io). The banking app reads bank, account number, amount
 * and transfer note from the code, so the customer does not type anything.
 *
 * Pure PHP, unit-tested.
 */
final class QrUrl {

	const BASE = 'https://img.vietqr.io/image/';

	/**
	 * Layouts offered by the Quick Link service.
	 */
	const TEMPLATES = array( 'compact', 'compact2', 'qr_only', 'print' );

	/**
	 * Build the URL.
	 *
	 * @param string $bank_bin       6-digit bank BIN.
	 * @param string $account_number Account number (digits only).
	 * @param int    $amount         Amount in VND, no decimals.
	 * @param string $note           Transfer note, normally the payment code.
	 * @param string $account_name   Account holder, shown under the code.
	 * @param string $template       One of self::TEMPLATES.
	 * @return string
	 * @throws \InvalidArgumentException When bank or account is invalid.
	 */
	public static function build( $bank_bin, $account_number, $amount, $note, $account_name = '', $template = 'compact2' ) {
		$bank_bin       = preg_replace( '/\D/', '', (string) $bank_bin );
		$account_number = preg_replace( '/[^0-9A-Za-z]/', '', (string) $account_number );

		if ( 6 !== strlen( $bank_bin ) ) {
			throw new \InvalidArgumentException( 'Bank BIN must have 6 digits.' );
		}
		if ( '' === $account_number ) {
			throw new \InvalidArgumentException( 'Account number is required.' );
		}
		if ( ! in_array( $template, self::TEMPLATES, true ) ) {
			$template = 'compact2';
		}

		$query = array(
			'amount'  => max( 0, (int) $amount ),
			'addInfo' => self::clean_note( $note ),
		);
		if ( '' !== $account_name ) {
			$query['accountName'] = self::clean_name( $account_name );
		}

		return self::BASE . rawurlencode( $bank_bin . '-' . $account_number . '-' . $template ) . '.png?'
			. http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Transfer notes are limited by banks: keep letters, digits and single
	 * spaces, max 50 chars. Accents are converted ("toán" => "toan"), not dropped.
	 *
	 * @param string $note Raw note.
	 * @return string
	 */
	public static function clean_note( $note ) {
		$note = self::remove_accents( (string) $note );
		$note = (string) preg_replace( '/[^0-9A-Za-z ]/', '', $note );
		$note = (string) preg_replace( '/\s+/', ' ', $note );
		return trim( substr( trim( $note ), 0, 50 ) );
	}

	/**
	 * Account holder names are shown upper-case without accents in banking apps.
	 *
	 * @param string $name Raw name, may contain Vietnamese accents.
	 * @return string
	 */
	public static function clean_name( $name ) {
		return strtoupper( trim( self::remove_accents( (string) $name ) ) );
	}

	/**
	 * Strip Vietnamese diacritics without depending on WordPress' remove_accents().
	 *
	 * @param string $text Text with accents.
	 * @return string
	 */
	public static function remove_accents( $text ) {
		$map = array(
			'a' => 'àáạảãâầấậẩẫăằắặẳẵ',
			'e' => 'èéẹẻẽêềếệểễ',
			'i' => 'ìíịỉĩ',
			'o' => 'òóọỏõôồốộổỗơờớợởỡ',
			'u' => 'ùúụủũưừứựửữ',
			'y' => 'ỳýỵỷỹ',
			'd' => 'đ',
			'A' => 'ÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴ',
			'E' => 'ÈÉẸẺẼÊỀẾỆỂỄ',
			'I' => 'ÌÍỊỈĨ',
			'O' => 'ÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠ',
			'U' => 'ÙÚỤỦŨƯỪỨỰỬỮ',
			'Y' => 'ỲÝỴỶỸ',
			'D' => 'Đ',
		);

		$search  = array();
		$replace = array();
		foreach ( $map as $ascii => $chars ) {
			foreach ( preg_split( '//u', $chars, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
				$search[]  = $char;
				$replace[] = $ascii;
			}
		}

		return str_replace( $search, $replace, $text );
	}
}
