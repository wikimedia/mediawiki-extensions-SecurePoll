( function () {
	const ImportDialog = require( 'ext.securepoll.htmlform/translation/dialog/ImportDialog.js' );

	QUnit.module( 'ext.securepoll.ImportDialog.test', ( hooks ) => {
		let dialog, originalForeignApi, windowManager;

		hooks.beforeEach( () => {
			originalForeignApi = mw.ForeignApi;

			windowManager = new OO.ui.WindowManager();
			$( document.body ).append( windowManager.$element );

			dialog = new ImportDialog( { id: 'test-dialog' } );
			windowManager.addWindows( [ dialog ] );
			dialog.initialize();
		} );

		hooks.afterEach( () => {
			mw.ForeignApi = originalForeignApi;

			dialog.$element.remove();
			dialog = null;
		} );

		QUnit.test( 'Initial state is valid', ( assert ) => {
			assert.strictEqual( dialog.source, '', 'Source should start empty' );
			assert.strictEqual(
				dialog.sourceApiValidated,
				false,
				'Validation flag should be false'
			);
		} );

		QUnit.test( 'Calling switchPage changes state', ( assert ) => {
			dialog.switchPage( 'SelectSourcePage' );
			assert.strictEqual(
				dialog.booklet.getCurrentPageName(),
				'SelectSourcePage'
			);
		} );

		QUnit.test( 'validateSourceApi returns true when response contains query', ( assert ) => {
			const done = assert.async();

			mw.ForeignApi = function () {
				return {
					get: () => Promise.resolve( { query: {} } )
				};
			};

			dialog.validateSourceApi( 'https://example.org/w/api.php' ).then( ( result ) => {
				assert.strictEqual( result, true, 'validateSourceApi returns true when query is present' );
				done();
			} );
		} );

		QUnit.test( 'validateSourceApi returns false when response does not contain query', ( assert ) => {
			const done = assert.async();

			mw.ForeignApi = function () {
				return {
					get: () => Promise.resolve( { batchcomplete: true } )
				};
			};

			dialog.validateSourceApi( 'https://example.org/w/api.php' ).then( ( result ) => {
				assert.strictEqual( result, false, 'validateSourceApi returns false when query is missing' );
				done();
			} );
		} );

		QUnit.test( 'validateSourceApi returns false on API error (rejected promise)', ( assert ) => {
			const done = assert.async();

			mw.ForeignApi = function () {
				return {
					get: () => Promise.reject( new Error( 'Network error' ) )
				};
			};

			dialog.validateSourceApi( 'https://example.org/w/api.php' ).then( ( result ) => {
				assert.strictEqual( result, false, 'validateSourceApi returns false on API error' );
				done();
			} );
		} );
	} );
}() );
