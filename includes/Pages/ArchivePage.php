<?php

namespace MediaWiki\Extension\SecurePoll\Pages;

use MediaWiki\Extension\SecurePoll\SpecialSecurePoll;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\MessageWidget;

/**
 * SecurePoll subpage for archiving past elections
 */
class ArchivePage extends ActionPage {
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

		$out->returnToMain( false, SpecialPage::getTitleFor( 'SecurePoll' ) );

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

		$out->setPageTitleMsg( $this->msg( 'securepoll-archive-title', $this->election->getMessage( 'title' ) ) );

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

		$token = $this->specialPage->getContext()->getCsrfTokenSet()->getToken();
		$request = $this->specialPage->getRequest();
		$tokenMatch = $token->match( $request->getVal( 'token' ) );
		if ( !$tokenMatch ) {
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-token-error' )->text(),
				'type' => 'error',
			] ) ) );
			return;
		}

		// Already archived?
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

		if ( !$isArchived ) {
			// Not archived if row doesn't exist; go ahead and archive
			$this->jobQueueGroup->push(
				new JobSpecification(
					'securePollArchiveElection',
					[ 'electionId' => $electionId ],
					[]
				)
			);
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-archive-in-progress' )->text(),
				'type' => 'success',
			] ) ) );
		} else {
			// Already archived
			$out->prependHTML( ( new MessageWidget( [
				'label' => $this->msg( 'securepoll-already-archived-error' )->text(),
				'type' => 'error',
			] ) ) );
		}
	}
}
