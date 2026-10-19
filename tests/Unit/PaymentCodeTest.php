<?php
/**
 * Tests for PaymentCode.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VietQRCheckout\Payment\PaymentCode;

final class PaymentCodeTest extends TestCase {

	public function test_build_joins_prefix_and_order_id() {
		$this->assertSame( 'DH1234', PaymentCode::build( 'DH', 1234 ) );
	}

	public function test_invalid_prefix_falls_back_to_dh() {
		$this->assertSame( 'DH', PaymentCode::sanitize_prefix( 'D' ) );
		$this->assertSame( 'DH', PaymentCode::sanitize_prefix( 'TOOLONG' ) );
		$this->assertSame( 'DH', PaymentCode::sanitize_prefix( 'D1' ) );
		$this->assertSame( 'SHOP', PaymentCode::sanitize_prefix( ' shop ' ) );
	}

	/**
	 * Notes as they really arrive from different banks.
	 *
	 * @return array<string, array{0: string, 1: int|null}>
	 */
	public static function notes() {
		return array(
			'exact'                 => array( 'DH1234', 1234 ),
			'lower case'            => array( 'dh1234', 1234 ),
			'bank prefix and dots'  => array( 'MBVCB.3561.DH1234.CT tu 0123 toi 4567', 1234 ),
			'space inside the code' => array( 'chuyen tien DH 1234', 1234 ),
			'code at the end'       => array( 'NGUYEN VAN A chuyen khoan DH77', 77 ),
			'glued to other letters' => array( 'ADH1234', null ),
			'no code'               => array( 'tra tien an trua', null ),
			'prefix only'           => array( 'DH', null ),
			'order zero'            => array( 'DH0', null ),
		);
	}

	/**
	 * @dataProvider notes
	 *
	 * @param string   $note     Transfer note.
	 * @param int|null $expected Expected order ID.
	 */
	public function test_extract_order_id( $note, $expected ) {
		$this->assertSame( $expected, PaymentCode::extract_order_id( 'DH', $note ) );
	}

	public function test_custom_prefix() {
		$this->assertSame( 58, PaymentCode::extract_order_id( 'SHOP', 'IBFT SHOP58 thanh toan' ) );
		$this->assertNull( PaymentCode::extract_order_id( 'SHOP', 'DH58' ) );
	}
}
