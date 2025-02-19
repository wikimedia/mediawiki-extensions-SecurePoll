// Dynamically add inputs for column labels if they've been enabled
// The number of labels is based on the min/max range in range voting
mw.hook( 'htmlform.enhance' ).add( ( $root ) => {
	const numRegex = /^[+-]?\d+$/;

	$root.find( '.securepoll-radiorange-messages' ).each( function () {
		let minInputWidget, maxInputWidget;

		const $i = $( this );
		const $layout = $i.children( '.oo-ui-horizontalLayout' );
		const name = $i.data( 'securepollColName' );
		const cells = {};

		$i.find( 'div[data-securepoll-col-num]' ).each( function () {
			const $t = $( this );
			cells[ $t.data( 'securepollColNum' ) ] = $t;
		} );

		function addSign( min, x ) {
			if ( min < 0 && x > 0 ) {
				return '+' + x;
			} else {
				return x.toString();
			}
		}

		function changeHandler() {
			let min = minInputWidget.getNumericValue();
			let max = maxInputWidget.getNumericValue();
			if ( !numRegex.test( min ) || !numRegex.test( max ) ) {
				return;
			}
			min = +min;
			max = +max;

			for ( let i = max; i >= min; i-- ) {
				if ( !cells[ i ] ) {
					cells[ i ] = ( new OO.ui.FieldLayout(
						new OO.ui.TextInputWidget( {
							name: name + '[' + i + ']'
						} ),
						{
							align: 'top',
							label: addSign( min, i )
						}
					) ).$element;
				}
				cells[ i ].find( 'label' ).html( addSign( min, i ) );
				$layout.prepend( cells[ i ] );
			}
			cells[ max ].nextAll().detach();
		}

		for ( let $p = $i.parent(); $p.length > 0; $p = $p.parent() ) {
			const $min = $p.find( '[name$="[min-score]"]' ).closest( '.oo-ui-numberInputWidget' );
			const $max = $p.find( '[name$="[max-score]"]' ).closest( '.oo-ui-numberInputWidget' );
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
