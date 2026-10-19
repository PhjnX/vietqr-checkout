<?php
/**
 * Tests for QrUrl.
 *
 * @package VietQRCheckout
 */

namespace VietQRCheckout\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VietQRCheckout\Payment\QrUrl;

final class QrUrlTest extends TestCase {

	public function test_builds_quick_link_url() {
		$url = QrUrl::build( '970422', '0123456789', 300000, 'DH13', 'Bùi Hoàng Phi' );

		$this->assertSame(
			'https://img.vietqr.io/image/970422-0123456789-compact2.png?amount=300000&addInfo=DH13&accountName=BUI%20HOANG%20PHI',
			$url
		);
	}

	public function test_account_name_is_optional() {
		$url = QrUrl::build( '970436', '1017588888', 50000, 'DH1' );
		$this->assertStringNotContainsString( 'accountName', $url );
	}

	public function test_unknown_template_falls_back() {
		$url = QrUrl::build( '970436', '1017588888', 1, 'DH1', '', 'fancy' );
		$this->assertStringContainsString( '-compact2.png', $url );
	}

	public function test_rejects_invalid_bin() {
		$this->expectException( \InvalidArgumentException::class );
		QrUrl::build( '9704', '1017588888', 1, 'DH1' );
	}

	public function test_rejects_empty_account() {
		$this->expectException( \InvalidArgumentException::class );
		QrUrl::build( '970436', ' - ', 1, 'DH1' );
	}

	public function test_note_is_cleaned_and_truncated() {
		$this->assertSame( 'DH12 thanh toan', QrUrl::clean_note( 'DH12 - thanh toán!' ) );
		$this->assertSame( 50, strlen( QrUrl::clean_note( str_repeat( 'A', 80 ) ) ) );
	}

	public function test_removes_vietnamese_accents() {
		$this->assertSame( 'Dang Thi Ngoc Anh', QrUrl::remove_accents( 'Đặng Thị Ngọc Ánh' ) );
		$this->assertSame( 'NGUYEN VAN UOC', QrUrl::clean_name( 'nguyễn văn ước' ) );
	}
}
