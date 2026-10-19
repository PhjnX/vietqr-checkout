<?php
/**
 * Plugin Name:          VietQR Checkout for WooCommerce
 * Plugin URI:           https://github.com/PhjnX/vietqr-checkout
 * Description:          Accept Vietnamese bank transfers via VietQR. Orders are confirmed automatically when the money arrives (SePay webhook).
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               Bui Hoang Phi
 * Author URI:           https://github.com/PhjnX
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          vietqr-checkout
 * Domain Path:          /languages
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 *
 * @package VietQRCheckout
 */

defined( 'ABSPATH' ) || exit;

define( 'VQRC_VERSION', '1.0.0' );
define( 'VQRC_FILE', __FILE__ );
define( 'VQRC_PATH', plugin_dir_path( __FILE__ ) );
define( 'VQRC_URL', plugin_dir_url( __FILE__ ) );

/*
 * A tiny PSR-4 autoloader: VietQRCheckout\Foo\Bar => src/Foo/Bar.php.
 * The plugin has no runtime Composer dependencies, so it works when installed
 * straight from a git checkout or a zip (WordPress Playground, wp-admin upload).
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'VietQRCheckout\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = VQRC_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'VietQRCheckout\\Install', 'activate' ) );

// Tell WooCommerce we support HPOS (custom order tables) and the block-based checkout.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		\VietQRCheckout\Plugin::instance()->boot();
	}
);
