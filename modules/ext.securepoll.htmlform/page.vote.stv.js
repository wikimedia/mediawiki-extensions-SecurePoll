const STVQuestionLayout = require( './stv.vote/STVQuestionLayout.js' );

function STVDragAndDropForm( $ ) {
	let $submitButton;
	const questionLayouts = [];

	function initializeSubmitButton() {
		const $submitVoteButton = $( '#submit-vote-button' ).get( 0 );
		if ( !$submitVoteButton ) {
			return null;
		}
		return OO.ui.infuse( $submitVoteButton );
	}

	function getVoteState( layouts ) {
		let voteDone = true;
		layouts.forEach( ( questionlayout ) => {
			if ( !questionlayout.voteDone ) {
				voteDone = false;
			}
		} );
		return voteDone;
	}

	function checkVoteSuccess() {
		const vote = getVoteState( questionLayouts );
		$submitButton.setDisabled( !vote );
	}

	function initComboBoxes() {
		const $comboLayouts = $( 'body' ).find( '.securepoll-option-stv-combobox' );
		if ( $comboLayouts.length < 1 ) {
			return null;
		}
		for ( let i = 0; i < $comboLayouts.length; i++ ) {
			const comboLayout = $comboLayouts[ i ],
				comboBoxWidget = comboLayout.querySelector( '.oo-ui-comboBoxInputWidget' ),
				comboBox = OO.ui.infuse( comboBoxWidget );

			comboBox.setDir( 'auto' );
			comboBox.getMenu().items.forEach( ( item ) => {
				item.$element.prop( 'dir', 'auto' );
			} );

			const questionLayout = new STVQuestionLayout( {
				comboBox: comboBox,
				classes: [ 'securepoll-question-layout' ],
				label: 'candidates'
			} );
			questionLayout.on( 'voteStatusUpdate', () => {
				checkVoteSuccess();
			} );
			questionLayouts.push( questionLayout );

			const layout = OO.ui.infuse( comboLayout );
			layout.$element.append( questionLayout.$element );
		}
	}

	/**
	 * Setup the form submission handler and convert the ranking data provided
	 * by the draggable group into input values.
	 */
	function initForm() {
		$( 'form.oo-ui-formLayout' ).on( 'submit', ( e ) => {
			const $draggableGroup = $( '.stv-ranking-draggable-group' );

			// A map of rankings for all questions in the pool (candidate ids)
			// that will be stored in an object like so:
			// {
			//   "41": [ "42", "45" ], --> ranking of Question 1
			//   "53": [ "58", "59", "54" ], --> ranking of Question 2
			// }
			const groupedRankings = {};
			$draggableGroup.each( ( _idx, el ) => {
				const rankingData = $( el ).data( 'ranking' ).map( ( item ) => item.data );
				const candidates = $( el ).data( 'candidates' );
				const questionId = $( el ).data( 'questionId' );

				groupedRankings[ questionId ] = rankingData.map(
					( name ) => Number( candidates[ name ] )
				);
			} );

			// Filter out form field keys that don't match the option pattern.
			const optionPattern = /^securepoll_q(\d+)_opt(\d+)$/;
			const inputKeys = Array.from( new FormData( e.target ) )
				.map( ( selection ) => selection[ 0 ] )
				.filter( ( key ) => optionPattern.test( key ) );

			inputKeys.forEach( ( key ) => {
				// Extract the entity id of the question and the relative id of
				// the option. The id used for the option isn't its entity id
				// as the form expects a relative id for each option which is
				// needed for being able to rank candidates over others.
				const match = key.match( optionPattern );
				const questionId = Number( match[ 1 ] );
				const optionIndex = Number( match[ 2 ] );

				// Update the corresponding hidden value in the form to have
				// the ranking set to the selected option.
				const $select = $( `[name="${ key }"]` );
				$select.val( groupedRankings[ questionId ][ optionIndex ] || 0 );
			} );
		} );
	}

	function init() {
		$submitButton = initializeSubmitButton();
		if ( !$submitButton ) {
			return;
		}
		$submitButton.setDisabled( true );
		initComboBoxes();
		initForm();
	}

	if ( typeof QUnit === 'undefined' ) {
		$( () => {
			init();
		} );
	}

	return {
		init: init,
		initializeSubmitButton: initializeSubmitButton,
		getVoteState: getVoteState,
		initComboBoxes: initComboBoxes
	};
}

module.exports = STVDragAndDropForm( $ );
