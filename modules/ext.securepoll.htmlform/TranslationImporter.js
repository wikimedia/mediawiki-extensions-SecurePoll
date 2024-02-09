var TranslationParser = require( './TranslationParser.js' );
var TranslationFlattener = require( './TranslationFlattener.js' );

function TranslationImporter( cfg ) {
	this.source = cfg.source;
	this.parser = new TranslationParser();
	this.flattener = new TranslationFlattener();

	// Mixin constructors
	OO.EventEmitter.call( this );
}

OO.initClass( TranslationImporter );
OO.mixinClass( TranslationImporter, OO.EventEmitter );

/**
 * import progress for a page: get content, parse it to json, flatten it and
 * save it to language subpage
 *
 * @param {Object} page
 * @param {number} electionId
 * @return {jQuery.Promise} Promise that is resolved when all steps for import are done
 */
TranslationImporter.prototype.startImportPage = function ( page, electionId ) {
	var dfd = new $.Deferred();
	var pageId = page.pageid;
	this.sourceContent = '';
	var language = this.getLanguage( page.title );

	if ( language === '' ) {
		dfd.resolve( mw.message( 'securepoll-translation-importer-no-selected-language' ).text(), '' );
	}
	this.electionId = electionId;

	var me = this;
	var dfdPageContent = this.getPageContent( page, pageId );

	$.when( dfdPageContent ).done( function ( sourceContent ) {
		if ( sourceContent !== '' ) {
			var parsedJson = me.parser.parseContent( sourceContent );
			var flattenedJson = me.flattener.flattenData( parsedJson, me.electionId );
			me.update( mw.message( 'securepoll-translation-importer-update-parsed-content', language ).text() );

			var dfdSaveContent = me.saveContent( flattenedJson, language );
			$.when( dfdSaveContent ).done( function () {
				dfd.resolve( 'saved', language );
			} ).fail( function ( error ) {
				dfd.resolve( error, language );
			} );
		} else {
			dfd.resolve( mw.message( 'securepoll-translation-importer-no-content' ).text(), language );
		}
	} ).fail( function ( error ) {
		dfd.resolve( error, language );
	} );

	return dfd.promise();
};

/**
 * get page content for a dedicated page
 *
 * @param {*} page
 * @param {number} pageId
 * @return {jQuery.Promise} Promise that is resolved when page content is received
 */
TranslationImporter.prototype.getPageContent = function ( page, pageId ) {
	var dfd = new $.Deferred();

	var params = {
		action: 'query',
		prop: 'revisions',
		rvprop: 'content',
		titles: page.title
	};
	this.update( mw.message( 'securepoll-translation-importer-update-start', page.title ).text() );

	mw.loader.using( 'mediawiki.ForeignApi' ).done( function () {
		var mwApi = new mw.ForeignApi( this.source, { anonymous: true } );

		var dfdResponse = mwApi.get( params );
		dfdResponse.done( function ( resp ) {
			var pageInfo = resp.query.pages[ pageId ];
			if ( pageInfo.pageid !== pageId ) {
				dfd.reject( resp );
			}
			if ( pageInfo.missing || !pageInfo.revisions || !pageInfo.revisions[ 0 ] ) {
				dfd.reject( resp );
			}
			if ( pageInfo.revisions[ 0 ][ '*' ].length === 0 ) {
				dfd.reject( mw.message( 'securepoll-translation-importer-no-content' ).text() );
			}
			var sourceContent = pageInfo.revisions[ 0 ][ '*' ];

			dfd.resolve( sourceContent );
		} ).fail( function ( error ) {
			dfd.reject( error );
		} );
	}.bind( this ) );

	return dfd.promise();
};

/**
 * @param {string} update information about import progress
 */
TranslationImporter.prototype.update = function ( update ) {
	this.emit( 'update', update );
};

/**
 * get language from source page title
 *
 * @param {Object} title
 * @return {string} language from title
 */
TranslationImporter.prototype.getLanguage = function ( title ) {
	var titleArray = title.split( '/' );
	return titleArray[ titleArray.length - 1 ];
};

/**
 * save parsed content to language subpage
 *
 * @param {string} content
 * @param {string} language
 * @return {jQuery.Promise} Promise that is resolved when content is saved
 */
TranslationImporter.prototype.saveContent = function ( content, language ) {
	var dfd = $.Deferred();

	$.ajax( {
		method: 'POST',
		url: this.makeUrl( language ),
		data: JSON.stringify( { data: content } ),
		contentType: 'application/json',
		dataType: 'json'
	} ).done( function ( response ) {
		if ( response.success === true ) {
			dfd.resolve( response );
		}
	} ).fail( function ( jgXHR, type, status ) {
		if ( type === 'error' ) {
			dfd.reject( {
				error: jgXHR.responseJSON || jgXHR.responseText
			} );
		}
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};

/**
 * @param {string} language
 * @return {string} url for rest endpoint
 */
TranslationImporter.prototype.makeUrl = function ( language ) {
	return mw.util.wikiScript( 'rest' ) + '/securepoll/set_translation/' +
		this.electionId + '/' + language;
};

module.exports = TranslationImporter;
