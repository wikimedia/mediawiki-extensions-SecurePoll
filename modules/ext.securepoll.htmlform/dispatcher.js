if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePoll' ) {
	require( './page.create.js' );
	require( './page.list.js' );
	require( './page.vote.js' );
} else if ( mw.config.get( 'wgCanonicalSpecialPageName' ) === 'SecurePollLog' ) {
	require( './page.log.js' );
}
