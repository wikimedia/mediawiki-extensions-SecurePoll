( function () {

	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePoll' ) {
		// Dynamically add inputs for column labels if they've been enabled
		// The number of labels is based on the min/max range in range voting
		mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
			var numRegex = /^[+-]?\d+$/;

			$root.find( '.securepoll-radiorange-messages' ).each( function () {
				var $p, $i, $labelRow, $inputRow,
					minInputWidget, maxInputWidget, $min, $max,
					name, size, cells;

				$i = $( this );
				$labelRow = $i.find( '.securepoll-label-row' );
				$inputRow = $i.find( '.securepoll-input-row' );
				name = $i.data( 'securepollColName' );
				size = $i.data( 'securepollInputSize' );

				cells = {};
				$labelRow.find( 'th' ).each( function () {
					var $t = $( this ),
						n = $t.data( 'securepollColNum' );

					if ( !cells[ n ] ) {
						cells[ n ] = {};
					}
					cells[ n ].$label = $t;
				} );
				$inputRow.find( 'td' ).each( function () {
					var $t = $( this ),
						n = $t.data( 'securepollColNum' );

					if ( !cells[ n ] ) {
						cells[ n ] = {};
					}
					cells[ n ].$input = $t;
				} );

				function addSign( min, x ) {
					if ( min < 0 && x > 0 ) {
						return '+' + x;
					} else {
						return x;
					}
				}

				function changeHandler() {
					var i, min, max, $input;

					min = minInputWidget.getNumericValue();
					max = maxInputWidget.getNumericValue();
					if ( !numRegex.test( min ) || !numRegex.test( max ) ) {
						return;
					}
					min = +min;
					max = +max;

					for ( i = max; i >= min; i-- ) {
						if ( !cells[ i ] ) {
							cells[ i ] = {};
						}
						if ( !cells[ i ].$label ) {
							cells[ i ].$label = $( '<th>' );
							cells[ i ].$label.data( 'securepollColNum', i );
						}
						cells[ i ].$label.text( addSign( min, i ) );
						if ( !cells[ i ].$input ) {
							$input = $( '<input>' );
							$input.attr( {
								type: 'text',
								name: name + '[' + i + ']',
								size: size
							} );
							cells[ i ].$input = $( '<td>' );
							cells[ i ].$input.data( 'securepollColNum', i )
								.append( $input );
						}

						$labelRow.prepend( cells[ i ].$label );
						$inputRow.prepend( cells[ i ].$input );
					}

					cells[ max ].$label.nextAll().detach();
					cells[ max ].$input.nextAll().detach();
				}

				for ( $p = $i.parent(); $p.length > 0; $p = $p.parent() ) {
					$min = $p.find( '[name$="[min-score]"]' ).closest( '.oo-ui-numberInputWidget' );
					$max = $p.find( '[name$="[max-score]"]' ).closest( '.oo-ui-numberInputWidget' );
					if ( $min.length > 0 && $max.length > 0 ) {
						minInputWidget = OO.ui.infuse( $min );
						maxInputWidget = OO.ui.infuse( $max );
						minInputWidget.on( 'change', changeHandler );
						maxInputWidget.on( 'change', changeHandler );
						changeHandler();
						break;
					}
				}
			} );
		} );
	}

	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePollLog' ) {
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
	}

}() );
