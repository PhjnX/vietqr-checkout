<?php
/**
 * REST API routes.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

use VietQRCheckout\Payment\AmountCheck;
use VietQRCheckout\Webhook\ApiKeyAuth;
use VietQRCheckout\Webhook\SepayTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /wp-json/vietqr-checkout/v1:
 *
 * POST /sepay-webhook                  SePay calls this for every bank transaction.
 * GET  /orders/{id}/status?key=...     The thank-you page polls this.
 * POST /orders/{id}/simulate           Test mode only: fake a payment.
 */
final class RestController {

	const NAMESPACE = 'vietqr-checkout/v1';

	/**
	 * Register routes on rest_api_init.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Route definitions.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NAMESPACE,
			'/sepay-webhook',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'webhook' ),
				'permission_callback' => array( __CLASS__, 'can_receive_webhook' ),
			)
		);

		$order_args = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		register_rest_route(
			self::NAMESPACE,
			'/orders/(?P<id>\d+)/status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => array( __CLASS__, 'can_view_order' ),
				'args'                => $order_args + array(
					'key' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/orders/(?P<id>\d+)/simulate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'simulate' ),
				'permission_callback' => array( __CLASS__, 'can_simulate' ),
				'args'                => $order_args,
			)
		);
	}

	/**
	 * Only SePay, holding the API key, may call the webhook.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function can_receive_webhook( \WP_REST_Request $request ) {
		$header = (string) $request->get_header( 'authorization' );

		if ( ApiKeyAuth::verify( Gateway::option( 'api_key' ), $header ) ) {
			return true;
		}

		return new \WP_Error( 'vqrc_unauthorized', 'Invalid API key.', array( 'status' => 401 ) );
	}

	/**
	 * Webhook handler.
	 *
	 * SePay counts a delivery as successful only with HTTP 200/201 and the body
	 * {"success": true}. Transactions we cannot match (another customer paying
	 * the shop by hand, wrong note...) still get success, otherwise SePay would
	 * retry them forever. Only a database error asks for a retry.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function webhook( \WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( empty( $data ) ) {
			$data = $request->get_body_params(); // SePay can also post form data.
		}

		try {
			$tx = SepayTransaction::from_array( $data );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				400
			);
		}

		$result = PaymentProcessor::handle( $tx );

		if ( PaymentProcessor::DB_ERROR === $result ) {
			return new \WP_REST_Response( array( 'success' => false ), 500 );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'result'  => $result,
			),
			200
		);
	}

	/**
	 * The order key in the thank-you URL proves the visitor owns the order,
	 * so guests can poll without logging in and nobody can enumerate orders.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_view_order( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );

		return $order instanceof \WC_Order
			&& Gateway::ID === $order->get_payment_method()
			&& hash_equals( $order->get_order_key(), (string) $request['key'] );
	}

	/**
	 * Current payment state for the polling script.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function status( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );

		$response = new \WP_REST_Response(
			array(
				'status'   => $order->get_status(),
				'paid'     => $order->is_paid(),
				'received' => (int) $order->get_meta( PaymentProcessor::META_RECEIVED ),
				'total'    => (int) round( (float) $order->get_total() ),
			)
		);

		// Never let a cache plugin or CDN serve a stale "unpaid" answer.
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Simulator: test mode on and a shop manager signed in (REST nonce required).
	 *
	 * @return bool
	 */
	public static function can_simulate() {
		return Gateway::is_test_mode() && current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Build a fake SePay transaction for the outstanding amount and process it
	 * exactly like a real webhook.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function simulate( \WP_REST_Request $request ) {
		$order = wc_get_order( (int) $request['id'] );
		if ( ! $order instanceof \WC_Order || Gateway::ID !== $order->get_payment_method() ) {
			return new \WP_Error( 'vqrc_not_found', 'Order not found.', array( 'status' => 404 ) );
		}

		$total     = (int) round( (float) $order->get_total() );
		$remaining = max( 1, $total - (int) $order->get_meta( PaymentProcessor::META_RECEIVED ) );

		$tx = SepayTransaction::from_array(
			array(
				'id'              => (int) ( microtime( true ) * 1000 ),
				'gateway'         => 'Simulator',
				'transactionDate' => current_time( 'mysql' ),
				'accountNumber'   => (string) Gateway::option( 'account_number' ),
				'code'            => null,
				'content'         => 'TEST ' . $order->get_meta( PaymentProcessor::META_CODE ),
				'transferType'    => 'in',
				'transferAmount'  => $remaining,
				'referenceCode'   => 'SIM' . wp_rand( 100000, 999999 ),
			)
		);

		$result = PaymentProcessor::handle( $tx, 'simulator' );

		return new \WP_REST_Response(
			array(
				'success' => AmountCheck::is_settled( $result ),
				'result'  => $result,
			)
		);
	}
}
