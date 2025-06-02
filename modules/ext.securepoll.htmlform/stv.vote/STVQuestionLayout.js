/**
 * @typedef STVQuestionLayoutConfig
 * @property {Object} comboBox ComboBoxInputWidget element
 * @property {string[]} [classes]
 * @property {string} [label]
 */

const DraggableGroupWidget = require( './DraggableGroupWidget.js' );
const DraggableItemWidget = require( './DraggableItemWidget.js' );

/**
 * Layout for STV poll
 *
 * @param {STVQuestionLayoutConfig} [config] Configuration options
 */
function STVQuestionLayout( config ) {
	config = config || {};

	this.comboBox = config.comboBox;
	this.boxMenu = config.comboBox.menu;
	const data = config.comboBox.data || {};
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

	// Save a reference to the ordered list and candidates
	// These will get processed by the form in order to convert the ordered
	// list created by the draggable group into inputs the ballot understands
	this.draggableGroup.$element.addClass( 'stv-ranking-draggable-group' );
	this.draggableGroup.$element.data( 'ranking', this.draggableGroup.items );
	this.draggableGroup.$element.data( 'candidates', data.candidates );
	this.draggableGroup.$element.data( 'questionId', data.questionId );

	this.draggableGroup.on( 'reorder', () => {
		this.updateLimitsLayout();
		this.checkMaxSeats();
	} );

	// Clear all candidates Button
	this.clearButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'securepoll-vote-stv-clear-btn-label' ).text(),
		icon: 'trash'
	} );
	this.clearButton.on( 'click', () => {
		this.draggableGroup.clearAll();
		// enable all candidates if selection was cleared
		this.boxMenu.items.forEach( ( item ) => {
			item.setDisabled( false );
		} );
		this.updateLimitsLayout();
		this.checkMaxSeats();
	} );

	// BoxMenu Events
	this.boxMenu.connect( this, { choose: function ( event ) {
		// create a new TagItemWidget
		const itemWidget = new DraggableItemWidget( {
			data: event.label,
			icon: 'tag',
			name: this.questionId,
			option: this.boxMenu.items.indexOf( event ),
			index: this.draggableGroup.items.length + 1,
			draggableGroup: this.draggableGroup,
			label: event.label
		} );
		itemWidget.on( 'deleteItem', () => {
			event.setDisabled( false );
		} );

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
	this.$element.append( this.draggableGroup.$element, this.clearButton.$element );
}

/* Setup */
OO.inheritClass( STVQuestionLayout, OO.ui.Layout );

STVQuestionLayout.prototype.selectDefaultItems = function ( selectedItems ) {
	selectedItems.forEach( ( value ) => {
		this.boxMenu.emit( 'choose', this.boxMenu.items[ value.itemKey ] );
	} );
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
	const selectedItems = this.draggableGroup.items.length;
	this.voteDone = selectedItems > 0; // at least 1 candidate chosen
	this.limitsLabel.setLabel( selectedItems + '/' + this.maxSeats );
	// disable boxMenu if limit is reached
	this.boxMenu.setDisabled( selectedItems === this.maxSeats );
	this.emit( 'voteStatusUpdate' );
};

STVQuestionLayout.prototype.checkMaxSeats = function () {
	const maxSeatsReached = this.draggableGroup.items.length >= this.maxSeats;
	this.comboBox.setDisabled( maxSeatsReached );
};

module.exports = STVQuestionLayout;
