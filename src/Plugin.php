<?php
/**
 * Bootstrap: wires every part of the plugin into WordPress.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Single entry point. Each class registers its own hooks, this class only
 * decides what gets loaded.
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register everything. Called on plugins_loaded once WooCommerce is present.
	 *
	 * @return void
	 */
	public function boot() {
		Install::maybe_upgrade();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_blocks' ) );

		RestController::register();
		Frontend::register();
		Expiry::register();

		if ( is_admin() ) {
			Admin::register();
		}
	}

	/**
	 * Load translations from /languages.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'vietqr-checkout', false, dirname( plugin_basename( VQRC_FILE ) ) . '/languages' );
	}

	/**
	 * Add the gateway class to WooCommerce.
	 *
	 * @param string[] $gateways Gateway class names.
	 * @return string[]
	 */
	public function add_gateway( $gateways ) {
		$gateways[] = Gateway::class;
		return $gateways;
	}

	/**
	 * Register the Checkout block integration.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry Registry.
	 * @return void
	 */
	public function register_blocks( $registry ) {
		$registry->register( new BlocksSupport() );
	}
}
