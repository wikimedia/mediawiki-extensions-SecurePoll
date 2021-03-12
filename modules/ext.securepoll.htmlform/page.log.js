// Ensure form fields are enabled/disabled according to selected log types
$( function () {
	var actionTypeWidget = OO.ui.infuse( $( '#mw-input-type' ) ),
		targetWidget = OO.ui.infuse( $( '#mw-input-target' ) ),
		actionsWidget = OO.ui.infuse( $( '.securepolllog-actions-radio.mw-htmlform-field-HTMLRadioField' ) );

	function updateDisabledFields() {
		if ( actionTypeWidget.getValue() === 'all' ) {
			targetWidget.setDisabled( false );
			actionsWidget.fieldWidget.setDisabled( true );
		} else if ( actionTypeWidget.getValue() === 'voter' ) {
			targetWidget.setDisabled( true );
			actionsWidget.fieldWidget.setDisabled( true );
		} else if ( actionTypeWidget.getValue() === 'admin' ) {
			targetWidget.setDisabled( false );
			actionsWidget.fieldWidget.setDisabled( false );
		}
	}

	actionTypeWidget.on( 'change', updateDisabledFields );
	updateDisabledFields();
} );
