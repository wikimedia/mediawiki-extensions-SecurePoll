function highlightWarnings( $ ) {

	function initializeButton() {
		const $buttons = $( '.highlight-warnings-button' );
		if ( $buttons.length < 1 ) {
			return null;
		}

		return OO.ui.infuse( $buttons[ 0 ] );
	}

	function getFieldSets() {
		return $( '.oo-ui-formLayout' ).find( 'fieldset' );
	}

	function parseWarningRows( $button ) {
		return JSON.parse( $button.data );
	}

	function toggleButtonState( $button, isActive ) {
		$button.active = !isActive;
		$button.$button[ 0 ].innerText = isActive ? mw.msg( 'securepoll-ballot-show-warnings' ) :
			mw.msg( 'securepoll-ballot-show-all' );
	}

	function toggleFieldSets( isActive, warningRows ) {
		if ( isActive ) {
			showAllFieldSets();
		} else {
			hideAllFieldSets();
			for ( const key in warningRows ) {
				if ( Object.prototype.hasOwnProperty.call( warningRows, key ) ) {
					const question = key;
					const options = warningRows[ key ];
					const $targetFieldSet = $( 'body .' + question );
					$targetFieldSet.prop( 'hidden', false );
					const $rows = $targetFieldSet.find( '.securepoll-ballot-row' );
					$rows.prop( 'hidden', true );
					for ( let i = 0; i < options.length; i++ ) {
						const option = options[ i ];
						const $targetRow = $targetFieldSet.find( '.' + option );
						$targetRow.prop( 'hidden', false );
					}
				}
			}
		}
	}

	function showAllFieldSets() {
		getFieldSets().prop( 'hidden', false );
		const $rows = getFieldSets().find( '.securepoll-ballot-row' );
		$rows.prop( 'hidden', false );
	}

	function hideAllFieldSets() {
		getFieldSets().prop( 'hidden', true );
	}

	function init() {
		const $button = initializeButton();
		if ( !$button ) {
			return;
		}

		const warningRows = parseWarningRows( $button );
		let isActive = true;

		$button.on( 'click', () => {
			isActive = !isActive;
			toggleFieldSets( isActive, warningRows );
			toggleButtonState( $button, isActive );
		} );
	}

	// eslint-disable-next-line no-jquery/no-ready-shorthand
	$( document ).ready( () => {
		if ( typeof QUnit === 'undefined' ) {
			init();
		}
	} );

	return {
		initializeButton: initializeButton,
		getFieldSets: getFieldSets,
		showAllFieldSets: showAllFieldSets,
		hideAllFieldSets: hideAllFieldSets
	};
}

module.exports = highlightWarnings( $ );
