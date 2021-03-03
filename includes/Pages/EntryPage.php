<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Html;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use Title;

/**
 * The entry page for SecurePoll. Shows a list of elections.
 */
class EntryPage extends ActionPage {
	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$pager = new ElectionPager( $this );
		$out = $this->specialPage->getOutput();
		$out->addWikiMsg( 'securepoll-entry-text' );
		$out->addParserOutputContent( $pager->getBodyOutput() );
		$out->addHTML( $pager->getNavigationBar() );

		if ( $this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
			$title = SpecialPage::getTitleFor( 'SecurePoll', 'create' );
			$out->addHTML(
				Html::rawElement(
					'p',
					[],
					MediaWikiServices::getInstance()->getLinkRenderer()->makeKnownLink(
						$title, $this->msg( 'securepoll-entry-createpoll' )->text() )
				)
			);
		}
	}

	/**
	 * @return Title
	 */
	public function getTitle() {
		return $this->specialPage->getPageTitle( 'entry' );
	}
}
