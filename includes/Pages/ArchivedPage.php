<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Linker\LinkRenderer;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * SecurePoll subpage for archiving past elections
 */
class ArchivedPage extends ActionPage {
	/** @var LinkRenderer */
	private $linkRenderer;
	/** @var ILoadBalancer */
	private $loadBalancer;

	/**
	 * @param SpecialSecurePoll $specialPage
	 * @param LinkRenderer $linkRenderer
	 * @param ILoadBalancer $loadBalancer
	 */
	public function __construct(
		SpecialSecurePoll $specialPage,
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer
	) {
		parent::__construct( $specialPage );
		$this->linkRenderer = $linkRenderer;
		$this->loadBalancer = $loadBalancer;
	}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();

		$out->setPageTitleMsg( $this->msg( 'securepoll-archived-title' ) );

		$pager = new ArchivedPager( $this, $this->linkRenderer, $this->loadBalancer );
		$out->addWikiMsg( 'securepoll-entry-text' );
		$out->addParserOutputContent( $pager->getBodyOutput() );
		$out->addHTML( $pager->getNavigationBar() );
	}

	public function getTitle() {
		return $this->specialPage->getPageTitle( 'archived' );
	}
}
