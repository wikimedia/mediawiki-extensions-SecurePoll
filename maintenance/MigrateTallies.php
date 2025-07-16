<?php

namespace MediaWiki\Extension\SecurePoll\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;

// @codeCoverageIgnoreStart
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
// @codeCoverageIgnoreEnd

/**
 * Migrates old tallies stored in securepoll_properties store the tallies in a
 * list to support multiple tallies.
 */
class MigrateTallies extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Migrates old tallies stored in securepoll_properties store ' .
			'the tallies in a list to support multiple tallies.'
		);

		$this->setBatchSize( 100 );

		$this->requireExtension( 'SecurePoll' );
	}

	/** @inheritDoc */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );
		$batchKey = 0;

		while ( true ) {
			// Query already existing tallies that are stored as properties.
			$tallies = $dbw->newSelectQueryBuilder()
				->select( [
					'sp_entity' => 'sp.pr_entity',
					'sp_timestamp' => 'sp2.pr_value',
					'sp_result' => 'sp.pr_value',
				] )
				->from( 'securepoll_properties', 'sp' )
				->join(
					'securepoll_properties',
					'sp2',
					"sp2.pr_entity = sp.pr_entity AND sp2.pr_key = 'tally-result-time'"
				)
				->where( [
					$dbw->expr( 'sp.pr_entity', '>', $batchKey ),
					'sp.pr_key' => 'tally-result',
				] )
				->orderBy( 'sp.pr_entity' )
				->limit( $this->getBatchSize() )
				->fetchResultSet();

			if ( !$tallies->numRows() ) {
				break;
			}

			$this->output( "{$tallies->numRows()} row(s) selected\n" );

			// We need to keep track of the last entity ID processed, so we can
			// start the next batch from the next entity ID.
			$lastEntity = 0;

			$this->beginTransactionRound( __METHOD__ );

			foreach ( $tallies as $tally ) {
				$lastEntity = max( $lastEntity, $tally->sp_entity );
				$result = json_decode( $tally->sp_result, true );

				// Skip invalid results and results that have already been
				// migrated to the new format.
				if ( !is_array( $result ) || array_is_list( $result ) ) {
					$this->output(
						"Skipping migration of results for election '{$tally->sp_entity}'\n"
					);
					continue;
				}

				$dbw->newReplaceQueryBuilder()
					->replaceInto( 'securepoll_properties' )
					->uniqueIndexFields( [ 'pr_entity', 'pr_key' ] )
					->row( [
						'pr_entity' => $tally->sp_entity,
						'pr_key' => 'tally-result',
						'pr_value' => json_encode( [
							[
								'tallyId' => 1,
								'resultTime' => $tally->sp_timestamp,
								'result' => $result,
							],
						] ),
					] )
					->caller( __METHOD__ )
					->execute();
			}

			$batchKey = $lastEntity;

			$this->commitTransactionRound( __METHOD__ );
		}

		return true;
	}

	/** @inheritDoc */
	protected function getUpdateKey() {
		return 'MigrateTallies';
	}
}

// @codeCoverageIgnoreStart
$maintClass = MigrateTallies::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
