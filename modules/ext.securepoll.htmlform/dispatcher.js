if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePoll' ) {
	const subPage = mw.config.get( 'SecurePollSubPage' );
	if ( subPage === 'vote' ) {
		require( './page.vote.js' );
		require( './page.vote.highlightWarnings.js' );
		if ( mw.config.get( 'SecurePollType' ) === 'droop-quota' ) {
			require( './page.vote.stv.js' );
		}
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
