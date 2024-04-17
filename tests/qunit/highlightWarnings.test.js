( function () {
	QUnit.module( 'ext.securepoll.highlightWarnings.test', QUnit.newMwEnvironment() );

	var highlighting = require( 'ext.securepoll.htmlform/page.vote.highlightWarnings.js' );
	QUnit.test( 'initializeButton returns correct button element', function ( assert ) {
		var data = {
			_: 'OO.ui.ButtonInputWidget',
			type: 'button',
			label: 'Show only warnings',
			data: [ {
				securepollq37: [
					'securepoll-q37_opt39',
					'securepoll-q37_opt40',
					'securepoll-q37_opt41'
				]
			} ],
			classes: [
				'highlight-warnings-button'
			]
		};
		var buttons = document.createElement( 'div' );
		buttons.setAttribute( 'data-ooui', JSON.stringify( data ) );
		buttons.classList.add( 'highlight-warnings-button' );
		buttons.setAttribute( 'data-ooui', JSON.stringify( data ) );
		$( '#qunit-fixture' ).append( buttons );

		var button = highlighting.initializeButton();
		assert.ok( button !== null, 'Button element should not be null' );

		buttons.remove();
	} );

	QUnit.test( 'getFieldSets returns correct fieldsets', function ( assert ) {
		var fieldset1 = document.createElement( 'fieldset' );
		var fieldset2 = document.createElement( 'fieldset' );
		var formLayout = document.createElement( 'fieldset' );
		formLayout.classList.add( 'oo-ui-formLayout' );
		formLayout.append( fieldset1 );
		formLayout.append( fieldset2 );
		$( '#qunit-fixture' ).append( formLayout );

		var fieldSets = highlighting.getFieldSets();
		assert.strictEqual( fieldSets.length, 2, 'Should return two fieldsets' );

		formLayout.remove();
	} );

	QUnit.test( 'showAllFieldSets shows all fieldsets and their rows', function ( assert ) {
		var fieldset1 = document.createElement( 'fieldset' );
		var fieldset2 = document.createElement( 'fieldset' );
		var row1 = document.createElement( 'div' );
		row1.classList.add( 'securepoll-ballot-row' );
		var row2 = document.createElement( 'div' );
		row2.classList.add( 'securepoll-ballot-row' );
		fieldset1.append( row1 );
		fieldset2.append( row2 );

		var formLayout = document.createElement( 'fieldset' );
		formLayout.classList.add( 'oo-ui-formLayout' );
		formLayout.append( fieldset1 );
		formLayout.append( fieldset2 );
		$( '#qunit-fixture' ).append( formLayout );

		highlighting.showAllFieldSets();

		assert.ok( !fieldset1.hidden, 'Fieldset 1 should be visible' );
		assert.ok( !fieldset2.hidden, 'Fieldset 2 should be visible' );
		assert.ok( !fieldset1.getElementsByClassName( 'securepoll-ballot-row' )[ 0 ].hidden,
			'Rows in Fieldset 1 should be visible' );
		assert.ok( !fieldset2.getElementsByClassName( 'securepoll-ballot-row' )[ 0 ].hidden,
			'Rows in Fieldset 2 should be visible' );

		formLayout.remove();
	} );

	QUnit.test( 'hideAllFieldSets hides all fieldsets', function ( assert ) {
		var fieldset1 = document.createElement( 'fieldset' );
		var fieldset2 = document.createElement( 'fieldset' );
		var row1 = document.createElement( 'div' );
		row1.classList.add( 'securepoll-ballot-row' );
		var row2 = document.createElement( 'div' );
		row2.classList.add( 'securepoll-ballot-row' );
		fieldset1.append( row1 );
		fieldset2.append( row2 );

		var formLayout = document.createElement( 'fieldset' );
		formLayout.classList.add( 'oo-ui-formLayout' );
		formLayout.append( fieldset1 );
		formLayout.append( fieldset2 );
		$( '#qunit-fixture' ).append( formLayout );

		highlighting.hideAllFieldSets();

		assert.ok( fieldset1.hidden, 'Fieldset 1 should be hidden' );
		assert.ok( fieldset2.hidden, 'Fieldset 2 should be hidden' );

		formLayout.remove();
	} );

}() );
