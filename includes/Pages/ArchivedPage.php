<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * SecurePoll subpage for archiving past elections
 */
class ArchivedPage extends ActionPage {

	public function __construct(
		SpecialSecurePoll $specialPage,
		private readonly LinkRenderer $linkRenderer,
		private readonly ILoadBalancer $loadBalancer,
	) {
		parent::__construct( $specialPage );
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
		$out->addParserOutputContent(
			$pager->getBodyOutput(),
			$this->context->getParserOptions()
		);
		$out->addHTML( $pager->getNavigationBar() );
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'archived' );
	}
}
