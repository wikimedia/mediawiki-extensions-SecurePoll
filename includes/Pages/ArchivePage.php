<?php

namespace MediaWiki\Extensions\SecurePoll\Pages;

use JobQueueGroup;
use JobSpecification;
use MediaWiki\Extensions\SecurePoll\Entities\Election;
use MediaWiki\Extensions\SecurePoll\SpecialSecurePoll;
use OOUI\MessageWidget;

/**
 * SecurePoll subpage for archiving past elections
 */
class ArchivePage extends ActionPage {
		/** @var Election */
		public $election;

		/** @var JobQueueGroup */
		private $jobQueueGroup;

		/**
		 * @param SpecialSecurePoll $specialPage
		 * @param JobQueueGroup $jobQueueGroup
		 */
		public function __construct(
			SpecialSecurePoll $specialPage,
			JobQueueGroup $jobQueueGroup
		) {
			parent::__construct( $specialPage );
			$this->jobQueueGroup = $jobQueueGroup;
		}

	/**
	 * Execute the subpage.
	 * @param array $params Array of subpage parameters.
	 */
	public function execute( $params ) {
		$out = $this->specialPage->getOutput();
		$out->enableOOUI();

		if ( !count( $params ) ) {
			$out->addWikiMsg( 'securepoll-too-few-params' );
			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->addWikiMsg( 'securepoll-invalid-election', $electionId );
			return;
		}

		$out->setPageTitle(
			$this->msg(
				'securepoll-archive-title',
				$this->election->getMessage( 'title' )
			)->text()
		);

		$isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );

		if ( !$isAdmin ) {
			$out->prependHtml( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-private' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->prependHtml( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-not-finished' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		// Already archived?
		$dbr = $this->election->context->getDB( DB_REPLICA );
		$isArchived = $dbr->selectField(
			'securepoll_properties',
			[ 'pr_value' ],
			[
				'pr_entity' => $this->election->getID(),
				'pr_key' => 'is-archived',
			],
			__METHOD__
		);

		if ( !$isArchived ) {
			// Not archived if row doesn't exist; go ahead and archive
			$this->jobQueueGroup->push(
				new JobSpecification(
					'securePollArchiveElection',
					[ 'electionId' => $electionId ],
					[]
				)
			);
			$out->prependHtml( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-in-progress' )->text(),
				'type' => 'success',
			] ) ) );
		} else {
			// Already archived
			$out->prependHtml( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-already-archived-error' )->text(),
				'type' => 'error',
			] ) ) );
		}
	}
}
