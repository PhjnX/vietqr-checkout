<?php
/**
 * Support for the block-based Checkout (default checkout on new WooCommerce sites).
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * Without this class the gateway only appears on the classic [woocommerce_checkout]
 * shortcode. The Checkout block renders payment methods in React and needs a
 * small script that registers our method on the client.
 */
final class BlocksSupport extends AbstractPaymentMethodType {

	/**
	 * Must equal the gateway ID.
	 *
	 * @var string
	 */
	protected $name = Gateway::ID;

	/**
	 * Load settings.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . Gateway::ID . '_settings', array() );
	}

	/**
	 * Reuse the gateway's availability rules (currency, account configured).
	 *
	 * @return bool
	 */
	public function is_active() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		return isset( $gateways[ Gateway::ID ] ) && $gateways[ Gateway::ID ]->is_available();
	}

	/**
	 * Script that registers the method in the Checkout block.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'vqrc-blocks',
			VQRC_URL . 'assets/js/blocks.js',
			array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ),
			VQRC_VERSION,
			true
		);
		return array( 'vqrc-blocks' );
	}

	/**
	 * Data passed to the script, read there with getSetting( 'vietqr_checkout_data' ).
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'icon'        => VQRC_URL . 'assets/images/qr-icon.svg',
			'supports'    => array( 'products' ),
		);
	}
}
