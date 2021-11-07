<?php

namespace MediaWiki\Extensions\SecurePoll;

use Linker;
use MediaWiki\Extensions\SecurePoll\Pages\ActionPage;
use MediaWiki\Extensions\SecurePoll\Pages\EntryPage;
use SpecialPage;

/**
 * The page that's initially called by MediaWiki when navigating to
 * Special:SecurePoll.  The actual pages are not actually subclasses of
 * this or of SpecialPage, they're subclassed from ActionPage.
 */
class SpecialSecurePoll extends SpecialPage {
	/** @var Context */
	public $sp_context;

	/** @var ActionPageFactory */
	private $actionPageFactory;

	/**
	 * Constructor
	 * @param ActionPageFactory $actionPageFactory
	 */
	public function __construct(
		ActionPageFactory $actionPageFactory
	) {
		parent::__construct( 'SecurePoll' );
		$this->sp_context = new Context;

		$this->actionPageFactory = $actionPageFactory;
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
		$out = $this->getOutput();

		$this->setHeaders();

		$out->addModuleStyles( 'ext.securepoll.special' );

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
	 * @return null|ActionPage
	 */
	public function getSubpage( $name ) {
		return $this->actionPageFactory->getPage( $name, $this );
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
			[ $title, $text ] = $link;
			$subtitle .= $pipe . Linker::linkKnown( $title, htmlspecialchars( $text ) );
		}
		$this->getOutput()->setSubtitle( $subtitle );
	}
}
