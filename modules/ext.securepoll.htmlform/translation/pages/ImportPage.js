var TranslationImporter = require( './../../TranslationImporter.js' );

function ImportPage( name, cfg ) {
	ImportPage.super.call( this, name, cfg );
	this.electionId = cfg.electionId;
	this.source = cfg.source;
	this.sourcePages = [];
	this.results = {
		pages: [],
		errors: []
	};

	this.content = new OO.ui.PanelLayout( {
		expanded: false
	} );
	this.$element.append( this.content.$element );

	// Mixin constructors
	OO.EventEmitter.call( this );

	this.importer = new TranslationImporter( {
		source: this.source
	} );
}

OO.inheritClass( ImportPage, OO.ui.PageLayout );
OO.mixinClass( ImportPage, OO.EventEmitter );

// source pages from which content should be grabbed
ImportPage.prototype.setSourcePages = function ( pages ) {
	this.sourcePages = pages;
};

// handle import of each page and fires updates which page is currently processed
// fires imported after all pages are processed and import is done
ImportPage.prototype.startImport = function () {
	var dfdImports = [];
	this.progressBar = new OO.ui.ProgressBarWidget();
	this.label = new OO.ui.LabelWidget( {
		label: mw.message( 'securepoll-translation-import-start' ).text()
	} );

	this.content.$element.append( this.progressBar.$element );
	this.content.$element.append( this.label.$element );

	this.importer.connect( this, {
		update: function ( statelabel ) {
			this.updateInfo( statelabel );
		}
	} );
	var me = this;
	me.sourcePages.forEach( function ( page ) {
		var dfdContent = me.importer.startImportPage( page, me.electionId );
		dfdImports.push( dfdContent );
		dfdContent.then( function ( state, language ) {
			if ( state === 'saved' ) {
				page.language = language;
				me.results.pages.push( page );
			} else {
				page.error = state;
				page.language = language;
				me.results.errors.push( page );
			}
		} );
	} );

	$.when.apply( me, dfdImports ).then( function () {
		me.emit( 'imported', me.results );
	} );
};

// update label
ImportPage.prototype.updateInfo = function ( info ) {
	this.label.setLabel( info );
};

module.exports = ImportPage;
