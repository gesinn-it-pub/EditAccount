$( function () {
	'use strict';
	$( '#EditAccountForm' ).find( 'input[type="text"]' ).on( 'focus', function () { // eslint-disable-line no-jquery/no-global-selector
		if ( $( this ).siblings( 'input[type="radio"]' ).length ) {
			$( this ).siblings( 'input[type="radio"]' ).prop( 'checked', true );
		}
	} );
} );
