/**
 * Registers VietQR in the WooCommerce Checkout block.
 *
 * Written with wp.element.createElement instead of JSX so the plugin needs no
 * build step. Dependencies (wc-blocks-registry, wc-settings, wp-element,
 * wp-html-entities) are declared in BlocksSupport.php.
 */
( function () {
	'use strict';

	var registry = window.wc.wcBlocksRegistry;
	var getSetting = window.wc.wcSettings.getSetting;
	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities.decodeEntities;

	var settings = getSetting( 'vietqr_checkout_data', {} );
	var title = decode( settings.title || 'VietQR' );

	function Content() {
		return el( 'p', null, decode( settings.description || '' ) );
	}

	function Label( props ) {
		var PaymentMethodLabel = props.components.PaymentMethodLabel;
		return el(
			'span',
			{ style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
			settings.icon ? el( 'img', { src: settings.icon, alt: '', width: 20, height: 20 } ) : null,
			el( PaymentMethodLabel, { text: title } )
		);
	}

	registry.registerPaymentMethod( {
		name: 'vietqr_checkout',
		label: el( Label ),
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true; // Availability is already decided on the server.
		},
		ariaLabel: title,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )();
