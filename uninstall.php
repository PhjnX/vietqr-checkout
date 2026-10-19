<?php
/**
 * Runs when the plugin is deleted from wp-admin (not on deactivation).
 *
 * Order notes and meta stay on the orders: they are part of the shop's
 * accounting history. Only the plugin's own table and options are removed.
 *
 * @package VietQRCheckout
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- dropping our own table on uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}vqrc_transactions" );

delete_option( 'vqrc_db_version' );
delete_option( 'woocommerce_vietqr_checkout_settings' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'vqrc_expire_order' );
}
