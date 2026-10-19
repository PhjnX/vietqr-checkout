<?php
/**
 * Prepare a demo shop: VND currency, gateway in test mode, a few products.
 *
 * Used by blueprint.json (WordPress Playground) and .wp-env.json (local Docker).
 * Run manually with: wp eval-file wp-content/plugins/vietqr-checkout/bin/demo-setup.php
 *
 * Not shipped in release zips (see .gitattributes).
 *
 * @package VietQRCheckout
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

// Store basics for a Vietnamese shop.
update_option( 'woocommerce_currency', 'VND' );
update_option( 'woocommerce_price_num_decimals', '0' );
update_option( 'woocommerce_price_thousand_sep', '.' );
update_option( 'woocommerce_price_decimal_sep', ',' );
update_option( 'woocommerce_currency_pos', 'right_space' );
update_option( 'woocommerce_default_country', 'VN' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );

// Gateway settings. The account is fake: the QR renders, but do not transfer real money.
update_option(
	'woocommerce_vietqr_checkout_settings',
	array(
		'enabled'        => 'yes',
		'title'          => 'Chuyển khoản VietQR',
		'description'    => 'Quét mã QR bằng app ngân hàng sau khi đặt hàng. Đơn được xác nhận tự động.',
		'bank_bin'       => '970422',
		'account_number' => '0123456789',
		'account_name'   => 'CONG TY DEMO',
		'prefix'         => 'DH',
		'expiry_minutes' => '30',
		'api_key'        => 'demo-api-key',
		'test_mode'      => 'yes',
	)
);

// A few products, created once.
$vqrc_demo_products = array(
	array( 'Áo thun cotton basic', 159000 ),
	array( 'Bình giữ nhiệt 500ml', 245000 ),
	array( 'Sổ tay bìa da A5', 89000 ),
);

if ( ! get_option( 'vqrc_demo_products_created' ) ) {
	foreach ( $vqrc_demo_products as $vqrc_item ) {
		$vqrc_product = new WC_Product_Simple();
		$vqrc_product->set_name( $vqrc_item[0] );
		$vqrc_product->set_regular_price( (string) $vqrc_item[1] );
		$vqrc_product->set_manage_stock( true );
		$vqrc_product->set_stock_quantity( 20 );
		$vqrc_product->set_status( 'publish' );
		$vqrc_product->save();
	}
	update_option( 'vqrc_demo_products_created', '1', false );
}
