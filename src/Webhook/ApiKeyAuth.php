<?php
/**
 * Verify the API key SePay sends with each webhook.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Webhook;

/**
 * SePay sends "Authorization: Apikey <KEY>" when the webhook is configured with
 * API key authentication. Some setups put "Bearer " in front, so both forms are
 * accepted.
 *
 * Pure PHP, unit-tested.
 */
final class ApiKeyAuth {

	/**
	 * Check the Authorization header against the configured key.
	 *
	 * @param string $expected_key Key saved in the gateway settings.
	 * @param string $header       Value of the Authorization header.
	 * @return bool
	 */
	public static function verify( $expected_key, $header ) {
		$expected_key = trim( (string) $expected_key );
		if ( '' === $expected_key ) {
			// No key configured means webhooks are refused, never accepted blindly.
			return false;
		}

		$header = trim( (string) $header );
		$header = preg_replace( '/^Bearer\s+/i', '', $header );

		if ( ! preg_match( '/^Apikey\s+(.+)$/i', (string) $header, $matches ) ) {
			return false;
		}

		// hash_equals() compares in constant time, so the key cannot be guessed by timing.
		return hash_equals( $expected_key, trim( $matches[1] ) );
	}
}
