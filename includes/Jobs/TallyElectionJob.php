<?php

namespace MediaWiki\Extension\SecurePoll\Jobs;

use Job;
use MediaWiki\Extension\SecurePoll\Context;
use MediaWiki\Extension\SecurePoll\Entities\Election;
use MediaWiki\Extension\SecurePoll\Exceptions\InvalidDataException;
use MediaWiki\Extension\SecurePoll\Talliers\ElectionTallier;
use Throwable;
use Wikimedia\Rdbms\IDatabase;

/**
 * Job for tallying an encrypted election.
 */
class TallyElectionJob extends Job {

	/** @var int */
	private $electionId;

	/** @var Election|bool */
	private $election;

	/** @var IDatabase|null */
	private $dbw;

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
	public function run(): bool {
		$context = new Context();

		$this->electionId = (int)$this->params['electionId'];
		$this->election = $context->getElection( $this->electionId );

		if ( !$this->election ) {
			$this->setLastError( "Could not get election '$this->electionId'" );

			return false;
		}

		$this->dbw = $context->getDB( DB_PRIMARY );

		try {
			$this->preRun();

			return $this->doRun();
		} catch ( Throwable $e ) {
			$this->markAsFailed( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );

			// Return here rather than re-throw the exception so that the explicit transaction
			// round created for this job is not moved into the error state before running
			// TallyElectionJob::postRun().
			return false;
		} finally {
			$this->postRun();
		}
	}

	/**
	 * @return bool
	 */
	private function doRun(): bool {
		$status = $this->election->tally();

		if ( !$status->isOK() ) {
			$this->markAsFailed( $status->getMessage(), __METHOD__ );

			return false;
		}

		$tallier = $status->value;
		'@phan-var ElectionTallier $tallier'; /** @var ElectionTallier $tallier */
		$result = json_encode( $tallier->getJSONResult() );
		$time = time();

		$this->dbw->replace(
			'securepoll_properties',
			[
				[
					'pr_entity',
					'pr_key'
				],
			],
			[
				[
					'pr_entity' => $this->electionId,
					'pr_key' => 'tally-result',
					'pr_value' => $result,
				],
				[
					'pr_entity' => $this->electionId,
					'pr_key' => 'tally-result-time',
					'pr_value' => $time,
				],
			],
			__METHOD__
		);

		return true;
	}

	/**
	 * Initializes the database for tallying the election by removing any previously recorded
	 * tallying errors and/or results.
	 */
	private function preRun() {
		$this->dbw->delete(
			'securepoll_properties',
			[
				'pr_entity' => $this->electionId,
				'pr_key' => [
					'tally-error',
				],
			],
			__METHOD__
		);
	}

	private function postRun() {
		$this->dbw->delete(
			'securepoll_properties',
			[
				'pr_entity' => $this->electionId,
				'pr_key' => 'tally-job-enqueued',
			],
			__METHOD__
		);

		try {
			$crypt = $this->election->getCrypt();
			if ( $crypt ) {
				$crypt->cleanupDbForTallyJob( $this->electionId, $this->dbw );
			}
		} catch ( InvalidDataException $e ) {
			// Election::getCrypt() throws InvalidDataException if an election has the "encrypt-type"
			// property set but the corresponding class cannot be instantiated.
			//
			// Swallow this exception for the following reasons:
			//
			// * This job can only be enqueued when the user clicks the "Create tally" button on
			//   the tally page. That page does not work in these circumstances.
			//
			// * At this point, the election has been tallied and the result can be displayed to
			//   the user. If this exception is caught by the job runner then the explicit
			//   transaction round created for this job will not be committed.
		}
	}

	/**
	 * @param string $message
	 * @param string $fname
	 */
	private function markAsFailed( string $message, string $fname ) {
		$this->setLastError( $message );

		$this->dbw->insert(
			'securepoll_properties',
			[
				'pr_entity' => $this->electionId,
				'pr_key' => 'tally-error',
				'pr_value' => $message
			],
			$fname
		);
	}
}
