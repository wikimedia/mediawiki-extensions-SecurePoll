<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use JobQueueGroup;
use JobSpecification;
use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\MessageWidget;

/**
 * SecurePoll subpage for archiving past elections
 */
class UnarchivePage extends ActionPage {
	/** @var JobQueueGroup */
	private $jobQueueGroup;

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

		$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );

		if ( !count( $params ) ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-too-few-params' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		$electionId = intval( $params[0] );
		$this->election = $this->context->getElection( $electionId );
		if ( !$this->election ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-invalid-election', $electionId )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		$out->setPageTitleMsg( $this->msg( 'securepoll-unarchive-title', $this->election->getMessage( 'title' ) ) );

		$isAdmin = $this->election->isAdmin( $this->specialPage->getUser() );

		if ( !$isAdmin ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-private' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		if ( !$this->election->isFinished() ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-not-finished' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		$dbr = $this->election->context->getDB( DB_REPLICA );
		$isArchived = $dbr->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $this->election->getId(),
				'pr_key' => 'is-archived',
			] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $isArchived ) {
			// If a row exists, it's archived; unarchive it
			$this->jobQueueGroup->push(
				new JobSpecification(
					'securePollUnarchiveElection',
					[ 'electionId' => $electionId ],
					[]
				)
			);
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-unarchive-in-progress' )->text(),
				'type' => 'success',
			] ) ) );
		} else {
			// Not archived
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-already-unarchived-error' )->text(),
				'type' => 'error',
			] ) ) );
		}
	}
}
