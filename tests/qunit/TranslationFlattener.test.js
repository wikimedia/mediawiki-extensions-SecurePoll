( function () {
	const TranslationFlattener = require( 'ext.securepoll.htmlform/TranslationFlattener.js' );

	QUnit.module( 'ext.securepoll.translationFlattener.test' );

	QUnit.test( 'Flatten parser test', ( assert ) => {
		const done = assert.async();
		const translationFlattener = new TranslationFlattener();

		const getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/JSONWithMultipleQuestions.json' );
		getOrigin.done( ( data ) => {
			const flattenedContent = translationFlattener.flattenData( data, 81 );
			const getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/ExpectedDataWithMultipleQuestions.json' );
			getExpected.done( ( expectedContent ) => {
				assert.deepEqual( flattenedContent, expectedContent, 'flattenedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Flatten parser test with wrong data', ( assert ) => {
		const done = assert.async();
		const translationFlattener = new TranslationFlattener();

		const getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/JSONWithWrongData.json' );
		getOrigin.done( ( data ) => {
			const flattenedContent = translationFlattener.flattenData( data, 81 );
			const getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/ExpectedDataWithWrongData.json' );
			getExpected.done( ( expectedContent ) => {
				assert.deepEqual( flattenedContent, expectedContent, 'flattenedContent' );
				done();
			} );
		} );
	} );

}() );
