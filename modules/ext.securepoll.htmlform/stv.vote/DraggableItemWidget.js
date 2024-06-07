/**
 * Drag/drop item
 *
 * @param {Object} [config] Configuration options
 */
function DraggableItemWidget( config ) {
	this.$element = $( '<div>' );
	this.name = config.name;
	this.option = config.option;
	this.fixed = false;
	this.positionIndex = config.index;
	this.draggableGroup = config.draggableGroup;

	// "x"-Button delete item from group
	this.itemDeleteButton = new OO.ui.ButtonWidget( {
		framed: false,
		icon: 'close',
		tabIndex: -1,
		title: OO.ui.msg( 'ooui-item-remove' ),
		classes: [ 'item-delete-buton' ]
	} );
	this.itemDeleteButton.on( 'click', this.onRemove.bind( this ) );

	// Item widgets
	this.icon = new OO.ui.IconWidget( {
		icon: 'draggable',
		classes: [ 'item-draggable-icon' ]
	} );
	this.label = new OO.ui.LabelWidget( {
		label: config.label,
		classes: [ 'item-name-label' ]
	} );
	this.position = new OO.ui.LabelWidget( {
		label: this.positionIndex.toString(),
		classes: [ 'item-position-index-label' ]
	} );

	// Hiden input used in submit form action
	this.$hiddenInput = $( '<input type="hidden" name="' + this.name + '_opt' + this.option +
	'" value="' + this.positionIndex + '" />' );

	// Parent constructor
	DraggableItemWidget.super.call( this, config );

	// Mixin constructors
	OO.ui.mixin.DraggableElement.call( this, $.extend( { $handle: this.$element } ), config );
	OO.EventEmitter.call( this );

	// Initialization & Events
	this.$element.addClass( 'oo-ui-tagItemWidget' );
	this.$element.append( this.icon.$element,
		this.position.$element,
		this.label.$element,
		this.itemDeleteButton.$element
	);
	this.$element.append( this.$hiddenInput );
	this.on( 'remove', this.onRemove.bind( this ) );
	this.on( 'drop', this.onDrop.bind( this ) );
}

/* Setup */
OO.inheritClass( DraggableItemWidget, OO.ui.Widget );
OO.mixinClass( DraggableItemWidget, OO.ui.mixin.DraggableElement );
OO.mixinClass( DraggableItemWidget, OO.ui.mixin.ButtonElement );
OO.mixinClass( DraggableItemWidget, OO.EventEmitter );

DraggableItemWidget.prototype.onRemove = function () {
	this.emit( 'deleteItem' );
	this.draggableGroup.onDeleteItem( this.positionIndex - 1 );
	this.$element.remove();
};

DraggableItemWidget.prototype.onDrop = function () {
	this.draggableGroup.onReorder();
};

DraggableItemWidget.prototype.updateindex = function ( id ) {
	this.positionIndex = id;
	this.position.setLabel( this.positionIndex + ' ' );
	this.$element.children()[ 4 ].value = this.positionIndex;
};

DraggableItemWidget.prototype.addhiddenInput = function () {
	var $hiddenInputElement = $( '<input>' );
	$hiddenInputElement.attr( 'type', 'hidden' );
	$hiddenInputElement.attr( 'name', '' );
	$hiddenInputElement.attr( 'value', '' );
	this.$element.append( $hiddenInputElement );
};

module.exports = DraggableItemWidget;
