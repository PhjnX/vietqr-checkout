<?php
/**
 * The WooCommerce payment gateway.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

use VietQRCheckout\Payment\Banks;
use VietQRCheckout\Payment\PaymentCode;

defined( 'ABSPATH' ) || exit;

/**
 * VietQR bank transfer gateway.
 *
 * WooCommerce builds the settings screen from $this->form_fields and stores the
 * values in the option "woocommerce_vietqr_checkout_settings".
 */
class Gateway extends \WC_Payment_Gateway {

	const ID = 'vietqr_checkout';

	/**
	 * Read one setting without instantiating the gateway.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	public static function option( $key, $fallback = '' ) {
		$settings = get_option( 'woocommerce_' . self::ID . '_settings', array() );
		return isset( $settings[ $key ] ) && '' !== $settings[ $key ] ? $settings[ $key ] : $fallback;
	}

	/**
	 * Whether the simulator button may be shown.
	 *
	 * @return bool
	 */
	public static function is_test_mode() {
		return 'yes' === self::option( 'test_mode', 'no' );
	}

	/**
	 * Set up the gateway.
	 */
	public function __construct() {
		$this->id                 = self::ID;
		$this->icon               = VQRC_URL . 'assets/images/qr-icon.svg';
		$this->has_fields         = false;
		$this->method_title       = __( 'VietQR bank transfer', 'vietqr-checkout' );
		$this->method_description = __( 'Customers scan a VietQR code with any Vietnamese banking app. Orders are confirmed automatically through a SePay webhook.', 'vietqr-checkout' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
	}

	/**
	 * Settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$webhook_url = rest_url( RestController::NAMESPACE . '/sepay-webhook' );

		$this->form_fields = array(
			'enabled'        => array(
				'title'   => __( 'Enable', 'vietqr-checkout' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable VietQR bank transfer', 'vietqr-checkout' ),
				'default' => 'no',
			),
			'title'          => array(
				'title'   => __( 'Title', 'vietqr-checkout' ),
				'type'    => 'text',
				'default' => __( 'Bank transfer (VietQR)', 'vietqr-checkout' ),
			),
			'description'    => array(
				'title'   => __( 'Description', 'vietqr-checkout' ),
				'type'    => 'textarea',
				'default' => __( 'Scan the QR code with your banking app after placing the order. The order is confirmed automatically.', 'vietqr-checkout' ),
			),
			'bank_section'   => array(
				'title' => __( 'Receiving account', 'vietqr-checkout' ),
				'type'  => 'title',
			),
			'bank_bin'       => array(
				'title'   => __( 'Bank', 'vietqr-checkout' ),
				'type'    => 'select',
				'class'   => 'wc-enhanced-select',
				'options' => Banks::all(),
				'default' => '970436',
			),
			'account_number' => array(
				'title' => __( 'Account number', 'vietqr-checkout' ),
				'type'  => 'text',
			),
			'account_name'   => array(
				'title'       => __( 'Account holder', 'vietqr-checkout' ),
				'type'        => 'text',
				'description' => __( 'Exactly as registered at the bank, e.g. NGUYEN VAN A.', 'vietqr-checkout' ),
			),
			'prefix'         => array(
				'title'       => __( 'Payment code prefix', 'vietqr-checkout' ),
				'type'        => 'text',
				'default'     => 'DH',
				'description' => __( '2-5 letters. The transfer note becomes prefix + order number, e.g. DH1234. Must match the payment code structure configured in SePay.', 'vietqr-checkout' ),
			),
			'expiry_minutes' => array(
				'title'             => __( 'Cancel unpaid orders after (minutes)', 'vietqr-checkout' ),
				'type'              => 'number',
				'default'           => '30',
				'custom_attributes' => array( 'min' => '0' ),
				'description'       => __( '0 keeps unpaid orders on hold forever. Cancelled orders return their stock.', 'vietqr-checkout' ),
			),
			'sepay_section'  => array(
				'title'       => __( 'SePay webhook', 'vietqr-checkout' ),
				'type'        => 'title',
				/* translators: %s: webhook URL. */
				'description' => sprintf( __( 'In my.sepay.vn > WebHooks, send transactions to %s with "API Key" authentication and JSON content type.', 'vietqr-checkout' ), '<code>' . esc_html( $webhook_url ) . '</code>' ),
			),
			'api_key'        => array(
				'title'       => __( 'API key', 'vietqr-checkout' ),
				'type'        => 'password',
				'description' => __( 'The same key you enter in SePay. Webhooks without this key are rejected.', 'vietqr-checkout' ),
			),
			'test_mode'      => array(
				'title'       => __( 'Test mode', 'vietqr-checkout' ),
				'type'        => 'checkbox',
				'label'       => __( 'Show a "Simulate payment" button on the order page to shop managers', 'vietqr-checkout' ),
				'default'     => 'no',
				'description' => __( 'For demos and local development. The simulated payment goes through the same code path as a real webhook.', 'vietqr-checkout' ),
			),
		);
	}

	/**
	 * Clean the prefix on save so a typo cannot break code matching.
	 *
	 * @param string $key   Field key.
	 * @param string $value Posted value.
	 * @return string
	 */
	public function validate_prefix_field( $key, $value ) {
		return PaymentCode::sanitize_prefix( $value );
	}

	/**
	 * Only offer the gateway when the shop sells in VND and an account is set.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}
		if ( 'VND' !== get_woocommerce_currency() ) {
			return false;
		}
		return '' !== $this->get_option( 'account_number' ) && '' !== $this->get_option( 'bank_bin' );
	}

	/**
	 * Called when the customer clicks "Place order".
	 *
	 * There is nothing to charge here: we put the order on hold, attach the
	 * payment code and send the customer to the thank-you page with the QR code.
	 *
	 * @param int $order_id Order ID.
	 * @return array{result: string, redirect: string}
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$code  = PaymentCode::build( $this->get_option( 'prefix', 'DH' ), $order->get_id() );

		$order->update_meta_data( PaymentProcessor::META_CODE, $code );
		$order->update_meta_data( PaymentProcessor::META_RECEIVED, '0' );
		/* translators: %s: payment code. */
		$order->update_status( 'on-hold', sprintf( __( 'Awaiting VietQR transfer with note %s.', 'vietqr-checkout' ), $code ) );

		Expiry::schedule( $order, (int) $this->get_option( 'expiry_minutes', 30 ) );

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Thank-you page output.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		Frontend::render_payment_box( wc_get_order( $order_id ) );
	}
}
