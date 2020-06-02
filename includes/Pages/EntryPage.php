<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use Html;
use Linker;
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
		$out->addHTML(
			$pager->getBody() . $pager->getNavigationBar()
		);

		if ( $this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
			$title = SpecialPage::getTitleFor( 'SecurePoll', 'create' );
			// @phan-suppress-next-line SecurityCheck-XSS
			$out->addHTML(
				Html::rawElement(
					'p',
					[],
					Linker::link(
						$title,
						$this->msg( 'securepoll-entry-createpoll' )->escaped(),
						[],
						[],
						[ 'known' ]
					)
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
