/**
 * Draggable group widget containing DraggableItemWidgets.
 *
 * @param {Object} [config] Configuration options
 * @param {boolean} [config.showPosition] Whether to show the candidate's
 *                                        numerical position
 */
function DraggableGroupWidget( config ) {
	this.$element = $( '<div>' );
	config = config || {};

	this.items = [];

	this.showPosition = config.showPosition;

	DraggableGroupWidget.parent.call( this, config );

	const mergedConfig = Object.assign( {}, config );
	mergedConfig.$group = this.$element;
	OO.ui.mixin.DraggableGroupElement.call( this, mergedConfig );

	this.on( 'reorder', this.onReorder.bind( this ) );
	this.$element.on( 'drop', this.onDrop.bind( this ) );
}

OO.inheritClass( DraggableGroupWidget, OO.ui.Widget );
OO.mixinClass( DraggableGroupWidget, OO.ui.mixin.DraggableGroupElement );

DraggableGroupWidget.prototype.onReorder = function () {
	this.items.forEach( ( element, index ) => {
		element.updateIndex( index + 1 );
	} );
};

DraggableGroupWidget.prototype.onDrop = function () {
	this.emit( 'reorder' );
};

module.exports = DraggableGroupWidget;
