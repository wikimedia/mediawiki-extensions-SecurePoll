<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "Not a valid entry point\n" );
}

class SecurePollPage extends UnlistedSpecialPage {
	static $pages = array(
		'details' => 'SecurePoll_DetailsPage',
		'dump' => 'SecurePoll_DumpPage',
		'entry' => 'SecurePoll_EntryPage',
		'list' => 'SecurePoll_ListPage',
		'login' => 'SecurePoll_LoginPage',
		'msgdump' => 'SecurePoll_MessageDumpPage',
		'tally' => 'SecurePoll_TallyPage',
		'translate' => 'SecurePoll_TranslatePage',
		'vote' => 'SecurePoll_VotePage',
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
		if ( $paramString === '' ) {
			$paramString = 'entry';
		}
		$params = explode( '/', $paramString );
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
		if ( $row ) {
			return SecurePoll_Election::newFromRow( $row );
		} else {
			return false;
		}
	}

	function getEditToken() {
		if ( !isset( $_SESSION['spToken'] ) ) {
			$_SESSION['spToken'] = sha1( mt_rand() . mt_rand() . mt_rand() );
		}
		return $_SESSION['spToken'];
	}
}
