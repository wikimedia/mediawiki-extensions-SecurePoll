<?php

/**
 * The entry page for SecurePoll. Shows a list of elections.
 */
class SecurePoll_EntryPage extends SecurePoll_ActionPage {
	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$pager = new SecurePoll_ElectionPager( $this );
		$out = $this->specialPage->getOutput();
		$out->addWikiMsg( 'securepoll-entry-text' );
		$out->addHTML(
			$pager->getBody() . $pager->getNavigationBar()
		);

		if ( $this->specialPage->getUser()->isAllowed( 'securepoll-create-poll' ) ) {
			$title = SpecialPage::getTitleFor( 'SecurePoll', 'create' );
			$out->addHTML(
				Html::rawElement(
					'p',
					[],
					Linker::link(
						$title,
						$this->msg( 'securepoll-entry-createpoll' )->text(),
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
