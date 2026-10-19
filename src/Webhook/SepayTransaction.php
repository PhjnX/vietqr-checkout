<?php
/**
 * Value object for one SePay webhook payload.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Webhook;

/**
 * Validated, typed view of the JSON SePay posts for each bank transaction.
 *
 * Payload reference: https://developer.sepay.vn/vi/sepay-webhooks/tich-hop-webhook
 *
 * Pure PHP, unit-tested.
 */
final class SepayTransaction {

	/**
	 * SePay transaction ID. Stays the same across retries, used for de-duplication.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Bank name, e.g. "Vietcombank".
	 *
	 * @var string
	 */
	public $gateway;

	/**
	 * Transaction time in Vietnam time, "YYYY-MM-DD HH:mm:ss".
	 *
	 * @var string
	 */
	public $transaction_date;

	/**
	 * Receiving account number.
	 *
	 * @var string
	 */
	public $account_number;

	/**
	 * Payment code detected by SePay, or null.
	 *
	 * @var string|null
	 */
	public $code;

	/**
	 * Raw transfer note.
	 *
	 * @var string
	 */
	public $content;

	/**
	 * "in" for money received, "out" for money sent.
	 *
	 * @var string
	 */
	public $transfer_type;

	/**
	 * Amount in VND, always positive.
	 *
	 * @var int
	 */
	public $amount;

	/**
	 * Bank reference code, may be empty.
	 *
	 * @var string
	 */
	public $reference_code;

	/**
	 * Original payload, stored for auditing.
	 *
	 * @var array<string, mixed>
	 */
	public $raw;

	/**
	 * Build from the decoded JSON body.
	 *
	 * @param mixed $data Decoded body.
	 * @return self
	 * @throws \InvalidArgumentException When a required field is missing or invalid.
	 */
	public static function from_array( $data ) {
		if ( ! is_array( $data ) ) {
			throw new \InvalidArgumentException( 'Payload must be a JSON object.' );
		}

		foreach ( array( 'id', 'content', 'transferType', 'transferAmount' ) as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message is a JSON API error, never printed as HTML.
				throw new \InvalidArgumentException( sprintf( 'Missing field: %s.', $field ) );
			}
		}

		if ( ! is_numeric( $data['id'] ) || (int) $data['id'] <= 0 ) {
			throw new \InvalidArgumentException( 'Invalid id.' );
		}
		if ( ! in_array( $data['transferType'], array( 'in', 'out' ), true ) ) {
			throw new \InvalidArgumentException( 'Invalid transferType.' );
		}
		if ( ! is_numeric( $data['transferAmount'] ) || (int) $data['transferAmount'] <= 0 ) {
			throw new \InvalidArgumentException( 'Invalid transferAmount.' );
		}

		$tx                   = new self();
		$tx->id               = (string) (int) $data['id'];
		$tx->gateway          = isset( $data['gateway'] ) ? (string) $data['gateway'] : '';
		$tx->transaction_date = isset( $data['transactionDate'] ) ? (string) $data['transactionDate'] : '';
		$tx->account_number   = isset( $data['accountNumber'] ) ? (string) $data['accountNumber'] : '';
		$tx->code             = isset( $data['code'] ) && '' !== $data['code'] ? (string) $data['code'] : null;
		$tx->content          = (string) $data['content'];
		$tx->transfer_type    = $data['transferType'];
		$tx->amount           = (int) $data['transferAmount'];
		$tx->reference_code   = isset( $data['referenceCode'] ) ? (string) $data['referenceCode'] : '';
		$tx->raw              = $data;

		return $tx;
	}

	/**
	 * Whether this is incoming money.
	 *
	 * @return bool
	 */
	public function is_incoming() {
		return 'in' === $this->transfer_type;
	}

	/**
	 * Text to search for the payment code: SePay's detected code first, then the note.
	 *
	 * @return string
	 */
	public function searchable_text() {
		return trim( ( null !== $this->code ? $this->code . ' ' : '' ) . $this->content );
	}
}
