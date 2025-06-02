( function () {
	const TranslationParser = require( 'ext.securepoll.htmlform/TranslationParser.js' );

	QUnit.module( 'ext.securepoll.translationParser.test' );

	function getTestData( fileName ) {
		return $.get( `${ mw.config.get( 'wgExtensionAssetsPath' ) }/SecurePoll/tests/qunit/data/parser/${ fileName }` );
	}

	QUnit.test.each( 'Parser test', {
		'with correct tags': [ 'TextWithCorrectTags.txt', 'ExpectedTextWithCorrectTags.json' ],
		'with some missing end tags': [ 'TextWithSomeMissingEndTag.txt', 'ExpectedTextWithSomeMissingEndTag.json' ],
		'with no end tags': [ 'TextWithNoEndTags.txt', 'ExpectedTextWithNoEndTags.json' ],
		'with multiple questions': [ 'TextWithMultipleQuestions.txt', 'ExpectedTextWithMultipleQuestions.json' ]
	}, async ( assert, [ inputFile, expectedFile ] ) => {
		const translationParser = new TranslationParser();

		const [ data, expected ] = await Promise.all( [
			getTestData( inputFile ),
			getTestData( expectedFile )
		] );

		const parseContent = translationParser.parseContent( data );
		assert.deepEqual( parseContent, expected, 'parsedContent' );
	} );
}() );
