<?php
/**
 * Admin screens.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Settings link on the Plugins screen and a transactions panel on the order page.
 */
final class Admin {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'plugin_action_links_' . plugin_basename( VQRC_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'order_panel' ) );
	}

	/**
	 * "Settings" link next to Deactivate.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . Gateway::ID );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'vietqr-checkout' ) . '</a>' );
		return $links;
	}

	/**
	 * Payment code, amount received and matched transactions on the order screen.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public static function order_panel( $order ) {
		if ( Gateway::ID !== $order->get_payment_method() ) {
			return;
		}

		$transactions = TransactionLog::for_order( $order->get_id() );

		echo '<div class="vqrc-admin" style="margin-top:12px">';
		echo '<h3>' . esc_html__( 'VietQR', 'vietqr-checkout' ) . '</h3>';
		echo '<p>' . esc_html__( 'Payment code', 'vietqr-checkout' ) . ': <code>' . esc_html( (string) $order->get_meta( PaymentProcessor::META_CODE ) ) . '</code><br>';
		echo esc_html__( 'Received', 'vietqr-checkout' ) . ': <strong>' . esc_html( PaymentProcessor::format_vnd( (int) $order->get_meta( PaymentProcessor::META_RECEIVED ) ) ) . '</strong></p>';

		if ( $transactions ) {
			echo '<table class="widefat striped" style="margin-top:6px"><thead><tr>';
			echo '<th>' . esc_html__( 'Time (UTC)', 'vietqr-checkout' ) . '</th>';
			echo '<th>' . esc_html__( 'Amount', 'vietqr-checkout' ) . '</th>';
			echo '<th>' . esc_html__( 'Result', 'vietqr-checkout' ) . '</th>';
			echo '</tr></thead><tbody>';
			foreach ( $transactions as $tx ) {
				echo '<tr><td>' . esc_html( $tx->created_at ) . '</td><td>' . esc_html( PaymentProcessor::format_vnd( (int) $tx->amount ) ) . '</td><td>' . esc_html( $tx->result ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</div>';
	}
}
