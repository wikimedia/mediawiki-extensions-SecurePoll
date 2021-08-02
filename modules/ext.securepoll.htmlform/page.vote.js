// Infuse widgets as necessary
$( function () {
	var $dropdownInputWidgets = $( '[name^="securepoll_q"]' );
	$dropdownInputWidgets.each( function () {
		var $dropdown = $( this );
		OO.ui.infuse( $dropdown.closest( '.oo-ui-dropdownInputWidget' ) );
	} );
} );
