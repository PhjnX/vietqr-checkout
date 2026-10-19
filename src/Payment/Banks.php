<?php
/**
 * Banks supported by the settings dropdown.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Payment;

/**
 * NAPAS BIN codes of common Vietnamese banks.
 *
 * VietQR identifies a bank by its 6-digit BIN. The list is filterable through
 * `vqrc_banks` so a shop can add a bank without editing the plugin.
 */
final class Banks {

	/**
	 * BIN => display name.
	 *
	 * @return array<int|string, string> BIN => name. PHP turns numeric string keys into ints.
	 */
	public static function all() {
		$banks = array(
			'970436' => 'Vietcombank',
			'970415' => 'VietinBank',
			'970418' => 'BIDV',
			'970405' => 'Agribank',
			'970407' => 'Techcombank',
			'970422' => 'MB Bank',
			'970416' => 'ACB',
			'970432' => 'VPBank',
			'970423' => 'TPBank',
			'970403' => 'Sacombank',
			'970441' => 'VIB',
			'970443' => 'SHB',
			'970437' => 'HDBank',
			'970448' => 'OCB',
			'970426' => 'MSB',
			'970431' => 'Eximbank',
			'970440' => 'SeABank',
			'970449' => 'LPBank',
			'970428' => 'Nam A Bank',
			'970452' => 'KienlongBank',
			'970424' => 'Shinhan Bank',
		);

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the bank list shown in the settings.
			 *
			 * @param array<int|string, string> $banks BIN => bank name.
			 */
			$banks = (array) apply_filters( 'vqrc_banks', $banks );
		}

		return $banks;
	}

	/**
	 * Bank display name for a BIN.
	 *
	 * @param string $bin Bank BIN.
	 * @return string
	 */
	public static function name( $bin ) {
		$banks = self::all();
		return isset( $banks[ $bin ] ) ? $banks[ $bin ] : (string) $bin;
	}
}
