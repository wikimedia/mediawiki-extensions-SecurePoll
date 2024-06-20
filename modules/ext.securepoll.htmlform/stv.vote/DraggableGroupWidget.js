/**
 * Draggable group widget containing drag/drop items
 *
 * @param {Object} [config] Configuration options
 */
function DraggableGroupWidget( config ) {
	// Configuration initialization
	this.$element = $( '<div>' );
	config = config || {};

	// Properties
	this.length = 1;
	this.dragItem = null;
	this.itemKeys = {};
	this.dir = null;
	this.items = [];
	this.itemsOrder = null;
	this.draggable = config.draggable === true;

	// Parent constructor
	DraggableGroupWidget.parent.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableGroupElement.call( this, Object.assign( {}, config, {
		$group: this.$element
	} ) );

	// Events
	this.on( 'deleteItem', this.onDeleteItem.bind( this ) );
	this.on( 'reorder', this.onReorder.bind( this ) );
}

/* Setup */
OO.inheritClass( DraggableGroupWidget, OO.ui.Widget );
OO.mixinClass( DraggableGroupWidget, OO.ui.mixin.DraggableGroupElement );

DraggableGroupWidget.prototype.onReorder = function () {
	this.items.forEach( function ( element, index ) {
		element.updateindex( index + 1 );
	} );
};

DraggableGroupWidget.prototype.clearAll = function () {
	this.items.forEach( function ( element ) {
		element.$element.remove();
	} );
	this.items = [];
};

DraggableGroupWidget.prototype.onDeleteItem = function ( id ) {
	this.items.splice( id, 1 );
	this.emit( 'reorder' );
};

module.exports = DraggableGroupWidget;
