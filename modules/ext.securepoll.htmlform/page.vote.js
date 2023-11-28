// Infuse widgets as necessary
$( function () {
	var $dropdownInputWidgets = $( '.securepoll-stvballot-option-dropdown' );
	$dropdownInputWidgets.each( function () {
		var $dropdown = $( this ).closest( '.oo-ui-dropdownInputWidget' );

		// Make sure the element we are trying to infuse exists
		// if we have anything more than 1 specific element
		// something has gone seriously gone wrong, Abort!
		if ( $dropdown.length !== 1 ) {
			mw.log.warn( 'Unable to find a specific element to infuse for ', $dropdown );
			return;
		}
		OO.ui.infuse( $dropdown );
	} );
} );
