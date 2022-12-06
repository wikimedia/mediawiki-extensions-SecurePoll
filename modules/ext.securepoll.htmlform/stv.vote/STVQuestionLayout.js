var DraggableGroupWidget = require( './DraggableGroupWidget.js' );
var DraggableItemWidget = require( './DraggableItemWidget.js' );

/**
 * Layout for STV poll
 *
 * @param {Object} [config] Configuration options
 */
function STVQuestionLayout( config ) {
	config = config || {};

	this.boxMenu = config.comboBox.menu;
	var data = config.comboBox.data || {};
	this.voteDone = false;
	this.maxSeats = data.maxSeats;
	this.questionId = this.boxMenu.$widget[ 0 ].className.split( ' ' )[ 0 ];
	this.createLimitsLayout();

	// Parent constructor
	STVQuestionLayout.super.call( this, config );

	// Draggable Group
	this.draggableGroup = new DraggableGroupWidget(
		{
			items: []
		}
	);
	this.draggableGroup.on( 'reorder', function () {
		this.updateLimitsLayout();
	}.bind( this ) );

	// Clear all candidates Button
	var clearButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'securepoll-vote-stv-clear-btn-label' ).text(),
		icon: 'trash',
		tabIndex: -1
	} );
	clearButton.on( 'click', function () {
		this.draggableGroup.clearAll();
		// enable all candidates if selection was cleared
		this.boxMenu.items.forEach( function ( item ) {
			item.setDisabled( false );
		} );
		this.updateLimitsLayout();
	}.bind( this ) );

	// BoxMenu Events
	this.boxMenu.connect( this, { choose: function ( event ) {
		// create a new TagItemWidget
		var itemWidget = new DraggableItemWidget( {
			data: 'item',
			icon: 'tag',
			name: this.questionId,
			option: this.boxMenu.items.indexOf( event ),
			index: this.draggableGroup.items.length + 1,
			draggableGroup: this.draggableGroup,
			label: event.label
		} );
		itemWidget.on( 'deleteItem', function () {
			event.setDisabled( false );
		} );

		// prove is candidate already selected
		if ( !event.disabled ) {
			this.draggableGroup.addItems( [ itemWidget ] );
			this.draggableGroup.$element.append( itemWidget.$element );
			this.draggableGroup.emit( 'reorder' );
		}
		event.setDisabled( true );
	} } );

	this.selectDefaultItems( data.selectedItems );
	this.updateLimitsLayout();

	// Initialization
	this.$element.prepend( this.limitsLayout.$element );
	this.$element.append( this.draggableGroup.$element, clearButton.$element );
}

/* Setup */
OO.inheritClass( STVQuestionLayout, OO.ui.Layout );

STVQuestionLayout.prototype.selectDefaultItems = function ( selectedItems ) {
	selectedItems.forEach( function ( value ) {
		this.boxMenu.emit( 'choose', this.boxMenu.items[ value.itemKey ] );
	}.bind( this ) );
};

STVQuestionLayout.prototype.createLimitsLayout = function () {
	this.limitsLabel = new OO.ui.LabelWidget( {
		label: 0 + '/' + this.maxSeats,
		classes: [ 'limit-label' ]
	} );

	this.limitsLayout = new OO.ui.PanelLayout( {
		expanded: false,
		framed: false,
		classes: [ 'limits-layout' ]
	} );
	this.limitsLayout.$element.append(
		this.limitsLabel.$element
	);
};

STVQuestionLayout.prototype.updateLimitsLayout = function () {
	var selectedItems = this.draggableGroup.items.length;
	this.voteDone = selectedItems > 0; // at least 1 candidate chosen
	this.limitsLabel.setLabel( selectedItems + '/' + this.maxSeats );
	// disable boxMenu if limit is reached
	this.boxMenu.setDisabled( selectedItems === this.maxSeats );
	this.emit( 'voteStatusUpdate' );
};

module.exports = STVQuestionLayout;
