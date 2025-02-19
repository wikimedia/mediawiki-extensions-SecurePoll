( function () {
	const TranslationParser = require( 'ext.securepoll.htmlform/TranslationParser.js' );

	QUnit.module( 'ext.securepoll.translationParser.test' );

	QUnit.test( 'Parser test with correct tags', ( assert ) => {
		const done = assert.async();
		const translationParser = new TranslationParser();

		const getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithCorrectTags.txt' );
		getOrigin.done( ( data ) => {
			const parseContent = translationParser.parseContent( data );
			const getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithCorrectTags.json' );
			getExpected.done( ( expectedContent ) => {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Parser test with some missing end tags', ( assert ) => {
		const done = assert.async();
		const translationParser = new TranslationParser();

		const getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithSomeMissingEndTag.txt' );
		getOrigin.done( ( data ) => {
			const parseContent = translationParser.parseContent( data );
			const getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithSomeMissingEndTag.json' );
			getExpected.done( ( expectedContent ) => {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Parser test with no end tags', ( assert ) => {
		const done = assert.async();
		const translationParser = new TranslationParser();

		const getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithNoEndTags.txt' );
		getOrigin.done( ( data ) => {
			const parseContent = translationParser.parseContent( data );
			const getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithNoEndTags.json' );
			getExpected.done( ( expectedContent ) => {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

	QUnit.test( 'Parser test with multiple questions', ( assert ) => {
		const done = assert.async();
		const translationParser = new TranslationParser();

		const getOrigin = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/TextWithMultipleQuestions.txt' );
		getOrigin.done( ( data ) => {
			const parseContent = translationParser.parseContent( data );
			const getExpected = $.get( '../extensions/SecurePoll/tests/qunit/data/parser/ExpectedTextWithMultipleQuestions.json' );
			getExpected.done( ( expectedContent ) => {
				assert.deepEqual( parseContent, expectedContent, 'parsedContent' );
				done();
			} );
		} );
	} );

}() );
