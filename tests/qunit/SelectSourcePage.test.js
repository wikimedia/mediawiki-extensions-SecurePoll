( function () {
	QUnit.module( 'ext.securepoll.SelectSourcePage', ( hooks ) => {
		const SelectSourcePage = require( 'ext.securepoll.htmlform/translation/pages/SelectSourcePage.js' );
		let page;

		hooks.beforeEach( () => {
			page = new SelectSourcePage( 'SelectSourcePage', {
				expanded: false
			} );
		} );

		QUnit.test( 'sourceInput widget is present and visible', ( assert ) => {
			assert.strictEqual(
				page.sourceInput.constructor,
				OO.ui.TextInputWidget,
				'sourceInput is an OO.ui.TextInputWidget'
			);

			assert.strictEqual(
				page.sourceInput.$element.find( 'input' ).length,
				1,
				'Input element is rendered exactly once'
			);

			assert.strictEqual(
				page.sourceInput.$element.find( 'input' ).attr( 'placeholder' ),
				'https://meta.wikimedia.org/w/api.php',
				'Placeholder text is correct'
			);
		} );

		QUnit.test( 'getSourceApi returns the current input value', ( assert ) => {
			const testUrl = 'https://example.org/w/api.php';

			page.sourceInput.setValue( testUrl );

			assert.strictEqual(
				page.getSourceApi(),
				testUrl,
				'getSourceApi returns the correct input value'
			);
		} );
	} );
}() );
