$( document ).ready( function() {
	'use strict';
	$( '#EditAccountForm' ).find( 'input[type="text"]' ).focus( function() {
		if ( $( this ).siblings( 'input[type="radio"]' ).length ) {
			$( this ).siblings( 'input[type="radio"]' ).prop( 'checked', true );
		}
	} );
});
