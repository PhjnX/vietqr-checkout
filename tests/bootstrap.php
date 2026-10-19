<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Unit tests cover the pure PHP classes (no WordPress loaded), so they run in
 * milliseconds. ABSPATH is defined only to get past the direct-access guard.
 *
 * @package VietQRCheckout
 */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';
