/**
 * Payment box behaviour: copy buttons, countdown, status polling, simulator.
 *
 * Plain JavaScript, no build step, no jQuery. Settings come from
 * wp_localize_script() as window.vqrcPayment.
 */
( function () {
	'use strict';

	var cfg = window.vqrcPayment;
	var box = document.querySelector( '.vqrc-box' );
	if ( ! cfg || ! box ) {
		return;
	}

	var statusText = box.querySelector( '.vqrc-box__status-text' );
	var timerEl = box.querySelector( '.vqrc-box__timer' );
	var pollDelay = 3000; // Grows slowly so an abandoned tab does not hammer the server.
	var pollTimer = null;
	var finished = false;

	function setState( state, message ) {
		box.setAttribute( 'data-state', state );
		if ( message ) {
			statusText.textContent = message;
		}
	}

	function finish( state, message ) {
		finished = true;
		clearTimeout( pollTimer );
		setState( state, message );
	}

	/* ---------- Copy buttons ---------- */

	function copyText( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for http:// local sites where the Clipboard API is unavailable.
		var input = document.createElement( 'textarea' );
		input.value = text;
		input.setAttribute( 'readonly', '' );
		input.style.position = 'absolute';
		input.style.left = '-9999px';
		document.body.appendChild( input );
		input.select();
		document.execCommand( 'copy' );
		document.body.removeChild( input );
		return Promise.resolve();
	}

	box.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.vqrc-copy' );
		if ( ! button ) {
			return;
		}
		var label = button.textContent;
		copyText( button.getAttribute( 'data-copy' ) ).then( function () {
			button.textContent = cfg.i18n.copied;
			setTimeout( function () {
				button.textContent = label;
			}, 1500 );
		} );
	} );

	/* ---------- Countdown ---------- */

	function tick() {
		if ( ! timerEl || finished ) {
			return;
		}
		var left = Number( cfg.expiresAt ) - Math.floor( Date.now() / 1000 );
		if ( left <= 0 ) {
			timerEl.textContent = '00:00';
			// One last check: the money may have arrived in the final seconds.
			poll( true );
			return;
		}
		var m = Math.floor( left / 60 );
		var s = left % 60;
		timerEl.textContent = ( m < 10 ? '0' : '' ) + m + ':' + ( s < 10 ? '0' : '' ) + s;
		setTimeout( tick, 1000 );
	}

	/* ---------- Status polling ---------- */

	function handleStatus( data ) {
		if ( data.paid ) {
			finish( 'paid', cfg.i18n.paid );
			// Reload so WooCommerce renders the normal "paid" order page.
			setTimeout( function () {
				window.location.reload();
			}, 2500 );
			return true;
		}
		if ( data.status === 'cancelled' || data.status === 'failed' ) {
			finish( 'expired', cfg.i18n.expired );
			return true;
		}
		if ( data.received > 0 ) {
			setState( 'partial', cfg.i18n.partial );
		}
		return false;
	}

	function poll( isFinal ) {
		if ( finished ) {
			return;
		}
		var url = cfg.statusUrl + ( cfg.statusUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'key=' + encodeURIComponent( cfg.orderKey );

		fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( response ) {
				return response.ok ? response.json() : null;
			} )
			.then( function ( data ) {
				if ( data && handleStatus( data ) ) {
					return;
				}
				if ( isFinal ) {
					finish( 'expired', cfg.i18n.expired );
					return;
				}
				schedule();
			} )
			.catch( schedule );
	}

	function schedule() {
		if ( finished ) {
			return;
		}
		clearTimeout( pollTimer );
		pollTimer = setTimeout( function () {
			poll( false );
		}, pollDelay );
		pollDelay = Math.min( pollDelay + 500, 10000 );
	}

	// Check right away when the customer comes back from the banking app.
	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'visible' && ! finished ) {
			pollDelay = 3000;
			poll( false );
		}
	} );

	/* ---------- Simulator (test mode) ---------- */

	var simulateButton = box.querySelector( '.vqrc-simulate' );
	if ( simulateButton ) {
		simulateButton.addEventListener( 'click', function () {
			simulateButton.disabled = true;
			fetch( cfg.simulateUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': cfg.nonce },
			} )
				.then( function ( response ) {
					return response.json();
				} )
				.then( function ( data ) {
					if ( ! data || ! data.success ) {
						throw new Error( 'simulate' );
					}
					pollDelay = 3000;
					poll( false );
				} )
				.catch( function () {
					simulateButton.disabled = false;
					window.alert( cfg.i18n.simError );
				} );
		} );
	}

	tick();
	schedule();
} )();
