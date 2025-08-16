function SelectSourcePage( name, cfg ) {
	SelectSourcePage.super.call( this, name, cfg );
	this.source = '';
	this.content = new OO.ui.PanelLayout( {
		expanded: false
	} );

	this.sourceApiLabel = new OO.ui.LabelWidget( {
		label: new OO.ui.HtmlSnippet(
			mw.message(
				'securepoll-translation-select-import-source-api'
			).parse()
		)
	} );
	this.sourceInput = new OO.ui.TextInputWidget( {
		placeholder: 'https://meta.wikimedia.org/w/api.php'
	} );
	this.sourceInput.$input.on( 'blur', () => {
		this.emit( 'sourceApiSelected' );
	} );

	this.label = new OO.ui.LabelWidget( {
		label: mw.message(
			'securepoll-translation-select-import-source-page'
		).text(),
		classes: [ 'securepoll-import-label-spacing' ]
	} );

	this.titleInput = new OO.ui.TextInputWidget();
	this.titleInput.connect( this, {
		change: function () {
			this.emit( 'sourceSelected' );
		}
	} );

	this.content.$element.append( this.sourceApiLabel.$element );
	this.content.$element.append( this.sourceInput.$element );
	this.content.$element.append( this.label.$element );
	this.content.$element.append( this.titleInput.$element );
	this.$element.append( this.content.$element );
}

OO.inheritClass( SelectSourcePage, OO.ui.PageLayout );

// get page title given from user
SelectSourcePage.prototype.getPageTitle = function () {
	return mw.Title.newFromText( this.titleInput.getValue() );
};

SelectSourcePage.prototype.getSourceApi = function () {
	return this.sourceInput.getValue();
};

module.exports = SelectSourcePage;
