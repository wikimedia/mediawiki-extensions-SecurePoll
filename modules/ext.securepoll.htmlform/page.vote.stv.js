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

	function init() {
		$submitButton = initializeSubmitButton();
		if ( !$submitButton ) {
			return;
		}
		$submitButton.setDisabled( true );
		initComboBoxes();
	}

	// eslint-disable-next-line no-jquery/no-ready-shorthand
	$( document ).ready( () => {
		if ( typeof QUnit === 'undefined' ) {
			init();
		}

		// Convert the ranking data provided by the draggable group
		// into input values
		$( 'form' ).on( 'submit', ( e ) => {
			const $draggableGroup = $( '.stv-ranking-draggable-group' );
			// List of rankings for all questions in the pool (candidate ids)
			// It will store an array like:
			// [
			//   [ "42", "45" ], --> ranking of Question 1
			//   [ "58", "59", "54" ], --> ranking of Question 2
			// ]
			const groupedRankings = [];
			$draggableGroup.each( ( _idx, el ) => {
				const rankingData = $( el ).data( 'ranking' ).map( ( item ) => item.data );
				const candidatess = $( el ).data( 'candidates' );
				groupedRankings.push( rankingData.map( ( name ) => candidatess[ name ] ) );
			} );

			// Get all option input keys in alphabetical order, as this
			// will correspond to an ordered ranked list. The keys can be
			// dumbly sorted because they follow a known format (securepoll_q{id}_rank{i})
			// and we ensured `id` and `i` always have fixed 7 digits (ie: '0000042')
			const formData = new FormData( e.target );
			let inputKeys = [];
			formData.forEach( ( _formDataValue, formDataKey ) => {
				inputKeys.push( formDataKey );
			} );
			const optionLikeRegExp = /^securepoll_q\d+_opt\d+$/;
			inputKeys = inputKeys
				.filter( ( key ) => optionLikeRegExp.test( key ) )
				.sort( ( strA, strB ) => String( strA ).localeCompare( String( strB ) ) );

			// Assign each ranking (grouped and ordered by question) to its
			// sequentially ordered input and fill out any remaining inputs with 0
			let groupIdx = -1;
			let lastQuestionGroup = null;
			let optIdx = 0;
			inputKeys.forEach( ( key ) => {
				// Consider that key is a string like `securepoll_q0000001_opt_0000002`
				// we know the question group is the first 19 characters of the key
				if ( lastQuestionGroup !== key.slice( 0, 20 ) ) {
					groupIdx++;
					lastQuestionGroup = key.slice( 0, 20 );
					optIdx = 0;
				}

				const $select = $( '[name="' + key + '"]' );
				if ( groupedRankings[ groupIdx ][ optIdx ] ) {
					$select.val( groupedRankings[ groupIdx ][ optIdx ] );
				} else {
					$select.val( 0 );
				}

				optIdx++;
			} );
		} );
	} );

	return {
		initializeSubmitButton: initializeSubmitButton,
		getVoteState: getVoteState,
		initComboBoxes: initComboBoxes
	};
}

module.exports = STVDragAndDropForm( $ );
