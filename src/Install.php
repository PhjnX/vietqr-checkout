<?php
/**
 * Activation and database schema.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the transaction log table and upgrades it when the schema changes.
 */
final class Install {

	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'vqrc_db_version';

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
	}

	/**
	 * Run on every load: cheap option check, creates the table after an update
	 * that shipped without re-activation (e.g. uploaded over FTP).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::create_tables();
		}
	}

	/**
	 * Create or update the table with dbDelta().
	 *
	 * The UNIQUE key on (provider, provider_txn_id) is what makes webhook handling
	 * idempotent: the same SePay transaction can never be inserted twice, even if
	 * two retries arrive at the same moment.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = TransactionLog::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta is picky: two spaces after PRIMARY KEY, one field per line.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			provider varchar(20) NOT NULL,
			provider_txn_id varchar(64) NOT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			amount bigint(20) NOT NULL DEFAULT 0,
			content varchar(255) NOT NULL DEFAULT '',
			reference_code varchar(64) NOT NULL DEFAULT '',
			result varchar(20) NOT NULL DEFAULT '',
			payload longtext,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY provider_txn (provider,provider_txn_id),
			KEY order_id (order_id)
		) {$charset};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}
}
