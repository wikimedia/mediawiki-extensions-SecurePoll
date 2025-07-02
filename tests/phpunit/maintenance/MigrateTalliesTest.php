<?php

namespace MediaWiki\Extension\SecurePoll\Test\Maintenance;

use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Utils\MWTimestamp;
use MigrateTallies;
use TestSelectQueryBuilder;

/**
 * @group Database
 */
class MigrateTalliesTest extends MaintenanceBaseTestCase {

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return MigrateTallies::class;
	}

	/**
	 * @covers MigrateTallies::execute
	 */
	public function testExecute() {
		$dbw = $this->getDb();

		$electionId = 1;
		$result = [ "key" => "value" ];
		$resultTime = time();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'securepoll_properties' )
			->row( [
				'pr_entity' => $electionId,
				'pr_key' => 'tally-result-time',
				'pr_value' => $dbw->timestamp( $resultTime ),
			] )
			->row( [
				'pr_entity' => $electionId,
				'pr_key' => 'tally-result',
				'pr_value' => json_encode( $result ),
			] )
			->caller( __METHOD__ )
			->execute();

		$this->maintenance->execute();

		$this->tallyQuery( $electionId )->assertRowValue( [
			0 => strval( $electionId ),
			1 => json_encode( [
				[
					'tallyId' => 1,
					'resultTime' => ( new MWTimestamp( $resultTime ) )->getTimestamp( TS_MW ),
					'result' => $result,
				],
			] ),
		] );
	}

	private function tallyQuery( int $electionId ): TestSelectQueryBuilder {
		return $this->newSelectQueryBuilder()
			->select( [ 'pr_entity', 'pr_value' ] )
			->from( 'securepoll_properties' )
			->where( [
				$this->db->expr( 'pr_entity', '=', $electionId ),
				'pr_key' => 'tally-result',
			] )
			->caller( __METHOD__ );
	}
}
