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
		$pager = new MainElectionsPager( $this, $this->linkRenderer, $this->loadBalancer );
		$out = $this->specialPage->getOutput();
		$out->addWikiMsg( 'securepoll-entry-text' );
		$out->addParserOutputContent( $pager->getBodyOutput() );
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
