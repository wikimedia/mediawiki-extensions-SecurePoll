<?php

namespace MediaWiki\Extensions\SecurePoll;

use Linker;
use MediaWiki\Extensions\SecurePoll\Pages\ActionPage;
use MediaWiki\Extensions\SecurePoll\Pages\CreatePage;
use MediaWiki\Extensions\SecurePoll\Pages\DetailsPage;
use MediaWiki\Extensions\SecurePoll\Pages\DumpPage;
use MediaWiki\Extensions\SecurePoll\Pages\EntryPage;
use MediaWiki\Extensions\SecurePoll\Pages\ListPage;
use MediaWiki\Extensions\SecurePoll\Pages\LoginPage;
use MediaWiki\Extensions\SecurePoll\Pages\MessageDumpPage;
use MediaWiki\Extensions\SecurePoll\Pages\TallyPage;
use MediaWiki\Extensions\SecurePoll\Pages\TranslatePage;
use MediaWiki\Extensions\SecurePoll\Pages\VotePage;
use MediaWiki\Extensions\SecurePoll\Pages\VoterEligibilityPage;
use SpecialPage;

/**
 * The page that's initially called by MediaWiki when navigating to
 * Special:SecurePoll.  The actual pages are not actually subclasses of
 * this or of SpecialPage, they're subclassed from ActionPage.
 */
class SpecialSecurePoll extends SpecialPage {
	public static $pages = [
		'create' => CreatePage::class,
		'edit' => CreatePage::class,
		'details' => DetailsPage::class,
		'dump' => DumpPage::class,
		'entry' => EntryPage::class,
		'list' => ListPage::class,
		'login' => LoginPage::class,
		'msgdump' => MessageDumpPage::class,
		'tally' => TallyPage::class,
		'translate' => TranslatePage::class,
		'vote' => VotePage::class,
		'votereligibility' => VoterEligibilityPage::class,
	];

	public $sp_context;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'SecurePoll' );
		$this->sp_context = new Context;
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

		$out->addModuleStyles( 'ext.securepoll.special' );
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

		if ( !( $page instanceof EntryPage ) ) {
			$this->setSubtitle();
		}

		$page->execute( $params );
	}

	/**
	 * Get a _ActionPage subclass object for the given subpage name
	 * @param string $name
	 * @return false|ActionPage
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
