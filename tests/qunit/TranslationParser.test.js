( function () {
	var TranslationParser = require( 'ext.securepoll.htmlform/TranslationParser.js' );

	QUnit.module( 'ext.securepoll.translationParser.test' );

	QUnit.test( 'Parser test with correct tags', function ( assert ) {
		var done = assert.async();
		var translationParser = new TranslationParser();

		var getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithCorrectTags.txt' );
		getOrigin.done( function ( data ) {
			var parseContent = translationParser.parseContent( data );
			var getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithCorrectTags.json' );
			getExpected.done( function ( expectedContent ) {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Parser test with some missing end tags', function ( assert ) {
		var done = assert.async();
		var translationParser = new TranslationParser();

		var getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithSomeMissingEndTag.txt' );
		getOrigin.done( function ( data ) {
			var parseContent = translationParser.parseContent( data );
			var getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithSomeMissingEndTag.json' );
			getExpected.done( function ( expectedContent ) {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Parser test with no end tags', function ( assert ) {
		var done = assert.async();
		var translationParser = new TranslationParser();

		var getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithNoEndTags.txt' );
		getOrigin.done( function ( data ) {
			var parseContent = translationParser.parseContent( data );
			var getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithNoEndTags.json' );
			getExpected.done( function ( expectedContent ) {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Parser test with multiple questions', function ( assert ) {
		var done = assert.async();
		var translationParser = new TranslationParser();

		var getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithMultipleQuestions.txt' );
		getOrigin.done( function ( data ) {
			var parseContent = translationParser.parseContent( data );
			var getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithMultipleQuestions.json' );
			getExpected.done( function ( expectedContent ) {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

}() );
