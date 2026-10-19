<?php
/**
 * Apply a bank transaction to an order.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

use VietQRCheckout\Payment\AmountCheck;
use VietQRCheckout\Payment\PaymentCode;
use VietQRCheckout\Webhook\SepayTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * The core business rule of the plugin, shared by the real webhook and the
 * test-mode simulator so both follow exactly the same path.
 *
 * Flow:
 * 1. Record the transaction (duplicates stop here).
 * 2. Ignore outgoing money.
 * 3. Find the payment code in the note, load the order.
 * 4. Add the amount to what the order already received.
 * 5. Paid in full: payment_complete(). Otherwise: leave a note.
 */
final class PaymentProcessor {

	const DUPLICATE       = 'duplicate';
	const OUTGOING        = 'outgoing';
	const NO_CODE         = 'no_code';
	const ORDER_NOT_FOUND = 'order_not_found';
	const NOT_AWAITING    = 'not_awaiting';
	const UNDERPAID       = 'underpaid';
	const PAID            = 'paid';
	const OVERPAID        = 'overpaid';
	const DB_ERROR        = 'db_error';

	const META_CODE     = '_vqrc_payment_code';
	const META_RECEIVED = '_vqrc_received';

	/**
	 * Handle one transaction.
	 *
	 * @param SepayTransaction $tx       Transaction.
	 * @param string           $provider Provider slug stored in the log.
	 * @return string One of the class constants.
	 */
	public static function handle( SepayTransaction $tx, $provider = 'sepay' ) {
		$inserted = TransactionLog::insert_once( $provider, $tx );
		if ( null === $inserted ) {
			return self::DB_ERROR;
		}
		if ( false === $inserted ) {
			return self::DUPLICATE;
		}

		$result   = self::apply( $tx );
		$order_id = isset( $result['order_id'] ) ? $result['order_id'] : null;

		TransactionLog::mark( $provider, $tx->id, $result['result'], $order_id );

		/**
		 * Fires after a bank transaction was processed.
		 *
		 * Useful to push a Telegram/Zalo notification to the shop owner.
		 *
		 * @param string           $result   Outcome constant.
		 * @param SepayTransaction $tx       Transaction.
		 * @param int|null         $order_id Matched order ID.
		 */
		do_action( 'vqrc_transaction_processed', $result['result'], $tx, $order_id );

		return $result['result'];
	}

	/**
	 * Match the transaction to an order and update it.
	 *
	 * @param SepayTransaction $tx Transaction.
	 * @return array{result: string, order_id?: int}
	 */
	private static function apply( SepayTransaction $tx ) {
		if ( ! $tx->is_incoming() ) {
			return array( 'result' => self::OUTGOING );
		}

		$order_id = PaymentCode::extract_order_id( Gateway::option( 'prefix', 'DH' ), $tx->searchable_text() );
		if ( null === $order_id ) {
			return array( 'result' => self::NO_CODE );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order || Gateway::ID !== $order->get_payment_method() ) {
			return array( 'result' => self::ORDER_NOT_FOUND );
		}

		$amount_text = self::format_vnd( $tx->amount );

		if ( ! $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			// Money arrived for an order that was already paid, cancelled or refunded.
			$order->add_order_note(
				sprintf(
					/* translators: 1: amount, 2: order status, 3: bank reference. */
					__( 'VietQR: received %1$s while the order was "%2$s". Please check manually. Bank ref: %3$s', 'vietqr-checkout' ),
					$amount_text,
					wc_get_order_status_name( $order->get_status() ),
					$tx->reference_code
				)
			);
			return array(
				'result'   => self::NOT_AWAITING,
				'order_id' => $order_id,
			);
		}

		$received = (int) $order->get_meta( self::META_RECEIVED ) + $tx->amount;
		$order->update_meta_data( self::META_RECEIVED, (string) $received );

		$check = AmountCheck::compare( (int) round( (float) $order->get_total() ), $received );

		if ( ! AmountCheck::is_settled( $check ) ) {
			$order->save();
			$order->add_order_note(
				sprintf(
					/* translators: 1: amount of this transfer, 2: total received, 3: order total. */
					__( 'VietQR: received %1$s (total %2$s of %3$s). Waiting for the rest.', 'vietqr-checkout' ),
					$amount_text,
					self::format_vnd( $received ),
					self::format_vnd( (int) $order->get_total() )
				)
			);
			return array(
				'result'   => self::UNDERPAID,
				'order_id' => $order_id,
			);
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: amount, 2: bank name, 3: bank reference. */
				__( 'VietQR: payment of %1$s confirmed via %2$s. Bank ref: %3$s', 'vietqr-checkout' ),
				$amount_text,
				'' !== $tx->gateway ? $tx->gateway : 'SePay',
				$tx->reference_code
			)
		);

		if ( AmountCheck::OVERPAID === $check ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: overpaid amount. */
					__( 'VietQR: customer overpaid by %s. Consider refunding the difference.', 'vietqr-checkout' ),
					self::format_vnd( $received - (int) $order->get_total() )
				)
			);
		}

		// Sets processing/completed, reduces stock if needed, sends emails, saves the order.
		$order->payment_complete( '' !== $tx->reference_code ? $tx->reference_code : 'sepay-' . $tx->id );

		return array(
			'result'   => AmountCheck::OVERPAID === $check ? self::OVERPAID : self::PAID,
			'order_id' => $order_id,
		);
	}

	/**
	 * "150.000 ₫" style amount for order notes, without HTML.
	 *
	 * @param int $amount Amount in VND.
	 * @return string
	 */
	public static function format_vnd( $amount ) {
		return number_format( (float) $amount, 0, ',', '.' ) . ' VND';
	}
}
