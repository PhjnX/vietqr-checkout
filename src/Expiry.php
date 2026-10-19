<?php
/**
 * Cancel orders that were never paid.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Uses Action Scheduler (bundled with WooCommerce) instead of WP-Cron
 * directly: jobs are stored in the database, retried on failure and visible
 * under WooCommerce > Status > Scheduled Actions.
 */
final class Expiry {

	const HOOK  = 'vqrc_expire_order';
	const GROUP = 'vietqr-checkout';

	/**
	 * Register the job handler.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( self::HOOK, array( __CLASS__, 'expire' ) );
	}

	/**
	 * Schedule the cancellation of an order.
	 *
	 * @param \WC_Order $order   Order.
	 * @param int       $minutes Minutes from now, 0 disables expiry.
	 * @return void
	 */
	public static function schedule( \WC_Order $order, $minutes ) {
		if ( $minutes <= 0 || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$expires_at = time() + $minutes * 60;
		$order->update_meta_data( '_vqrc_expires_at', (string) $expires_at );
		$order->save();

		as_schedule_single_action( $expires_at, self::HOOK, array( 'order_id' => $order->get_id() ), self::GROUP );
	}

	/**
	 * Unix time when the order expires, or 0.
	 *
	 * @param \WC_Order $order Order.
	 * @return int
	 */
	public static function expires_at( \WC_Order $order ) {
		return (int) $order->get_meta( '_vqrc_expires_at' );
	}

	/**
	 * Job handler.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function expire( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order || Gateway::ID !== $order->get_payment_method() ) {
			return;
		}
		if ( ! $order->has_status( array( 'on-hold', 'pending' ) ) ) {
			return; // Paid or handled by staff in the meantime.
		}

		if ( (int) $order->get_meta( PaymentProcessor::META_RECEIVED ) > 0 ) {
			// Part of the money arrived: cancelling would confuse the customer.
			$order->add_order_note( __( 'VietQR: payment window ended with a partial payment. Please contact the customer.', 'vietqr-checkout' ) );
			return;
		}

		// WooCommerce restores stock automatically when an order is cancelled.
		$order->update_status( 'cancelled', __( 'VietQR: no transfer received before the payment window ended.', 'vietqr-checkout' ) );
	}
}
