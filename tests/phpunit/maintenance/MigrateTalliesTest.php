<?php

namespace MediaWiki\Extension\SecurePoll\Test\Maintenance;

use MediaWiki\Extension\SecurePoll\Maintenance\MigrateTallies;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Utils\MWTimestamp;
use TestSelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @covers \MediaWiki\Extension\SecurePoll\Maintenance\MigrateTallies
 */
class MigrateTalliesTest extends MaintenanceBaseTestCase {

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return MigrateTallies::class;
	}

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
				$this->getDb()->expr( 'pr_entity', '=', $electionId ),
				'pr_key' => 'tally-result',
			] )
			->caller( __METHOD__ );
	}

	/**
	 * Make sure this key name doesn't change if we change the class's namespace.
	 * Else this creates a bug where this gets run twice during updates.
	 */
	public function testGetUpdateKey() {
		/** @var TestingAccessWrapper $maintenance */
		$maintenance = $this->maintenance;
		$this->assertSame( 'MigrateTallies', $maintenance->getUpdateKey() );
	}
}
