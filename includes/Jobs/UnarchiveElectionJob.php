<?php

namespace MediaWiki\Extensions\SecurePoll\Jobs;

use Job;
use MediaWiki\Extensions\SecurePoll\Context;

/**
 * Unarchive an election
 */
class UnarchiveElectionJob extends Job {
	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'securePollUnarchiveElection', $title, $params );
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
		if ( $isArchived ) {
			$dbw->delete(
				'securepoll_properties',
				[
					'pr_entity' => $electionId,
					'pr_key' => 'is-archived',
				],
				__METHOD__
			);
		}
		return true;
	}
}
