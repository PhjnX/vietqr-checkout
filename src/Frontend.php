<?php
/**
 * Customer-facing output: payment box and emails.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

use VietQRCheckout\Payment\Banks;
use VietQRCheckout\Payment\QrUrl;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the QR code box on the thank-you and "view order" pages and adds the
 * transfer details to the "order on hold" email.
 */
final class Frontend {

	/**
	 * Hook into WooCommerce.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'woocommerce_view_order', array( __CLASS__, 'view_order' ), 5 );
		add_action( 'woocommerce_email_before_order_table', array( __CLASS__, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Whether an order is ours and still waiting for money.
	 *
	 * @param mixed $order Order.
	 * @return bool
	 */
	public static function is_awaiting_payment( $order ) {
		return $order instanceof \WC_Order
			&& Gateway::ID === $order->get_payment_method()
			&& $order->has_status( array( 'on-hold', 'pending' ) );
	}

	/**
	 * Data needed by the template and the email.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, mixed>|null Null when the gateway is not configured.
	 */
	public static function payment_data( \WC_Order $order ) {
		$bin       = (string) Gateway::option( 'bank_bin' );
		$account   = (string) Gateway::option( 'account_number' );
		$name      = (string) Gateway::option( 'account_name' );
		$code      = (string) $order->get_meta( PaymentProcessor::META_CODE );
		$total     = (int) round( (float) $order->get_total() );
		$remaining = max( 0, $total - (int) $order->get_meta( PaymentProcessor::META_RECEIVED ) );

		try {
			$qr_url = QrUrl::build( $bin, $account, $remaining, $code, $name );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}

		return array(
			'order_id'   => $order->get_id(),
			'order_key'  => $order->get_order_key(),
			'qr_url'     => $qr_url,
			'bank_name'  => Banks::name( $bin ),
			'account'    => $account,
			'name'       => QrUrl::clean_name( $name ),
			'code'       => $code,
			'amount'     => $remaining,
			'expires_at' => Expiry::expires_at( $order ),
		);
	}

	/**
	 * Print the payment box and load its script.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public static function render_payment_box( $order ) {
		if ( ! self::is_awaiting_payment( $order ) ) {
			return;
		}

		$data = self::payment_data( $order );
		if ( null === $data ) {
			return;
		}

		wp_enqueue_style( 'vqrc-payment', VQRC_URL . 'assets/css/payment.css', array(), VQRC_VERSION );
		wp_enqueue_script( 'vqrc-payment', VQRC_URL . 'assets/js/payment.js', array(), VQRC_VERSION, true );
		wp_localize_script(
			'vqrc-payment',
			'vqrcPayment',
			array(
				'statusUrl'   => rest_url( RestController::NAMESPACE . '/orders/' . $data['order_id'] . '/status' ),
				'simulateUrl' => rest_url( RestController::NAMESPACE . '/orders/' . $data['order_id'] . '/simulate' ),
				'orderKey'    => $data['order_key'],
				'expiresAt'   => $data['expires_at'],
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'i18n'        => array(
					'copied'   => __( 'Copied', 'vietqr-checkout' ),
					'paid'     => __( 'Payment received. Thank you!', 'vietqr-checkout' ),
					'expired'  => __( 'The payment window has ended. Please contact us if you already transferred.', 'vietqr-checkout' ),
					'partial'  => __( 'Part of the payment arrived. Please transfer the remaining amount.', 'vietqr-checkout' ),
					'simError' => __( 'Simulation failed.', 'vietqr-checkout' ),
				),
			)
		);

		$show_simulator = RestController::can_simulate();

		include VQRC_PATH . 'templates/payment-box.php';
	}

	/**
	 * Show the box again on My account > Orders > View, until the order is paid.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function view_order( $order_id ) {
		self::render_payment_box( wc_get_order( $order_id ) );
	}

	/**
	 * Transfer details in the "order on hold" email, so the customer can still
	 * pay after closing the browser tab.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Whether the email goes to the shop.
	 * @param bool      $plain_text    Plain text email.
	 * @return void
	 */
	public static function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! self::is_awaiting_payment( $order ) ) {
			return;
		}

		$data = self::payment_data( $order );
		if ( null === $data ) {
			return;
		}

		$lines = array(
			__( 'Bank', 'vietqr-checkout' )           => $data['bank_name'],
			__( 'Account number', 'vietqr-checkout' ) => $data['account'],
			__( 'Account holder', 'vietqr-checkout' ) => $data['name'],
			__( 'Amount', 'vietqr-checkout' )         => PaymentProcessor::format_vnd( $data['amount'] ),
			__( 'Transfer note', 'vietqr-checkout' )  => $data['code'],
		);

		if ( $plain_text ) {
			echo esc_html__( 'Please transfer with these details:', 'vietqr-checkout' ) . "\n";
			foreach ( $lines as $label => $value ) {
				echo esc_html( $label . ': ' . $value ) . "\n";
			}
			echo "\n";
			return;
		}

		echo '<h2>' . esc_html__( 'Bank transfer details', 'vietqr-checkout' ) . '</h2>';
		echo '<p><img src="' . esc_url( $data['qr_url'] ) . '" alt="VietQR" width="220" style="max-width:220px;height:auto"></p>';
		echo '<ul>';
		foreach ( $lines as $label => $value ) {
			echo '<li>' . esc_html( $label ) . ': <strong>' . esc_html( $value ) . '</strong></li>';
		}
		echo '</ul>';
	}
}
