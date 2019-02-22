/* eslint-disable camelcase, no-use-before-define */
// eslint-disable-next-line no-implicit-globals
function securepoll_strike_popup( e, action, id ) {
	var target, left, top, containing,
		strikeButton, unstrikeButton, reason,
		pop = document.getElementById( 'securepoll-popup' );

	e = window.event || e;
	if ( !e ) {
		return;
	}
	target = e.target || e.srcElement;
	if ( !target ) {
		return;
	}

	if ( pop.parentNode.tagName.toLowerCase() !== 'body' ) {
		pop = pop.parentNode.removeChild( pop );
		pop = document.body.appendChild( pop );
	}

	left = 0;
	top = 0;
	containing = target;
	while ( containing ) {
		left += containing.offsetLeft;
		top += containing.offsetTop;
		containing = containing.offsetParent;
	}
	left += target.offsetWidth - 10;
	top += target.offsetHeight - 10;

	// Show the appropriate button
	strikeButton = document.getElementById( 'securepoll-strike-button' );
	unstrikeButton = document.getElementById( 'securepoll-unstrike-button' );
	if ( action === 'strike' ) {
		strikeButton.style.display = 'inline';
		strikeButton.disabled = false;
		unstrikeButton.style.display = 'none';
		unstrikeButton.disabled = true;
	} else {
		unstrikeButton.style.display = 'inline';
		unstrikeButton.disabled = false;
		strikeButton.style.display = 'none';
		strikeButton.disabled = true;
	}
	document.getElementById( 'securepoll-strike-result' ).innerHTML = '';

	// Set the hidden fields for submission
	document.getElementById( 'securepoll-action' ).value = action;
	document.getElementById( 'securepoll-vote-id' ).value = id;

	// Show the popup
	pop.style.left = left + 'px';
	pop.style.top = top + 'px';
	pop.style.display = 'block';

	// Focus on the reason box
	reason = document.getElementById( 'securepoll-strike-reason' );
	reason.focus();
	reason.select();
}

// eslint-disable-next-line no-implicit-globals, no-unused-vars
function securepoll_strike( action ) {
	var id, strikeButton, unstrikeButton, spinner, reason,
		popup = document.getElementById( 'securepoll-popup' );
	if ( action === 'cancel' ) {
		popup.style.display = '';
		return;
	}
	if ( action === 'submit' ) {
		action = document.getElementById( 'securepoll-action' ).value;
	}
	id = document.getElementById( 'securepoll-vote-id' ).value;
	strikeButton = document.getElementById( 'securepoll-strike-button' );
	unstrikeButton = document.getElementById( 'securepoll-unstrike-button' );
	spinner = document.getElementById( 'securepoll-strike-spinner' );
	strikeButton.disabled = true;
	unstrikeButton.disabled = true;
	spinner.style.display = 'block';
	reason = document.getElementById( 'securepoll-strike-reason' ).value;

	new mw.Api().postWithToken( 'edit', {
		action: 'strikevote', // API action module
		option: action, // 'strike' or 'unstrike'
		voteid: id,
		reason: reason
	} )
		.then(
			function ( response ) {
				if ( response.strikevote.status === 'good' ) {
					popup.style.display = 'none';
				} else {
					$( '#securepoll-strike-result' ).text( response.error.info );
				}

				securepoll_modify_document( action, id );
			},
			function ( code, response ) { // fail callback
				$( '#securepoll-strike-result' ).text( response.error.info );
			}
		)
		.always( function () {
			spinner.style.display = 'none';
			strikeButton.disabled = false;
			unstrikeButton.disabled = false;
		} );
}

// eslint-disable-next-line no-implicit-globals
function securepoll_modify_document( action, voteId ) {
	var popupButton = document.getElementById( 'securepoll-popup-' + voteId ),
		// TODO: if possible this should be replaced with getElementById
		row = popupButton.parentNode.parentNode;
	if ( action === 'strike' ) {
		row.className += ' securepoll-struck-vote';
		// FIXME: This yields "ReferenceError: securepoll_unstrike_button is not defined"
		// eslint-disable-next-line no-undef
		popupButton.value = securepoll_unstrike_button;
	} else {
		row.className = row.className.replace( 'securepoll-struck-vote', '' );
		// eslint-disable-next-line no-undef
		popupButton.value = securepoll_strike_button;
	}
	popupButton.onclick = function ( event ) {
		securepoll_strike_popup( event, action === 'strike' ? 'unstrike' : 'strike', voteId );
	};
}

// eslint-disable-next-line no-implicit-globals, no-unused-vars
function securepoll_ballot_setup() {
	var i, elt,
		anchors = document.getElementsByTagName( 'a' );
	for ( i = 0; i < anchors.length; i++ ) {
		elt = anchors.item( i );
		if ( elt.className !== 'securepoll-error-jump' ) {
			continue;
		}
		if ( elt.addEventListener ) {
			elt.addEventListener( 'click',
				function () {
					securepoll_error_jump( this, anchors );
				},
				false );
		} else {
			elt.attachEvent( 'onclick', securepoll_error_jump );
		}
	}
}

// TODO: make prettier
// eslint-disable-next-line no-implicit-globals
function securepoll_error_jump( source, anchors ) {
	var i, anchor, id, elt;
	for ( i = 0; i < anchors.length; i++ ) {
		anchor = anchors.item( i );
		if ( anchor.className !== 'securepoll-error-jump' ) {
			continue;
		}
		id = anchor.getAttribute( 'href' ).substr( 1 );
		elt = document.getElementById( id );
		if ( !elt ) {
			continue;
		}

		try {
			if ( anchor === source ) {
				elt.style.borderColor = '#ff0000';
				elt.style.backgroundColor = '#ffcc99';
			} else {
				elt.style.backgroundColor = 'transparent';
				elt.style.borderColor = 'transparent';
			}
		} catch ( e ) {}
	}
}
