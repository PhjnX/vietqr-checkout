<?php
/**
 * Storage for received bank transactions.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout;

use VietQRCheckout\Webhook\SepayTransaction;

defined( 'ABSPATH' ) || exit;

/**
 * Thin wrapper around the {prefix}vqrc_transactions table.
 */
final class TransactionLog {

	/**
	 * Full table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'vqrc_transactions';
	}

	/**
	 * Insert a transaction unless it was already recorded.
	 *
	 * @param string           $provider Provider slug, e.g. "sepay".
	 * @param SepayTransaction $tx       Transaction.
	 * @return bool|null True when inserted, false when it is a duplicate, null on database error.
	 */
	public static function insert_once( $provider, SepayTransaction $tx ) {
		global $wpdb;

		$table = self::table();

		// INSERT IGNORE + UNIQUE key: the database decides atomically whether this
		// transaction is new. Checking with SELECT first would race on retries.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, write query.
		$rows = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be a placeholder.
				"INSERT IGNORE INTO {$table} (provider, provider_txn_id, amount, content, reference_code, payload, created_at) VALUES (%s, %s, %d, %s, %s, %s, %s)",
				$provider,
				$tx->id,
				$tx->amount,
				substr( $tx->content, 0, 255 ),
				$tx->reference_code,
				wp_json_encode( $tx->raw ),
				current_time( 'mysql', true )
			)
		);

		if ( false === $rows ) {
			return null;
		}

		return $rows > 0;
	}

	/**
	 * Save what happened to a transaction.
	 *
	 * @param string   $provider Provider slug.
	 * @param string   $txn_id   Provider transaction ID.
	 * @param string   $result   Outcome, see PaymentProcessor constants.
	 * @param int|null $order_id Matched order, if any.
	 * @return void
	 */
	public static function mark( $provider, $txn_id, $result, $order_id = null ) {
		global $wpdb;

		$data = array( 'result' => $result );
		if ( $order_id ) {
			$data['order_id'] = (int) $order_id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
		$wpdb->update(
			self::table(),
			$data,
			array(
				'provider'        => $provider,
				'provider_txn_id' => $txn_id,
			)
		);
	}

	/**
	 * Transactions matched to an order, newest first.
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, object>
	 */
	public static function for_order( $order_id ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, admin-only read.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
				"SELECT provider_txn_id, amount, content, reference_code, result, created_at FROM {$table} WHERE order_id = %d ORDER BY id DESC",
				$order_id
			)
		);
	}
}
