<?php

namespace MediaWiki\Extension\SecurePoll\Test\Maintenance;

use MediaWiki\Extension\SecurePoll\Maintenance\DropUnusedTables;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use Wikimedia\Rdbms\IMaintainableDatabase;

/**
 * @covers \MediaWiki\Extension\SecurePoll\Maintenance\DropUnusedTables
 * @group Database
 */
class DropUnusedTablesTest extends MaintenanceBaseTestCase {

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return DropUnusedTables::class;
	}

	public function testExecuteWhenNothingToDo() {
		// Mock that a group has permission to create polls
		$this->setGroupPermissions( '*', 'securepoll-create-poll', true );

		$this->maintenance->execute();

		// Verify that the script does not drop any tables because they could be used.
		/** @var IMaintainableDatabase $dbw */
		$dbw = $this->getDb();
		foreach ( DropUnusedTables::TABLES_TO_DROP as $table ) {
			$this->assertTrue( $dbw->tableExists( $table ), "$table was expected to exist." );
		}
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Found user groups having permission to create polls, nothing to do', $actualOutput
		);
	}
}
