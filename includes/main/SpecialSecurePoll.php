<?php

/**
 * The page that's initially called by MediaWiki when navigating to
 * Special:SecurePoll.  The actual pages are not actually subclasses of
 * this or of SpecialPage, they're subclassed from SecurePoll_ActionPage.
 */
class SecurePoll_SpecialSecurePoll extends SpecialPage {
	public static $pages = [
		'create' => 'SecurePoll_CreatePage',
		'edit' => 'SecurePoll_CreatePage',
		'details' => 'SecurePoll_DetailsPage',
		'dump' => 'SecurePoll_DumpPage',
		'entry' => 'SecurePoll_EntryPage',
		'list' => 'SecurePoll_ListPage',
		'login' => 'SecurePoll_LoginPage',
		'msgdump' => 'SecurePoll_MessageDumpPage',
		'tally' => 'SecurePoll_TallyPage',
		'translate' => 'SecurePoll_TranslatePage',
		'vote' => 'SecurePoll_VotePage',
		'votereligibility' => 'SecurePoll_VoterEligibilityPage',
	];

	public $sp_context;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'SecurePoll' );
		$this->sp_context = new SecurePoll_Context;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $paramString parameter passed to the page or null
	 */
	public function execute( $paramString ) {
		global $wgExtensionAssetsPath;

		$out = $this->getOutput();

		$this->setHeaders();
		$out->addLink(
			[
				'rel' => 'stylesheet',
				'href' => "$wgExtensionAssetsPath/SecurePoll/resources/SecurePoll.css",
				'type' => 'text/css'
			]
		);
		$out->addScriptFile( "$wgExtensionAssetsPath/SecurePoll/resources/SecurePoll.js" );

		$paramString = strval( $paramString );
		if ( $paramString === '' ) {
			$paramString = 'entry';
		}
		$params = explode( '/', $paramString );
		$pageName = array_shift( $params );
		$page = $this->getSubpage( $pageName );
		if ( !$page ) {
			$out->addWikiMsg( 'securepoll-invalid-page', $pageName );

			return;
		}

		if ( !( $page instanceof SecurePoll_EntryPage ) ) {
			$this->setSubtitle();
		}

		$page->execute( $params );
	}

	/**
	 * Get a SecurePoll_ActionPage subclass object for the given subpage name
	 * @param string $name
	 * @return false|SecurePoll_ActionPage
	 */
	public function getSubpage( $name ) {
		if ( !isset( self::$pages[$name] ) ) {
			return false;
		}
		$className = self::$pages[$name];

		return new $className( $this );
	}

	/**
	 * Set a navigation subtitle.
	 * Each argument is a two-element array giving a Title object to be used as
	 * a link target, and the link text.
	 * @param array ...$links
	 */
	public function setSubtitle( ...$links ) {
		$title = $this->getPageTitle();
		$subtitle = '&lt; ' . Linker::linkKnown( $title, htmlspecialchars( $title->getText() ) );
		$pipe = $this->msg( 'pipe-separator' )->text();
		foreach ( $links as $link ) {
			list( $title, $text ) = $link;
			$subtitle .= $pipe . Linker::linkKnown( $title, htmlspecialchars( $text ) );
		}
		$this->getOutput()->setSubtitle( $subtitle );
	}
}
