( function () {
	if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePoll' ) {
		require( './page.create.js' );
	} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePollLog' ) {
		require( './page.log.js' );
	}
}() );
