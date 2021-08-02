<?php

namespace MediaWiki\Extensions\SecurePoll\Jobs;

use Job;
use MediaWiki\Extensions\SecurePoll\Context;

/**
 * Job for tallying an encrypted election.
 */
class TallyElectionJob extends Job {
	/**
	 * @inheritDoc
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'securePollTallyElection', $title, $params );
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
		$context = new Context;
		$dbw = $context->getDB();
		$electionId = $this->params['electionId'];
		$election = $context->getElection( $electionId );
		$crypt = $election->getCrypt();
		$method = __METHOD__;
		$status = $election->tally();

		if ( $crypt ) {
			$crypt->cleanupDbForTallyJob( $electionId, $dbw );
		}
		$dbw->delete(
			'securepoll_properties',
			[
				'pr_entity' => $electionId,
				'pr_key' => 'tally-job-enqueued',
			],
			$method
		);
		if ( !$status->isOK() ) {
			$dbw->upsert(
				'securepoll_properties',
				[
					'pr_entity' => $electionId,
					'pr_key' => 'tally-error',
					'pr_value' => $status->getMessage(),
				],
				[
					[ 'pr_entity',
					'pr_key' ]
				],
				[
					'pr_entity' => $electionId,
					'pr_key' => 'tally-error',
					'pr_value' => $status->getMessage(),
				],
				$method
			);
		} else {
			$tallier = $status->value;
			$result = json_encode( $tallier->getJSONResult() );

			$dbUpsert = $dbw->upsert(
				'securepoll_properties',
				[
					'pr_entity' => $electionId,
					'pr_key' => 'tally-result',
					'pr_value' => $result,
				],
				[
					[ 'pr_entity',
						'pr_key' ]
				],
				[
					'pr_entity' => $electionId,
					'pr_key' => 'tally-result',
					'pr_value' => $result,
				],
				$method
			);

			if ( $dbUpsert ) {
				$time = time();
				$dbInsert = $dbw->upsert(
					'securepoll_properties',
					[
						'pr_entity' => $electionId,
						'pr_key' => 'tally-result-time',
						'pr_value' => $time,
					],
					[
						[ 'pr_entity',
							'pr_key' ]
					],
					[
						'pr_entity' => $electionId,
						'pr_key' => 'tally-result-time',
						'pr_value' => $time,
					],
					$method
				);
			}

			if ( $dbUpsert === false ) {
				$dbw->upsert(
					'securepoll_properties',
					[
						'pr_entity' => $electionId,
						'pr_key' => 'tally-error',
						'pr_value' => $status->getMessage(),
					],
					[
						[ 'pr_entity',
						'pr_key' ]
					],
					[
						'pr_entity' => $electionId,
						'pr_key' => 'tally-error',
						'pr_value' => $status->getMessage(),
					],
					$method
				);
			}
		}

		return true;
	}
}
