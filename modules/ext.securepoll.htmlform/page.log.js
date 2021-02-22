( function () {

	// Ensure form fields are enabled/disabled according to selected log types
	$( function () {
		var actionTypeWidget = OO.ui.infuse( $( '#mw-input-type' ) ),
			targetWidget = OO.ui.infuse( $( '#mw-input-target' ) );

		function updateDisabledFields() {
			if ( actionTypeWidget.getValue() === 'voter' ) {
				targetWidget.setDisabled( true );
			} else {
				targetWidget.setDisabled( false );
			}
		}

		actionTypeWidget.on( 'change', updateDisabledFields );
		updateDisabledFields();
	} );

}() );
