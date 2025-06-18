/**
 * @typedef DraggableItemWidgetConfig
 * @property {string} label The label of the item that is displayed to the user
 * @property {number} positionIndex The index position of this item in the group
 * @property {Object} parentGroup The DraggableGroupWidget that contains this item
 */

const DraggableGroupWidget = require( './DraggableGroupWidget.js' );

/**
 * Drag/drop item
 *
 * @param {DraggableItemWidgetConfig} [config] Configuration options
 */
function DraggableItemWidget( config ) {
	this.$element = $( '<div>' );

	/**
	 * This name is needed by the form to associate candidates with rankings.
	 *
	 * @type {string}
	 */
	this.name = config.label;

	/**
	 * Represents the position and ranking of this item in the group.
	 *
	 * @type {number}
	 */
	this.positionIndex = config.positionIndex;

	/**
	 * We keep track of the parent group to assist with transferring candidates.
	 *
	 * @type {DraggableGroupWidget}
	 */
	this.parentGroup = config.parentGroup;

	this.icon = new OO.ui.IconWidget( {
		icon: 'draggable',
		classes: [ 'item-draggable-icon' ]
	} );
	this.label = new OO.ui.LabelWidget( {
		label: new OO.ui.HtmlSnippet( $( '<bdi>' ).text( config.label ).get( 0 ).outerHTML ),
		classes: [ 'item-name-label' ]
	} );
	this.position = new OO.ui.LabelWidget( {
		label: this.positionIndex.toString(),
		classes: [ 'item-position-index-label' ]
	} );

	DraggableItemWidget.super.call( this, config );
	OO.ui.mixin.DraggableElement.call( this, $.extend( { $handle: this.$element } ), config );
	OO.EventEmitter.call( this );

	this.$element.addClass( 'oo-ui-tagItemWidget' );
	this.$element.append(
		this.icon.$element,
		this.position.$element,
		this.label.$element,
		this.$hiddenInput
	);
	this.on( 'drop', this.onDrop.bind( this ) );
}

OO.inheritClass( DraggableItemWidget, OO.ui.Widget );
OO.mixinClass( DraggableItemWidget, OO.ui.mixin.DraggableElement );
OO.mixinClass( DraggableItemWidget, OO.EventEmitter );

DraggableItemWidget.prototype.onDrop = function () {
	this.parentGroup.onReorder();
};

DraggableItemWidget.prototype.updateIndex = function ( id ) {
	this.positionIndex = id;
	this.position.setLabel( this.positionIndex + ' ' );
};

module.exports = DraggableItemWidget;
