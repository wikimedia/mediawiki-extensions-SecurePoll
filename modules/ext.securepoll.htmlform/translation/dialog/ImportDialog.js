var ImportPage = require( './../pages/ImportPage.js' );
var ResultPage = require( './../pages/ResultPage.js' );
var SelectSourcePage = require( './../pages/SelectSourcePage.js' );

function ImportDialog( cfg ) {
	this.source = cfg.source;
	ImportDialog.super.call( this, cfg );
	this.sourcePages = [];
}

OO.inheritClass( ImportDialog, OO.ui.ProcessDialog );

ImportDialog.static.name = 'import-translations-dialog';
ImportDialog.static.title = mw.message( 'securepoll-translation-import-dialog-title' ).text();
ImportDialog.static.size = 'medium';

ImportDialog.static.actions = [
	{
		title: mw.message( 'cancel' ).text(),
		icon: 'close',
		flags: 'safe',
		modes: [ 'SelectSourcePage', 'ImportPage' ]
	},
	{
		label: mw.message( 'securepoll-translation-import-action-import' ).text(),
		action: 'import',
		flags: [ 'primary', 'progressive' ],
		modes: [ 'SelectSourcePage' ]
	},
	{
		action: 'done',
		label: mw.message( 'securepoll-translation-import-action-done' ).text(),
		flags: [ 'primary', 'progressive' ],
		modes: [ 'ImportPage', 'ResultPage' ]
	}
];

ImportDialog.prototype.getSetupProcess = function () {
	return ImportDialog.parent.prototype.getSetupProcess.call( this )
		.next( function () {
			// Prevent flickering, disable all actions before init is done
			this.actions.setMode( 'INVALID' );
		}, this );
};

ImportDialog.prototype.initialize = function () {
	ImportDialog.super.prototype.initialize.apply( this, arguments );

	this.electionId = this.getElectionId();
	this.content = new OO.ui.PanelLayout( { padded: true, expanded: true } );

	this.pages = [
		new SelectSourcePage( 'SelectSourcePage', {
			source: this.source,
			expanded: false
		} ),
		new ImportPage( 'ImportPage', {
			expanded: false,
			source: this.source,
			electionId: this.electionId
		} ),
		new ResultPage( 'ResultPage', {
			expanded: false,
			sourceWiki: this.source,
			sourceId: this.electionId
		} )
	];
	this.booklet = new OO.ui.BookletLayout( {
		expanded: true,
		outlined: false,
		showMenu: false,
		autoFocus: false
	} );
	this.booklet.addPages( this.pages );
	this.switchPage( 'SelectSourcePage' );

	this.content.$element.append( this.booklet.$element );
	this.$body.append( this.content.$element );
};

ImportDialog.prototype.switchPage = function ( name, data ) {
	var page = this.booklet.getPage( name );
	if ( !page ) {
		return;
	}

	this.booklet.setPage( name );
	this.actions.setMode( name );
	this.popPending();

	switch ( name ) {
		case 'SelectSourcePage':
			this.actions.setAbilities( { cancel: true, import: false, done: false } );
			page.connect( this, {
				sourceSelected: function () {
					this.actions.setAbilities( { import: true } );
				}
			} );
			this.updateSize();
			break;
		case 'ImportPage':
			this.actions.setAbilities( { cancel: true, import: false, done: false } );
			page.setSourcePages( data );
			page.connect( this, {
				imported: function ( results ) {
					this.switchPage( 'ResultPage', results );
				}
			} );
			page.startImport();
			this.updateSize();

			break;
		case 'ResultPage':
			this.actions.setAbilities( { cancel: false, import: false, done: true } );
			page.addSourceTitle( this.pageTitle );
			page.setResults( data );
			break;
	}
};

ImportDialog.prototype.getReadyProcess = function ( data ) {
	return ImportDialog.parent.prototype.getReadyProcess.call(
		this, data
	).next(
		function () {
			this.actions.setAbilities( { cancel: true, import: false, done: false } );
			this.switchPage( 'SelectSourcePage' );
		},
		this
	);
};

ImportDialog.prototype.getActionProcess = function ( action ) {
	var page = this.booklet.getCurrentPage();
	return ImportDialog.parent.prototype.getActionProcess.call(
		this, action
	).next(
		function () {
			switch ( action ) {
				case 'import':
					this.popPending();
					var me = this;
					me.pageTitle = page.getPageTitle();
					if ( !me.pageTitle ) {
						me.showErrors(
							new OO.ui.Error(
								mw.message( 'securepoll-translation-error-no-page-title' ).text(),
								{ recoverable: false }
							)
						);
						me.updateSize();
						break;
					}
					var dfdSourcePages = me.getSourcePages( me.pageTitle.getMain(), me.pageTitle.getNamespaceId(), '' );

					$.when( dfdSourcePages ).done( function () {
						if ( me.sourcePages.length > 0 ) {
							me.switchPage( 'ImportPage', me.sourcePages );
						}
					} ).fail( function ( error ) {
						me.showErrors(
							new OO.ui.Error(
								error,
								{ recoverable: false }
							)
						);
						me.updateSize();
					} );
					break;
				case 'done':
					this.close();
					break;
			}
		},
		this
	);
};

ImportDialog.prototype.getBodyHeight = function () {
	// eslint-disable-next-line no-jquery/no-class-state
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	if ( this.booklet.getCurrentPageName() === 'SelectSourcePage' ||
			this.booklet.getCurrentPageName() === 'ImportPage' ) {
		return 100;
	}
	return 400;
};

ImportDialog.prototype.getSourcePages = function ( pageName, namespace, continueVal ) {
	var me = this;
	var dfd = new $.Deferred();
	var params = {
		action: 'query',
		list: 'allpages',
		apprefix: pageName + '/',
		apnamespace: namespace
	};

	if ( continueVal !== '' ) {
		params.apcontinue = continueVal;
	}

	// foreign api to get page from different wiki
	mw.loader.using( 'mediawiki.ForeignApi' ).done( function () {
		var mwApi = new mw.ForeignApi( me.source, { anonymous: true } );

		var dfdResponse = mwApi.get( params );
		dfdResponse.done( function ( resp ) {
			if ( resp.query.allpages.length === 0 ) {
				dfd.reject( mw.message( 'securepoll-translation-error-no-page-title' ).text() );
			}
			resp.query.allpages.forEach( function ( page ) {
				me.sourcePages.push( page );
			} );
			if ( resp.continue ) {
				var recursiveCall = me.getSourcePages(
					pageName,
					namespace,
					resp.continue.apcontinue
				);
				recursiveCall.done( function () {
					dfd.resolve( resp );
				} );
			} else {
				dfd.resolve( resp );
			}
		} ).fail( function ( error ) {
			dfd.reject( error );
		} );

	} );

	return dfd.promise();
};

ImportDialog.prototype.getElectionId = function () {
	var url = mw.util.getUrl();
	var translate = url.slice( url.indexOf( 'translate' ), url.length );
	var params = translate.split( '/' );
	return params[ 1 ];
};

$( function () {
	var sourceUrl = mw.config.get( 'SecurePollTranslationImportSourceUrl' );
	var $translationButton = $( '#import-trans-btn' );

	if ( $translationButton.length < 1 ) {
		return;
	}
	var importBtn = OO.ui.infuse( $translationButton );

	function openDialog() {
		var windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );

		var dialog = new ImportDialog( {
			id: 'securepoll-import-translations',
			source: sourceUrl
		} );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog );

	}
	importBtn.on( 'click', openDialog );

} );
