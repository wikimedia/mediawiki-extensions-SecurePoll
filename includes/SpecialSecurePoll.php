<?php

namespace MediaWiki\Extension\SecurePoll;

use MediaWiki\Extension\SecurePoll\Pages\ActionPage;
use MediaWiki\Extension\SecurePoll\Pages\EntryPage;
use MediaWiki\SpecialPage\SpecialPage;

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

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $subPage parameter passed to the page or null
	 */
	public function execute( $subPage ) {
		$out = $this->getOutput();

		$this->setHeaders();

		$out->addModuleStyles( 'ext.securepoll.special' );

		if ( $subPage === null || $subPage === '' ) {
			$subPage = 'entry';
		}

		$page = $this->getSubpage( $subPage );
		if ( !$page ) {
			$out->addWikiMsg( 'securepoll-invalid-page', $subPage );
			return;
		}

		if ( !( $page instanceof EntryPage ) ) {
			$this->setSubtitle();
		}

		$params = explode( '/', $subPage );

		// Remove the special page name from the params.
		array_shift( $params );

		$page->execute( $params );
	}

	/**
	 * Get a _ActionPage subclass object for the given subpage name
	 * @param string $url
	 * @return null|ActionPage
	 */
	public function getSubpage( $url ) {
		return $this->actionPageFactory->getPage( $url, $this );
	}

	/**
	 * Set a navigation subtitle.
	 * Each argument is a two-element array giving a Title object to be used as
	 * a link target, and the link text.
	 * @param array ...$links
	 */
	public function setSubtitle( ...$links ) {
		$title = $this->getPageTitle();
		$linkRenderer = $this->getLinkRenderer();

		$subtitle = '&lt; ' . $linkRenderer->makeKnownLink( $title, $title->getText() );
		$pipe = $this->msg( 'pipe-separator' )->escaped();
		foreach ( $links as $link ) {
			[ $title, $text ] = $link;
			$subtitle .= $pipe . $linkRenderer->makeKnownLink( $title, $text );
		}
		$this->getOutput()->setSubtitle( $subtitle );
	}
}
