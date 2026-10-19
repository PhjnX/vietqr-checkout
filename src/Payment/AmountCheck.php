<?php
/**
 * Compare the money received with the order total.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Payment;

/**
 * Decides whether a transfer pays for an order.
 *
 * Customers sometimes send less (typo) or more (rounding up). Underpaid orders
 * stay unpaid with a note for the shop owner; paid and overpaid orders are
 * completed, and an overpayment is noted so the owner can refund the difference.
 *
 * Pure PHP, unit-tested.
 */
final class AmountCheck {

	const PAID      = 'paid';
	const OVERPAID  = 'overpaid';
	const UNDERPAID = 'underpaid';

	/**
	 * Compare amounts in VND.
	 *
	 * @param int $order_total Order total in VND.
	 * @param int $received    Total received for this order so far, in VND.
	 * @return string One of the class constants.
	 */
	public static function compare( $order_total, $received ) {
		$order_total = (int) round( (float) $order_total );
		$received    = (int) $received;

		if ( $received < $order_total ) {
			return self::UNDERPAID;
		}
		if ( $received > $order_total ) {
			return self::OVERPAID;
		}
		return self::PAID;
	}

	/**
	 * Whether the result means the order can be marked as paid.
	 *
	 * @param string $result Result of compare().
	 * @return bool
	 */
	public static function is_settled( $result ) {
		return self::PAID === $result || self::OVERPAID === $result;
	}
}
