( function () {
	'use strict';
	const form = document.querySelector( '.ironcreed-request-log .icrl-connection' );
	if ( form ) {
		const updateMode = function () {
			const choice = form.querySelector( '[name="connection_mode"]:checked' );
			form.querySelectorAll( '.icrl-mode' ).forEach( function ( panel ) {
				const active = panel.dataset.mode === choice.value;
				panel.hidden = ! active;
				panel.querySelectorAll( 'input' ).forEach( function ( input ) {
					input.disabled = ! active;
				} );
			} );
		};
		form.querySelectorAll( '[name="connection_mode"]' ).forEach( function ( input ) {
			input.addEventListener( 'change', updateMode );
		} );
		updateMode();
	}

	if ( window.location.hash.indexOf( '#icrl-help-' ) === 0 ) {
		const details = document.getElementById( window.location.hash.slice( 1 ) );
		if ( details && details.tagName === 'DETAILS' ) {
			details.open = true;
			details.querySelector( 'summary' ).focus();
		}
	}

	const refresh = document.getElementById( 'ironcreed-refresh' );
	if ( ! refresh ) {
		return;
	}
	// Local display refresh only: this code never calls a provider action.
	const key = 'ironcreed-request-log-refresh:' + window.location.pathname;
	let timer;
	try {
		const saved = window.sessionStorage.getItem( key );
		if ( [ '0', '15', '30', '60' ].includes( saved ) ) {
			refresh.value = saved;
		}
	} catch ( error ) {
		// Storage can be disabled by the browser.
	}
	const schedule = function () {
		window.clearTimeout( timer );
		if ( ! document.hidden && [ '15', '30', '60' ].includes( refresh.value ) ) {
			timer = window.setTimeout( function () {
				// Preserve filters and paging; stop while the user confirms a clear.
				if ( ! document.querySelector( '[name="confirm_clear"]:checked' ) ) {
					window.location.reload();
				} else {
					schedule();
				}
			}, Number( refresh.value ) * 1000 );
		}
	};
	refresh.addEventListener( 'change', function () {
		try {
			window.sessionStorage.setItem( key, refresh.value );
		} catch ( error ) {
			// Refresh still works without persistence.
		}
		schedule();
	} );
	document.addEventListener( 'visibilitychange', schedule );
	schedule();
}() );
