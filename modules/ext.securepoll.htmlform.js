/**
 * Utility functions for HTMLForm date picker
 */
( function ( mw, $ ) {

	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		var inputs, i;

		inputs = $root.find( 'input[type=date]' );
		if ( inputs.length === 0 ) {
			return;
		}

		// Assume that if the browser implements validation for <input type=date>
		// (so it rejects "bogus" as a value) then it supports a date picker too.
		i = document.createElement( 'input' );
		i.setAttribute( 'type', 'date' );
		i.value = 'bogus';
		if ( i.value === 'bogus' ) {
			mw.loader.using( 'jquery.ui.datepicker', function () {
				inputs.each( function () {
					var $i = $( this );
					// Reset the type, Just In Case
					$i.prop( 'type', 'text' );
					$i.datepicker( {
						dateFormat: 'yy-mm-dd',
						constrainInput: true,
						showOn: 'focus',
						changeMonth: true,
						changeYear: true,
						showButtonPanel: true,
						minDate: $i.data( 'min' ),
						maxDate: $i.data( 'max' ),
					} );
				} );
			} );
		}
	} );

	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		var numRegex = /^[+-]?\d+$/;

		$root.find( '.securepoll-radiorange-messages' ).each( function() {
			var $p, $i, $labelRow, $inputRow, $min, $max, name, size, cells;

			$i = $( this );
			$labelRow = $i.find( '.securepoll-label-row' );
			$inputRow = $i.find( '.securepoll-input-row' );
			name = $i.data( 'securepollColName' );
			size = $i.data( 'securepollInputSize' );

			cells = {};
			$labelRow.find( 'th' ).each( function () {
				var $t = $( this ),
					n = $t.data( 'securepollColNum' );

				if ( !cells[n] ) {
					cells[n] = {};
				}
				cells[n].label = $t;
			} );
			$inputRow.find( 'td' ).each( function () {
				var $t = $( this ),
					n = $t.data( 'securepollColNum' );

				if ( !cells[n] ) {
					cells[n] = {};
				}
				cells[n].input = $t;
			} );

			function changeHandler() {
				var i, min, max, $input;

				min = $min.val();
				max = $max.val();
				if ( !numRegex.test( min ) || !numRegex.test( max ) ) {
					return;
				}
				min = +min;
				max = +max;

				for ( i = max; i >= min; i-- ) {
					if ( !cells[i] ) {
						cells[i] = {};
					}
					if ( !cells[i].label ) {
						cells[i].label = $( '<th>' )
						cells[i].label.data( 'securepollColNum', i )
							.text( i );
					}
					if ( !cells[i].input ) {
						$input = $( '<input>' );
						$input.attr( {
							'type': 'text',
							'name': name + '[' + i + ']',
							'size': size,
						} );
						cells[i].input = $( '<td>' );
						cells[i].input.data( 'securepollColNum', i )
							.append( $input );
					}

					$labelRow.prepend( cells[i].label );
					$inputRow.prepend( cells[i].input );
				}

				cells[max].label.nextAll().detach();
				cells[max].input.nextAll().detach();
			}

			for ( $p = $i.parent(); $p.length > 0; $p = $p.parent() ) {
				$min = $p.find( '[name$="[min-score]"]' );
				$max = $p.find( '[name$="[max-score]"]' );
				if ( $min.length > 0 && $max.length > 0 ) {
					$min.change( changeHandler );
					$max.change( changeHandler );
					changeHandler();
					break;
				}
			}
		} );
	} );

}( mediaWiki, jQuery ) );
