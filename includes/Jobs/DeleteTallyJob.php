<?php

namespace MediaWiki\Extension\SecurePoll\Jobs;

use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\JobQueue\Job;

/**
 * Delete a tally.
 */
class DeleteTallyJob extends Job {

	private Context $context;

	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'securePollDeleteTally', $title, $params );

		$this->context = new Context();
	}

	/**
	 * @inheritDoc
	 */
	public function allowRetries() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function run() {
		$electionId = $this->params['electionId'];
		$tallyId = $this->params['tallyId'];

		$election = $this->context->getElection( $electionId );
		if ( !$election ) {
			$this->setLastError( "Could not get election '{$electionId}'" );
			return false;
		}

		$dbw = $this->context->getDB( DB_PRIMARY );
		$election->deleteTallyResult( $dbw, $tallyId );

		return true;
	}

	public function setContext( Context $context ): void {
		$this->context = $context;
	}
}
