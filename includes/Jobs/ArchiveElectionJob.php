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

		$isArchived = $dbw->selectField(
			'securepoll_properties',
			[ 'pr_value' ],
			[
				'pr_entity' => $electionId,
				'pr_key' => 'is-archived',
			],
			__METHOD__
		);
		if ( !$isArchived ) {
			$dbw->insert(
				'securepoll_properties',
				[
					'pr_entity' => $electionId,
					'pr_key' => 'is-archived',
					'pr_value' => 1,
				],
				__METHOD__
			);
		}
		return true;
	}
}
