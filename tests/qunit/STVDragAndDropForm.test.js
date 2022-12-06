( function () {
	QUnit.module( 'ext.securepoll.stv.vote.test', QUnit.newMwEnvironment() );

	var STVDragAndDropForm = require( 'ext.securepoll.htmlform/page.vote.stv.js' );
	var STVQuestionLayout = require( 'ext.securepoll.htmlform/stv.vote/STVQuestionLayout.js' );
	QUnit.test( 'initializeSubmitButton returns correct button element', function ( assert ) {
		var data = {
			_: 'OO.ui.ButtonInputWidget',
			type: 'button',
			label: 'Submit vote',
			classes: [
				'submit-vote-button'
			]
		};
		var buttons = document.createElement( 'div' );
		buttons.classList.add( 'submit-vote-button' );
		buttons.setAttribute( 'data-ooui', JSON.stringify( data ) );
		$( '#qunit-fixture' ).append( buttons );

		var button = STVDragAndDropForm.initializeSubmitButton();
		assert.ok( button !== null, 'Button element should not be null' );

		buttons.remove();
	} );

	QUnit.test( 'getVoteState', function ( assert ) {
		var layouts = [
			new STVQuestionLayout( {
				comboBox: new OO.ui.ComboBoxInputWidget( {
					options: [],
					data: {
						selectedItems: []
					}
				} ),
				voteDone: false
			} ),
			new STVQuestionLayout( {
				comboBox: new OO.ui.ComboBoxInputWidget( {
					options: [],
					data: {
						selectedItems: []
					}
				} ),
				voteDone: true
			} ),
			new STVQuestionLayout( {
				comboBox: new OO.ui.ComboBoxInputWidget( {
					options: [],
					data: {
						selectedItems: []
					}
				} ),
				voteDone: false
			} )
		];

		var vote = STVDragAndDropForm.getVoteState( layouts );
		assert.ok( !vote, 'Vote should be false' );
	} );
}() );
