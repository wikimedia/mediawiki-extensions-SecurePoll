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

	function reset() {
		$submitButton = null;
		questionLayouts.splice( 0, questionLayouts.length );
	}

	function initQuestionLayouts() {
		const $questionOuterPanels = $( 'body' ).find( '.securepoll-option-stv-panel-outer' );
		if ( $questionOuterPanels.length < 1 ) {
			return null;
		}

		for ( const questionOuterPanel of $questionOuterPanels ) {
			const outerPanel = OO.ui.infuse( questionOuterPanel );

			const questionLayout = new STVQuestionLayout( {
				outerPanel,
				classes: [ 'securepoll-question-layout' ]
			} );

			questionLayout.on( 'voteStatusUpdate', () => {
				checkVoteSuccess();
			} );
			questionLayouts.push( questionLayout );

			outerPanel.$element.append( questionLayout.$element[ 0 ] );
		}
	}

	/**
	 * Setup the form submission handler and convert the ranking data provided
	 * by the draggable group into input values.
	 */
	function initForm() {
		$( 'form.oo-ui-formLayout' ).on( 'submit', ( e ) => {
			const $draggableGroup = $( '.securepoll-question-stv-panel-ranked .stv-ranking-draggable-group' );

			// A map of rankings for all questions in the pool (candidate ids)
			// that will be stored in an object like so:
			// {
			//   "41": [ "42", "45" ], --> ranking of Question 1
			//   "53": [ "58", "59", "54" ], --> ranking of Question 2
			// }
			const groupedRankings = {};
			$draggableGroup.each( ( _idx, el ) => {
				const rankingData = $( el ).data( 'ranking' )
					.map( ( draggableItemWidget ) => draggableItemWidget.name );
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
		// If body has the mw-mf class, site is using mobile view via MobileFrontend
		// Abort initialization in order to return the nojs mode instead, as drag and drop
		// doesn't work on mobile. See T400243.
		if ( $( 'body.mw-mf' ).length ) {
			return;
		}

		reset();
		$submitButton = initializeSubmitButton();
		if ( !$submitButton ) {
			return;
		}
		$submitButton.setDisabled( true );
		initQuestionLayouts();
		initForm();
	}

	if ( typeof QUnit === 'undefined' ) {
		$( () => {
			init();
		} );
	}

	return {
		questionLayouts,
		init: init,
		initializeSubmitButton: initializeSubmitButton,
		getVoteState: getVoteState,
		initQuestionLayouts
	};
}

module.exports = STVDragAndDropForm( $ );
