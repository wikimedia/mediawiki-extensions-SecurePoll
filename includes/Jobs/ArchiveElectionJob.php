<?php

namespace MediaWiki\Extension\SecurePoll\Jobs;

use Job;
use MediaWiki\Extension\SecurePoll\Context;

/**
 * Archive an election
 */
class ArchiveElectionJob extends Job {
	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'securePollArchiveElection', $title, $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$electionId = $this->params['electionId'];
		$context = new Context();
		$dbw = $context->getDB( DB_PRIMARY );

		$isArchived = $dbw->newSelectQueryBuilder()
			->select( 'pr_value' )
			->from( 'securepoll_properties' )
			->where( [
				'pr_entity' => $electionId,
				'pr_key' => 'is-archived',
			] )
			->caller( __METHOD__ )
			->fetchField();
		if ( !$isArchived ) {
			$dbw->newInsertQueryBuilder()
				->insertInto( 'securepoll_properties' )
				->row( [
					'pr_entity' => $electionId,
					'pr_key' => 'is-archived',
					'pr_value' => 1,
				] )
				->caller( __METHOD__ )
				->execute();
		}
		return true;
	}
}
