<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * The entry page for SecurePoll. Shows a list of elections.
 */
class EntryPage extends ActionPage {

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
		$pager = new MainElectionsPager( $this, $this->linkRenderer, $this->loadBalancer );
		$pager->setContext( $this->specialPage->getContext() );

		$out = $this->specialPage->getOutput();
		$out->addWikiMsg( 'securepoll-entry-text' );
		$out->addParserOutputContent(
			$pager->getBodyOutput(),
			$this->context->getParserOptions()
		);
		$out->addHTML( $pager->getNavigationBar() );

		$links = [
			$this->linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'SecurePoll', 'archived' ),
				$this->msg( 'securepoll-entry-archived' )->text()
			),
		];

		if ( $this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
			$links[] = $this->linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'SecurePoll', 'create' ),
				$this->msg( 'securepoll-entry-createpoll' )->text()
			);
		}

		$subtitle = implode( ' | ', array_filter( $links, static function ( $link ) {
			return (bool)$link;
		} ) );

		$out->setSubtitle( $subtitle );
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'entry' );
	}
}
