function StrikePopupWidget( config ) {
	var content;
	config = config || {};

	this.strikeButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'securepoll-strike-button' ),
		flags: [ 'primary', 'progressive' ]
	} );
	this.reasonInput = new OO.ui.TextInputWidget( {
		placeholder: mw.msg( 'securepoll-strike-reason' )
	} );

	content = new OO.ui.ActionFieldLayout(
		this.reasonInput,
		this.strikeButton,
		{
			classes: [ 'securepoll-popup' ]
		}
	);
	config.$content = content.$element;

	// Parent constructor
	StrikePopupWidget.super.call( this, config );

	this.voteid = config.voteid;
	this.action = config.action;

	this.$result = $( document.createElement( 'div' ) ).addClass( 'securepoll-strike-result' ).appendTo( content.$element );
	this.$spinner = $.createSpinner( { size: 'small', type: 'inline' } ).appendTo( content.$element );

	this.strikeButton.connect( this, { click: 'onStrikeClick' } );

	this.connect( this, { ready: 'onReady' } );
}
OO.inheritClass( StrikePopupWidget, OO.ui.PopupWidget );

StrikePopupWidget.prototype.onReady = function () {
	this.reasonInput.$input.trigger( 'focus' ).trigger( 'select' );
	if ( this.action === 'strike' ) {
		this.strikeButton.setLabel( mw.msg( 'securepoll-strike-button' ) );
	} else {
		this.strikeButton.setLabel( mw.msg( 'securepoll-unstrike-button' ) );
	}
};

StrikePopupWidget.prototype.onStrikeClick = function () {
	this.makeRequest( this.action, this.voteid, this.reasonInput.getValue() );
};

StrikePopupWidget.prototype.makeRequest = function ( action, voteid, reason ) {
	var widget = this;
	new mw.Api().postWithToken( 'csrf', {
		action: 'strikevote', // API action module
		option: action, // 'strike' or 'unstrike'
		voteid: this.voteid,
		reason: reason
	} )
		.then(
			function ( response ) {
				var $row;
				if ( response.strikevote.status === 'good' ) {
					widget.toggle( false );
				} else {
					widget.$result.text( response.error.info );
				}

				$row = $( '#securepoll-popup-' + voteid ).closest( 'tr' );
				if ( action === 'strike' ) {
					widget.action = 'unstrike';
					$row.addClass( 'securepoll-struck-vote' );
					$row.find( '.TablePager_col_strike > span > a > .oo-ui-labelElement-label' ).text( mw.msg( 'securepoll-unstrike-button' ) );
				} else {
					widget.action = 'strike';
					$row.removeClass( 'securepoll-struck-vote' );
					$row.find( '.TablePager_col_strike > span > a > .oo-ui-labelElement-label' ).text( mw.msg( 'securepoll-strike-button' ) );
				}
				widget.reasonInput.setValue( '' );
			},
			function ( code, response ) { // fail callback
				widget.$result.text( response.error.info );
			}
		)
		.always( function () {
			widget.$spinner.hide();
			widget.strikeButton.setDisabled( false );
		} );
	this.strikeButton.setDisabled( true );
	this.$spinner.css( 'display', 'inline-block' );
};

$( '.TablePager_col_strike > .oo-ui-buttonWidget a' ).on( 'click', function ( e ) {
	var $widget = $( this ).parent(),
		popup = $widget.data( 'popup' );
	e.preventDefault();

	if ( !popup ) {
		popup = new StrikePopupWidget( {
			action: $widget.data( 'action' ),
			voteid: $widget.data( 'voteid' ),
			autoClose: true,
			padded: true
		} );
		$widget.append( popup.$element );
		$widget.data( 'popup', popup );
	}

	popup.$result.empty();

	popup.toggle();
} );
