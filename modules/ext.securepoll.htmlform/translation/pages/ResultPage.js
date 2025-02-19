function ResultPage( name, cfg ) {
	ResultPage.super.call( this, name, cfg );
	this.sourceWiki = cfg.sourceWiki;
	this.sourceId = cfg.sourceId;

	this.label = new OO.ui.LabelWidget( {
		padded: true,
		label: mw.message( 'securepoll-translation-result-description' ).text()
	} );

	this.$element.append( this.label.$element );
}

OO.inheritClass( ResultPage, OO.ui.PageLayout );

// create tabs to layout with errors and pages
ResultPage.prototype.setResults = function ( results ) {
	const errors = results.errors;
	const pages = results.pages;

	this.layout = new OO.ui.IndexLayout( {
		expanded: false,
		framed: true
	} );

	if ( errors.length > 0 ) {
		this.getErrorsTab( errors );
	}

	if ( pages.length > 0 ) {
		this.getPagesTab( pages );
	}
	this.$element.append( this.layout.$element );

};

// add list with imported pages to result tab
ResultPage.prototype.getPagesTab = function ( pages ) {
	const pageTab = new OO.ui.TabPanelLayout( 'imported-pages', {
		label: mw.message( 'securepoll-translation-result-import-pages-title' ).text(),
		expanded: false
	} );
	const label = new OO.ui.LabelWidget( {
		label: mw.message( 'securepoll-translation-result-import-pages-text' ).text()
	} );
	pageTab.$element.append( label.$element );

	const $list = $( '<ul>' );
	pages.forEach( ( page ) => {
		let listEntry = '<li>';
		listEntry += '<span>' + page.language + '</span> ';
		listEntry += ' <a href=' + this.sourceId + '/' + page.language + '> ' +
			this.sourceId + '/' + page.language + ' </a>';
		listEntry += '</li>';
		$list.append( listEntry );
	} );
	pageTab.$element.append( $list );

	this.layout.addTabPanels( [ pageTab ] );
};

// add list with errors to error tabs
ResultPage.prototype.getErrorsTab = function ( errorpages ) {
	const pageTab = new OO.ui.TabPanelLayout( 'error-pages', {
		label: mw.message( 'securepoll-translation-result-error-title' ).text(),
		expanded: false
	} );
	const label = new OO.ui.LabelWidget( {
		label: mw.message( 'securepoll-translation-result-error-text' ).text()
	} );
	pageTab.$element.append( label.$element );

	const $list = $( '<ul>' );
	errorpages.forEach( ( page ) => {
		let listEntry = '<li>';
		listEntry += '<span>' + page.language + ' - ' + page.error + '</span> ';
		listEntry += '</li>';
		$list.append( listEntry );
	} );
	pageTab.$element.append( $list );

	this.layout.addTabPanels( [ pageTab ] );
};

ResultPage.prototype.addSourceTitle = function ( title ) {
	const sourceUrl = this.sourceWiki + '/' + title.getPrefixedText();

	const $link = $( '<a>' );
	$link.attr( 'href', sourceUrl );
	$link.text( sourceUrl );
	this.label.$element.append( $link );
};

module.exports = ResultPage;
