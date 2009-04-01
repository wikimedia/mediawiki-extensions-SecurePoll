<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "Not a valid entry point\n" );
}

class SecurePollPage extends UnlistedSpecialPage {
	static $pages = array(
		'entry' => 'SecurePoll_EntryPage',
		'vote' => 'SecurePoll_VotePage',
		'list' => 'SecurePoll_ListPage',
		'details' => 'SecurePoll_DetailsPage',
		'dump' => 'SecurePoll_DumpPage',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'SecurePoll' );
	}

	/**
	 * Show the special page
	 *
	 * @param $paramString Mixed: parameter passed to the page or null
	 */
	public function execute( $paramString ) {
		global $wgOut, $wgRequest, $wgScriptPath;

		wfLoadExtensionMessages( 'SecurePoll' );

		$this->setHeaders();
		$wgOut->addLink( array(
			'rel' => 'stylesheet',
			'href' => "$wgScriptPath/extensions/SecurePoll/SecurePoll.css",
			'type' => 'text/css'
		) );
		$wgOut->addScriptFile( "$wgScriptPath/extensions/SecurePoll/SecurePoll.js" );

		$this->request = $wgRequest;

		$paramString = strval( $paramString );
		$params = explode( '/', $paramString );
		if ( !isset( $params[0] ) ) {
			$params = array( 'entry' );
		}
		$pageName = array_shift( $params );
		$page = $this->getSubpage( $pageName );
		if ( !$page ) {
			$wgOut->addWikiMsg( 'securepoll-invalid-page', $pageName );
			return;
		}

		$page->execute( $params );
	}

	function getSubpage( $name ) {
		if ( !isset( self::$pages[$name] ) ) {
			return false;
		}
		$className = self::$pages[$name];
		$page = new $className( $this );
		return $page;
	}

	function getElection( $id ) {
		$db = wfGetDB( DB_MASTER );
		$row = $db->selectRow( 'securepoll_elections', '*', array( 'el_entity' => $id ), __METHOD__ );
		return SecurePoll_Election::newFromRow( $row );
	}

	function getEditToken() {
		if ( !isset( $_SESSION['bvToken'] ) ) {
			$_SESSION['bvToken'] = sha1( mt_rand() . mt_rand() . mt_rand() );
		}
		return $_SESSION['bvToken'];
	}
}
