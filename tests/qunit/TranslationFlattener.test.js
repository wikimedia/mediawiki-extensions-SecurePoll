( function () {
	var TranslationFlattener = require( 'ext.securepoll.htmlform/TranslationFlattener.js' );

	QUnit.module( 'ext.securepoll.translationFlattener.test' );

	QUnit.test( 'Flatten parser test', function ( assert ) {
		var done = assert.async();
		var translationFlattener = new TranslationFlattener();

		var getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/JSONWithMultipleQuestions.json' );
		getOrigin.done( function ( data ) {
			var flattenedContent = translationFlattener.flattenData( data, 81 );
			var getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/ExpectedDataWithMultipleQuestions.json' );
			getExpected.done( function ( expectedContent ) {
				assert.deepEqual( flattenedContent, expectedContent, 'flattenedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Flatten parser test with wrong data', function ( assert ) {
		var done = assert.async();
		var translationFlattener = new TranslationFlattener();

		var getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/JSONWithWrongData.json' );
		getOrigin.done( function ( data ) {
			var flattenedContent = translationFlattener.flattenData( data, 81 );
			var getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/flattener/ExpectedDataWithWrongData.json' );
			getExpected.done( function ( expectedContent ) {
				assert.deepEqual( flattenedContent, expectedContent, 'flattenedContent' );
				done();
			} );
		} );
	} );

}() );
