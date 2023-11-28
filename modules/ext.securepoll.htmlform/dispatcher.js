if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePoll' ) {
	var subPage = mw.config.get( 'SecurePollSubPage' );
	if ( subPage === 'vote' ) {
		require( './page.vote.js' );
	} else if ( subPage === 'translate' ) {
		require( './translation/dialog/ImportDialog.js' );
	} else if ( subPage === 'list' ) {
		require( './page.list.js' );
	} else if ( subPage === 'create' ) {
		require( './page.create.js' );
	}
} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePollLog' ) {
	require( './page.log.js' );
}
