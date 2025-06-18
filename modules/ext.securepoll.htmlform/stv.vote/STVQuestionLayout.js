/**
 * @typedef STVQuestionLayoutConfig
 * @property {Object} outerPanel PanelLayout OOUI component
 * @property {string[]} [classes]
 */

const DraggableGroupWidget = require( './DraggableGroupWidget.js' );
const DraggableItemWidget = require( './DraggableItemWidget.js' );

/**
 * The layout for STV questions in an STV poll.
 *
 * @param {STVQuestionLayoutConfig} [config] Configuration options
 */
function STVQuestionLayout( config ) {
	config = config || {};

	const data = config.outerPanel.data || {};

	/**
	 * The max number of candidates that can be ranked.
	 *
	 * @type {number}
	 */
	this.maxSeats = data.maxSeats;

	/**
	 * Indicates whether any candidates have been ranked yet. This is used by
	 * the form to enable / disable the submit button.
	 *
	 * @type {boolean}
	 */
	this.voteDone = false;

	/**
	 * We keep track of the group that is currently having an item dragged from
	 * to assist with transferring candidates from one group to another.
	 *
	 * @type {DraggableGroupWidget}
	 */
	this.sourceGroup = null;

	/**
	 * We keep track of the item that is currently being dragged to assist with
	 * transferring candidates from one group to another.
	 *
	 * @type {DraggableItemWidget}
	 */
	this.targetItem = null;

	STVQuestionLayout.super.call( this, config );

	this.unrankedPanel = new OO.ui.PanelLayout( {
		framed: true,
		padded: true,
		scrollable: false,
		expanded: false,
		classes: [ 'securepoll-question-stv-panel', 'securepoll-question-stv-panel-unranked' ]
	} );
	this.unrankedPanel.$element.append(
		$( '<h3>' )
			.addClass( 'stv-panel-header' )
			.text( mw.msg( 'securepoll-stv-unranked-candidates' ) )
	);

	this.rankedPanel = new OO.ui.PanelLayout( {
		framed: true,
		padded: true,
		scrollable: false,
		expanded: false,
		classes: [ 'securepoll-question-stv-panel', 'securepoll-question-stv-panel-ranked' ]
	} );
	this.rankedPanel.$element.append(
		$( '<h3>' )
			.addClass( 'stv-panel-header' )
			.text( mw.msg( 'securepoll-stv-ranked-candidates' ) )
	);

	this.unrankedGroup = this.createDraggableGroup( data );
	this.rankedGroup = this.createDraggableGroup( data );

	// We have to add in the items after initialization so that each item can
	// keep a reference to the group they are currently in, which is important
	// for transferring candidates between groups later.
	this.unrankedGroup.addItems(
		Object.keys( data.candidates ).map( ( name ) => (
			new DraggableItemWidget( {
				label: name,
				positionIndex: Object.keys( data.candidates ).indexOf( name ),
				parentGroup: this.unrankedGroup
			} )
		) )
	);

	// Create a clear button that removes all ranked candidates.
	this.clearButton = new OO.ui.ButtonWidget( {
		framed: false,
		label: mw.message( 'securepoll-vote-stv-clear-btn-label' ).text(),
		icon: 'trash'
	} );
	this.clearButton.on( 'click', () => {
		this.clearVotes();
		this.updateLimitsLayout();
	} );

	this.createLimitsLayout();
	this.selectDefaultItems( data.selectedItems );
	this.updateLimitsLayout();

	this.unrankedPanel.$element.append( this.unrankedGroup.$element );
	this.rankedPanel.$element.append( this.rankedGroup.$element );

	const $panelContainer = $( '<div>' ).addClass( 'stv-ranking-panel-container' );
	$panelContainer.append( this.unrankedPanel.$element, this.rankedPanel.$element );

	this.unrankedGroup.emit( 'reorder' );
	this.$element.append( this.limitsLayout.$element, $panelContainer, this.clearButton.$element );
}

OO.inheritClass( STVQuestionLayout, OO.ui.Layout );

/**
 * Create and configure a DraggableGroupWidget with event handlers.
 *
 * @param {Object} data Configuration data containing candidates and questionId
 * @return {DraggableGroupWidget} The configured draggable group widget
 */
STVQuestionLayout.prototype.createDraggableGroup = function ( data ) {
	const group = new DraggableGroupWidget( { items: [] } );

	group.$element.addClass( 'stv-ranking-draggable-group' );

	// Save a reference to the ranked list of candidates in the element's data.
	// These will get processed by the form in order to convert the ordered
	// list created by the draggable group into inputs the ballot understands.
	group.$element.data( 'ranking', group.items );
	group.$element.data( 'candidates', data.candidates );
	group.$element.data( 'questionId', data.questionId );

	group.$element.on( 'dragover', this.onGroupDragOver.bind( this ) );
	group.$element.on( 'drop', this.onGroupDrop.bind( this ) );
	group.on( 'itemDragStart', this.onGroupItemDragStart.bind( this ) );
	group.on( 'itemDragEnd', this.onGroupItemDragEnd.bind( this ) );
	group.on( 'reorder', this.onGroupReorder.bind( this ) );

	return group;
};

/**
 * Seek through selectedItems and transfer candidates from the unranked group
 * to the ranked group whose positionIndex matches the itemKey.
 *
 * @param {Array} selectedItems
 */
STVQuestionLayout.prototype.selectDefaultItems = function ( selectedItems ) {
	selectedItems.forEach( ( selectedItem ) => {
		const targetItem = this.unrankedGroup.getItems().find(
			( item ) => item.positionIndex === selectedItem.itemKey
		);
		if ( targetItem ) {
			this.transferCandidate( targetItem, this.rankedGroup );
		}
	} );
};

/**
 * Clear all votes from the ranked group by transfering them to unranked.
 */
STVQuestionLayout.prototype.clearVotes = function () {
	// Create a copy to avoid mutation during iteration
	const rankedItems = this.rankedGroup.getItems().slice();

	rankedItems.forEach( ( item ) => {
		this.transferCandidate( item, this.unrankedGroup );
	} );
};

/**
 * Initialize the max seat limit counter at the top right corner of the layout.
 */
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

/**
 * Updates the counter of selected candidates.
 */
STVQuestionLayout.prototype.updateLimitsLayout = function () {
	const selectedItems = this.rankedGroup.items.length;

	this.voteDone = selectedItems > 0; // At least 1 candidate is chosen
	this.limitsLabel.setLabel( selectedItems + '/' + this.maxSeats );

	// The page listens for this event to know when to enable the submit button.
	this.emit( 'voteStatusUpdate' );
};

/**
 * Transfer a candidate from one group to another.
 *
 * @param {DraggableItemWidget} targetItem
 * @param {DraggableGroupWidget} targetGroup
 */
STVQuestionLayout.prototype.transferCandidate = function ( targetItem, targetGroup ) {
	const sourceGroup = targetItem.parentGroup;
	if ( targetGroup === sourceGroup ) {
		return;
	}

	// Remove the item and reattach our dragover handler since OOUI removes it
	// when the item is removed from the group.
	sourceGroup.removeItems( [ targetItem ] );
	sourceGroup.$element.on( 'dragover', this.onGroupDragOver.bind( this ) );
	sourceGroup.emit( 'reorder' );

	// Reparent and add the item to the target group. OOUI will add back its
	// handlers with the addItems call.
	targetItem.parentGroup = targetGroup;
	targetGroup.addItems( [ targetItem ], targetGroup.getItems().length );
	targetGroup.emit( 'reorder' );
};

/**
 * Clear the drag state to prevent state issues with the next drag.
 */
STVQuestionLayout.prototype.clearDragState = function () {
	this.sourceGroup = null;
	this.targetItem = null;
};

/**
 * Stop the drag and drop "miss" animation from playing if any
 * DraggableItemWidget is dragged onto either of the DraggableGroupWidgets.
 *
 * @param {Event} e
 */
STVQuestionLayout.prototype.onGroupDragOver = function ( e ) {
	e.preventDefault();
};

/**
 * Implement cross-group transfer of candidate items. OOUI doesn't support this
 * so we have to handle it ourselves.
 *
 * @param {Event} e
 */
STVQuestionLayout.prototype.onGroupDrop = function ( e ) {
	let targetGroup;
	if ( e.currentTarget === this.unrankedGroup.$element[ 0 ] ) {
		targetGroup = this.unrankedGroup;
	} else if ( e.currentTarget === this.rankedGroup.$element[ 0 ] ) {
		targetGroup = this.rankedGroup;
	} else {
		throw new Error( 'Invalid drop target' );
	}

	// Bail out if the dragged item is already in the target group. No cross
	// group transfer will be necessary. We bail out if an attempt to move
	// candidates between questions happens.
	if (
		targetGroup.getItems().find( ( item ) => item === this.targetItem ) ||
		!this.sourceGroup ||
		!this.sourceGroup.getItems().find( ( item ) => item === this.targetItem )
	) {
		return;
	}

	// Enforce the max seat limit for the target group if it's the ranked group.
	if ( targetGroup === this.rankedGroup && targetGroup.items.length + 1 > this.maxSeats ) {
		return;
	}

	// Cancel any OOUI handling of the drop event and cancel the drag. If we
	// don't cleanup before the transfer then we will run into state issues.
	e.stopImmediatePropagation();
	this.sourceGroup.unsetDragItem();

	this.transferCandidate( this.targetItem, targetGroup );
};

/**
 * Keep track of which item is being held and what group it is coming from.
 *
 * @param {DraggableItemWidget} item
 */
STVQuestionLayout.prototype.onGroupItemDragStart = function ( item ) {
	this.targetItem = item;
	this.sourceGroup = item.parentGroup;
};

STVQuestionLayout.prototype.onGroupItemDragEnd = function () {
	this.clearDragState();
};

STVQuestionLayout.prototype.onGroupReorder = function () {
	this.updateLimitsLayout();
};

module.exports = STVQuestionLayout;
