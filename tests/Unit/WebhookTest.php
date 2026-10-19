<?php
/**
 * Tests for the webhook value object, API key check and amount comparison.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VietQRCheckout\Payment\AmountCheck;
use VietQRCheckout\Webhook\ApiKeyAuth;
use VietQRCheckout\Webhook\SepayTransaction;

final class WebhookTest extends TestCase {

	/**
	 * Payload copied from the SePay documentation.
	 *
	 * @return array<string, mixed>
	 */
	private function payload() {
		return array(
			'id'              => 92704,
			'gateway'         => 'Vietcombank',
			'transactionDate' => '2024-07-02 11:08:33',
			'accountNumber'   => '1017588888',
			'subAccount'      => '',
			'code'            => null,
			'content'         => 'DH13 chuyen tien',
			'transferType'    => 'in',
			'description'     => 'NGUYEN VAN A chuyen tien',
			'transferAmount'  => 5000000,
			'accumulated'     => 105000000,
			'referenceCode'   => 'FT24012345678',
		);
	}

	public function test_parses_documented_payload() {
		$tx = SepayTransaction::from_array( $this->payload() );

		$this->assertSame( '92704', $tx->id );
		$this->assertSame( 5000000, $tx->amount );
		$this->assertTrue( $tx->is_incoming() );
		$this->assertNull( $tx->code );
		$this->assertSame( 'DH13 chuyen tien', $tx->searchable_text() );
	}

	public function test_detected_code_is_searched_first() {
		$data         = $this->payload();
		$data['code'] = 'DH99';
		$this->assertSame( 'DH99 DH13 chuyen tien', SepayTransaction::from_array( $data )->searchable_text() );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function invalid_payloads() {
		return array(
			'missing amount'  => array( array( 'transferAmount' => null ) ),
			'zero amount'     => array( array( 'transferAmount' => 0 ) ),
			'bad type'        => array( array( 'transferType' => 'refund' ) ),
			'negative id'     => array( array( 'id' => -1 ) ),
			'text id'         => array( array( 'id' => 'abc' ) ),
		);
	}

	/**
	 * @dataProvider invalid_payloads
	 *
	 * @param array<string, mixed> $override Fields to break.
	 */
	public function test_rejects_invalid_payload( $override ) {
		$this->expectException( \InvalidArgumentException::class );
		SepayTransaction::from_array( array_merge( $this->payload(), $override ) );
	}

	public function test_rejects_non_array() {
		$this->expectException( \InvalidArgumentException::class );
		SepayTransaction::from_array( 'hello' );
	}

	public function test_api_key_header_forms() {
		$this->assertTrue( ApiKeyAuth::verify( 'secret', 'Apikey secret' ) );
		$this->assertTrue( ApiKeyAuth::verify( 'secret', 'apikey   secret ' ) );
		$this->assertTrue( ApiKeyAuth::verify( 'secret', 'Bearer Apikey secret' ) );
		$this->assertFalse( ApiKeyAuth::verify( 'secret', 'Apikey wrong' ) );
		$this->assertFalse( ApiKeyAuth::verify( 'secret', 'secret' ) );
		$this->assertFalse( ApiKeyAuth::verify( 'secret', '' ) );
	}

	public function test_empty_configured_key_rejects_everything() {
		$this->assertFalse( ApiKeyAuth::verify( '', 'Apikey ' ) );
		$this->assertFalse( ApiKeyAuth::verify( '  ', 'Apikey anything' ) );
	}

	public function test_amount_check() {
		$this->assertSame( AmountCheck::PAID, AmountCheck::compare( 300000, 300000 ) );
		$this->assertSame( AmountCheck::UNDERPAID, AmountCheck::compare( 300000, 299999 ) );
		$this->assertSame( AmountCheck::OVERPAID, AmountCheck::compare( 300000, 300001 ) );
		$this->assertSame( AmountCheck::PAID, AmountCheck::compare( '300000.00', 300000 ) );
		$this->assertTrue( AmountCheck::is_settled( AmountCheck::OVERPAID ) );
		$this->assertFalse( AmountCheck::is_settled( AmountCheck::UNDERPAID ) );
	}
}
