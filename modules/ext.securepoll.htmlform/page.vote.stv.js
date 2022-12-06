var STVQuestionLayout = require( './stv.vote/STVQuestionLayout.js' );

function STVDragAndDropForm( $ ) {
	var $submitButton,
		questionLayouts = [];

	function initializeSubmitButton() {
		var $submitButtons = $( 'body' ).find( '.submit-vote-button' );
		if ( $submitButtons.length < 1 ) {
			return null;
		}
		return OO.ui.infuse( $submitButtons[ 0 ] );
	}

	function getVoteState( layouts ) {
		var voteDone = true;
		layouts.forEach( function ( questionlayout ) {
			if ( !questionlayout.voteDone ) {
				voteDone = false;
			}
		} );
		return voteDone;
	}

	function checkVoteSuccess() {
		var vote = getVoteState( questionLayouts );
		$submitButton.setDisabled( !vote );
	}

	function initComboBoxes() {
		var $comboLayouts = $( 'body' ).find( '.securepoll-option-stv-combobox' );
		if ( $comboLayouts.length < 1 ) {
			return null;
		}
		for ( var i = 0; i < $comboLayouts.length; i++ ) {
			var comboLayout = $comboLayouts[ i ],
				comboBoxWidget = comboLayout.querySelector( '.oo-ui-comboBoxInputWidget' ),
				comboBox = OO.ui.infuse( comboBoxWidget );

			var questionLayout = new STVQuestionLayout( {
				comboBox: comboBox,
				classes: [ 'securepoll-question-layout' ],
				label: 'candidates'
			} );
			questionLayout.on( 'voteStatusUpdate', function () {
				checkVoteSuccess();
			} );
			questionLayouts.push( questionLayout );

			var layout = OO.ui.infuse( comboLayout );
			layout.$element.append( questionLayout.$element );
		}
	}

	function init() {
		$submitButton = initializeSubmitButton();
		if ( !$submitButton ) {
			return;
		}
		$submitButton.setDisabled( true );
		initComboBoxes();
	}

	// eslint-disable-next-line no-jquery/no-ready-shorthand
	$( document ).ready( function () {
		if ( typeof QUnit === 'undefined' ) {
			init();
		}
	} );

	return {
		initializeSubmitButton: initializeSubmitButton,
		getVoteState: getVoteState,
		initComboBoxes: initComboBoxes
	};
}

module.exports = STVDragAndDropForm( $ );
