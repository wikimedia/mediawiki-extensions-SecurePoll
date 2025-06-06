( function () {
	const TranslationFlattener = require( 'ext.securepoll.htmlform/TranslationFlattener.js' );

	QUnit.module( 'ext.securepoll.translationFlattener.test' );

	function getTestData( fileName ) {
		return $.get( `${ mw.config.get( 'wgExtensionAssetsPath' ) }/SecurePoll/tests/qunit/data/flattener/${ fileName }` );
	}

	QUnit.test.each( 'Flatten parser test', {
		'with multiple questions': [ 'JSONWithMultipleQuestions.json', 'ExpectedDataWithMultipleQuestions.json' ],
		'with wrong data': [ 'JSONWithWrongData.json', 'ExpectedDataWithWrongData.json' ]
	}, async ( assert, [ inputFile, expectedFile ] ) => {
		const translationFlattener = new TranslationFlattener();

		const [ data, expected ] = await Promise.all( [
			getTestData( inputFile ),
			getTestData( expectedFile )
		] );

		const flattenedContent = translationFlattener.flattenData( data, 81 );

		assert.deepEqual( flattenedContent, expected, 'flattenedContent' );
	} );
}() );
