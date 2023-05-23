function SelectSourcePage( name, cfg ) {
	SelectSourcePage.super.call( this, name, cfg );
	this.source = cfg.source;

	this.content = new OO.ui.PanelLayout( {
		expanded: false
	} );

	this.label = new OO.ui.LabelWidget( {
		label: mw.message( 'securepoll-translation-select-import-source', this.source ).text()
	} );

	this.titleInput = new OO.ui.TextInputWidget();
	this.titleInput.connect( this, {
		change: function () {
			this.emit( 'sourceSelected' );
		}
	} );

	this.content.$element.append( this.label.$element );
	this.content.$element.append( this.titleInput.$element );
	this.$element.append( this.content.$element );
}

OO.inheritClass( SelectSourcePage, OO.ui.PageLayout );

// get page title given from user
SelectSourcePage.prototype.getPageTitle = function () {
	return mw.Title.newFromText( this.titleInput.getValue() );
};

module.exports = SelectSourcePage;
